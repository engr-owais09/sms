<?php

namespace Tests\Feature\Student;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Livewire\StudentAdmissionForm;
use App\Modules\Student\Livewire\StudentDirectory;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentLivewireComponentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $schoolTenant;
    protected Branch $schoolBranch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected SchoolSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolTenant = Tenant::create([
            'name' => 'Army Public School',
            'slug' => 'aps-rawalpindi',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->schoolBranch = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->schoolTenant->id,
            'name' => 'Main Campus',
            'code' => 'APS-01',
            'city' => 'Rawalpindi',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->schoolTenant);
        app(TenantContext::class)->setBranch($this->schoolBranch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->schoolTenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'name' => 'Grade 7',
            'numeric_level' => 7,
            'display_order' => 7,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->schoolTenant->id,
            'school_class_id' => $this->class->id,
            'name' => 'Section Green',
            'capacity' => 30,
        ]);
    }

    public function test_school_student_admission_form_submission(): void
    {
        Livewire::test(StudentAdmissionForm::class)
            ->assertStatus(200)
            ->set('firstName', 'Mustafa')
            ->set('lastName', 'Ali')
            ->set('gender', 'male')
            ->set('dateOfBirth', '2013-05-12')
            ->set('bForm', '35201-1122334-5')
            ->set('admissionDate', '2026-04-01')
            ->set('guardianName', 'Ali Raza')
            ->set('guardianPhone', '+923001234567')
            ->set('guardianEmail', 'ali.raza@example.test')
            ->set('guardianCnic', '35201-7788990-1')
            ->set('guardianRelation', 'father')
            ->set('sessionId', $this->session->id)
            ->set('classId', $this->class->id)
            ->set('sectionId', $this->section->id)
            ->set('rollNumber', '15')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('enrolled successfully');

        $student = Student::where('first_name', 'Mustafa')->first();
        $this->assertNotNull($student);
        $this->assertEquals('Mustafa Ali', $student->full_name);
        $this->assertEquals('35201-1122334-5', $student->b_form);
        $this->assertCount(1, $student->schoolEnrollments);
        $this->assertEquals($this->section->id, $student->schoolEnrollments->first()->school_section_id);
    }

    public function test_academy_student_admission_form_submission(): void
    {
        $academyTenant = Tenant::create([
            'name' => 'Star Entry Test Academy',
            'slug' => 'star-academy',
            'type' => 'academy',
            'status' => 'active',
        ]);

        $academyBranch = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $academyTenant->id,
            'name' => 'Star Campus',
            'code' => 'STAR-01',
            'city' => 'Faisalabad',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($academyTenant);
        app(TenantContext::class)->setBranch($academyBranch);

        $program = AcademyProgram::create([
            'tenant_id' => $academyTenant->id,
            'branch_id' => $academyBranch->id,
            'name' => 'ECAT Crash Course',
            'category' => 'entry_test',
        ]);

        $course = AcademyCourse::create([
            'tenant_id' => $academyTenant->id,
            'academy_program_id' => $program->id,
            'name' => 'Mathematics for ECAT',
            'base_fee' => 18000.00,
            'duration_weeks' => 8,
        ]);

        $batch = AcademyBatch::create([
            'tenant_id' => $academyTenant->id,
            'branch_id' => $academyBranch->id,
            'academy_course_id' => $course->id,
            'name' => 'ECAT Math Evening Batch',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
            'max_capacity' => 40,
            'status' => 'upcoming',
        ]);

        Livewire::test(StudentAdmissionForm::class)
            ->assertStatus(200)
            ->set('firstName', 'Ahmad')
            ->set('lastName', 'Nawaz')
            ->set('gender', 'male')
            ->set('dateOfBirth', '2008-09-19')
            ->set('admissionDate', '2026-05-20')
            ->set('guardianName', 'Nawaz Sharif')
            ->set('guardianPhone', '+923007654321')
            ->set('selectedBatchIds', [$batch->id])
            ->set('agreedFee', 18000.00)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('registered successfully');

        $student = Student::where('first_name', 'Ahmad')->first();
        $this->assertNotNull($student);
        $this->assertCount(1, $student->academyEnrollments);
        $this->assertEquals($batch->id, $student->academyEnrollments->first()->academy_batch_id);
    }

    public function test_student_directory_renders_and_filters_correctly(): void
    {
        Student::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'admission_number' => 'APS-2026-0001',
            'first_name' => 'Haris',
            'last_name' => 'Rauf',
            'gender' => 'male',
            'date_of_birth' => '2012-08-10',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);

        Student::create([
            'tenant_id' => $this->schoolTenant->id,
            'branch_id' => $this->schoolBranch->id,
            'admission_number' => 'APS-2026-0002',
            'first_name' => 'Babar',
            'last_name' => 'Azam',
            'gender' => 'male',
            'date_of_birth' => '2012-10-15',
            'status' => 'alumni',
            'admission_date' => now()->toDateString(),
        ]);

        Livewire::test(StudentDirectory::class)
            ->assertStatus(200)
            ->assertSee('Haris Rauf')
            ->assertSee('Babar Azam')
            // Search test
            ->set('search', 'Haris')
            ->assertSee('Haris Rauf')
            ->assertDontSee('Babar Azam')
            // Status filter test
            ->set('search', '')
            ->set('statusFilter', 'alumni')
            ->assertSee('Babar Azam')
            ->assertDontSee('Haris Rauf');
    }
}

