<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Finance\Exceptions\DuplicateInvoiceException;
use App\Modules\Finance\Exceptions\InvoiceCancellationException;
use App\Modules\Finance\Jobs\GenerateSchoolMonthlyVouchersJob;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\InstallmentMilestone;
use App\Modules\Finance\Models\InstallmentPlan;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\InvoiceCancellationService;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\StudentConcessionService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DualInvoicingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected SchoolSection $section;
    protected Student $student;
    protected FeeCategory $tuitionCat;
    protected FeeCategory $labCat;
    protected FeeStructure $feeStructure;
    protected GeneralLedgerService $glService;
    protected InvoiceGenerationService $invoiceGenService;
    protected InvoiceCancellationService $invoiceCancelService;

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

        $this->glService = new GeneralLedgerService();
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $tuitionAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();
        $labAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4030')->firstOrFail();

        $this->tuitionCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
            'category_scope' => 'school',
            'default_revenue_account_id' => $tuitionAccount->id,
        ]);

        $this->labCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'LAB',
            'name' => 'Lab Fee',
            'category_scope' => 'school',
            'default_revenue_account_id' => $labAccount->id,
        ]);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 10',
            'numeric_level' => 10,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'name' => 'Section A',
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-1001',
            'first_name' => 'Daniyal',
            'last_name' => 'Khan',
            'date_of_birth' => '2011-04-12',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        DB::table('school_student_enrollments')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'roll_number' => '10-01',
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $structureService = new FeeStructureService();
        $this->feeStructure = $structureService->createStructure(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'academic_session_id' => $this->session->id,
                'school_class_id' => $this->class->id,
                'name' => 'Grade 10 Standard 2026-2027',
                'frequency' => 'monthly',
                'currency' => 'PKR',
                'is_active' => true,
            ],
            [
                ['fee_category_id' => $this->tuitionCat->id, 'amount' => 10000.00, 'sort_order' => 1, 'is_optional' => false],
                ['fee_category_id' => $this->labCat->id, 'amount' => 2000.00, 'sort_order' => 2, 'is_optional' => false],
            ]
        );

        $this->invoiceGenService = new InvoiceGenerationService();
        $this->invoiceCancelService = new InvoiceCancellationService();
    }

    public function test_can_generate_school_monthly_invoice_with_balanced_accrual_gl_entry(): void
    {
        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2026-10',
            '2026-10-01',
            '2026-10-15',
            $this->session->id,
            $this->class->id
        );

        $this->assertNotNull($invoice->id);
        $this->assertEquals('school_monthly', $invoice->invoice_type);
        $this->assertEquals('2026-10', $invoice->billing_period);
        $this->assertEquals(12000.00, $invoice->subtotal);
        $this->assertEquals(0.00, $invoice->discount_amount);
        $this->assertEquals(12000.00, $invoice->total_amount);
        $this->assertEquals(0.00, $invoice->paid_amount);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertNotEmpty($invoice->psid_1bill);
        $this->assertCount(2, $invoice->items);

        // Verify linked GL journal entry
        $this->assertNotNull($invoice->journal_entry_id);
        $journalEntry = JournalEntry::with('lines.account')->find($invoice->journal_entry_id);
        $this->assertNotNull($journalEntry);
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertEquals('invoice_accrual', $journalEntry->source_type);

        // Verify balanced debit/credit lines
        $lines = $journalEntry->lines;
        $totalDebit = $lines->where('entry_type', 'debit')->sum('amount');
        $totalCredit = $lines->where('entry_type', 'credit')->sum('amount');

        $this->assertEquals(12000.00, $totalDebit);
        $this->assertEquals(12000.00, $totalCredit);

        // Debit must target Account 1200 A/R
        $arLine = $lines->where('entry_type', 'debit')->first();
        $this->assertEquals('1200', $arLine->account->account_code);
    }

    public function test_monthly_invoice_generation_is_strictly_idempotent(): void
    {
        // First generation succeeds
        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2026-10',
            '2026-10-01',
            '2026-10-15',
            $this->session->id,
            $this->class->id
        );

        $this->assertNotNull($invoice);

        // Duplicate attempt throws DuplicateInvoiceException
        $this->expectException(DuplicateInvoiceException::class);
        $this->expectExceptionMessage('Active monthly fee invoice already exists');

        $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2026-10',
            '2026-10-01',
            '2026-10-15',
            $this->session->id,
            $this->class->id
        );
    }

    public function test_can_generate_school_monthly_invoice_with_concessions_accrual(): void
    {
        $contraAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5010')->firstOrFail();

        $discount = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Scholarship 25%',
            'code' => 'SCHOLAR_25',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'contra_account_id' => $contraAccount->id,
            'applicable_category_id' => $this->tuitionCat->id,
            'stacking_rule' => 'highest_wins',
            'is_active' => true,
        ]);

        $concessionService = new StudentConcessionService();
        $concessionService->assignConcession($this->student, $discount, ['valid_from' => '2026-01-01']);

        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2026-11',
            '2026-11-01',
            '2026-11-15',
            $this->session->id,
            $this->class->id
        );

        // Tuition: 10,000 (25% disc = 2,500, net = 7,500)
        // Lab: 2,000 (disc = 0, net = 2,000)
        // Subtotal = 12,000 | Discount = 2,500 | Total Amount = 9,500
        $this->assertEquals(12000.00, $invoice->subtotal);
        $this->assertEquals(2500.00, $invoice->discount_amount);
        $this->assertEquals(9500.00, $invoice->total_amount);

        // Verify GL accrual entry
        $entry = JournalEntry::with('lines.account')->find($invoice->journal_entry_id);
        $lines = $entry->lines;

        $arDebit = $lines->first(fn($l) => $l->entry_type === 'debit' && $l->account->account_code === '1200');
        $discountDebit = $lines->first(fn($l) => $l->entry_type === 'debit' && $l->account->account_code === '5010');

        $this->assertEquals(9500.00, (float) $arDebit->amount);
        $this->assertEquals(2500.00, (float) $discountDebit->amount);

        $totalCredit = $lines->where('entry_type', 'credit')->sum('amount');
        $this->assertEquals(12000.00, $totalCredit);
    }

    public function test_can_generate_academy_installment_invoice(): void
    {
        $plan = InstallmentPlan::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'total_amount' => 60000.00,
            'number_of_installments' => 2,
            'status' => 'active',
        ]);

        $m1 = InstallmentMilestone::create([
            'tenant_id' => $this->tenant->id,
            'installment_plan_id' => $plan->id,
            'milestone_name' => '1st Installment (MDCAT)',
            'sequence_number' => 1,
            'due_date' => '2026-09-15',
            'amount' => 30000.00,
            'status' => 'pending',
        ]);

        $courseRevenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4020')->firstOrFail();
        $academyCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'ACAD_FEE',
            'name' => 'Academy Fee',
            'category_scope' => 'academy',
            'default_revenue_account_id' => $courseRevenueAccount->id,
        ]);

        $invoice = $this->invoiceGenService->generateAcademyInstallmentInvoice(
            $m1,
            '2026-09-01',
            $academyCat
        );

        $this->assertNotNull($invoice->id);
        $this->assertEquals('academy_installment', $invoice->invoice_type);
        $this->assertEquals(30000.00, $invoice->total_amount);

        $m1->refresh();
        $this->assertEquals('invoiced', $m1->status);

        // Verify GL Accrual: Debit 1200 A/R (30,000), Credit 4020 Academy Revenue (30,000)
        $entry = JournalEntry::find($invoice->journal_entry_id);
        $this->assertEquals(30000.00, $entry->lines()->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(30000.00, $entry->lines()->where('entry_type', 'credit')->sum('amount'));
    }

    public function test_can_cancel_unpaid_invoice_and_reverse_accrual_gl_entry(): void
    {
        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2026-12',
            '2026-12-01',
            '2026-12-15',
            $this->session->id,
            $this->class->id
        );

        $originalGlId = $invoice->journal_entry_id;

        $cancelledInvoice = $this->invoiceCancelService->cancelInvoice($invoice, 'Administrative correction');

        $this->assertEquals('cancelled', $cancelledInvoice->status);

        // Verify original journal entry is now marked reversed
        $originalGl = JournalEntry::find($originalGlId);
        $this->assertEquals('reversed', $originalGl->status);
        $this->assertNotNull($originalGl->reversed_by_entry_id);

        // Verify reversal journal entry was posted
        $reversalGl = JournalEntry::find($originalGl->reversed_by_entry_id);
        $this->assertEquals('posted', $reversalGl->status);
        $this->assertEquals('reversal', $reversalGl->source_type);
        $this->assertEquals($originalGl->id, $reversalGl->reverses_entry_id);
    }

    public function test_cannot_cancel_invoice_with_existing_payments(): void
    {
        $invoice = $this->invoiceGenService->generateSchoolMonthlyInvoice(
            $this->student,
            '2027-01',
            '2027-01-01',
            '2027-01-15',
            $this->session->id,
            $this->class->id
        );

        // Simulate recorded payment
        $invoice->update(['paid_amount' => 5000.00]);

        $this->expectException(InvoiceCancellationException::class);
        $this->expectExceptionMessage('cannot be cancelled because it has payments');

        $this->invoiceCancelService->cancelInvoice($invoice, 'Attempted invalid cancellation');
    }

    public function test_immediate_rls_isolation_on_invoicing_tables(): void
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
            'admission_number' => 'BHS-001',
            'first_name' => 'Ali',
            'last_name' => 'Raza',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $invA = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'invoice_number' => 'INV-TEST-A',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 1000.00,
        ]);

        $invB = FeeInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'student_id' => $studentB->id,
            'invoice_number' => 'INV-TEST-B',
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'total_amount' => 2000.00,
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visibleInvoices = DB::table('fee_invoices')->pluck('id')->toArray();

            $this->assertContains($invA->id, $visibleInvoices);
            $this->assertNotContains($invB->id, $visibleInvoices);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}

