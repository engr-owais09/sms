<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\StudentCreditWallet;
use App\Modules\Finance\Models\StudentFeeAdvance;
use App\Modules\Finance\Models\StudentFeeAdvanceAllocation;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StudentAdvanceService
{
    protected GeneralLedgerService $glService;

    public function __construct(?GeneralLedgerService $glService = null)
    {
        $this->glService = $glService ?? new GeneralLedgerService();
    }

    /**
     * Resolve the Unearned Liability Account (GL 2010 vs 2015 vs Custom Override)
     * using the strict 3-tier waterfall defined in Design Review v1.1.7.
     */
    public function resolveUnearnedAccount(string $tenantId, ?FeeCategory $category = null): ChartOfAccount
    {
        // Tier 1: Explicit Head Override
        if ($category !== null && $category->unearned_liability_account_id !== null) {
            $account = ChartOfAccount::withoutGlobalScopes()->find($category->unearned_liability_account_id);
            if ($account) {
                return $account;
            }
        }

        // Tier 2: Category Academic Scope
        if ($category !== null && in_array($category->category_scope, ['school', 'academy'])) {
            $code = ($category->category_scope === 'academy') ? '2015' : '2010';
            return ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('account_code', $code)
                ->firstOrFail();
        }

        // Tier 3: Default Institute Domain
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);
        $code = ($tenant->type === 'academy') ? '2015' : '2010';

        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('account_code', $code)
            ->firstOrFail();
    }

    /**
     * Record an advance fee receipt from a student for a future billing period.
     * GL Impact: Debit 1010 Cash in Hand, Credit 2010/2015 Unearned Tuition/Course Revenue.
     */
    public function recordAdvancePayment(
        Student $student,
        float $amount,
        string $targetBillingPeriod,
        ?FeeCategory $category = null,
        ?string $paymentId = null,
        ?User $actor = null
    ): StudentFeeAdvance {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Advance amount must be greater than zero.");
        }

        return TenantTransactionManager::runFinancialTransaction(function () use (
            $student,
            $amount,
            $targetBillingPeriod,
            $category,
            $paymentId,
            $actor
        ) {
            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 4: Advance Stage
            FinancialLockManager::enterAdvance();

            $glAccount = $this->resolveUnearnedAccount($student->tenant_id, $category);
            $receiptNumber = $this->generateAdvanceReceiptNumber($student->tenant_id);

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $advance = StudentFeeAdvance::create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'fee_payment_id' => $paymentId,
                'fee_category_id' => $category?->id,
                'gl_account_id' => $glAccount->id,
                'target_billing_period' => $targetBillingPeriod,
                'receipt_date' => now()->toDateString(),
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'applied_amount' => 0.00,
                'refunded_amount' => 0.00,
                'status' => 'unapplied',
            ]);

            // Post Accrual GL: Debit 1010 Cash, Credit 2010/2015 Unearned Revenue
            $cashAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $student->tenant_id)
                ->where('account_code', '1010')
                ->firstOrFail();

            $this->glService->postEntry(
                [
                    'tenant_id' => $student->tenant_id,
                    'branch_id' => $student->branch_id,
                    'entry_date' => now()->toDateString(),
                    'source_type' => 'advance_receipt',
                    'source_id' => $advance->id,
                    'description' => "Advance fee receipt #{$receiptNumber} for student {$student->admission_number} ({$targetBillingPeriod})",
                ],
                [
                    ['account_id' => $cashAccount->id, 'entry_type' => 'debit', 'amount' => $amount, 'memo' => 'Cash received for advance fee'],
                    ['account_id' => $glAccount->id, 'entry_type' => 'credit', 'amount' => $amount, 'memo' => "Unearned Revenue ({$glAccount->account_code})"],
                ],
                $actor
            );

            return $advance->fresh(['student', 'glAccount', 'feeCategory']);
        });
    }

    /**
     * Allocate available advance prepayments to an invoice in deterministic FIFO order.
     * GL Impact: Debit 2010/2015 Unearned Revenue, Credit 1200 Accounts Receivable.
     */
    public function allocateAdvancesToInvoice(
        FeeInvoice $invoice,
        ?User $actor = null
    ): array {
        return TenantTransactionManager::runFinancialTransaction(function () use ($invoice, $actor) {
            $student = $invoice->student;

            // Stage 2: Student Lock
            FinancialLockManager::enterStudent($student->id);

            // Stage 3: Invoice Lock
            FinancialLockManager::enterInvoice([$invoice->id]);
            $lockedInvoice = FeeInvoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($lockedInvoice->balance <= 0) {
                return [];
            }

            // Stage 4: Advance Lock (Deterministic FIFO: receipt_date ASC, id ASC)
            FinancialLockManager::enterAdvance();

            $advancesQuery = StudentFeeAdvance::where('tenant_id', $invoice->tenant_id)
                ->where('student_id', $student->id)
                ->whereIn('status', ['unapplied', 'partially_applied'])
                ->whereRaw('(amount - applied_amount - refunded_amount) > 0')
                ->orderBy('receipt_date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate();

            if ($invoice->billing_period !== null) {
                // Prioritize advances targeted for this billing period, then untargeted/general
                $advancesQuery->orderByRaw("target_billing_period = '{$invoice->billing_period}' DESC");
            }

            $advances = $advancesQuery->get();

            if ($advances->isEmpty()) {
                return [];
            }

            $arAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('account_code', '1200')
                ->firstOrFail();

            $allocations = [];
            $remainingInvoiceBalance = (float) $lockedInvoice->balance;

            // Stage 6: Mutation & GL Posting
            FinancialLockManager::enterMutationAndGL();

            $seq = 1;
            foreach ($advances as $advance) {
                if ($remainingInvoiceBalance <= 0) {
                    break;
                }

                $availableInAdvance = (float) ($advance->amount - $advance->applied_amount - $advance->refunded_amount);
                if ($availableInAdvance <= 0) {
                    continue;
                }

                $drawdown = min($availableInAdvance, $remainingInvoiceBalance);
                $allocRef = "ALLOC-INV-{$lockedInvoice->id}-ALL-{$advance->id}-{$seq}";

                // GL Settlement Entry: Debit 2010/2015 Unearned, Credit 1200 A/R
                $settlementEntry = $this->glService->postEntry(
                    [
                        'tenant_id' => $invoice->tenant_id,
                        'branch_id' => $invoice->branch_id,
                        'entry_date' => now()->toDateString(),
                        'source_type' => 'advance_settlement',
                        'source_id' => $lockedInvoice->id,
                        'description' => "Advance #{$advance->receipt_number} settlement for Invoice #{$lockedInvoice->invoice_number}",
                    ],
                    [
                        ['account_id' => $advance->gl_account_id, 'entry_type' => 'debit', 'amount' => $drawdown, 'memo' => "Relieve Unearned Revenue ({$advance->glAccount->account_code})"],
                        ['account_id' => $arAccount->id, 'entry_type' => 'credit', 'amount' => $drawdown, 'memo' => 'Relieve Accounts Receivable'],
                    ],
                    $actor
                );

                // Insert allocation row (authoritative ledger)
                // DB trigger trg_sync_advance_applied_amount automatically updates parent advance
                $allocation = StudentFeeAdvanceAllocation::create([
                    'tenant_id' => $invoice->tenant_id,
                    'student_fee_advance_id' => $advance->id,
                    'fee_invoice_id' => $lockedInvoice->id,
                    'fee_invoice_item_id' => null,
                    'allocation_reference' => $allocRef,
                    'settlement_journal_entry_id' => $settlementEntry->id,
                    'allocated_amount' => $drawdown,
                    'allocation_date' => now()->toDateString(),
                    'status' => 'active',
                ]);

                $allocations[] = $allocation;
                $remainingInvoiceBalance = round($remainingInvoiceBalance - $drawdown, 2);
                $seq++;
            }

            // Update invoice paid_amount
            $totalAllocated = array_sum(array_map(fn($a) => (float) $a->allocated_amount, $allocations));
            $newPaid = round((float) $lockedInvoice->paid_amount + $totalAllocated, 2);

            $lockedInvoice->update([
                'paid_amount' => $newPaid,
                'status' => ($newPaid >= (float) $lockedInvoice->total_amount) ? 'paid' : 'partially_paid',
            ]);

            return $allocations;
        });
    }

    /**
     * Compute authoritative mathematical reconciliation for all 4 sub-ledger boundaries:
     * 1. Student Credit Wallets (Account 2025)
     * 2. Unearned Tuition Prepayments (Account 2010)
     * 3. Unearned Academy Course Fees (Account 2015)
     * 4. Student Accounts Receivable (Account 1200)
     */
    public function reconcileSubledgers(string $tenantId): array
    {
        // 1. Wallet Subledger vs GL 2025
        $walletSubledgerTotal = (float) StudentCreditWallet::where('tenant_id', $tenantId)->sum('balance');
        $gl2025Balance = $this->getGlCreditBalance($tenantId, '2025');
        $walletDiff = round($walletSubledgerTotal - $gl2025Balance, 2);

        // 2. School Advance Subledger vs GL 2010
        $schoolAdvanceSubledgerTotal = (float) StudentFeeAdvance::where('tenant_id', $tenantId)
            ->whereHas('glAccount', fn($q) => $q->where('account_code', '2010'))
            ->sum(DB::raw('amount - applied_amount - refunded_amount'));
        $gl2010Balance = $this->getGlCreditBalance($tenantId, '2010');
        $schoolAdvanceDiff = round($schoolAdvanceSubledgerTotal - $gl2010Balance, 2);

        // 3. Academy Advance Subledger vs GL 2015
        $academyAdvanceSubledgerTotal = (float) StudentFeeAdvance::where('tenant_id', $tenantId)
            ->whereHas('glAccount', fn($q) => $q->where('account_code', '2015'))
            ->sum(DB::raw('amount - applied_amount - refunded_amount'));
        $gl2015Balance = $this->getGlCreditBalance($tenantId, '2015');
        $academyAdvanceDiff = round($academyAdvanceSubledgerTotal - $gl2015Balance, 2);

        // 4. Accounts Receivable Subledger vs GL 1200
        $arSubledgerTotal = (float) FeeInvoice::where('tenant_id', $tenantId)
            ->where('status', '<>', 'cancelled')
            ->sum(DB::raw('total_amount - paid_amount'));
        $gl1200Balance = $this->getGlDebitBalance($tenantId, '1200');
        $arDiff = round($arSubledgerTotal - $gl1200Balance, 2);

        $isBalanced = ($walletDiff === 0.00 && $schoolAdvanceDiff === 0.00 && $academyAdvanceDiff === 0.00 && $arDiff === 0.00);

        return [
            'is_reconciled' => $isBalanced,
            'wallets' => [
                'subledger_total' => $walletSubledgerTotal,
                'gl_2025_balance' => $gl2025Balance,
                'difference' => $walletDiff,
                'is_balanced' => ($walletDiff === 0.00),
            ],
            'school_advances' => [
                'subledger_total' => $schoolAdvanceSubledgerTotal,
                'gl_2010_balance' => $gl2010Balance,
                'difference' => $schoolAdvanceDiff,
                'is_balanced' => ($schoolAdvanceDiff === 0.00),
            ],
            'academy_advances' => [
                'subledger_total' => $academyAdvanceSubledgerTotal,
                'gl_2015_balance' => $gl2015Balance,
                'difference' => $academyAdvanceDiff,
                'is_balanced' => ($academyAdvanceDiff === 0.00),
            ],
            'accounts_receivable' => [
                'subledger_total' => $arSubledgerTotal,
                'gl_1200_balance' => $gl1200Balance,
                'difference' => $arDiff,
                'is_balanced' => ($arDiff === 0.00),
            ],
        ];
    }

    protected function getGlCreditBalance(string $tenantId, string $accountCode): float
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', $accountCode)->first();
        if (!$account) {
            return 0.00;
        }

        $postedLines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $account->id)
            ->select('journal_entry_lines.entry_type', 'journal_entry_lines.amount')
            ->get();

        $credits = $postedLines->where('entry_type', 'credit')->sum('amount');
        $debits = $postedLines->where('entry_type', 'debit')->sum('amount');

        return round((float) ($credits - $debits), 2);
    }

    protected function getGlDebitBalance(string $tenantId, string $accountCode): float
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('account_code', $accountCode)->first();
        if (!$account) {
            return 0.00;
        }

        $postedLines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.tenant_id', $tenantId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $account->id)
            ->select('journal_entry_lines.entry_type', 'journal_entry_lines.amount')
            ->get();

        $debits = $postedLines->where('entry_type', 'debit')->sum('amount');
        $credits = $postedLines->where('entry_type', 'credit')->sum('amount');

        return round((float) ($debits - $credits), 2);
    }

    protected function generateAdvanceReceiptNumber(string $tenantId): string
    {
        $yearMonth = now()->format('Ym');
        $random = strtoupper(substr(uniqid(), -5));
        return "ADV-{$yearMonth}-{$random}";
    }
}

