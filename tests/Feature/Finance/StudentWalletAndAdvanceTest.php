<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\StudentCreditWallet;
use App\Modules\Finance\Models\StudentFeeAdvance;
use App\Modules\Finance\Models\StudentFeeAdvanceAllocation;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\StudentAdvanceService;
use App\Modules\Finance\Services\StudentWalletService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentWalletAndAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $schoolTenant;
    protected Tenant $academyTenant;
    protected Branch $schoolBranch;
    protected Branch $academyBranch;
    protected Student $schoolStudent;
    protected Student $academyStudent;
    protected GeneralLedgerService $glService;
    protected StudentWalletService $walletService;
    protected StudentAdvanceService $advanceService;
    protected InvoiceGenerationService $invoiceGenService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. School Tenant
        $this->schoolTenant = Tenant::create([
            'name' => 'Lahore Grammar Main Campus',
            'slug' => 'lgs-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->schoolBranch = Branch::create([
            'tenant_id' => $this->schoolTenant->id,
            'name' => 'Gulberg Branch',
            'code' => 'GB-01',
            'city' => 'Lahore',
        ]);

        // 2. Academy Tenant
        $this->academyTenant = Tenant::create([
            'name' => 'KIPS Academy Gulberg',
            'slug' => 'kips-gulberg',
            'type' => 'academy',
            'status' => 'active',
        ]);

        $this->academyBranch = Branch::create([
            'tenant_id' => $this->academyTenant->id,
            'name' => 'KIPS Campus 1',
            'code' => 'KIPS-01',
            'city' => 'Lahore',
        ]);

        app(TenantContext::class)->setTenant($this->schoolTenant);

        $this->glService = new GeneralLedgerService();
        $this->glService->seedDefaultChartOfAccounts($this->schoolTenant->id);
        $this->glService->seedDefaultChartOfAccounts($this->academyTenant->id);

        $this->walletService = new StudentWalletService();
        $this->advanceService = new StudentAdvanceService();
        $this->invoiceGenService = new InvoiceGenerationService();

        $this->schoolStudent = Student::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'admission_number' => 'LGS-2026-901',
            'first_name' => 'Zayd',
            'last_name' => 'Ahmed',
            'date_of_birth' => '2012-03-10',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->academyStudent = Student::withoutGlobalScopes()->create([
            'tenant_id' => $this->academyTenant->id,
            'branch_id' => $this->academyBranch->id,
            'admission_number' => 'KIPS-2026-501',
            'first_name' => 'Hamza',
            'last_name' => 'Tariq',
            'date_of_birth' => '2008-06-15',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);
    }

    public function test_can_credit_and_debit_student_credit_wallet_with_gl_consistency(): void
    {
        // 1. Credit Wallet by PKR 15,000
        $creditTx = $this->walletService->creditWallet(
            $this->schoolStudent,
            15000.00,
            'direct_deposit',
            null,
            'Guardian initial wallet deposit'
        );

        $this->assertEquals(15000.00, $creditTx->amount);
        $this->assertEquals(15000.00, $creditTx->balance_after);
        $this->assertEquals(15000.00, $this->walletService->getWalletBalance($this->schoolStudent));

        // Verify GL: Credit balance on 2025 is exactly 15,000
        $glEntry = $creditTx->journalEntry;
        $this->assertNotNull($glEntry);
        $this->assertEquals('posted', $glEntry->status);
        $this->assertEquals('wallet_credit', $glEntry->source_type);

        // 2. Create invoice for PKR 10,000
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'student_id' => $this->schoolStudent->id,
            'invoice_number' => 'INV-TEST-W1',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        // 3. Debit Wallet by PKR 10,000 against Invoice
        $debitTx = $this->walletService->debitWallet(
            $this->schoolStudent,
            10000.00,
            $invoice
        );

        $this->assertEquals(10000.00, $debitTx->amount);
        $this->assertEquals(5000.00, $debitTx->balance_after);
        $this->assertEquals(5000.00, $this->walletService->getWalletBalance($this->schoolStudent));

        $invoice->refresh();
        $this->assertEquals(10000.00, $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_advance_payment_3_tier_waterfall_gl_mapping(): void
    {
        // Tier 3: School Tenant without category defaults to GL 2010 (Unearned Tuition)
        $schoolAdvance = $this->advanceService->recordAdvancePayment(
            $this->schoolStudent,
            8000.00,
            '2026-11'
        );
        $this->assertEquals('2010', $schoolAdvance->glAccount->account_code);

        // Tier 3: Academy Tenant without category defaults to GL 2015 (Unearned Course Revenue)
        $academyAdvance = $this->advanceService->recordAdvancePayment(
            $this->academyStudent,
            25000.00,
            '2026-11'
        );
        $academyGl = ChartOfAccount::withoutGlobalScopes()->findOrFail($academyAdvance->gl_account_id);
        $this->assertEquals('2015', $academyGl->account_code);

        // Tier 2: Category Academic Scope override
        $academyScopedCat = FeeCategory::create([
            'tenant_id' => $this->schoolTenant->id,
            'code' => 'ACAD_PREP',
            'name' => 'Academy Preparation Course Head',
            'category_scope' => 'academy',
        ]);

        $scopedAdvance = $this->advanceService->recordAdvancePayment(
            $this->schoolStudent,
            12000.00,
            '2026-11',
            $academyScopedCat
        );
        $this->assertEquals('2015', $scopedAdvance->glAccount->account_code);
    }

    public function test_multiple_advance_receipts_for_same_period_accepted_and_allocated_fifo(): void
    {
        // Advance Receipt 1: Rs. 5,000 for October tuition (admitted earlier)
        $adv1 = $this->advanceService->recordAdvancePayment(
            $this->schoolStudent,
            5000.00,
            '2026-10'
        );

        // Advance Receipt 2: Rs. 3,000 for October tuition (admitted separately)
        $adv2 = $this->advanceService->recordAdvancePayment(
            $this->schoolStudent,
            3000.00,
            '2026-10'
        );

        $this->assertNotEquals($adv1->id, $adv2->id);
        $this->assertNotEquals($adv1->receipt_number, $adv2->receipt_number);
        $this->assertEquals(5000.00, $adv1->remaining_balance);
        $this->assertEquals(3000.00, $adv2->remaining_balance);

        // Invoice for October is generated: Rs. 7,000
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'student_id' => $this->schoolStudent->id,
            'invoice_number' => 'INV-OCT-001',
            'billing_period' => '2026-10',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 7000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        // Allocate advances via deterministic FIFO
        $allocations = $this->advanceService->allocateAdvancesToInvoice($invoice);

        $this->assertCount(2, $allocations);

        // Allocation 1: Consumes entire Rs. 5,000 from Advance 1
        $this->assertEquals(5000.00, $allocations[0]->allocated_amount);
        $this->assertEquals($adv1->id, $allocations[0]->student_fee_advance_id);

        // Allocation 2: Consumes Rs. 2,000 from Advance 2 (leaving Rs. 1,000 unapplied)
        $this->assertEquals(2000.00, $allocations[1]->allocated_amount);
        $this->assertEquals($adv2->id, $allocations[1]->student_fee_advance_id);

        // Verify Zero-Drift DB Trigger updated parent advance records automatically
        $adv1->refresh();
        $adv2->refresh();

        $this->assertEquals(5000.00, $adv1->applied_amount);
        $this->assertEquals(0.00, $adv1->remaining_balance);
        $this->assertEquals('fully_applied', $adv1->status);

        $this->assertEquals(2000.00, $adv2->applied_amount);
        $this->assertEquals(1000.00, $adv2->remaining_balance);
        $this->assertEquals('partially_applied', $adv2->status);

        // Invoice is now fully paid
        $invoice->refresh();
        $this->assertEquals(7000.00, $invoice->paid_amount);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_subledger_reconciliation_maintains_exact_mathematical_equilibrium(): void
    {
        // 1. Initial State: Reconciled at 0.00
        $recon = $this->advanceService->reconcileSubledgers($this->schoolTenant->id);
        $this->assertTrue($recon['is_reconciled']);
        $this->assertTrue($recon['wallets']['is_balanced']);
        $this->assertTrue($recon['school_advances']['is_balanced']);
        $this->assertTrue($recon['academy_advances']['is_balanced']);
        $this->assertTrue($recon['accounts_receivable']['is_balanced']);

        // 2. Perform various financial operations
        // Credit wallet Rs. 20,000
        $this->walletService->creditWallet($this->schoolStudent, 20000.00, 'deposit', null, 'Initial wallet credit');

        // Advance receipt Rs. 15,000 for Nov
        $this->advanceService->recordAdvancePayment($this->schoolStudent, 15000.00, '2026-11');

        // Invoice Rs. 12,000 for Nov
        $session = AcademicSession::create([
            'tenant_id' => $this->schoolTenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'name' => 'Grade 8',
            'numeric_level' => 8,
        ]);

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $this->schoolTenant->id,
            'code' => 'TUITION_8',
            'name' => 'Grade 8 Tuition',
            'default_revenue_account_id' => ChartOfAccount::where('tenant_id', $this->schoolTenant->id)->where('account_code', '4010')->first()->id,
        ]);

        $structService = new FeeStructureService();
        $structService->createStructure(
            [
                'tenant_id' => $this->schoolTenant->id,
                'branch_id' => $this->schoolBranch->id,
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'name' => 'Grade 8 Structure',
            ],
            [['fee_category_id' => $tuitionCat->id, 'amount' => 12000.00]]
        );

        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->schoolStudent,
            '2026-11',
            '2026-11-01',
            '2026-11-15',
            $session->id,
            $class->id
        );

        // 3. Subledgers must be 100% reconciled before advance allocation
        $reconMid = $this->advanceService->reconcileSubledgers($this->schoolTenant->id);
        $this->assertTrue($reconMid['is_reconciled']);
        $this->assertEquals(20000.00, $reconMid['wallets']['subledger_total']);
        $this->assertEquals(15000.00, $reconMid['school_advances']['subledger_total']);
        $this->assertEquals(12000.00, $reconMid['accounts_receivable']['subledger_total']);

        // 4. Allocate advance of Rs. 12,000 to invoice
        $this->advanceService->allocateAdvancesToInvoice($invoice);

        // 5. Post-allocation reconciliation must remain in exact mathematical balance
        $reconPost = $this->advanceService->reconcileSubledgers($this->schoolTenant->id);
        $this->assertTrue($reconPost['is_reconciled']);
        $this->assertEquals(20000.00, $reconPost['wallets']['subledger_total']);
        $this->assertEquals(3000.00, $reconPost['school_advances']['subledger_total']); // 15,000 - 12,000 = 3,000 remaining
        $this->assertEquals(0.00, $reconPost['accounts_receivable']['subledger_total']); // Fully paid
    }

    public function test_immediate_rls_isolation_on_wallets_and_advances(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS test requires PostgreSQL.');
        }

        $walletA = StudentCreditWallet::create([
            'tenant_id' => $this->schoolTenant->id,
            'student_id' => $this->schoolStudent->id,
            'balance' => 500.00,
        ]);

        $walletB = StudentCreditWallet::withoutGlobalScopes()->create([
            'tenant_id' => $this->academyTenant->id,
            'student_id' => $this->academyStudent->id,
            'balance' => 1000.00,
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->schoolTenant->id);

        try {
            $visibleWallets = DB::table('student_credit_wallets')->pluck('id')->toArray();

            $this->assertContains($walletA->id, $visibleWallets);
            $this->assertNotContains($walletB->id, $visibleWallets);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
