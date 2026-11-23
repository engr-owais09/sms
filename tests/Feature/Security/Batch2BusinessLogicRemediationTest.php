<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Attendance\Livewire\LeaveApprovalConsole;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use App\Modules\Attendance\Services\LeaveManagementService;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Onboarding\Livewire\OnboardingWizard;
use App\Modules\Student\Livewire\StudentAdmissionForm;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Services\StudentAdmissionService;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class Batch2BusinessLogicRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_def_f01_onboarding_wizard_requires_owner_password_and_rejects_short_or_empty_passwords(): void
    {
        // Step 2 without password must fail validation
        Livewire::test(OnboardingWizard::class)
            ->set('currentStep', 2)
            ->set('name', 'Crescent Model School')
            ->set('slug', 'crescent-model')
            ->set('email', 'owner@crescent.test')
            ->set('phone', '03001234567')
            ->set('city', 'Lahore')
            ->set('ownerPassword', '')
            ->call('nextStep')
            ->assertHasErrors(['ownerPassword' => 'required'])
            ->assertSet('currentStep', 2)
            ->set('ownerPassword', 'short')
            ->call('nextStep')
            ->assertHasErrors(['ownerPassword' => 'min'])
            ->assertSet('currentStep', 2);

        // TenantProvisioningService also enforces non-empty >= 8 char password server-side
        $this->expectException(InvalidArgumentException::class);
        (new TenantProvisioningService())->provision([
            'name' => 'No Pass School',
            'slug' => 'no-pass',
            'type' => 'school',
            'owner_name' => 'Admin',
            'owner_email' => 'admin@nopass.test',
            'owner_password' => '123',
        ]);
    }

    public function test_def_f02_and_def_f03_hybrid_onboarding_seeds_both_school_and_academy_relational_records_idempotently(): void
    {
        $service = new TenantProvisioningService();

        $payload = [
            'name' => 'Scholars Hybrid Institute',
            'slug' => 'scholars-hybrid',
            'type' => 'both', // Tests 'both' -> 'hybrid' normalization
            'default_locale' => 'en',
            'owner_name' => 'Dr. Salman',
            'owner_email' => 'salman@scholars.test',
            'owner_password' => 'StrongPass#2026',
            'owner_phone' => '03211234567',
            'branch_name' => 'Model Town Campus',
            'city' => 'Lahore',
            'session_name' => 'Session 2026-2027',
            'session_start' => '2026-08-01',
            'session_end' => '2027-05-31',
            'selected_templates' => [
                'Matriculation (Grades 9 & 10)',
                'MDCAT Comprehensive Test Prep',
            ],
            'principal_name' => 'Principal Zara',
            'principal_email' => 'zara@scholars.test',
            'cashier_name' => 'Cashier Bilal',
            'cashier_email' => 'bilal@scholars.test',
            'seed_financial_defaults' => true,
            'onboarding_completed' => true,
        ];

        $firstRun = $service->provision($payload);
        $tenant = $firstRun['tenant'];

        $this->assertEquals('hybrid', $tenant->type);
        $this->assertTrue($tenant->isSchool());
        $this->assertTrue($tenant->isAcademy());
        $this->assertTrue($tenant->isHybrid());

        $classCountAfterFirst = SchoolClass::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $batchCountAfterFirst = AcademyBatch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $coaCountAfterFirst = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $userCountAfterFirst = User::where('tenant_id', $tenant->id)->count();

        $this->assertEquals(2, $classCountAfterFirst); // Grade 9 & Grade 10
        $this->assertEquals(1, $batchCountAfterFirst); // MDCAT batch
        $this->assertGreaterThanOrEqual(20, $coaCountAfterFirst);
        $this->assertEquals(3, $userCountAfterFirst); // Owner + Principal + Cashier

        // Verify invited staff do not have hardcoded passwords
        $principal = User::where('email', 'zara@scholars.test')->firstOrFail();
        $this->assertFalse(Hash::check('Owner123456!', $principal->password));

        // Second idempotent retry with identical payload must NOT duplicate records
        $service->provision($payload);

        $this->assertEquals($classCountAfterFirst, SchoolClass::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertEquals($batchCountAfterFirst, AcademyBatch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertEquals($coaCountAfterFirst, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertEquals($userCountAfterFirst, User::where('tenant_id', $tenant->id)->count());
        $this->assertEquals(1, AcademicSession::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_def_f03_onboarding_transaction_rolls_back_completely_on_failure(): void
    {
        $service = new TenantProvisioningService();

        // Pre-create a conflicting user email for another tenant so Step 8 staff creation fails mid-transaction
        // when trying to assign a duplicate/invalid state or simulate a DB failure inside the transaction
        User::saving(function (User $user) {
            if ($user->email === 'rollback-trigger@scholars.test') {
                throw new \RuntimeException('Simulated failure during Step 8 staff provisioning.');
            }
        });

        try {
            $service->provision([
                'name' => 'Rollback Test School',
                'slug' => 'rollback-test-school',
                'type' => 'school',
                'owner_name' => 'Rollback Owner',
                'owner_email' => 'owner@rollback.test',
                'owner_password' => 'StrongPass#2026',
                'branch_name' => 'Rollback Campus',
                'city' => 'Lahore',
                'session_name' => 'Session 2026-2027',
                'selected_templates' => ['Matriculation (Grades 9 & 10)'],
                'principal_name' => 'Trigger Principal',
                'principal_email' => 'rollback-trigger@scholars.test',
            ]);
            $this->fail('Expected RuntimeException during onboarding rollback test.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated failure during Step 8', $e->getMessage());
        } finally {
            User::flushEventListeners();
            User::boot();
        }

        // Verify zero partial records exist after rollback
        $this->assertNull(Tenant::where('slug', 'rollback-test-school')->first());
        $this->assertNull(User::where('email', 'owner@rollback.test')->first());
        $this->assertNull(User::where('email', 'rollback-trigger@scholars.test')->first());
    }

    public function test_def_f05_academy_admission_persists_and_deduplicates_guardian_by_cnic(): void
    {
        $tenant = Tenant::create([
            'name' => 'Apex Coaching Academy',
            'slug' => 'apex-academy',
            'type' => 'academy',
            'status' => 'active',
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Campus',
            'code' => 'APEX-1',
            'city' => 'Islamabad',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($tenant);
        app(TenantContext::class)->setBranch($branch);
        PostgresTenantContext::set($tenant->id);

        $prog = AcademyProgram::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'ECAT', 'category' => 'entry_test']);
        $course = AcademyCourse::create(['tenant_id' => $tenant->id, 'academy_program_id' => $prog->id, 'name' => 'Physics', 'base_fee' => 12000, 'duration_weeks' => 12]);
        $batch = AcademyBatch::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'academy_course_id' => $course->id,
            'name' => 'Physics Morning Batch',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'max_capacity' => 30,
            'status' => 'upcoming',
        ]);

        $admissionService = new StudentAdmissionService();

        $res1 = $admissionService->admitToAcademy([
            'first_name' => 'Hina',
            'last_name' => 'Malik',
            'gender' => 'female',
            'date_of_birth' => '2009-04-10',
            'guardian_name' => 'Malik Riaz',
            'guardian_phone' => '+923001112233',
            'guardian_email' => 'malik@example.test',
            'guardian_cnic' => '35202-8888888-1',
            'guardian_relation' => 'father',
            'batch_ids' => [$batch->id],
        ]);

        $res2 = $admissionService->admitToAcademy([
            'first_name' => 'Saad',
            'last_name' => 'Malik',
            'gender' => 'male',
            'date_of_birth' => '2010-07-15',
            'guardian_name' => 'Malik Riaz',
            'guardian_phone' => '+923001112233',
            'guardian_cnic' => '3520288888881', // Unformatted variant of same CNIC
            'guardian_relation' => 'father',
            'batch_ids' => [$batch->id],
        ]);

        $this->assertNotNull($res1['guardian']);
        $this->assertNotNull($res2['guardian']);
        $this->assertEquals($res1['guardian']->id, $res2['guardian']->id, 'Siblings in Academy track must share deduplicated Guardian record.');
        $this->assertEquals(1, Guardian::where('tenant_id', $tenant->id)->count());
    }

    public function test_def_f07_quota_enforcement_blocks_student_admission_and_branch_creation_when_limit_reached(): void
    {
        (new SubscriptionPlanService())->seedDefaultPlans();
        $starter = SubscriptionPlan::where('code', 'starter')->firstOrFail();

        $tenant = Tenant::create([
            'name' => 'Capped School',
            'slug' => 'capped-school',
            'type' => 'school',
            'status' => 'active',
        ]);

        // Starter plan has max_branches = 1; override custom_max_students = 1
        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $starter->id,
            'status' => 'active',
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
            'custom_max_students' => 1,
        ]);

        app(TenantContext::class)->setTenant($tenant);
        PostgresTenantContext::set($tenant->id);

        // 1. First branch succeeds (0 < 1)
        $branch1 = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Campus',
            'code' => 'CAP-01',
            'city' => 'Lahore',
            'is_main' => true,
        ]);
        app(TenantContext::class)->setBranch($branch1);

        // 2. Second branch creation is blocked server-side by Branch::creating hook & TenantProvisioningService::createBranch
        $branchBlocked = false;
        try {
            Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Second Campus',
                'code' => 'CAP-02',
                'city' => 'Lahore',
            ]);
        } catch (QuotaExceededException $e) {
            $branchBlocked = true;
            $this->assertStringContainsString('Branch quota exceeded (1/1)', $e->getMessage());
        }
        $this->assertTrue($branchBlocked);
        $this->assertEquals(1, Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // 3. Setup session, class, section and admit 1st student (0 < 1)
        $session = AcademicSession::create([
            'tenant_id' => $tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $tenant->id, 'branch_id' => $branch1->id, 'name' => 'Grade 1', 'numeric_level' => 1, 'display_order' => 1]);
        $section = SchoolSection::create(['tenant_id' => $tenant->id, 'school_class_id' => $class->id, 'name' => 'A', 'capacity' => 30]);

        $admissionService = new StudentAdmissionService();
        $admissionService->admitToSchool([
            'first_name' => 'First',
            'last_name' => 'Student',
            'gender' => 'male',
            'date_of_birth' => '2015-01-01',
            'guardian_name' => 'Parent One',
            'guardian_phone' => '03001111111',
            'academic_session_id' => $session->id,
            'school_section_id' => $section->id,
        ]);
        $this->assertEquals(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // 4. Admitting 2nd student via Livewire StudentAdmissionForm or Service is blocked by QuotaEnforcementService
        Livewire::test(StudentAdmissionForm::class)
            ->set('firstName', 'Second')
            ->set('lastName', 'Overflow')
            ->set('gender', 'male')
            ->set('dateOfBirth', '2015-02-02')
            ->set('admissionDate', '2026-04-02')
            ->set('guardianName', 'Parent Two')
            ->set('guardianPhone', '03002222222')
            ->set('guardianRelation', 'father')
            ->set('sessionId', $session->id)
            ->set('classId', $class->id)
            ->set('sectionId', $section->id)
            ->call('submit')
            ->assertHasErrors(['admission'])
            ->assertSee('Active student quota exceeded (1/1)');

        $this->assertEquals(1, Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_leave_submission_workflow_validates_prevents_overlaps_and_isolates_tenants(): void
    {
        $tenantA = Tenant::create(['name' => 'Leave School A', 'slug' => 'leave-a', 'type' => 'school', 'status' => 'active']);
        $branchA = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'Main A', 'code' => 'LA-1', 'city' => 'Lahore', 'is_main' => true]);

        $tenantB = Tenant::create(['name' => 'Leave School B', 'slug' => 'leave-b', 'type' => 'school', 'status' => 'active']);
        $branchB = Branch::create(['tenant_id' => $tenantB->id, 'name' => 'Main B', 'code' => 'LB-1', 'city' => 'Karachi', 'is_main' => true]);

        $studentB = Student::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'admission_number' => 'LB-101',
            'first_name' => 'Foreign',
            'last_name' => 'Student',
            'date_of_birth' => '2013-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        app(TenantContext::class)->setTenant($tenantA);
        app(TenantContext::class)->setBranch($branchA);
        PostgresTenantContext::set($tenantA->id);

        $teacherA = User::factory()->create(['tenant_id' => $tenantA->id, 'name' => 'Teacher A', 'status' => 'active']);
        $this->actingAs($teacherA);

        $studentA = Student::create([
            'tenant_id' => $tenantA->id,
            'branch_id' => $branchA->id,
            'admission_number' => 'LA-101',
            'first_name' => 'Ahsan',
            'last_name' => 'Raza',
            'date_of_birth' => '2013-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        // 1. Submit leave via Livewire LeaveApprovalConsole
        $console = Livewire::test(LeaveApprovalConsole::class)
            ->set('studentId', $studentA->id)
            ->set('leaveType', 'sick')
            ->set('startDate', '2026-11-20')
            ->set('endDate', '2026-11-22')
            ->set('reason', 'Viral flu and fever')
            ->call('submitLeaveRequest')
            ->assertHasNoErrors()
            ->assertSee('Leave request submitted successfully.');

        $leave = StudentLeaveRequest::where('student_id', $studentA->id)->first();
        $this->assertNotNull($leave);
        $this->assertEquals('pending', $leave->status);

        // 2. Duplicate/overlapping submission for the same student must be rejected
        $console
            ->set('studentId', $studentA->id)
            ->set('leaveType', 'casual')
            ->set('startDate', '2026-11-21')
            ->set('endDate', '2026-11-23')
            ->set('reason', 'Overlap test')
            ->call('submitLeaveRequest')
            ->assertSee('An overlapping pending or approved leave request already exists for this student.');

        $this->assertEquals(1, StudentLeaveRequest::where('student_id', $studentA->id)->count());

        // 3. Cross-tenant student ID submission must be rejected
        $console
            ->set('studentId', $studentB->id)
            ->set('leaveType', 'sick')
            ->set('startDate', '2026-12-01')
            ->set('endDate', '2026-12-02')
            ->set('reason', 'Cross tenant attempt')
            ->call('submitLeaveRequest')
            ->assertSee('Selected student does not belong to this workspace.');

        // 4. Approve the submitted leave request
        $console
            ->call('approve', $leave->id)
            ->assertSee('Leave request approved and attendance records synchronized.');

        $this->assertEquals('approved', $leave->fresh()->status);
    }
}
