<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\FeeStructureItem;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\StudentConcessionService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeeStructureAndConcessionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected FeeStructureService $structureService;
    protected StudentConcessionService $concessionService;
    protected GeneralLedgerService $glService;

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

        $this->structureService = new FeeStructureService();
        $this->concessionService = new StudentConcessionService();
    }

    public function test_can_create_fee_structure_with_items_linked_to_revenue_accounts(): void
    {
        $tuitionAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4010')->firstOrFail();
        $labAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4030')->firstOrFail();

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Monthly Tuition Fee',
            'category_scope' => 'school',
            'default_revenue_account_id' => $tuitionAccount->id,
        ]);

        $labCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'LAB',
            'name' => 'Computer & Science Lab Fee',
            'category_scope' => 'school',
            'default_revenue_account_id' => $labAccount->id,
        ]);

        $session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
        ]);

        $structure = $this->structureService->createStructure(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'academic_session_id' => $session->id,
                'school_class_id' => $class->id,
                'name' => 'Grade 9 Standard 2026-2027',
                'frequency' => 'monthly',
                'currency' => 'PKR',
                'is_active' => true,
            ],
            [
                ['fee_category_id' => $tuitionCat->id, 'amount' => 12000.00, 'sort_order' => 1, 'is_optional' => false],
                ['fee_category_id' => $labCat->id, 'amount' => 2500.00, 'sort_order' => 2, 'is_optional' => false],
            ]
        );

        $this->assertNotNull($structure->id);
        $this->assertEquals(2, $structure->items->count());
        $this->assertEquals(14500.00, $structure->total_amount);

        // Verify clean resolution
        $resolved = $this->structureService->resolveActiveStructureForClass(
            $this->tenant->id,
            $this->branch->id,
            $class->id,
            $session->id
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($structure->id, $resolved->id);
        $this->assertEquals('4010', $resolved->items[0]->feeCategory->defaultRevenueAccount->account_code);
    }

    public function test_can_resolve_academy_course_fee_structure(): void
    {
        $tuitionAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '4020')->firstOrFail();

        $courseCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'COURSE_FEE',
            'name' => 'Academy Course Fee',
            'category_scope' => 'academy',
            'default_revenue_account_id' => $tuitionAccount->id,
        ]);

        $program = AcademyProgram::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Entry Test Prep',
        ]);

        $course = AcademyCourse::create([
            'tenant_id' => $this->tenant->id,
            'academy_program_id' => $program->id,
            'name' => 'MDCAT Intensive Crash Course',
            'base_fee' => 45000.00,
        ]);

        $structure = $this->structureService->createStructure(
            [
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'academy_course_id' => $course->id,
                'name' => 'MDCAT 2026 Package Fee',
                'frequency' => 'one_time',
                'currency' => 'PKR',
                'is_active' => true,
            ],
            [
                ['fee_category_id' => $courseCat->id, 'amount' => 45000.00, 'sort_order' => 1, 'is_optional' => false],
            ]
        );

        $resolved = $this->structureService->resolveActiveStructureForCourse(
            $this->tenant->id,
            $this->branch->id,
            $course->id
        );

        $this->assertNotNull($resolved);
        $this->assertEquals($structure->id, $resolved->id);
        $this->assertEquals(45000.00, $resolved->total_amount);
    }

    public function test_evaluates_sibling_kinship_hierarchy_accurately(): void
    {
        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Muhammad',
            'last_name' => 'Tariq',
            'relationship' => 'father',
            'phone' => '03001234567',
        ]);

        // First Child (Admitted 2024)
        $child1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2024-001',
            'first_name' => 'Ahmed',
            'last_name' => 'Tariq',
            'date_of_birth' => '2012-05-15',
            'admission_date' => '2024-08-01',
            'status' => 'active',
        ]);

        StudentGuardian::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $child1->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
            'is_primary' => true,
            'is_financial_sponsor' => true,
        ]);

        // Second Child (Admitted 2025)
        $child2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2025-002',
            'first_name' => 'Zainab',
            'last_name' => 'Tariq',
            'date_of_birth' => '2014-09-20',
            'admission_date' => '2025-08-01',
            'status' => 'active',
        ]);

        StudentGuardian::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $child2->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
            'is_primary' => true,
            'is_financial_sponsor' => true,
        ]);

        // Third Child (Admitted 2026)
        $child3 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-003',
            'first_name' => 'Bilal',
            'last_name' => 'Tariq',
            'date_of_birth' => '2017-02-10',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        StudentGuardian::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $child3->id,
            'guardian_id' => $guardian->id,
            'relationship_type' => 'father',
            'is_primary' => true,
            'is_financial_sponsor' => true,
        ]);

        $this->assertEquals(1, $this->concessionService->evaluateSiblingKinship($child1));
        $this->assertEquals(2, $this->concessionService->evaluateSiblingKinship($child2));
        $this->assertEquals(3, $this->concessionService->evaluateSiblingKinship($child3));
    }

    public function test_concession_stacking_highest_wins(): void
    {
        $contraAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5010')->firstOrFail();

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
        ]);

        // Discount A: 10%
        $disc10 = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Early Bird 10%',
            'code' => 'EB_10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'contra_account_id' => $contraAccount->id,
            'stacking_rule' => 'highest_wins',
            'is_active' => true,
        ]);

        // Discount B: 25% Sibling
        $disc25 = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sibling 25%',
            'code' => 'SIB_25',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'contra_account_id' => $contraAccount->id,
            'stacking_rule' => 'highest_wins',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-TEST-01',
            'first_name' => 'Hamza',
            'last_name' => 'Ali',
            'date_of_birth' => '2013-01-01',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->concessionService->assignConcession($student, $disc10, ['valid_from' => '2026-01-01']);
        $this->concessionService->assignConcession($student, $disc25, ['valid_from' => '2026-01-01']);

        $studentDiscounts = StudentFeeDiscount::where('student_id', $student->id)->with('feeDiscount')->get();

        $result = $this->concessionService->calculateItemConcession(10000.00, $tuitionCat, $studentDiscounts, '2026-09-01');

        // Highest wins: 25% of 10,000 = 2,500
        $this->assertEquals(2500.00, $result['discount_amount']);
        $this->assertEquals(7500.00, $result['net_amount']);
        $this->assertCount(1, $result['applied_discounts']);
        $this->assertEquals('SIB_25', $result['applied_discounts'][0]['code']);
    }

    public function test_concession_stacking_compound(): void
    {
        $contraAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5010')->firstOrFail();

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
        ]);

        // First discount: 20%
        $disc20 = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff Child 20%',
            'code' => 'STAFF_20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'contra_account_id' => $contraAccount->id,
            'stacking_rule' => 'compound',
            'is_active' => true,
        ]);

        // Second discount: 10%
        $disc10 = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Merit 10%',
            'code' => 'MERIT_10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'contra_account_id' => $contraAccount->id,
            'stacking_rule' => 'compound',
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-TEST-02',
            'first_name' => 'Fatima',
            'last_name' => 'Noor',
            'date_of_birth' => '2013-02-02',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->concessionService->assignConcession($student, $disc20, ['valid_from' => '2026-01-01']);
        $this->concessionService->assignConcession($student, $disc10, ['valid_from' => '2026-01-01']);

        $studentDiscounts = StudentFeeDiscount::where('student_id', $student->id)->with('feeDiscount')->get();

        $result = $this->concessionService->calculateItemConcession(10000.00, $tuitionCat, $studentDiscounts, '2026-09-01');

        // Gross 10,000 -> 20% = 2,000 (net 8,000) -> 10% on 8,000 = 800 -> Total discount = 2,800 -> Net = 7,200
        $this->assertEquals(2800.00, $result['discount_amount']);
        $this->assertEquals(7200.00, $result['net_amount']);
        $this->assertCount(2, $result['applied_discounts']);
    }

    public function test_concession_validity_dates_and_approval_workflow(): void
    {
        $contraAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5010')->firstOrFail();

        $tuitionCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION',
            'name' => 'Tuition Fee',
        ]);

        $specialAid = FeeDiscount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Special Financial Aid 50%',
            'code' => 'AID_50',
            'discount_type' => 'percentage',
            'discount_value' => 50.00,
            'contra_account_id' => $contraAccount->id,
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-TEST-03',
            'first_name' => 'Usman',
            'last_name' => 'Khan',
            'date_of_birth' => '2013-03-03',
            'admission_date' => '2026-08-01',
            'status' => 'active',
        ]);

        // Assign unapproved concession
        $sfd = $this->concessionService->assignConcession($student, $specialAid, [
            'valid_from' => '2026-09-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->assertNull($sfd->approved_at);

        $studentDiscounts = StudentFeeDiscount::where('student_id', $student->id)->with('feeDiscount')->get();

        // 1. Unapproved: No discount applied
        $resUnapproved = $this->concessionService->calculateItemConcession(10000.00, $tuitionCat, $studentDiscounts, '2026-10-01');
        $this->assertEquals(0.00, $resUnapproved['discount_amount']);
        $this->assertEquals(10000.00, $resUnapproved['net_amount']);

        // 2. Approve concession
        $user = User::factory()->create();
        $sfd->update([
            'approved_by_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $studentDiscounts = StudentFeeDiscount::where('student_id', $student->id)->with('feeDiscount')->get();

        // 3. Within date range: Discount applied
        $resApproved = $this->concessionService->calculateItemConcession(10000.00, $tuitionCat, $studentDiscounts, '2026-10-01');
        $this->assertEquals(5000.00, $resApproved['discount_amount']);
        $this->assertEquals(5000.00, $resApproved['net_amount']);

        // 4. Outside date range (e.g. 2027-01-15): Expired
        $resExpired = $this->concessionService->calculateItemConcession(10000.00, $tuitionCat, $studentDiscounts, '2027-01-15');
        $this->assertEquals(0.00, $resExpired['discount_amount']);
        $this->assertEquals(10000.00, $resExpired['net_amount']);
    }

    public function test_immediate_rls_isolation_on_fee_structure_and_discount_tables(): void
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

        // Create category and structure for Tenant A
        $catA = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TUITION_A',
            'name' => 'Tuition Tenant A',
        ]);

        $structA = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Structure A',
        ]);

        // Create category and structure for Tenant B
        $catB = FeeCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'code' => 'TUITION_B',
            'name' => 'Tuition Tenant B',
        ]);

        $structB = FeeStructure::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'name' => 'Structure B',
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visibleCategories = DB::table('fee_categories')->pluck('id')->toArray();
            $visibleStructures = DB::table('fee_structures')->pluck('id')->toArray();

            $this->assertContains($catA->id, $visibleCategories);
            $this->assertNotContains($catB->id, $visibleCategories);

            $this->assertContains($structA->id, $visibleStructures);
            $this->assertNotContains($structB->id, $visibleStructures);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}
