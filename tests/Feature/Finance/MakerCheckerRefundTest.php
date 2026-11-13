<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\FeeRefund;
use App\Modules\Finance\Services\CashierSessionService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\RefundManagementService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class MakerCheckerRefundTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $maker;
    protected User $checker;
    protected Student $student;
    protected GeneralLedgerService $glService;
    protected CashierSessionService $cashierService;
    protected RefundManagementService $refundService;

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

        $this->maker = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant Maker',
            'email' => 'maker@educore.test',
        ]);

        $this->checker = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Principal Checker',
            'email' => 'checker@educore.test',
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-777',
            'first_name' => 'Mustafa',
            'last_name' => 'Ali',
            'date_of_birth' => '2012-05-10',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->glService = new GeneralLedgerService();
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $this->cashierService = new CashierSessionService();
        $this->refundService = new RefundManagementService();
    }

    public function test_maker_can_request_refund_with_proportional_concession_clawback(): void
    {
        // Invoice: Gross 20,000 | Discount 5,000 | Net Paid: 15,000
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-REF-01',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'subtotal' => 20000.00,
            'discount_amount' => 5000.00,
            'total_amount' => 15000.00,
            'paid_amount' => 15000.00,
            'status' => 'paid',
        ]);

        $payment = FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'received_by_user_id' => $this->maker->id,
            'receipt_number' => 'REC-REF-01',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 15000.00,
            'allocated_to_invoices' => 15000.00,
            'status' => 'posted',
        ]);

        // Request 50% refund (Rs. 7,500 out of Rs. 15,000 paid)
        // Ratio = 7,500 / 15,000 = 50%
        // Concession clawback = 50% * 5,000 = 2,500
        // Net Cash Payout = 7,500 - 2,500 = 5,000 (Or if requested is 10,000 gross, net payout is 7,500)
        // In our service: requestedAmount = 10,000 gross
        $refund = $this->refundService->requestRefund(
            $payment,
            10000.00,
            'Student withdrawal after 2 weeks',
            $this->maker,
            ['fee_invoice_id' => $invoice->id, 'refund_method' => 'cash']
        );

        $this->assertNotNull($refund->id);
        $this->assertEquals('pending_approval', $refund->status);
        $this->assertEquals($this->maker->id, $refund->maker_user_id);
        $this->assertNull($refund->checker_user_id);
        $this->assertEquals(10000.00, $refund->requested_amount);
        $this->assertEquals(2500.00, $refund->concession_clawback_amount);
        $this->assertEquals(7500.00, $refund->net_payout_amount);
    }

    public function test_maker_cannot_approve_own_refund_enforcing_segregation_of_duties(): void
    {
        $payment = FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'received_by_user_id' => $this->maker->id,
            'receipt_number' => 'REC-REF-02',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 5000.00,
            'allocated_to_invoices' => 5000.00,
            'status' => 'posted',
        ]);

        $refund = $this->refundService->requestRefund(
            $payment,
            5000.00,
            'Admission cancellation',
            $this->maker
        );

        // Attempt by Maker to approve their own refund is strictly rejected
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maker-Checker segregation of duties violation');

        $this->refundService->approveRefund($refund, $this->maker);
    }

    public function test_checker_approves_and_disburses_refund_with_gl_entry_and_drawer_deduction(): void
    {
        $session = $this->cashierService->openSession($this->maker, $this->branch, 10000.00);

        $payment = FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'received_by_user_id' => $this->maker->id,
            'receipt_number' => 'REC-REF-03',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 8000.00,
            'allocated_to_invoices' => 8000.00,
            'status' => 'posted',
        ]);

        $refund = $this->refundService->requestRefund(
            $payment,
            8000.00,
            'Security deposit refund',
            $this->maker,
            ['refund_method' => 'cash']
        );

        // Checker approves
        $approvedRefund = $this->refundService->approveRefund($refund, $this->checker);
        $this->assertEquals('approved', $approvedRefund->status);
        $this->assertEquals($this->checker->id, $approvedRefund->checker_user_id);
        $this->assertNotNull($approvedRefund->approved_at);

        // Disburse refund
        $disbursedRefund = $this->refundService->disburseRefund($approvedRefund, $this->maker, $session);
        $this->assertEquals('disbursed', $disbursedRefund->status);
        $this->assertNotNull($disbursedRefund->disbursed_at);
        $this->assertEquals($this->maker->id, $disbursedRefund->disbursed_by_user_id);

        // Cashier drawer deducted: 10,000 - 8,000 = 2,000
        $session->refresh();
        $this->assertEquals(2000.00, $session->expected_cash_balance);

        // GL Entry posted:
        // Debit 4010 Tuition Revenue (8,000), Credit 1010 Cash (8,000)
        $glEntry = $disbursedRefund->journalEntry;
        $this->assertNotNull($glEntry);
        $this->assertEquals('posted', $glEntry->status);
        $this->assertEquals('fee_refund', $glEntry->source_type);
        $this->assertEquals(8000.00, $glEntry->lines()->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(8000.00, $glEntry->lines()->where('entry_type', 'credit')->sum('amount'));

        // Parent payment status updated to fully_refunded
        $payment->refresh();
        $this->assertEquals('fully_refunded', $payment->status);
    }

    public function test_immediate_rls_isolation_on_fee_refund_tables(): void
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
            'admission_number' => 'BHS-2026-999',
            'first_name' => 'Zubair',
            'last_name' => 'Malik',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $paymentA = FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'received_by_user_id' => $this->maker->id,
            'receipt_number' => 'REC-A-REF',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 3000.00,
            'allocated_to_invoices' => 3000.00,
        ]);

        $paymentB = FeePayment::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'student_id' => $studentB->id,
            'received_by_user_id' => $this->maker->id,
            'receipt_number' => 'REC-B-REF',
            'payment_date' => now()->toDateString(),
            'amount_tendered' => 4000.00,
            'allocated_to_invoices' => 4000.00,
        ]);

        $refA = FeeRefund::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'fee_payment_id' => $paymentA->id,
            'refund_number' => 'REF-A-001',
            'refund_date' => now()->toDateString(),
            'requested_amount' => 3000.00,
            'reason' => 'Test A',
            'maker_user_id' => $this->maker->id,
        ]);

        $refB = FeeRefund::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'student_id' => $studentB->id,
            'fee_payment_id' => $paymentB->id,
            'refund_number' => 'REF-B-001',
            'refund_date' => now()->toDateString(),
            'requested_amount' => 4000.00,
            'reason' => 'Test B',
            'maker_user_id' => $this->maker->id,
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visibleRefunds = DB::table('fee_refunds')->pluck('id')->toArray();

            $this->assertContains($refA->id, $visibleRefunds);
            $this->assertNotContains($refB->id, $visibleRefunds);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}

