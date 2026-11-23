<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\FeeRefund;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Finance\Services\ExpenseVoucherService;
use App\Modules\Finance\Services\RefundManagementService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;

class ExpenseAndRefundConsole extends Component
{
    public string $activeTab = 'expenses'; // expenses | refunds | concessions

    // 1. Expense Voucher Fields
    public string $payeeName = '';
    public string $paymentMethod = 'cash';
    public ?string $debitAccountId = null;
    public ?string $creditAccountId = null;
    public float $expenseAmount = 0.00;
    public string $expenseDescription = '';
    public string $voidReason = 'Operational reversal';

    // 2. Maker-Checker Refund Fields
    public ?string $selectedPaymentId = null;
    public float $refundAmount = 0.00;
    public string $refundMethod = 'cash';
    public string $refundReason = '';
    public string $rejectionReason = 'Policy non-compliance';

    // 3. Fee Concession & Student Discount Fields
    public string $discountCode = '';
    public string $discountName = '';
    public string $discountType = 'percentage'; // percentage | fixed_amount
    public float $discountValue = 10.00;
    public string $stackingRule = 'highest_wins'; // highest_wins | compound | sum_capped
    public ?string $applicableCategoryId = null;

    public ?string $concessionStudentId = null;
    public ?string $concessionDiscountId = null;
    public string $concessionValidFrom = '';
    public ?string $concessionValidUntil = null;
    public string $concessionReason = '';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->concessionValidFrom = now()->startOfMonth()->toDateString();

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return;
        }

        $this->debitAccountId = ChartOfAccount::where('tenant_id', $tenant->id)
            ->where('account_type', 'expense')
            ->where('account_code', '5100')
            ->value('id')
            ?? ChartOfAccount::where('tenant_id', $tenant->id)->where('account_type', 'expense')->value('id');

        $this->creditAccountId = ChartOfAccount::where('tenant_id', $tenant->id)
            ->where('account_code', '1010')
            ->value('id');
    }

    protected function authorizeActor(string $permission = 'finance.manage_structures'): User
    {
        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$actor->isSuperAdmin() && (!$tenant || $actor->tenant_id !== $tenant->id)) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        if (!$actor->isSuperAdmin()) {
            if (!$actor->can($permission)) {
                throw new AuthorizationException("Missing required finance permission [{$permission}].");
            }
        }

        return $actor;
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function createExpense(): void
    {
        $this->resetFeedback();
        $actor = $this->authorizeActor('finance.collect_fee');

        $this->validate([
            'payeeName' => 'required|string|min:2|max:150',
            'paymentMethod' => 'required|in:cash,bank_transfer,cheque',
            'debitAccountId' => 'required|uuid',
            'creditAccountId' => 'required|uuid',
            'expenseAmount' => 'required|numeric|gt:0',
            'expenseDescription' => 'nullable|string|max:255',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $debitAcc = ChartOfAccount::where('tenant_id', $tenant->id)->find($this->debitAccountId);
            $creditAcc = ChartOfAccount::where('tenant_id', $tenant->id)->find($this->creditAccountId);

            if (!$debitAcc || !$creditAcc) {
                $this->errorMessage = 'Selected GL accounts do not belong to this workspace.';
                return;
            }

            $openSession = CashierSession::where('tenant_id', $tenant->id)
                ->where('cashier_user_id', $actor->id)
                ->where('status', 'open')
                ->latest()
                ->first();

            $voucher = app(ExpenseVoucherService::class)->createExpenseVoucher([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'cashier_session_id' => $openSession?->id,
                'payee_name' => trim($this->payeeName),
                'payment_method' => $this->paymentMethod,
                'debit_account_id' => $debitAcc->id,
                'credit_account_id' => $creditAcc->id,
                'amount' => $this->expenseAmount,
                'description' => $this->expenseDescription ?: null,
            ], $actor);

            $this->reset(['payeeName', 'expenseAmount', 'expenseDescription']);
            $this->successMessage = "Expense voucher #{$voucher->voucher_number} posted successfully!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function voidExpense(string $voucherId): void
    {
        $this->resetFeedback();
        $actor = $this->authorizeActor('finance.manage_structures');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $voucher = ExpenseVoucher::where('tenant_id', $tenant->id)->findOrFail($voucherId);
            app(ExpenseVoucherService::class)->voidExpenseVoucher($voucher, $this->voidReason ?: 'Operational reversal', $actor);
            $this->successMessage = "Expense voucher #{$voucher->voucher_number} voided and GL entry reversed.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function requestRefund(): void
    {
        $this->resetFeedback();
        $maker = $this->authorizeActor('finance.collect_fee');

        $this->validate([
            'selectedPaymentId' => 'required|uuid',
            'refundAmount' => 'required|numeric|gt:0',
            'refundMethod' => 'required|in:cash,bank_transfer,cheque',
            'refundReason' => 'required|string|min:3|max:255',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $payment = FeePayment::where('tenant_id', $tenant->id)->findOrFail($this->selectedPaymentId);
            $refund = app(RefundManagementService::class)->requestRefund(
                $payment,
                $this->refundAmount,
                trim($this->refundReason),
                $maker,
                ['refund_method' => $this->refundMethod]
            );

            $this->reset(['selectedPaymentId', 'refundAmount', 'refundReason']);
            $this->successMessage = "Refund request #{$refund->refund_number} submitted for checker approval.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function approveRefund(string $refundId): void
    {
        $this->resetFeedback();
        $checker = $this->authorizeActor('finance.manage_structures');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $refund = FeeRefund::where('tenant_id', $tenant->id)->findOrFail($refundId);
            app(RefundManagementService::class)->approveRefund($refund, $checker);
            \App\Core\Audit\Services\TenantAuditService::record('refund.approved', FeeRefund::class, $refund->id, ['refund_number' => $refund->refund_number], $tenant->id, $checker->id);
            $this->successMessage = "Refund #{$refund->refund_number} approved by checker.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function rejectRefund(string $refundId): void
    {
        $this->resetFeedback();
        $checker = $this->authorizeActor('finance.manage_structures');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $refund = FeeRefund::where('tenant_id', $tenant->id)->findOrFail($refundId);
            app(RefundManagementService::class)->rejectRefund($refund, $checker, $this->rejectionReason ?: 'Rejected by checker');
            $this->successMessage = "Refund #{$refund->refund_number} rejected.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function disburseRefund(string $refundId): void
    {
        $this->resetFeedback();
        $disburser = $this->authorizeActor('finance.collect_fee');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $refund = FeeRefund::where('tenant_id', $tenant->id)->findOrFail($refundId);
            $openSession = CashierSession::where('tenant_id', $tenant->id)
                ->where('cashier_user_id', $disburser->id)
                ->where('status', 'open')
                ->latest()
                ->first();

            app(RefundManagementService::class)->disburseRefund($refund, $disburser, $openSession);
            \App\Core\Audit\Services\TenantAuditService::record('refund.disbursed', FeeRefund::class, $refund->id, ['refund_number' => $refund->refund_number], $tenant->id, $disburser->id);
            $this->successMessage = "Refund #{$refund->refund_number} disbursed and posted to General Ledger.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function createDiscountPolicy(): void
    {
        $this->resetFeedback();
        $this->authorizeActor('finance.manage_structures');

        if ($this->discountType === 'fixed') {
            $this->discountType = 'fixed_amount';
        }
        if ($this->stackingRule === 'additive') {
            $this->stackingRule = 'sum_capped';
        } elseif ($this->stackingRule === 'highest_only' || $this->stackingRule === 'exclusive') {
            $this->stackingRule = 'highest_wins';
        }

        $this->validate([
            'discountCode' => 'required|string|min:2|max:30',
            'discountName' => 'required|string|min:2|max:120',
            'discountType' => 'required|in:percentage,fixed_amount',
            'discountValue' => 'required|numeric|gt:0',
            'stackingRule' => 'required|in:highest_wins,compound,sum_capped',
        ]);

        if ($this->discountType === 'percentage' && $this->discountValue > 100) {
            $this->errorMessage = 'Percentage discount cannot exceed 100%.';
            return;
        }

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $contraAccount = ChartOfAccount::where('tenant_id', $tenant->id)
                ->where('account_code', '5010')
                ->first();

            if (!$contraAccount) {
                $this->errorMessage = 'Contra-revenue account 5010 (Student Fee Discounts) not found.';
                return;
            }

            $discount = TenantTransactionManager::run(function () use ($tenant, $contraAccount) {
                return FeeDiscount::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'code' => strtoupper(trim($this->discountCode)),
                    ],
                    [
                        'name' => trim($this->discountName),
                        'discount_type' => $this->discountType,
                        'discount_value' => $this->discountValue,
                        'contra_account_id' => $contraAccount->id,
                        'applicable_category_id' => $this->applicableCategoryId ?: null,
                        'stacking_rule' => $this->stackingRule,
                        'is_active' => true,
                    ]
                );
            });

            $this->discountCode = '';
            $this->discountName = '';
            $this->concessionDiscountId = $discount->id;
            $this->successMessage = 'Fee concession policy created successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function assignStudentConcession(): void
    {
        $this->resetFeedback();
        $actor = $this->authorizeActor('finance.manage_structures');

        $this->validate([
            'concessionStudentId' => 'required|uuid',
            'concessionDiscountId' => 'required|uuid',
            'concessionValidFrom' => 'required|date',
            'concessionValidUntil' => 'nullable|date|after_or_equal:concessionValidFrom',
            'concessionReason' => 'required|string|min:3|max:255',
        ]);

        $tenant = app(TenantContext::class)->getTenant();

        try {
            $student = Student::where('tenant_id', $tenant->id)->find($this->concessionStudentId);
            $discount = FeeDiscount::where('tenant_id', $tenant->id)->find($this->concessionDiscountId);

            if (!$student || !$discount) {
                $this->errorMessage = 'Selected student or concession policy does not belong to this workspace.';
                return;
            }

            if (!$discount->is_active) {
                $this->errorMessage = 'Selected concession policy is inactive.';
                return;
            }

            // Reuse the existing concession service so approval/validity rules stay in one place.
            app(\App\Modules\Finance\Services\StudentConcessionService::class)->assignConcession($student, $discount, [
                'valid_from' => $this->concessionValidFrom,
                'valid_until' => $this->concessionValidUntil ?: null,
                'status' => 'active',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'reason' => trim($this->concessionReason),
            ]);

            $this->concessionReason = '';
            $this->successMessage = "Concession '{$discount->name}' granted to {$student->full_name}.";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function revokeStudentConcession(string $studentDiscountId): void
    {
        $this->resetFeedback();
        $this->authorizeActor('finance.manage_structures');
        $tenant = app(TenantContext::class)->getTenant();

        try {
            $record = StudentFeeDiscount::where('tenant_id', $tenant->id)->findOrFail($studentDiscountId);
            $record->update(['status' => 'suspended']);
            $this->successMessage = 'Student fee concession suspended.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $expenses = $tenant
            ? ExpenseVoucher::with(['debitAccount', 'creditAccount', 'approvedByUser'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->limit(25)
                ->get()
            : collect();

        $refunds = $tenant
            ? FeeRefund::with(['student', 'payment', 'maker', 'checker'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->limit(25)
                ->get()
            : collect();

        $payments = $tenant
            ? FeePayment::with('student')
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['posted', 'partially_refunded'])
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $discounts = $tenant ? FeeDiscount::with('applicableCategory')->where('tenant_id', $tenant->id)->get() : collect();
        $studentDiscounts = $tenant
            ? StudentFeeDiscount::with(['student', 'feeDiscount'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->get()
            : collect();

        $expenseAccounts = $tenant ? ChartOfAccount::where('tenant_id', $tenant->id)->where('account_type', 'expense')->get() : collect();
        $assetAccounts = $tenant ? ChartOfAccount::where('tenant_id', $tenant->id)->whereIn('account_code', ['1010', '1020', '1021'])->get() : collect();
        $categories = $tenant ? FeeCategory::where('tenant_id', $tenant->id)->where('is_active', true)->get() : collect();
        $students = $tenant ? Student::where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('first_name')->limit(100)->get() : collect();

        return view('livewire.finance.expense-and-refund-console', [
            'tenant' => $tenant,
            'expenses' => $expenses,
            'refunds' => $refunds,
            'payments' => $payments,
            'discounts' => $discounts,
            'studentDiscounts' => $studentDiscounts,
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
            'categories' => $categories,
            'students' => $students,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
