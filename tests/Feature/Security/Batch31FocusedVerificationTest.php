<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Livewire\AcademicSetupManager;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Exam\Livewire\AcademyAssessmentManager;
use App\Modules\Exam\Models\AcademyAssessment;
use App\Modules\Exam\Models\AcademyAssessmentScore;
use App\Modules\Finance\Livewire\ExpenseAndRefundConsole;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeeRefund;
use App\Modules\Finance\Models\JournalEntryLine;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Finance\Services\StudentConcessionService;
use App\Modules\Institute\Livewire\TenantStaffManager;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Livewire\StudentDirectory;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Batch 3.1 — focused verification of the six Batch 3 operational modules.
 */
class Batch31FocusedVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $owner;
    protected Tenant $other;
    protected Branch $otherBranch;
    protected User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();
        (new RoleAndPermissionSeeder())->run();

        $a = (new TenantProvisioningService())->provision([
            'name' => 'Verify Campus A', 'slug' => 'verify-a', 'type' => 'hybrid',
            'owner_name' => 'Owner A', 'owner_email' => 'owner@verify-a.test', 'owner_password' => 'StrongPass#2026',
            'branch_name' => 'Main A', 'city' => 'Lahore', 'seed_financial_defaults' => true,
        ]);
        $this->tenant = $a['tenant'];
        $this->branch = $a['branch'];
        $this->owner = $a['owner'];

        $b = (new TenantProvisioningService())->provision([
            'name' => 'Verify Campus B', 'slug' => 'verify-b', 'type' => 'hybrid',
            'owner_name' => 'Owner B', 'owner_email' => 'owner@verify-b.test', 'owner_password' => 'StrongPass#2026',
            'branch_name' => 'Main B', 'city' => 'Karachi', 'seed_financial_defaults' => true,
        ]);
        $this->other = $b['tenant'];
        $this->otherBranch = $b['branch'];
        $this->otherOwner = $b['owner'];

        $this->actingAs($this->owner);
        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);
        PostgresTenantContext::set($this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
    }

    // ------------------------------------------------------------------ helpers

    protected function userWithPermissions(string $roleName, array $permissions): User
    {
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $role->syncPermissions($permissions);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        setPermissionsTeamId($this->tenant->id);
        $user->assignRole($role);

        return $user;
    }

    protected function assertRaises(string $class, callable $fn, ?string $contains = null): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e, 'Unexpected exception: ' . get_class($e) . ' - ' . $e->getMessage());
            if ($contains !== null) {
                $this->assertStringContainsString($contains, $e->getMessage());
            }
            return;
        }
        $this->fail("Expected {$class} to be thrown.");
    }

    protected function makeStudent(string $adm, ?Tenant $tenant = null, ?Branch $branch = null): Student
    {
        $tenant ??= $this->tenant;
        $branch ??= $this->branch;

        return Student::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'admission_number' => $adm,
            'first_name' => 'Stu' . $adm,
            'last_name' => 'Test',
            'gender' => 'male',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);
    }

    protected function timetableFixture(bool $current = true): array
    {
        $session = AcademicSession::create([
            'tenant_id' => $this->tenant->id, 'name' => 'FX ' . uniqid(),
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => $current,
        ]);
        $class = SchoolClass::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Grade FX', 'numeric_level' => 10, 'display_order' => 10]);
        $secA = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id, 'name' => 'FX-A', 'max_capacity' => 30]);
        $secB = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id, 'name' => 'FX-B', 'max_capacity' => 30]);
        $period = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'period_number' => 1,
            'label' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'is_break' => false,
        ]);
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'code' => 'FX-' . strtoupper(substr(uniqid(), -5)), 'name' => 'Physics', 'category' => 'science']);

        return compact('session', 'class', 'secA', 'secB', 'period', 'subject');
    }

    protected function entry(array $fx, SchoolSection $section, ?User $teacher, int $day = 1, bool $active = true): SchoolTimetableEntry
    {
        return SchoolTimetableEntry::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'academic_session_id' => $fx['session']->id, 'school_section_id' => $section->id,
            'school_period_id' => $fx['period']->id, 'subject_id' => $fx['subject']->id,
            'teacher_id' => $teacher?->id, 'day_of_week' => $day, 'is_active' => $active,
        ]);
    }

    // ------------------------------------------------------- 1. ACADEMIC SETUP

    public function test_academic_setup_isolation_current_session_uniqueness_and_delete_restrictions(): void
    {
        $foreignSession = AcademicSession::withoutGlobalScopes()->create([
            'tenant_id' => $this->other->id, 'name' => 'FOREIGN-SESSION',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);
        $foreignSubject = Subject::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'code' => 'FOREIGN', 'name' => 'Foreign Subject', 'category' => 'general']);
        $fProgram = AcademyProgram::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Foreign Prog', 'category' => 'entry_test']);
        $fCourse = AcademyCourse::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'academy_program_id' => $fProgram->id, 'name' => 'Foreign Course', 'base_fee' => 100, 'duration_weeks' => 4]);

        $comp = Livewire::test(AcademicSetupManager::class);

        // Isolation of listed data
        $this->assertFalse($comp->viewData('sessions')->contains('id', $foreignSession->id));
        $this->assertFalse($comp->viewData('subjects')->contains('id', $foreignSubject->id));
        $this->assertFalse($comp->viewData('courses')->contains('id', $fCourse->id));

        // Foreign entity manipulation is rejected and the foreign row is untouched
        $comp->call('setCurrentSession', $foreignSession->id)->assertSee('No query results');
        $comp->call('deleteSession', $foreignSession->id)->assertSee('No query results');
        $comp->call('deleteSubject', $foreignSubject->id)->assertSee('No query results');
        $this->assertNotSoftDeleted('academic_sessions', ['id' => $foreignSession->id]);
        $this->assertNotSoftDeleted('subjects', ['id' => $foreignSubject->id]);

        $comp->set('batchCourseId', $fCourse->id)->set('batchName', 'Hijack Batch')
            ->set('batchStartDate', '2026-06-01')->set('batchEndDate', '2026-07-01')
            ->call('saveAcademyBatch')->assertSee('Selected course does not belong to this workspace.');
        $this->assertEquals(0, AcademyBatch::withoutGlobalScopes()->where('name', 'Hijack Batch')->count());

        // Current session uniqueness
        $comp->set('sessionName', 'S-One')->set('sessionStartDate', '2026-04-01')->set('sessionEndDate', '2027-03-31')
            ->set('sessionIsCurrent', true)->call('saveSession');
        $comp->set('sessionName', 'S-Two')->set('sessionIsCurrent', true)->call('saveSession');
        $s1 = AcademicSession::where('tenant_id', $this->tenant->id)->where('name', 'S-One')->firstOrFail();
        $s2 = AcademicSession::where('tenant_id', $this->tenant->id)->where('name', 'S-Two')->firstOrFail();
        $this->assertFalse($s1->is_current);
        $this->assertTrue($s2->is_current);
        $comp->call('setCurrentSession', $s1->id);
        $this->assertEquals(1, AcademicSession::where('tenant_id', $this->tenant->id)->where('is_current', true)->count());
        $this->assertTrue($foreignSession->fresh()->is_current, "Other tenant's current session must be untouched.");

        // Delete restrictions: current session
        $comp->call('deleteSession', $s1->id)->assertSee('Cannot delete the current academic session');
        $this->assertNotSoftDeleted('academic_sessions', ['id' => $s1->id]);

        // Delete restriction: session with timetable entries / enrollments
        $fx = $this->timetableFixture(false);
        $this->entry($fx, $fx['secA'], null);
        $comp->call('deleteSession', $fx['session']->id)->assertSee('timetable entries');
        $this->assertNotSoftDeleted('academic_sessions', ['id' => $fx['session']->id]);

        $enrSession = AcademicSession::create(['tenant_id' => $this->tenant->id, 'name' => 'Enr', 'start_date' => '2025-04-01', 'end_date' => '2026-03-31', 'is_current' => false]);
        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id, 'student_id' => $this->makeStudent('ENR-1')->id,
            'academic_session_id' => $enrSession->id, 'school_section_id' => $fx['secA']->id,
            'enrollment_status' => 'active', 'enrolled_at' => '2025-04-01',
        ]);
        $comp->call('deleteSession', $enrSession->id)->assertSee('student enrollments');

        // Delete restriction: subject used by timetable
        $comp->call('deleteSubject', $fx['subject']->id)->assertSee('timetable entries');
        $this->assertNotSoftDeleted('subjects', ['id' => $fx['subject']->id]);

        // Unreferenced entities can be removed
        $free = Subject::create(['tenant_id' => $this->tenant->id, 'code' => 'FREE', 'name' => 'Free', 'category' => 'general']);
        $comp->call('deleteSubject', $free->id)->assertSee('Subject removed.');
        $this->assertSoftDeleted('subjects', ['id' => $free->id]);
        $comp->call('deleteSession', $s2->id)->assertSee('Academic session removed.');
    }

    public function test_academic_setup_denies_users_without_permission_or_from_other_tenant(): void
    {
        $limited = $this->userWithPermissions('viewer_only', ['students.view']);
        $this->actingAs($limited);
        $c = new AcademicSetupManager();
        $c->sessionName = 'X';
        $this->assertRaises(AuthorizationException::class, fn () => $c->saveSession());
        $this->assertRaises(AuthorizationException::class, fn () => $c->deleteSubject('00000000-0000-0000-0000-000000000000'));

        $this->actingAs($this->otherOwner); // valid owner of ANOTHER tenant
        $this->assertRaises(AuthorizationException::class, fn () => (new AcademicSetupManager())->saveSession(), 'do not belong');
    }

    // ------------------------------------------------------ 2. STAFF & ROLES

    public function test_staff_manager_blocks_cross_tenant_escalation_self_change_and_enforces_quota(): void
    {
        $comp = Livewire::test(TenantStaffManager::class);

        // Cross-tenant staff access
        $comp->call('assignRole', $this->otherOwner->id, 'teacher')->assertSee('does not belong to this workspace');
        $comp->call('toggleStatus', $this->otherOwner->id)->assertSee('does not belong to this workspace');
        $this->assertEquals('active', $this->otherOwner->fresh()->status);

        // Self protection
        $comp->call('assignRole', $this->owner->id, 'teacher')->assertSee('You cannot change your own role.');
        $comp->call('toggleStatus', $this->owner->id)->assertSee('You cannot deactivate your own active account.');
        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($this->owner->fresh()->hasRole('institute_owner'));

        // Staff quota (plan is shared; RefreshDatabase rolls the change back)
        $sub = TenantSubscription::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $sub->plan->update(['max_staff' => 2]);
        $sub->update(['custom_max_staff' => null]);

        $comp->set('name', 'Teacher One')->set('email', 't1@verify-a.test')->set('password', 'TeacherPass#1')->set('roleName', 'teacher')
            ->call('createStaff')->assertSee('Staff member created');
        $comp->set('name', 'Teacher Two')->set('email', 't2@verify-a.test')->set('password', 'TeacherPass#2')->set('roleName', 'teacher')
            ->call('createStaff')->assertSee('Staff quota exceeded');
        $this->assertNull(User::where('email', 't2@verify-a.test')->first());
        $this->assertEquals(2, User::where('tenant_id', $this->tenant->id)->where('status', 'active')->count());
    }

    public function test_staff_manager_prevents_privilege_escalation_and_unauthorized_actions(): void
    {
        $target = $this->userWithPermissions('teacher', ['students.view']);

        // HR admin: may manage staff but is NOT an Institute Owner
        $hr = $this->userWithPermissions('hr_admin', ['institute.edit_settings']);
        $this->actingAs($hr);

        $c = new TenantStaffManager();
        $this->assertRaises(AuthorizationException::class, fn () => $c->assignRole($target->id, 'institute_owner'), 'Only an Institute Owner');

        $c2 = new TenantStaffManager();
        $c2->name = 'Sneaky';
        $c2->roleName = 'institute_owner';
        $this->assertRaises(AuthorizationException::class, fn () => $c2->createStaff(), 'Only an Institute Owner');

        // Cannot suspend or demote an existing owner
        $this->assertRaises(AuthorizationException::class, fn () => (new TenantStaffManager())->toggleStatus($this->owner->id), 'Only an Institute Owner');
        $this->assertRaises(AuthorizationException::class, fn () => (new TenantStaffManager())->assignRole($this->owner->id, 'teacher'), 'Only an Institute Owner');

        // Ordinary role changes remain allowed
        Livewire::test(TenantStaffManager::class)->call('assignRole', $target->id, 'cashier')->assertSee('Updated role');
        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($target->fresh()->hasRole('cashier'));
        $this->assertFalse($target->fresh()->hasRole('institute_owner'));

        // A user without institute.edit_settings is denied outright
        $this->actingAs($target);
        $this->assertRaises(AuthorizationException::class, fn () => (new TenantStaffManager())->toggleStatus($hr->id));

        // A roleless user is denied (no fail-open path)
        $roleless = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $this->actingAs($roleless);
        $this->assertRaises(AuthorizationException::class, fn () => (new TenantStaffManager())->toggleStatus($hr->id));
    }

    // ----------------------------------------------------- 3. STUDENT MGMT

    public function test_student_edit_encrypts_pii_and_enforces_authorization(): void
    {
        $student = $this->makeStudent('EDIT-1');
        $guardian = Guardian::create(['tenant_id' => $this->tenant->id, 'first_name' => 'Old', 'last_name' => 'Guardian', 'phone' => '03000000000']);
        $student->guardians()->attach($guardian->id, ['tenant_id' => $this->tenant->id, 'relationship_type' => 'father', 'is_primary' => true]);

        Livewire::test(StudentDirectory::class)
            ->call('openEditModal', $student->id)
            ->set('editFirstName', 'Updated')
            ->set('editBForm', '35202-7654321-5')
            ->call('saveStudentEdit')
            ->assertSee('Student profile updated successfully.');

        $raw = DB::table('students')->where('id', $student->id)->value('b_form_encrypted');
        $this->assertNotEmpty($raw);
        $this->assertStringNotContainsString('35202-7654321-5', (string) $raw);
        $this->assertNotNull(Student::whereBForm('3520276543215')->first(), 'Blind index lookup must work after edit.');
        $this->assertEquals('Updated', $student->fresh()->first_name);

        // Invalid status / bad input rejected
        Livewire::test(StudentDirectory::class)
            ->call('openEditModal', $student->id)
            ->set('editStatus', 'hacked')
            ->call('saveStudentEdit')
            ->assertHasErrors(['editStatus']);

        // Unauthorized (read-only) user cannot edit; foreign student cannot be opened
        $viewer = $this->userWithPermissions('read_only', ['students.view']);
        $this->actingAs($viewer);
        $this->assertRaises(AuthorizationException::class, fn () => (new StudentDirectory())->openEditModal($student->id));

        $this->actingAs($this->owner);
        $foreign = $this->makeStudent('FOREIGN-1', $this->other, $this->otherBranch);
        $this->assertRaises(ModelNotFoundException::class, fn () => (new StudentDirectory())->openEditModal($foreign->id));
        $this->assertRaises(ModelNotFoundException::class, fn () => (new StudentDirectory())->openPromotionModal($foreign->id));
    }

    public function test_student_promotion_and_academy_enrollment_ownership(): void
    {
        $fx = $this->timetableFixture(true);
        $to = AcademicSession::create(['tenant_id' => $this->tenant->id, 'name' => 'Next', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31', 'is_current' => false]);
        $student = $this->makeStudent('PROMO-1');
        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id, 'student_id' => $student->id, 'academic_session_id' => $fx['session']->id,
            'school_section_id' => $fx['secA']->id, 'enrollment_status' => 'active', 'enrolled_at' => '2026-04-01',
        ]);

        $fSession = AcademicSession::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'name' => 'FS', 'start_date' => '2027-04-01', 'end_date' => '2028-03-31', 'is_current' => false]);
        $fClass = SchoolClass::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'name' => 'FC', 'numeric_level' => 1, 'display_order' => 1]);
        $fSection = SchoolSection::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'school_class_id' => $fClass->id, 'name' => 'FS-A', 'max_capacity' => 10]);

        $dir = Livewire::test(StudentDirectory::class)->call('openPromotionModal', $student->id);

        // Foreign target section / session are rejected, no promotion written
        $dir->set('toSessionId', $to->id)->set('toSectionId', $fSection->id)->call('promoteStudent')->assertSee('No query results');
        $dir->set('toSessionId', $fSession->id)->set('toSectionId', $fx['secB']->id)->call('promoteStudent')->assertSee('No query results');
        $this->assertEquals(0, DB::table('school_promotions')->where('student_id', $student->id)->count());
        $this->assertEquals('active', SchoolStudentEnrollment::where('student_id', $student->id)->value('enrollment_status'), 'Failed promotion must not close the current enrollment.');

        // Valid promotion, then duplicate to same target session is rejected atomically
        $dir->set('toSessionId', $to->id)->set('toSectionId', $fx['secB']->id)->call('promoteStudent')->assertSee('promoted to');
        $dir->call('openPromotionModal', $student->id)->set('fromSessionId', $fx['session']->id)->set('fromSectionId', $fx['secA']->id)
            ->set('toSessionId', $to->id)->set('toSectionId', $fx['secB']->id)->call('promoteStudent');
        $this->assertEquals(1, DB::table('school_promotions')->where('student_id', $student->id)->count());

        // Academy enrollment ownership + duplicate prevention
        $prog = AcademyProgram::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'P', 'category' => 'entry_test']);
        $course = AcademyCourse::create(['tenant_id' => $this->tenant->id, 'academy_program_id' => $prog->id, 'name' => 'C', 'base_fee' => 5000, 'duration_weeks' => 4]);
        $batch = AcademyBatch::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'academy_course_id' => $course->id, 'name' => 'B1', 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'max_capacity' => 5, 'status' => 'upcoming']);
        $fProg = AcademyProgram::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'name' => 'FP', 'category' => 'entry_test']);
        $fCourse = AcademyCourse::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'academy_program_id' => $fProg->id, 'name' => 'FCo', 'base_fee' => 1, 'duration_weeks' => 1]);
        $fBatch = AcademyBatch::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'academy_course_id' => $fCourse->id, 'name' => 'FB', 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'max_capacity' => 5, 'status' => 'upcoming']);

        $stu2 = $this->makeStudent('ACAD-1');
        $d2 = Livewire::test(StudentDirectory::class);
        $d2->call('openPromotionModal', $stu2->id)->set('targetBatchId', $fBatch->id)->call('promoteStudent')->assertSee('No query results');
        $this->assertEquals(0, AcademyBatchEnrollment::withoutGlobalScopes()->where('student_id', $stu2->id)->count());
        $d2->call('openPromotionModal', $stu2->id)->set('targetBatchId', $batch->id)->call('promoteStudent')->assertSee('enrolled into batch');
        $d2->call('openPromotionModal', $stu2->id)->set('targetBatchId', $batch->id)->call('promoteStudent')->assertSee('already enrolled');
        $this->assertEquals(1, AcademyBatchEnrollment::where('student_id', $stu2->id)->count());

        // Unauthorized user cannot promote
        $this->actingAs($this->userWithPermissions('read_only2', ['students.view']));
        $this->assertRaises(AuthorizationException::class, fn () => (new StudentDirectory())->openPromotionModal($student->id));
    }

    public function test_student_documents_validate_mime_size_path_authorization_and_tenant_isolation(): void
    {
        Storage::fake('local');
        $student = $this->makeStudent('DOC-1');

        $dir = Livewire::test(StudentDirectory::class)->call('openDocumentsModal', $student->id)->set('documentType', 'b_form');

        // Valid real upload -> private, server-generated, tenant-scoped path
        $dir->set('uploadedDocument', UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\n%test\n"))
            ->call('addStudentDocument')->assertSee('added to vault successfully');
        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/students/{$student->id}/documents/", $doc->file_path);
        $this->assertStringNotContainsString('..', $doc->file_path);
        Storage::disk('local')->assertExists($doc->file_path);

        // Executable / wrong MIME rejected
        Livewire::test(StudentDirectory::class)->call('openDocumentsModal', $student->id)->set('documentType', 'b_form')
            ->set('uploadedDocument', UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'))
            ->call('addStudentDocument')->assertHasErrors(['uploadedDocument']);
        // Oversize rejected (limit 5 MB)
        Livewire::test(StudentDirectory::class)->call('openDocumentsModal', $student->id)->set('documentType', 'b_form')
            ->set('uploadedDocument', UploadedFile::fake()->createWithContent('big.pdf', "%PDF-1.4\n" . str_repeat('a', 6 * 1024 * 1024)))
            ->call('addStudentDocument')->assertHasErrors(['uploadedDocument']);
        $this->assertEquals(1, StudentDocument::where('student_id', $student->id)->count());

        // Metadata mode: tampered MIME rejected; traversal / bad extension / client path never trusted
        $meta = Livewire::test(StudentDirectory::class)->call('openDocumentsModal', $student->id)->set('documentType', 'medical_form');
        $meta->set('documentFilename', 'note.pdf')->set('documentMimeType', 'text/x-php')->call('addStudentDocument')->assertHasErrors(['documentMimeType']);
        $meta->set('documentMimeType', 'application/pdf')->set('documentFilename', 'evil.php')->call('addStudentDocument')->assertSee('safe name');
        $meta->set('documentFilename', '../../etc/passwd.pdf')->set('documentFilePath', '/etc/passwd')->call('addStudentDocument')->assertSee('added to vault');
        $traversal = StudentDocument::where('student_id', $student->id)->where('original_filename', 'passwd.pdf')->firstOrFail();
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/", $traversal->file_path);
        $this->assertStringNotContainsString('..', $traversal->file_path);
        $this->assertStringNotContainsString('/etc/passwd', $traversal->file_path);
        $meta->set('documentFilename', 'huge.pdf')->set('documentFileSize', 99999999)->call('addStudentDocument')->assertHasErrors(['documentFileSize']);

        // Delete removes the stored file
        $path = $doc->file_path;
        Livewire::test(StudentDirectory::class)->call('deleteStudentDocument', $doc->id)->assertSee('Student document removed.');
        Storage::disk('local')->assertMissing($path);

        // Cross-tenant: cannot verify or delete another tenant's document
        $fStudent = $this->makeStudent('FDOC-1', $this->other, $this->otherBranch);
        $fDoc = StudentDocument::withoutGlobalScopes()->create([
            'tenant_id' => $this->other->id, 'student_id' => $fStudent->id, 'document_type' => 'b_form',
            'original_filename' => 'f.pdf', 'file_path' => "tenants/{$this->other->id}/f.pdf", 'mime_type' => 'application/pdf', 'file_size_bytes' => 10,
        ]);
        Storage::disk('local')->put($fDoc->file_path, 'x');
        $x = Livewire::test(StudentDirectory::class);
        $x->call('verifyStudentDocument', $fDoc->id)->assertSee('No query results');
        $x->call('deleteStudentDocument', $fDoc->id)->assertSee('No query results');
        $this->assertNull($fDoc->fresh()->verified_at);
        $this->assertNotNull(StudentDocument::withoutGlobalScopes()->find($fDoc->id));
        Storage::disk('local')->assertExists($fDoc->file_path);
        $this->assertRaises(ModelNotFoundException::class, fn () => (new StudentDirectory())->openDocumentsModal($fStudent->id));

        // Authorization: viewer cannot verify, editor cannot delete
        $other = StudentDocument::where('student_id', $student->id)->firstOrFail();
        $this->actingAs($this->userWithPermissions('doc_viewer', ['students.view']));
        $this->assertRaises(AuthorizationException::class, fn () => (new StudentDirectory())->verifyStudentDocument($other->id));
        $this->actingAs($this->userWithPermissions('doc_editor', ['students.view', 'students.edit']));
        $this->assertRaises(AuthorizationException::class, fn () => (new StudentDirectory())->deleteStudentDocument($other->id));
    }

    // ------------------------------------------------ 4. TEACHER SUBSTITUTION

    public function test_substitution_validates_teacher_slot_date_and_keeps_attendance_consistent(): void
    {
        $fx = $this->timetableFixture();
        $svc = app(SchoolAttendanceService::class);
        $regular = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $sub = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $sub2 = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
        $suspended = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'suspended']);
        $foreign = User::factory()->create(['tenant_id' => $this->other->id, 'status' => 'active']);
        $entry = $this->entry($fx, $fx['secA'], $regular, 1);
        $monday = '2026-11-16';

        // Teacher ownership / status / identity
        $this->assertRaises(InvalidArgumentException::class, fn () => $svc->assignSubstituteTeacher($entry, $monday, $suspended), 'active staff member');
        $this->assertRaises(InvalidArgumentException::class, fn () => $svc->assignSubstituteTeacher($entry, $monday, $foreign), 'active staff member');
        $this->assertRaises(InvalidArgumentException::class, fn () => $svc->assignSubstituteTeacher($entry, $monday, $regular), 'same as the regularly assigned');

        // Weekday and slot activity
        $this->assertRaises(InvalidArgumentException::class, fn () => $svc->assignSubstituteTeacher($entry, '2026-11-17', $sub), 'weekday');
        $inactive = $this->entry($fx, $fx['secB'], null, 1, false);
        $this->assertRaises(InvalidArgumentException::class, fn () => $svc->assignSubstituteTeacher($inactive, $monday, $sub), 'inactive timetable slot');
        $this->assertEquals(0, SchoolAttendanceSession::where('tenant_id', $this->tenant->id)->count());

        // Valid assignment -> consistent session
        $s = $svc->assignSubstituteTeacher($entry, $monday, $sub, $this->owner);
        $this->assertEquals($entry->id, $s->school_timetable_entry_id);
        $this->assertEquals($entry->school_section_id, $s->school_section_id);
        $this->assertEquals($entry->academic_session_id, $s->academic_session_id);
        $this->assertEquals($entry->branch_id, $s->branch_id);
        $this->assertEquals('period', $s->attendance_mode);
        $this->assertEquals($sub->id, $s->marked_by_user_id);
        $this->assertEquals('draft', $s->status);

        // Duplicate (same teacher) is idempotent; draft can be re-assigned; still exactly one session
        $this->assertEquals($s->id, $svc->assignSubstituteTeacher($entry, $monday, $sub)->id);
        $this->assertEquals($sub2->id, $svc->assignSubstituteTeacher($entry, $monday, $sub2)->marked_by_user_id);
        $this->assertEquals(1, SchoolAttendanceSession::where('school_timetable_entry_id', $entry->id)->count());

        // Same substitute cannot cover two classes in the same period/date
        $entry2 = $this->entry($fx, $fx['secB'], User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']), 1);
        $this->assertRaises(RuntimeException::class, fn () => $svc->assignSubstituteTeacher($entry2, $monday, $sub2), 'already assigned to another class');

        // Submitted attendance must never be reset or re-assigned
        SchoolAttendanceSession::where('id', $s->id)->update(['status' => 'submitted']);
        $this->assertRaises(RuntimeException::class, fn () => $svc->assignSubstituteTeacher($entry, $monday, $sub), 'already submitted');
        $fresh = SchoolAttendanceSession::find($s->id);
        $this->assertEquals('submitted', $fresh->status);
        $this->assertEquals($sub2->id, $fresh->marked_by_user_id);
    }

    public function test_substitution_ui_denies_unauthorized_and_foreign_actors(): void
    {
        $fx = $this->timetableFixture();
        $entry = $this->entry($fx, $fx['secA'], null, 1);
        $sub = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

        $this->actingAs($this->userWithPermissions('no_attendance', ['students.view']));
        $c = new \App\Modules\Attendance\Livewire\TimetableBuilder();
        $c->substitutionEntryId = $entry->id;
        $c->substituteTeacherId = $sub->id;
        $c->substitutionDate = '2026-11-16';
        $this->assertRaises(AuthorizationException::class, fn () => $c->assignSubstitute());

        $this->actingAs($this->otherOwner);
        $this->assertRaises(AuthorizationException::class, fn () => $c->assignSubstitute(), 'do not belong');
        $this->assertEquals(0, SchoolAttendanceSession::where('tenant_id', $this->tenant->id)->count());
    }

    // ------------------------------------------------------ 5. FINANCE OPS

    protected function paidPayment(Student $student, float $amount = 10000.00)
    {
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'invoice_number' => 'INV-' . strtoupper(substr(uniqid(), -6)), 'issue_date' => '2026-11-01', 'due_date' => '2026-11-10',
            'subtotal' => $amount, 'total_amount' => $amount, 'paid_amount' => 0, 'status' => 'unpaid',
        ]);

        return (new PaymentProcessingService())->processPayment($student, $amount, [
            'payment_method' => 'cash', 'received_by_user_id' => $this->owner->id, 'target_invoice_ids' => [$invoice->id],
        ]);
    }

    protected function glBalance(string $entryId): array
    {
        return [
            (float) JournalEntryLine::where('journal_entry_id', $entryId)->where('entry_type', 'debit')->sum('amount'),
            (float) JournalEntryLine::where('journal_entry_id', $entryId)->where('entry_type', 'credit')->sum('amount'),
        ];
    }

    public function test_expense_and_refund_gl_entries_are_balanced_and_maker_checker_is_enforced(): void
    {
        $student = $this->makeStudent('FIN-1');
        $payment = $this->paidPayment($student);

        // Balanced expense, and balanced reversal on void
        $console = Livewire::test(ExpenseAndRefundConsole::class)
            ->set('payeeName', 'Vendor X')->set('expenseAmount', 1234.56)->set('paymentMethod', 'cash')->call('createExpense');
        $voucher = ExpenseVoucher::where('tenant_id', $this->tenant->id)->firstOrFail();
        [$d, $c] = $this->glBalance($voucher->journal_entry_id);
        $this->assertEquals(1234.56, $d);
        $this->assertEquals($d, $c);

        $console->call('voidExpense', $voucher->id);
        $this->assertEquals('reversed', DB::table('journal_entries')->where('id', $voucher->journal_entry_id)->value('status'));
        $reversalId = DB::table('journal_entries')->where('tenant_id', $this->tenant->id)->where('id', '!=', $voucher->journal_entry_id)
            ->where('source_type', '!=', 'payment_receipt')->latest('created_at')->value('id');
        [$rd, $rc] = $this->glBalance($reversalId);
        $this->assertGreaterThan(0, $rd);
        $this->assertEquals($rd, $rc);

        // Invalid amount rejected; over-refund rejected
        $console->set('expenseAmount', 0)->call('createExpense')->assertHasErrors(['expenseAmount']);
        $console->set('selectedPaymentId', $payment->id)->set('refundAmount', 99999)->set('refundReason', 'Too much')->call('requestRefund')
            ->assertSee('exceeds available refundable');

        // Maker requests; cashier (collect_fee only) is a different user but NOT authorized to approve
        $console->set('refundAmount', 3000)->set('refundMethod', 'cash')->call('requestRefund')->assertSee('submitted for checker approval');
        $refund = FeeRefund::where('fee_payment_id', $payment->id)->firstOrFail();

        $console->call('approveRefund', $refund->id)->assertSee('segregation of duties');
        $console->call('rejectRefund', $refund->id)->assertSee('segregation of duties');
        $console->call('disburseRefund', $refund->id)->assertSee('Cannot disburse refund');
        $this->assertEquals('pending_approval', $refund->fresh()->status);

        $cashier = $this->userWithPermissions('cashier_only', ['students.view', 'finance.collect_fee', 'finance.cashier_closing']);
        $this->actingAs($cashier);
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->approveRefund($refund->id));
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->rejectRefund($refund->id));
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->voidExpense($voucher->id));
        $this->assertEquals('pending_approval', $refund->fresh()->status);

        // Users with no finance rights cannot disburse or request
        $teacher = $this->userWithPermissions('teacher_only', ['students.view', 'attendance.mark']);
        $this->actingAs($teacher);
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->disburseRefund($refund->id));
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->createExpense());

        // Authorized checker approves & disburses -> balanced GL
        $checker = $this->userWithPermissions('checker', ['students.view', 'finance.manage_structures', 'finance.collect_fee']);
        $this->actingAs($checker);
        Livewire::test(ExpenseAndRefundConsole::class)->call('approveRefund', $refund->id)->call('disburseRefund', $refund->id);
        $refund->refresh();
        $this->assertEquals('disbursed', $refund->status);
        [$gd, $gc] = $this->glBalance($refund->journal_entry_id);
        $this->assertEquals(3000.0, $gd);
        $this->assertEquals($gd, $gc);

        // Tenant isolation: foreign payment / refund ids
        $this->actingAs($this->owner);
        $fStudent = $this->makeStudent('FIN-F', $this->other, $this->otherBranch);
        $fPayment = \App\Modules\Finance\Models\FeePayment::withoutGlobalScopes()->create([
            'tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'student_id' => $fStudent->id,
            'received_by_user_id' => $this->otherOwner->id, 'receipt_number' => 'REC-F', 'payment_date' => '2026-11-01',
            'amount_tendered' => 100, 'allocated_to_invoices' => 100,
        ]);
        Livewire::test(ExpenseAndRefundConsole::class)
            ->set('selectedPaymentId', $fPayment->id)->set('refundAmount', 10)->set('refundReason', 'Steal')->call('requestRefund')
            ->assertSee('No query results');
        $this->assertEquals(0, FeeRefund::withoutGlobalScopes()->where('fee_payment_id', $fPayment->id)->count());
    }

    public function test_concession_policy_validation_calculation_and_isolation(): void
    {
        $student = $this->makeStudent('CON-1');
        $fStudent = $this->makeStudent('CON-F', $this->other, $this->otherBranch);
        $fDiscount = FeeDiscount::withoutGlobalScopes()->create([
            'tenant_id' => $this->other->id, 'name' => 'Foreign 50', 'code' => 'F50', 'discount_type' => 'percentage',
            'discount_value' => 50, 'stacking_rule' => 'sum_capped', 'is_active' => true,
        ]);

        $c = Livewire::test(ExpenseAndRefundConsole::class);

        // Validation boundaries
        $c->set('discountCode', 'BAD')->set('discountName', 'Bad')->set('discountType', 'percentage')->set('discountValue', 150)->set('stackingRule', 'sum_capped')
            ->call('createDiscountPolicy')->assertSee('cannot exceed 100%');
        $c->set('discountValue', 0)->call('createDiscountPolicy')->assertHasErrors(['discountValue']);
        $this->assertEquals(0, FeeDiscount::where('tenant_id', $this->tenant->id)->count());

        // Valid 25% policy -> assigned to student -> engine computes 25% of 10,000
        $c->set('discountCode', 'merit_25')->set('discountName', 'Merit 25')->set('discountType', 'percentage')->set('discountValue', 25)->set('stackingRule', 'sum_capped')
            ->call('createDiscountPolicy')->assertSee('created successfully');
        $policy = FeeDiscount::where('tenant_id', $this->tenant->id)->where('code', 'MERIT_25')->firstOrFail();

        $c->set('concessionStudentId', $student->id)->set('concessionDiscountId', $policy->id)->set('concessionReason', 'Merit')
            ->call('assignStudentConcession')->assertSee('granted to');

        $category = FeeCategory::where('tenant_id', $this->tenant->id)->where('code', 'TUITION')->firstOrFail();
        $sfd = StudentFeeDiscount::with('feeDiscount')->where('student_id', $student->id)->get();
        $calc = app(StudentConcessionService::class)->calculateItemConcession(10000.0, $category, $sfd);
        $this->assertEquals(2500.0, $calc['discount_amount']);
        $this->assertEquals(7500.0, $calc['net_amount']);

        // Date-bound validation and revoke stops the discount
        $c->set('concessionReason', 'Merit')->set('concessionValidUntil', '2000-01-01')->call('assignStudentConcession')->assertHasErrors(['concessionValidUntil']);
        $c->call('revokeStudentConcession', $sfd->first()->id);
        $after = StudentFeeDiscount::with('feeDiscount')->where('student_id', $student->id)->get();
        $this->assertEquals(0.0, app(StudentConcessionService::class)->calculateItemConcession(10000.0, $category, $after)['discount_amount']);

        // Isolation and inactive policy
        $c->set('concessionReason', 'Merit')->set('concessionValidUntil', null)->set('concessionStudentId', $fStudent->id)->set('concessionDiscountId', $policy->id)->call('assignStudentConcession')->assertSee('does not belong');
        $c->set('concessionReason', 'Merit')->set('concessionStudentId', $student->id)->set('concessionDiscountId', $fDiscount->id)->call('assignStudentConcession')->assertSee('does not belong');
        $policy->update(['is_active' => false]);
        $c->set('concessionReason', 'Merit')->set('concessionDiscountId', $policy->id)->call('assignStudentConcession')->assertSee('inactive');
        $this->assertEquals(0, StudentFeeDiscount::withoutGlobalScopes()->where('student_id', $fStudent->id)->count());

        // Unauthorized
        $this->actingAs($this->userWithPermissions('cashier_c', ['finance.collect_fee']));
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->createDiscountPolicy());
        $this->assertRaises(AuthorizationException::class, fn () => (new ExpenseAndRefundConsole())->assignStudentConcession());
    }

    // --------------------------------------------------- 6. ACADEMY ASSESSMENTS

    protected function assessmentFixture(): array
    {
        $prog = AcademyProgram::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'AP', 'category' => 'entry_test']);
        $course = AcademyCourse::create(['tenant_id' => $this->tenant->id, 'academy_program_id' => $prog->id, 'name' => 'AC', 'base_fee' => 1000, 'duration_weeks' => 4]);
        $batch = AcademyBatch::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'academy_course_id' => $course->id, 'name' => 'AB', 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'max_capacity' => 10, 'status' => 'in_progress']);
        $enrolled1 = $this->makeStudent('AS-1');
        $enrolled2 = $this->makeStudent('AS-2');
        $notEnrolled = $this->makeStudent('AS-3');
        $dropped = $this->makeStudent('AS-4');
        foreach ([$enrolled1, $enrolled2] as $s) {
            AcademyBatchEnrollment::create(['tenant_id' => $this->tenant->id, 'student_id' => $s->id, 'academy_batch_id' => $batch->id, 'agreed_fee_amount' => 1000, 'enrollment_status' => 'active', 'enrolled_at' => '2026-06-01']);
        }
        AcademyBatchEnrollment::create(['tenant_id' => $this->tenant->id, 'student_id' => $dropped->id, 'academy_batch_id' => $batch->id, 'agreed_fee_amount' => 1000, 'enrollment_status' => 'dropped', 'enrolled_at' => '2026-06-01']);

        return compact('batch', 'enrolled1', 'enrolled2', 'notEnrolled', 'dropped');
    }

    protected function rowIndex($component, string $studentId): int
    {
        foreach ($component->get('scoreRows') as $i => $row) {
            if ($row['student_id'] === $studentId) {
                return $i;
            }
        }
        $this->fail('Student row not found.');
    }

    public function test_assessment_scoring_eligibility_bounds_absent_handling_and_ranking(): void
    {
        $fx = $this->assessmentFixture();
        $fStudent = $this->makeStudent('AS-F', $this->other, $this->otherBranch);

        $m = Livewire::test(AcademyAssessmentManager::class)
            ->set('selectedBatchId', $fx['batch']->id)->set('title', 'Mock 1')->set('assessmentType', 'mock_exam')
            ->set('maxMarks', 100)->set('passingMarks', 40)->call('createAssessment');

        // Only actively enrolled students are listed (dropped / never-enrolled excluded)
        $ids = collect($m->get('scoreRows'))->pluck('student_id')->all();
        $this->assertEqualsCanonicalizing([$fx['enrolled1']->id, $fx['enrolled2']->id], $ids);

        // Tampered rows: foreign / not-enrolled / dropped students cannot be scored
        foreach ([$fStudent->id, $fx['notEnrolled']->id, $fx['dropped']->id] as $badId) {
            $m->set('scoreRows.0.student_id', $badId)->call('saveScores', true)->assertSee('actively enrolled');
        }
        $this->assertEquals(0, AcademyAssessmentScore::withoutGlobalScopes()->count());
        $m->call('loadScoreRows');

        // Boundaries: >max, negative, non-numeric
        $i1 = $this->rowIndex($m, $fx['enrolled1']->id);
        $m->set("scoreRows.$i1.marks", 150)->call('saveScores', false)->assertSee('must be between 0 and 100');
        $m->set("scoreRows.$i1.marks", -5)->call('saveScores', false)->assertSee('must be between 0 and 100');
        $m->set("scoreRows.$i1.marks", 'abc')->call('saveScores', false)->assertSee('valid number');
        $this->assertEquals(0, AcademyAssessmentScore::withoutGlobalScopes()->count());

        // Boundary values 0 and max are accepted; absent ignores supplied marks and ranks last
        $i2 = $this->rowIndex($m, $fx['enrolled2']->id);
        $m->set("scoreRows.$i1.marks", 100)->set("scoreRows.$i2.marks", 99)->set("scoreRows.$i2.is_absent", true)->call('saveScores', false);
        $assessment = AcademyAssessment::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEquals('draft', $assessment->status, 'Draft save must not publish.');

        $top = AcademyAssessmentScore::where('student_id', $fx['enrolled1']->id)->firstOrFail();
        $abs = AcademyAssessmentScore::where('student_id', $fx['enrolled2']->id)->firstOrFail();
        $this->assertEquals(100.0, (float) $top->marks_obtained);
        $this->assertEquals(1, $top->rank);
        $this->assertTrue($abs->is_absent);
        $this->assertEquals(0.0, (float) $abs->marks_obtained);
        $this->assertEquals(0.0, (float) $abs->percentage);
        $this->assertEquals(2, $abs->rank);

        // Tied scores share rank; publish by manager
        $m->set("scoreRows.$i2.is_absent", false)->set("scoreRows.$i2.marks", 100)->call('saveScores', true);
        $this->assertEquals('published', $assessment->fresh()->status);
        $this->assertEquals(1, AcademyAssessmentScore::where('student_id', $fx['enrolled2']->id)->value('rank'));
        $this->assertEquals(1, AcademyAssessmentScore::where('student_id', $fx['enrolled1']->id)->value('rank'));
        $this->assertEquals(2, AcademyAssessmentScore::where('academy_assessment_id', $assessment->id)->count());
    }

    public function test_assessment_creation_scoring_and_publication_authorization_and_isolation(): void
    {
        $fx = $this->assessmentFixture();

        // Creation requires exams.manage
        $marker = $this->userWithPermissions('marker', ['students.view', 'exams.enter_marks']);
        $this->actingAs($marker);
        $c = new AcademyAssessmentManager();
        $this->assertRaises(AuthorizationException::class, fn () => $c->createAssessment());

        // Mark-entry staff may save drafts (reaches business logic) but can NOT publish
        $this->assertRaises(AuthorizationException::class, fn () => (new AcademyAssessmentManager())->saveScores(true));
        $draft = new AcademyAssessmentManager();
        $draft->saveScores(false);
        $this->assertStringContainsString('Select an assessment', (string) $draft->errorMessage);

        // Users without any exam permission cannot score at all
        $this->actingAs($this->userWithPermissions('no_exams', ['students.view']));
        $this->assertRaises(AuthorizationException::class, fn () => (new AcademyAssessmentManager())->saveScores(false));

        // Foreign tenant's owner is rejected even though they hold exams.manage in their own tenant
        $this->actingAs($this->otherOwner);
        $this->assertRaises(AuthorizationException::class, fn () => (new AcademyAssessmentManager())->saveScores(true), 'do not belong');

        // Cross-tenant assessment id cannot be loaded or scored
        $this->actingAs($this->owner);
        $fProg = AcademyProgram::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'name' => 'FP', 'category' => 'entry_test']);
        $fCourse = AcademyCourse::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'academy_program_id' => $fProg->id, 'name' => 'FC', 'base_fee' => 1, 'duration_weeks' => 1]);
        $fBatch = AcademyBatch::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'academy_course_id' => $fCourse->id, 'name' => 'FB', 'start_date' => '2026-06-01', 'end_date' => '2026-08-01', 'max_capacity' => 5, 'status' => 'upcoming']);
        $fAssessment = AcademyAssessment::withoutGlobalScopes()->create([
            'tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'academy_batch_id' => $fBatch->id,
            'title' => 'Foreign', 'assessment_type' => 'mock_exam', 'assessment_date' => '2026-07-01', 'max_marks' => 100, 'passing_marks' => 40, 'status' => 'draft',
        ]);
        $m = Livewire::test(AcademyAssessmentManager::class)->set('selectedAssessmentId', $fAssessment->id);
        $this->assertCount(0, $m->get('scoreRows'));
        $m->call('saveScores', true)->assertSee('Select an assessment');

        // Cannot create an assessment against a foreign batch
        Livewire::test(AcademyAssessmentManager::class)->set('selectedBatchId', $fBatch->id)->set('title', 'Hijack')->set('maxMarks', 100)->set('passingMarks', 40)
            ->call('createAssessment')->assertSee('does not belong to this workspace');
        $this->assertEquals(0, AcademyAssessment::withoutGlobalScopes()->where('title', 'Hijack')->count());
        $this->assertEquals(0, AcademyAssessmentScore::withoutGlobalScopes()->count());
    }
}
