<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Exceptions\DuplicateInvoiceException;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeInvoiceItem;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\InstallmentMilestone;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InvoiceGenerationService
{
    protected FeeStructureService $structureService;
    protected StudentConcessionService $concessionService;
    protected GeneralLedgerService $glService;

    public function __construct(
        ?FeeStructureService $structureService = null,
        ?StudentConcessionService $concessionService = null,
        ?GeneralLedgerService $glService = null
    ) {
        $this->structureService = $structureService ?? new FeeStructureService();
        $this->concessionService = $concessionService ?? new StudentConcessionService();
        $this->glService = $glService ?? new GeneralLedgerService();
    }

    /**
     * Generate an idempotent monthly fee voucher for a school student.
     */
    public function generateSchoolMonthlyInvoice(
        Student $student,
        string $billingPeriod, // 'YYYY-MM'
        string $issueDate,
        string $dueDate,
        ?string $sessionId = null,
        ?string $classId = null,
        ?User $actor = null
    ): FeeInvoice {
        return TenantTransactionManager::runFinancialTransaction(function () use (
            $student,
            $billingPeriod,
            $issueDate,
            $dueDate,
            $sessionId,
            $classId,
            $actor
        ) {
            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 3: Invoice Lock & Idempotency
            FinancialLockManager::enterInvoice();

            $existingInvoice = FeeInvoice::where('tenant_id', $student->tenant_id)
                ->where('student_id', $student->id)
                ->where('billing_period', $billingPeriod)
                ->where('invoice_type', 'school_monthly')
                ->where('status', '<>', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($existingInvoice !== null) {
                throw new DuplicateInvoiceException("Active monthly fee invoice already exists for student {$student->admission_number} in period {$billingPeriod} (Invoice #{$existingInvoice->invoice_number}).");
            }

            // Resolve target Class ID if not provided
            $resolvedClassId = $classId;
            if ($resolvedClassId === null) {
                $enrollment = DB::table('school_student_enrollments')
                    ->join('school_sections', 'school_student_enrollments.school_section_id', '=', 'school_sections.id')
                    ->where('school_student_enrollments.tenant_id', $student->tenant_id)
                    ->where('school_student_enrollments.student_id', $student->id)
                    ->where('school_student_enrollments.enrollment_status', 'active')
                    ->select('school_sections.school_class_id', 'school_student_enrollments.academic_session_id')
                    ->first();

                if ($enrollment) {
                    $resolvedClassId = $enrollment->school_class_id;
                    $sessionId = $sessionId ?? $enrollment->academic_session_id;
                }
            }

            if ($resolvedClassId === null) {
                throw new InvalidArgumentException("Cannot determine school class for student {$student->admission_number}.");
            }

            // Resolve Fee Structure
            $structure = $this->structureService->resolveActiveStructureForClass(
                $student->tenant_id,
                $student->branch_id,
                $resolvedClassId,
                $sessionId
            );

            if ($structure === null) {
                throw new RuntimeException("No active fee structure configured for class {$resolvedClassId}.");
            }

            // Load student's active concessions
            $studentDiscounts = StudentFeeDiscount::where('tenant_id', $student->tenant_id)
                ->where('student_id', $student->id)
                ->with('feeDiscount')
                ->get();

            // Calculate items & concessions
            $itemsData = [];
            $subtotal = 0.00;
            $totalDiscount = 0.00;
            $sortOrder = 1;

            foreach ($structure->items as $structItem) {
                if ($structItem->is_optional) {
                    continue;
                }

                $gross = (float) $structItem->amount;
                $concession = $this->concessionService->calculateItemConcession(
                    $gross,
                    $structItem->feeCategory,
                    $studentDiscounts,
                    $issueDate
                );

                $itemDiscount = (float) $concession['discount_amount'];
                $net = (float) $concession['net_amount'];

                $subtotal += $gross;
                $totalDiscount += $itemDiscount;

                $itemsData[] = [
                    'fee_category_id' => $structItem->fee_category_id,
                    'gross_amount' => $gross,
                    'discount_amount' => $itemDiscount,
                    'net_amount' => $net,
                    'sort_order' => $sortOrder++,
                    'revenue_account_id' => $structItem->feeCategory->default_revenue_account_id,
                ];
            }

            $totalAmount = max(0.00, round($subtotal - $totalDiscount, 2));

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $invoiceNumber = $this->generateInvoiceNumber($student->tenant_id, 'SCH');
            $psid = $this->generatePsid($student->tenant_id, $student->admission_number, $billingPeriod);

            $invoice = FeeInvoice::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $student->branch_id,
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'school_class_id' => $resolvedClassId,
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'school_monthly',
                'billing_period' => $billingPeriod,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'late_fee_amount' => 0.00,
                'total_amount' => $totalAmount,
                'paid_amount' => 0.00,
                'status' => 'unpaid',
                'psid_1bill' => $psid,
                'barcode_code128' => $psid,
            ]);

            foreach ($itemsData as $item) {
                FeeInvoiceItem::create([
                    'tenant_id' => $student->tenant_id,
                    'fee_invoice_id' => $invoice->id,
                    'fee_category_id' => $item['fee_category_id'],
                    'gross_amount' => $item['gross_amount'],
                    'discount_amount' => $item['discount_amount'],
                    'net_amount' => $item['net_amount'],
                    'sort_order' => $item['sort_order'],
                ]);
            }

            // Post Accrual GL Journal Entry
            $journalEntry = $this->postAccrualJournalEntry($invoice, $itemsData, $actor);
            $invoice->update(['journal_entry_id' => $journalEntry->id]);

            return $invoice->fresh(['items.feeCategory', 'journalEntry.lines']);
        });
    }

    /**
     * Generate an invoice for an Academy installment milestone.
     */
    public function generateAcademyInstallmentInvoice(
        InstallmentMilestone $milestone,
        string $issueDate,
        FeeCategory $revenueCategory,
        ?User $actor = null
    ): FeeInvoice {
        return TenantTransactionManager::runFinancialTransaction(function () use (
            $milestone,
            $issueDate,
            $revenueCategory,
            $actor
        ) {
            $plan = $milestone->plan;
            $student = $plan->student;

            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 3: Invoice Lock
            FinancialLockManager::enterInvoice();

            $milestoneLocked = InstallmentMilestone::where('id', $milestone->id)->lockForUpdate()->firstOrFail();
            if ($milestoneLocked->status !== 'pending') {
                throw new RuntimeException("Installment milestone {$milestoneLocked->milestone_name} is already {$milestoneLocked->status}.");
            }

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $invoiceNumber = $this->generateInvoiceNumber($student->tenant_id, 'ACAD');
            $psid = $this->generatePsid($student->tenant_id, $student->admission_number, 'M' . $milestone->sequence_number);
            $amount = (float) $milestoneLocked->amount;

            $invoice = FeeInvoice::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $student->branch_id,
                'student_id' => $student->id,
                'installment_milestone_id' => $milestoneLocked->id,
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'academy_installment',
                'billing_period' => null,
                'issue_date' => $issueDate,
                'due_date' => $milestoneLocked->due_date->toDateString(),
                'subtotal' => $amount,
                'discount_amount' => 0.00,
                'late_fee_amount' => 0.00,
                'total_amount' => $amount,
                'paid_amount' => 0.00,
                'status' => 'unpaid',
                'psid_1bill' => $psid,
                'barcode_code128' => $psid,
            ]);

            FeeInvoiceItem::create([
                'tenant_id' => $student->tenant_id,
                'fee_invoice_id' => $invoice->id,
                'fee_category_id' => $revenueCategory->id,
                'gross_amount' => $amount,
                'discount_amount' => 0.00,
                'net_amount' => $amount,
                'description' => $milestoneLocked->milestone_name,
                'sort_order' => 1,
            ]);

            $itemsData = [
                [
                    'fee_category_id' => $revenueCategory->id,
                    'gross_amount' => $amount,
                    'discount_amount' => 0.00,
                    'net_amount' => $amount,
                    'revenue_account_id' => $revenueCategory->default_revenue_account_id,
                ],
            ];

            $journalEntry = $this->postAccrualJournalEntry($invoice, $itemsData, $actor);
            $invoice->update(['journal_entry_id' => $journalEntry->id]);

            $milestoneLocked->update(['status' => 'invoiced']);

            return $invoice->fresh(['items.feeCategory', 'journalEntry.lines']);
        });
    }

    /**
     * Post Accrual GL Journal Entry:
     * Debit: 1200 Accounts Receivable (for total net amount)
     * Debit: 5010 Fee Discounts & Concessions (for total discount amount, if > 0)
     * Credit: Respective Revenue accounts (for gross amounts)
     */
    protected function postAccrualJournalEntry(FeeInvoice $invoice, array $itemsData, ?User $actor): \App\Modules\Finance\Models\JournalEntry
    {
        $tenantId = $invoice->tenant_id;
        $arAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', '1200')->firstOrFail();

        $lines = [];
        $lineNum = 1;

        // Debit Accounts Receivable
        if ((float) $invoice->total_amount > 0) {
            $lines[] = [
                'account_id' => $arAccount->id,
                'entry_type' => 'debit',
                'amount' => (float) $invoice->total_amount,
                'line_number' => $lineNum++,
                'memo' => "A/R Accrual for Invoice {$invoice->invoice_number}",
            ];
        }

        // Debit Fee Discounts & Concessions (if applicable)
        if ((float) $invoice->discount_amount > 0) {
            $discountAccount = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', '5010')->first();
            if ($discountAccount) {
                $lines[] = [
                    'account_id' => $discountAccount->id,
                    'entry_type' => 'debit',
                    'amount' => (float) $invoice->discount_amount,
                    'line_number' => $lineNum++,
                    'memo' => "Concessions for Invoice {$invoice->invoice_number}",
                ];
            }
        }

        // Credit Revenue accounts per fee category
        $fallbackRevenueAccount = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('account_code', ($invoice->invoice_type === 'academy_installment' ? '4020' : '4010'))
            ->firstOrFail();

        foreach ($itemsData as $item) {
            $revenueAccountId = $item['revenue_account_id'] ?? $fallbackRevenueAccount->id;
            $lines[] = [
                'account_id' => $revenueAccountId,
                'entry_type' => 'credit',
                'amount' => (float) $item['gross_amount'],
                'line_number' => $lineNum++,
                'memo' => "Revenue Accrual for Invoice {$invoice->invoice_number}",
            ];
        }

        return $this->glService->postEntry(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $invoice->branch_id,
                'entry_date' => $invoice->issue_date->toDateString(),
                'source_type' => 'invoice_accrual',
                'source_id' => $invoice->id,
                'description' => "Invoice #{$invoice->invoice_number} billing accrual",
            ],
            $lines,
            $actor
        );
    }

    /**
     * Generate an invoice number unique to the tenant.
     */
    protected function generateInvoiceNumber(string $tenantId, string $prefix): string
    {
        $yearMonth = now()->format('Ym');
        $random = strtoupper(substr(uniqid(), -5));
        return "{$prefix}-{$yearMonth}-{$random}";
    }

    /**
     * Generate an 18-digit numeric 1Bill PSID.
     */
    protected function generatePsid(string $tenantId, string $admissionNo, string $period): string
    {
        $numericAdmission = preg_replace('/\D/', '', $admissionNo);
        $numericAdmission = str_pad(substr($numericAdmission, -6), 6, '0', STR_PAD_LEFT);

        $numericPeriod = preg_replace('/\D/', '', $period);
        $numericPeriod = str_pad(substr($numericPeriod, -6), 6, '0', STR_PAD_LEFT);

        $prefix = '1006'; // Standard 1Bill institute code placeholder
        $salt = str_pad(abs(crc32($tenantId)) % 100, 2, '0', STR_PAD_LEFT);

        return $prefix . $salt . $numericAdmission . $numericPeriod;
    }
}

