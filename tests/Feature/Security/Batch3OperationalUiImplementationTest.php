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
use App\Modules\Attendance\Livewire\TimetableBuilder;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use App\Modules\Exam\Livewire\AcademyAssessmentManager;
use App\Modules\Exam\Models\AcademyAssessment;
use App\Modules\Exam\Models\AcademyAssessmentScore;
use App\Modules\Finance\Livewire\ExpenseAndRefundConsole;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeDiscount;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Models\FeeRefund;
use App\Modules\Finance\Models\StudentFeeDiscount;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Institute\Livewire\TenantStaffManager;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Institute\Services\TenantProvisioningService;
use App\Modules\Student\Livewire\StudentDirectory;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Batch3OperationalUiImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $owner;
    protected Tenant $otherTenant;
    protected Branch $otherBranch;
    protected User $otherOwner;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleAndPermissionSeeder())->run();

        $provA = (new TenantProvisioningService())->provision([
            'name' => 'Batch 3 Hybrid Campus',
            'slug' => 'batch3-campus',
            'type' => 'hybrid',
            'owner_name' => 'Batch 3 Owner',
            'owner_email' => 'owner@batch3.test',
            'owner_password' => 'StrongPass#2026',
            'branch_name' => 'Main Campus',
            'city' => 'Lahore',
            'seed_financial_defaults' => true,
        ]);
        $this->tenant = $provA['tenant'];
        $this->branch = $provA['branch'];
        $this->owner = $provA['owner'];

        Domain::firstOrCreate(
            ['domain' => 'batch3-campus.localhost'],
            ['tenant_id' => $this->tenant->id, 'is_primary' => true, 'is_verified' => true]
        );

        $provB = (new TenantProvisioningService())->provision([
            'name' => 'Foreign Campus B',
            'slug' => 'foreign-b',
            'type' => 'hybrid',
            'owner_name' => 'Foreign Owner',
            'owner_email' => 'owner@foreign.test',
            'owner_password' => 'StrongPass#2026',
            'branch_name' => 'Foreign Main',
            'city' => 'Karachi',
        ]);
        $this->otherTenant = $provB['tenant'];
        $this->otherBranch = $provB['branch'];
        $this->otherOwner = $provB['owner'];

        $this->actingAs($this->owner);
        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);
        PostgresTenantContext::set($this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
    }

    public function test_module_1_academic_setup_manager_crud_and_tenant_isolation(): void
    {
        $foreignClass = SchoolClass::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'branch_id' => $this->otherBranch->id,
            'name' => 'Foreign Grade',
            'numeric_level' => 10,
            'display_order' => 10,
        ]);

        $comp = Livewire::test(AcademicSetupManager::class)
            // 1. Create two Academic Sessions and toggle current session invariant
            ->set('sessionName', '2025-2026')
            ->set('sessionStartDate', '2025-04-01')
            ->set('sessionEndDate', '2026-03-31')
            ->set('sessionIsCurrent', true)
            ->call('saveSession')
            ->assertSee('Academic session saved successfully.')
            ->set('sessionName', '2026-2027')
            ->set('sessionStartDate', '2026-04-01')
            ->set('sessionEndDate', '2027-03-31')
            ->set('sessionIsCurrent', false)
            ->call('saveSession')
            ->assertSee('Academic session saved successfully.');

        $s1 = AcademicSession::where('tenant_id', $this->tenant->id)->where('name', '2025-2026')->firstOrFail();
        $s2 = AcademicSession::where('tenant_id', $this->tenant->id)->where('name', '2026-2027')->firstOrFail();
        $this->assertTrue($s1->is_current);
        $this->assertFalse($s2->is_current);

        $comp->call('setCurrentSession', $s2->id)
            ->assertSee('Current active academic session updated.');
        $this->assertFalse($s1->fresh()->is_current);
        $this->assertTrue($s2->fresh()->is_current);

        // 2. Create Class & Section (and reject cross-tenant class ID)
        $comp->set('className', 'Grade 9')
            ->set('classNumericLevel', 9)
            ->set('classDisplayOrder', 9)
            ->call('saveClass')
            ->assertSee('School class saved successfully.');

        $class9 = SchoolClass::where('tenant_id', $this->tenant->id)->where('name', 'Grade 9')->firstOrFail();

        $comp->set('sectionClassId', $foreignClass->id)
            ->set('sectionName', 'Hacked Section')
            ->call('saveSection')
            ->assertSee('Selected class does not belong to this workspace.');

        $comp->set('sectionClassId', $class9->id)
            ->set('sectionName', 'Section Blue')
            ->set('sectionMaxCapacity', 35)
            ->call('saveSection')
            ->assertSee('Class section saved successfully.');

        $this->assertDatabaseHas('school_sections', [
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class9->id,
            'name' => 'Section Blue',
            'max_capacity' => 35,
        ]);

        // 3. Create Subject
        $comp->set('subjectCode', 'chem-9')
            ->set('subjectName', 'Chemistry')
            ->set('subjectCategory', 'science')
            ->call('saveSubject')
            ->assertSee('Subject saved successfully.');

        $this->assertDatabaseHas('subjects', [
            'tenant_id' => $this->tenant->id,
            'code' => 'CHEM-9',
            'name' => 'Chemistry',
        ]);

        // 4. Create Period & Campus Room
        $comp->set('periodNumber', 1)
            ->set('periodLabel', 'Period 1')
            ->set('periodStartTime', '08:00')
            ->set('periodEndTime', '08:45')
            ->call('savePeriod')
            ->assertSee('Bell schedule period saved successfully.')
            ->set('roomCode', 'lab-chem')
            ->set('roomName', 'Chemistry Lab')
            ->set('roomType', 'lab')
            ->set('roomCapacity', 30)
            ->call('saveRoom')
            ->assertSee('Campus room saved successfully.');

        $this->assertDatabaseHas('campus_rooms', [
            'tenant_id' => $this->tenant->id,
            'room_code' => 'LAB-CHEM',
        ]);

        // 5. Create Academy Program, Course & Batch
        $comp->set('programName', 'MDCAT 2026')
            ->set('programCategory', 'entry_test')
            ->call('saveAcademyProgram')
            ->assertSee('Academy program saved successfully.')
            ->set('courseName', 'MDCAT Chemistry')
            ->set('courseBaseFee', 18000)
            ->set('courseDurationWeeks', 12)
            ->call('saveAcademyCourse')
            ->assertSee('Academy course saved successfully.')
            ->set('batchName', 'MDCAT Chem Evening Batch')
            ->set('batchStartDate', '2026-06-01')
            ->set('batchEndDate', '2026-08-31')
            ->set('batchMaxCapacity', 45)
            ->set('batchStatus', 'upcoming')
            ->call('saveAcademyBatch')
            ->assertSee('Academy batch saved successfully.');

        $this->assertDatabaseHas('academy_batches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'MDCAT Chem Evening Batch',
            'max_capacity' => 45,
        ]);
    }

    public function test_module_2_tenant_staff_manager_creates_staff_assigns_roles_and_toggles_status(): void
    {
        $comp = Livewire::test(TenantStaffManager::class)
            ->set('name', 'Teacher Bilal')
            ->set('email', 'bilal.teacher@batch3.test')
            ->set('phone', '03001112233')
            ->set('password', 'TeacherPass#1')
            ->set('roleName', 'teacher')
            ->call('createStaff')
            ->assertHasNoErrors()
            ->assertSee('Staff member created and role assigned successfully.');

        $staff = User::where('email', 'bilal.teacher@batch3.test')->firstOrFail();
        $this->assertEquals($this->tenant->id, $staff->tenant_id);
        setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($staff->hasRole('teacher'));

        // Reassign role to principal
        $comp->call('assignRole', $staff->id, 'principal')
            ->assertSee('Updated role for Teacher Bilal to principal.');

        $staff->unsetRelation('roles');
        $this->assertTrue($staff->hasRole('principal'));

        // Suspend and reactivate staff
        $comp->call('toggleStatus', $staff->id)
            ->assertSee('status updated to suspended.');
        $this->assertEquals('suspended', $staff->fresh()->status);

        // Cannot deactivate own active account
        $comp->call('toggleStatus', $this->owner->id)
            ->assertSee('You cannot deactivate your own active account.');

        // Cross-tenant staff manipulation must be rejected
        $comp->call('toggleStatus', $this->otherOwner->id)
            ->assertSee('Staff member does not belong to this workspace.');
    }

    public function test_module_3_student_directory_edits_promotes_and_manages_documents(): void
    {
        $s2025 = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2025-2026',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => false,
        ]);
        $s2026 = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $c8 = SchoolClass::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Grade 8', 'numeric_level' => 8, 'display_order' => 8]);
        $sec8A = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $c8->id, 'name' => '8-A', 'max_capacity' => 30]);

        $c9 = SchoolClass::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Grade 9', 'numeric_level' => 9, 'display_order' => 9]);
        $sec9A = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $c9->id, 'name' => '9-A', 'max_capacity' => 30]);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'B3-STU-01',
            'first_name' => 'Zain',
            'last_name' => 'Malik',
            'gender' => 'male',
            'date_of_birth' => '2012-05-05',
            'admission_date' => '2025-04-01',
            'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Rashid',
            'last_name' => 'Malik',
            'phone' => '03001112222',
        ]);
        $student->guardians()->attach($guardian->id, [
            'tenant_id' => $this->tenant->id,
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $s2025->id,
            'school_section_id' => $sec8A->id,
            'enrollment_status' => 'active',
            'enrolled_at' => '2025-04-01',
        ]);

        // 1. Edit student profile & guardian
        $dir = Livewire::test(StudentDirectory::class)
            ->call('openEditModal', $student->id)
            ->assertSet('editingStudentId', $student->id)
            ->set('editFirstName', 'Zain-ul-Abideen')
            ->set('editBForm', '35202-1234567-9')
            ->set('editGuardianPhone', '03009998888')
            ->call('saveStudentEdit')
            ->assertSee('Student profile updated successfully.');

        $this->assertEquals('Zain-ul-Abideen', $student->fresh()->first_name);
        $this->assertEquals('35202-1234567-9', $student->fresh()->b_form);
        $this->assertEquals('03009998888', $guardian->fresh()->phone);

        // 2. Promote student from 2025-2026 (8-A) to 2026-2027 (9-A)
        $dir->call('openPromotionModal', $student->id)
            ->set('toSessionId', $s2026->id)
            ->set('toSectionId', $sec9A->id)
            ->set('promotionResult', 'promoted')
            ->set('promotionRemarks', 'Passed with distinction')
            ->call('promoteStudent')
            ->assertSee('promoted to 9-A (2026-2027).');

        $this->assertDatabaseHas('school_promotions', [
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'from_session_id' => $s2025->id,
            'to_session_id' => $s2026->id,
            'to_section_id' => $sec9A->id,
            'result_summary' => 'promoted',
        ]);

        // 3. Document management (add, verify, delete)
        $dir->call('openDocumentsModal', $student->id)
            ->set('documentType', 'b_form')
            ->set('documentFilename', 'zain-bform.pdf')
            ->call('addStudentDocument')
            ->assertSee('Student document added to vault successfully.');

        $doc = StudentDocument::where('student_id', $student->id)->firstOrFail();
        $this->assertNull($doc->verified_at);

        $dir->call('verifyStudentDocument', $doc->id)
            ->assertSee("Document 'zain-bform.pdf' marked as verified.");
        $this->assertNotNull($doc->fresh()->verified_at);
        $this->assertEquals($this->owner->id, $doc->fresh()->verified_by_user_id);

        $dir->call('deleteStudentDocument', $doc->id)
            ->assertSee('Student document removed.');
        $this->assertEquals(0, StudentDocument::where('student_id', $student->id)->count());
    }

    public function test_module_4_teacher_substitution_workflow_integrates_with_timetable_and_attendance_and_detects_clashes(): void
    {
        $session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);
        $class = SchoolClass::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'Grade 10', 'numeric_level' => 10, 'display_order' => 10]);
        $secA = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id, 'name' => '10-A', 'max_capacity' => 30]);
        $secB = SchoolSection::create(['tenant_id' => $this->tenant->id, 'school_class_id' => $class->id, 'name' => '10-B', 'max_capacity' => 30]);

        $period1 = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'is_break' => false,
        ]);

        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'code' => 'PHYS-10', 'name' => 'Physics', 'category' => 'science']);

        $regularTeacher = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Regular Teacher', 'status' => 'active']);
        $freeSubTeacher = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Free Substitute', 'status' => 'active']);
        $busyTeacher = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Busy Teacher', 'status' => 'active']);

        // Regular slot for 10-A with Regular Teacher on Monday Period 1
        $entryA = SchoolTimetableEntry::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $secA->id,
            'school_period_id' => $period1->id,
            'subject_id' => $subject->id,
            'teacher_id' => $regularTeacher->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        // Busy Teacher is already teaching 10-B on Monday Period 1
        SchoolTimetableEntry::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $session->id,
            'school_section_id' => $secB->id,
            'school_period_id' => $period1->id,
            'subject_id' => $subject->id,
            'teacher_id' => $busyTeacher->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);

        // 1. Attempting to substitute with Busy Teacher must be blocked by clash detector
        $tt = Livewire::test(TimetableBuilder::class)
            ->set('substitutionEntryId', $entryA->id)
            ->set('substitutionDate', '2026-11-16') // Monday
            ->set('substituteTeacherId', $busyTeacher->id)
            ->call('assignSubstitute')
            ->assertSee('Teacher clash detected');

        // 2. Assigning Free Substitute succeeds and creates period attendance session with marked_by_user_id = Free Substitute
        $tt->set('substituteTeacherId', $freeSubTeacher->id)
            ->call('assignSubstitute')
            ->assertSee("Substitute teacher {$freeSubTeacher->name} assigned for 2026-11-16!");

        $this->assertDatabaseHas('school_attendance_sessions', [
            'tenant_id' => $this->tenant->id,
            'school_timetable_entry_id' => $entryA->id,
            'attendance_date' => '2026-11-16',
            'attendance_mode' => 'period',
            'marked_by_user_id' => $freeSubTeacher->id,
        ]);
    }

    public function test_module_5_finance_expenses_maker_checker_refunds_and_concessions(): void
    {
        $checkerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Finance Checker',
            'status' => 'active',
        ]);
        $ownerRole = Role::where('tenant_id', $this->tenant->id)->where('name', 'institute_owner')->firstOrFail();
        setPermissionsTeamId($this->tenant->id);
        $checkerUser->assignRole($ownerRole);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'FIN-STU-01',
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'date_of_birth' => '2012-01-01',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        // 1. Post and Void an Expense Voucher
        $console = Livewire::test(ExpenseAndRefundConsole::class)
            ->set('payeeName', 'Lahore Electric Supply (LESCO)')
            ->set('paymentMethod', 'cash')
            ->set('expenseAmount', 4500.00)
            ->set('expenseDescription', 'November electricity bill')
            ->call('createExpense')
            ->assertSee('posted successfully!');

        $voucher = ExpenseVoucher::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEquals('posted', $voucher->status);
        $this->assertNotNull($voucher->journal_entry_id);

        $console->call('voidExpense', $voucher->id)
            ->assertSee('voided and GL entry reversed.');
        $this->assertEquals('voided', $voucher->fresh()->status);

        // 2. Maker-Checker Refund Workflow
        $invoice = FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'invoice_number' => 'INV-B3-01',
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-10',
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'status' => 'unpaid',
        ]);

        $payment = (new PaymentProcessingService())->processPayment($student, 10000.00, [
            'payment_method' => 'cash',
            'received_by_user_id' => $this->owner->id,
            'target_invoice_ids' => [$invoice->id],
        ]);

        // Request refund as Maker ($this->owner)
        $console->set('selectedPaymentId', $payment->id)
            ->set('refundAmount', 2500.00)
            ->set('refundMethod', 'cash')
            ->set('refundReason', 'Transport service cancelled')
            ->call('requestRefund')
            ->assertSee('submitted for checker approval.');

        $refund = FeeRefund::where('fee_payment_id', $payment->id)->firstOrFail();
        $this->assertEquals('pending_approval', $refund->status);

        // Maker attempting to approve their own refund must be blocked by Segregation of Duties
        $console->call('approveRefund', $refund->id)
            ->assertSee('Maker-Checker segregation of duties violation');
        $this->assertEquals('pending_approval', $refund->fresh()->status);

        // Switch to Checker User to approve and disburse
        $this->actingAs($checkerUser);
        Livewire::test(ExpenseAndRefundConsole::class)
            ->call('approveRefund', $refund->id)
            ->assertSee('approved by checker.')
            ->call('disburseRefund', $refund->id)
            ->assertSee('disbursed and posted to General Ledger.');

        $this->assertEquals('disbursed', $refund->fresh()->status);
        $this->assertNotNull($refund->fresh()->journal_entry_id);

        // 3. Fee Concessions & Student Discounts
        $this->actingAs($this->owner);
        Livewire::test(ExpenseAndRefundConsole::class)
            ->set('discountCode', 'merit_25')
            ->set('discountName', 'Merit Scholarship 25%')
            ->set('discountType', 'percentage')
            ->set('discountValue', 25.00)
            ->set('stackingRule', 'additive')
            ->call('createDiscountPolicy')
            ->assertSee('Fee concession policy created successfully.')
            ->set('concessionStudentId', $student->id)
            ->set('concessionReason', 'Top rank in term exams')
            ->call('assignStudentConcession')
            ->assertSee("Concession 'Merit Scholarship 25%' granted to Sara Khan.");

        $stuDiscount = StudentFeeDiscount::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals('active', $stuDiscount->status);
    }

    public function test_module_6_academy_assessment_manager_creates_assessment_and_ranks_scores(): void
    {
        $prog = AcademyProgram::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'name' => 'ECAT', 'category' => 'entry_test']);
        $course = AcademyCourse::create(['tenant_id' => $this->tenant->id, 'academy_program_id' => $prog->id, 'name' => 'ECAT Math', 'base_fee' => 15000, 'duration_weeks' => 10]);
        $batch = AcademyBatch::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academy_course_id' => $course->id,
            'name' => 'ECAT Math Batch A',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-15',
            'max_capacity' => 40,
            'status' => 'in_progress',
        ]);

        $stu1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'AC-01',
            'first_name' => 'Ayesha',
            'last_name' => 'topper',
            'date_of_birth' => '2008-01-01',
            'admission_date' => '2026-06-01',
            'status' => 'active',
        ]);
        $stu2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'AC-02',
            'first_name' => 'Hamza',
            'last_name' => 'Runner',
            'date_of_birth' => '2008-02-02',
            'admission_date' => '2026-06-01',
            'status' => 'active',
        ]);

        AcademyBatchEnrollment::create(['tenant_id' => $this->tenant->id, 'student_id' => $stu1->id, 'academy_batch_id' => $batch->id, 'agreed_fee_amount' => 15000, 'enrollment_status' => 'active', 'enrolled_at' => '2026-06-01']);
        AcademyBatchEnrollment::create(['tenant_id' => $this->tenant->id, 'student_id' => $stu2->id, 'academy_batch_id' => $batch->id, 'agreed_fee_amount' => 15000, 'enrollment_status' => 'active', 'enrolled_at' => '2026-06-01']);

        $mgr = Livewire::test(AcademyAssessmentManager::class)
            ->set('selectedBatchId', $batch->id)
            ->set('title', 'ECAT Mock Test 1')
            ->set('assessmentType', 'mock_exam')
            ->set('assessmentDate', '2026-07-01')
            ->set('maxMarks', 100)
            ->set('passingMarks', 50)
            ->call('createAssessment')
            ->assertSee("Academy assessment 'ECAT Mock Test 1' created successfully!")
            ->assertCount('scoreRows', 2)
            ->set('scoreRows.0.marks', 75)
            ->set('scoreRows.1.marks', 92)
            ->call('saveScores', true)
            ->assertSee('Assessment scores saved, ranked, and published!');

        $assessment = AcademyAssessment::where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertEquals('published', $assessment->status);

        $topScore = AcademyAssessmentScore::where('academy_assessment_id', $assessment->id)->where('student_id', $stu2->id)->firstOrFail();
        $secondScore = AcademyAssessmentScore::where('academy_assessment_id', $assessment->id)->where('student_id', $stu1->id)->firstOrFail();

        $this->assertEquals(1, $topScore->rank);
        $this->assertEquals(92.00, (float) $topScore->percentage);
        $this->assertEquals(2, $secondScore->rank);
        $this->assertEquals(75.00, (float) $secondScore->percentage);
    }

    public function test_new_batch_3_http_routes_enforce_tenancy_and_rbac_permissions(): void
    {
        $this->withoutVite();

        $newRoutes = [
            'http://batch3-campus.localhost/academics/setup',
            'http://batch3-campus.localhost/settings/staff',
            'http://batch3-campus.localhost/finance/operations',
            'http://batch3-campus.localhost/exams/assessments',
        ];

        // 1. Authorized Tenant Owner gets 200 OK on all 4 new operational routes
        foreach ($newRoutes as $url) {
            $this->actingAs($this->owner)->get($url)->assertStatus(200);
        }

        // 2. Central host without tenant domain gets 404 Not Found
        $this->actingAs($this->owner)->get('http://localhost/academics/setup')->assertStatus(404);
        $this->actingAs($this->owner)->get('http://localhost/settings/staff')->assertStatus(404);

        // 3. Restricted receptionist role lacking finance/academics/staff permissions gets 403 Forbidden
        $receptionistRole = Role::firstOrCreate([
            'name' => 'receptionist',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $receptionistRole->syncPermissions(['students.view']);

        $receptionist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);
        setPermissionsTeamId($this->tenant->id);
        $receptionist->assignRole($receptionistRole);

        foreach ($newRoutes as $url) {
            $this->actingAs($receptionist)->get($url)->assertStatus(403);
        }
    }
}
