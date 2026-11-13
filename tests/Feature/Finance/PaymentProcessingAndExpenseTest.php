<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\StudentCreditWallet;
use App\Modules\Finance\Models\StudentFeeAdvance;
use App\Modules\Finance\Services\CashierSessionService;
use App\Modules\Finance\Services\ExpenseVoucherService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Finance\Services\StudentAdvanceService;
use App\Modules\Finance\Services\StudentWalletService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentProcessingAndExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $cashier;
    protected Student $student;
    protected GeneralLedgerService $glService;
    protected CashierSessionService $cashierService;
    protected PaymentProcessingService $paymentService;
    protected ExpenseVoucherService $expenseService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Lahore Grammar Main Campus',
            'slug' => 'lgs-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg Branch',
            'code' => 'GB-01',
            'city' => 'Lahore',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);

        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Saleem',
            'email' => 'saleem@educore.test',
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-888',
            'first_name' => 'Ibrahim',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-04-10',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->glService = new GeneralLedgerService();
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $this->cashierService = new CashierSessionService();
        $this->paymentService = new PaymentProcessingService();
        $this->expenseService = new ExpenseVoucherService();
    }

    public function test_can_process_exact_payment_and_pay_off_invoice(): void
    {
        $session = $this->cashierService->openSession($this->cashier, $this->branch, 5000.00);

        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-PAY-01',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 12000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        $payment = $this->paymentService->processPayment(
            $this->student,
            12000.00,
            [
                'payment_method' => 'cash',
                'cashier_session_id' => $session->id,
                'received_by_user_id' => $this->cashier->id,
                'target_invoice_ids' => [$invoice->id],
            ]
        );

        $this->assertNotNull($payment->id);
        $this->assertEquals(12000.00, $payment->amount_tendered);
        $this->assertEquals(12000.00, $payment->allocated_to_invoices);
        $this->assertEquals(0.00, $payment->allocated_to_advances);
        $this->assertEquals(0.00, $payment->credited_to_wallet);
        $this->assertCount(1, $payment->allocations);

        // Verify invoice updated to paid
        $invoice->refresh();
        $this->assertEquals(12000.00, $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->status);

        // Verify cashier drawer increased: 5,000 + 12,000 = 17,000
        $session->refresh();
        $this->assertEquals(17000.00, $session->expected_cash_balance);

        // Verify GL: Debit 1010 Cash (12,000), Credit 1200 A/R (12,000)
        $glEntry = $payment->journalEntry;
        $this->assertNotNull($glEntry);
        $this->assertEquals('posted', $glEntry->status);
        $lines = $glEntry->lines;
        $this->assertEquals(12000.00, $lines->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(12000.00, $lines->where('entry_type', 'credit')->sum('amount'));
    }

    public function test_overpayment_splits_across_invoice_and_wallet_with_zero_drift(): void
    {
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-PAY-02',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 8000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        // Student tenders Rs. 10,000 for an Rs. 8,000 invoice (Rs. 2,000 overpayment)
        $payment = $this->paymentService->processPayment(
            $this->student,
            10000.00,
            [
                'payment_method' => 'bank_transfer',
                'received_by_user_id' => $this->cashier->id,
                'target_invoice_ids' => [$invoice->id],
            ]
        );

        $this->assertEquals(10000.00, $payment->amount_tendered);
        $this->assertEquals(8000.00, $payment->allocated_to_invoices);
        $this->assertEquals(0.00, $payment->allocated_to_advances);
        $this->assertEquals(2000.00, $payment->credited_to_wallet);

        // Wallet has Rs. 2,000
        $wallet = StudentCreditWallet::where('student_id', $this->student->id)->first();
        $this->assertNotNull($wallet);
        $this->assertEquals(2000.00, $wallet->balance);

        // GL Lines: Debit 1020 Bank (10,000), Credit 1200 A/R (8,000), Credit 2025 Wallet Liability (2,000)
        $lines = $payment->journalEntry->lines;
        $this->assertEquals(10000.00, $lines->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(10000.00, $lines->where('entry_type', 'credit')->sum('amount'));

        $arCredit = $lines->first(fn($l) => $l->entry_type === 'credit' && $l->account->account_code === '1200');
        $walletCredit = $lines->first(fn($l) => $l->entry_type === 'credit' && $l->account->account_code === '2025');

        $this->assertEquals(8000.00, (float) $arCredit->amount);
        $this->assertEquals(2000.00, (float) $walletCredit->amount);
    }

    public function test_advance_targeted_payment_allocates_excess_to_future_prepayment(): void
    {
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-PAY-03',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 5000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        // Student tenders Rs. 15,000 (Rs. 5,000 clears invoice, Rs. 10,000 targeted to November advance)
        $payment = $this->paymentService->processPayment(
            $this->student,
            15000.00,
            [
                'payment_method' => 'cash',
                'received_by_user_id' => $this->cashier->id,
                'target_invoice_ids' => [$invoice->id],
                'target_advance_period' => '2026-11',
            ]
        );

        $this->assertEquals(15000.00, $payment->amount_tendered);
        $this->assertEquals(5000.00, $payment->allocated_to_invoices);
        $this->assertEquals(10000.00, $payment->allocated_to_advances);
        $this->assertEquals(0.00, $payment->credited_to_wallet);

        // Advance prepayment row was created
        $advance = StudentFeeAdvance::where('student_id', $this->student->id)->where('target_billing_period', '2026-11')->first();
        $this->assertNotNull($advance);
        $this->assertEquals(10000.00, $advance->amount);
        $this->assertEquals(10000.00, $advance->remaining_balance);
        $this->assertEquals('2010', $advance->glAccount->account_code);
    }

    public function test_can_disburse_and_void_expense_voucher_with_cashier_drawer_integration(): void
    {
        $session = $this->cashierService->openSession($this->cashier, $this->branch, 10000.00);

        $utilAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5100')->firstOrFail();
        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '1010')->firstOrFail();

        $voucher = $this->expenseService->createExpenseVoucher(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'cashier_session_id' => $session->id,
                'payee_name' => 'Lahore Electric Supply Company (LESCO)',
                'payment_method' => 'cash',
                'debit_account_id' => $utilAccount->id,
                'credit_account_id' => $cashAccount->id,
                'amount' => 3500.00,
                'description' => 'Electricity bill payment',
            ],
            $this->cashier
        );

        $this->assertNotNull($voucher->id);
        $this->assertEquals('posted', $voucher->status);
        $this->assertEquals(3500.00, $voucher->amount);

        // Drawer balance deducted: 10,000 - 3,500 = 6,500
        $session->refresh();
        $this->assertEquals(6500.00, $session->expected_cash_balance);

        // Verify GL: Debit 5100 Utilities (3,500), Credit 1010 Cash (3,500)
        $glEntry = $voucher->journalEntry;
        $this->assertNotNull($glEntry);
        $this->assertEquals('posted', $glEntry->status);
        $this->assertEquals(3500.00, $glEntry->lines()->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(3500.00, $glEntry->lines()->where('entry_type', 'credit')->sum('amount'));

        // Void the voucher
        $voidedVoucher = $this->expenseService->voidExpenseVoucher($voucher, 'Cheque cancelled');
        $this->assertEquals('voided', $voidedVoucher->status);

        // Original GL entry must be marked reversed
        $glEntry->refresh();
        $this->assertEquals('reversed', $glEntry->status);
    }

    public function test_immediate_rls_isolation_on_payment_and_expense_tables(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS test requires PostgreSQL.');
        }

        $tenantB = Tenant::create([
            'name' => 'Beaconhouse Defence Campus',
            'slug' => 'bhs-defence',
            'type' => 'school',
            'status' => 'active',
        ]);

        $branchB = Branch::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Defence Branch',
            'code' => 'BHS-01',
            'city' => 'Lahore',
        ]);

        $studentB = Student::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'admission_number' => 'BHS-2026-002',
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $payA = FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'received_by_user_id' => $this->cashier->id,
            'receipt_number' => 'REC-A-001',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 5000.00,
            'allocated_to_invoices' => 5000.00,
        ]);

        $payB = FeePayment::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'student_id' => $studentB->id,
            'received_by_user_id' => $this->cashier->id,
            'receipt_number' => 'REC-B-001',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 6000.00,
            'allocated_to_invoices' => 6000.00,
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visiblePayments = DB::table('fee_payments')->pluck('id')->toArray();

            $this->assertContains($payA->id, $visiblePayments);
            $this->assertNotContains($payB->id, $visiblePayments);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}

