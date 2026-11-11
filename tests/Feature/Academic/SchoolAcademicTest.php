<?php

namespace Tests\Feature\Academic;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolClassSubject;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Services\SchoolPromotionService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SchoolAcademicTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session2026;
    protected AcademicSession $session2027;
    protected SchoolClass $grade9;
    protected SchoolClass $grade10;
    protected SchoolSection $section9A;
    protected SchoolSection $section9B;
    protected SchoolSection $section10A;
    protected SchoolPromotionService $promotionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionService = app(SchoolPromotionService::class);

        $this->tenant = Tenant::create([
            'name' => 'The City School',
            'slug' => 'tcs-north',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'North Campus',
            'code' => 'TCS-01',
            'city' => 'Islamabad',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        // Academic Sessions
        $this->session2026 = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->session2027 = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2027-2028',
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
            'is_current' => false,
        ]);

        // Classes
        $this->grade9 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
            'display_order' => 9,
        ]);

        $this->grade10 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 10',
            'numeric_level' => 10,
            'display_order' => 10,
        ]);

        // Sections
        $this->section9A = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->grade9->id,
            'name' => 'Section A',
            'capacity' => 30,
        ]);

        $this->section9B = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->grade9->id,
            'name' => 'Section B',
            'capacity' => 30,
        ]);

        $this->section10A = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->grade10->id,
            'name' => 'Section A',
            'capacity' => 30,
        ]);
    }

    public function test_school_hierarchy_and_subject_allocations(): void
    {
        $subjectPhysics = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Physics',
            'code' => 'PHY-01',
            'type' => 'theory',
        ]);

        $classSubject = SchoolClassSubject::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->grade9->id,
            'subject_id' => $subjectPhysics->id,
            'credit_hours' => 3,
            'is_elective' => false,
        ]);

        $this->assertNotNull($classSubject);
        $this->assertEquals($this->grade9->id, $classSubject->school_class_id);
        $this->assertEquals($subjectPhysics->id, $classSubject->subject_id);
        $this->assertEquals('Physics', $classSubject->subject->name);
        $this->assertCount(1, $this->grade9->classSubjects);
    }

    public function test_single_section_enrollment_per_session_unique_constraint(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'TCS-2026-0001',
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'gender' => 'male',
            'date_of_birth' => '2011-04-10',
            'admission_date' => '2026-04-01',
        ]);

        // 1. Initial valid enrollment
        $enrollment = SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session2026->id,
            'school_section_id' => $this->section9A->id,
            'roll_number' => '101',
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-04-01',
        ]);
        $this->assertNotNull($enrollment);

        // 2. Attempting a second enrollment for the same student in the same session must violate database unique constraint
        $this->expectException(QueryException::class);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session2026->id,
            'school_section_id' => $this->section9B->id, // Trying to enroll in Section B within same session
            'roll_number' => '102',
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-04-02',
        ]);
    }

    public function test_school_promotion_service_promotes_student_and_maintains_audit_trail(): void
    {
        $admin = User::factory()->create([
            'name' => 'Principal Khan',
            'email' => 'principal@tcs.test',
        ]);

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'TCS-2026-0002',
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'gender' => 'female',
            'date_of_birth' => '2011-07-20',
            'admission_date' => '2026-04-01',
        ]);

        // Enrolled in Grade 9 Section A for 2026
        $enrollment2026 = SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session2026->id,
            'school_section_id' => $this->section9A->id,
            'roll_number' => '12',
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-04-01',
        ]);

        // Execute Promotion to Grade 10 Section A for 2027
        $promotion = $this->promotionService->promote(
            student: $student,
            fromSession: $this->session2026,
            toSession: $this->session2027,
            fromSection: $this->section9A,
            toSection: $this->section10A,
            resultSummary: 'promoted',
            promotedBy: $admin,
            remarks: 'Promoted with 88% aggregate marks'
        );

        $this->assertNotNull($promotion);
        $this->assertEquals($student->id, $promotion->student_id);
        $this->assertEquals($this->session2026->id, $promotion->from_session_id);
        $this->assertEquals($this->session2027->id, $promotion->to_session_id);
        $this->assertEquals($this->section9A->id, $promotion->from_section_id);
        $this->assertEquals($this->section10A->id, $promotion->to_section_id);
        $this->assertEquals('promoted', $promotion->result_summary);
        $this->assertEquals($admin->id, $promotion->promoted_by_user_id);

        // Verify previous enrollment status is updated to 'promoted'
        $freshEnrollment2026 = $enrollment2026->fresh();
        $this->assertEquals('promoted', $freshEnrollment2026->enrollment_status);

        // Verify new active enrollment exists in Grade 10
        $enrollment2027 = SchoolStudentEnrollment::where('student_id', $student->id)
            ->where('academic_session_id', $this->session2027->id)
            ->first();

        $this->assertNotNull($enrollment2027);
        $this->assertEquals($this->section10A->id, $enrollment2027->school_section_id);
        $this->assertEquals('active', $enrollment2027->enrollment_status);
    }

    public function test_promotion_service_rejects_duplicate_target_enrollment(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'TCS-2026-0003',
            'first_name' => 'Kamran',
            'last_name' => 'Akmal',
            'gender' => 'male',
            'date_of_birth' => '2011-09-12',
            'admission_date' => '2026-04-01',
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session2026->id,
            'school_section_id' => $this->section9A->id,
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-04-01',
        ]);

        // Pre-create enrollment in target session
        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academic_session_id' => $this->session2027->id,
            'school_section_id' => $this->section10A->id,
            'enrollment_status' => 'active',
            'enrolled_at' => '2027-04-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already enrolled in target session');

        $this->promotionService->promote(
            student: $student,
            fromSession: $this->session2026,
            toSession: $this->session2027,
            fromSection: $this->section9A,
            toSection: $this->section10A
        );
    }
}

