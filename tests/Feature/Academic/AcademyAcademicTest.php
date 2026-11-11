<?php

namespace Tests\Feature\Academic;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Academic\Models\Academy\AcademyBatchSchedule;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyCourseCompletion;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Services\AcademyEnrollmentService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AcademyAcademicTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademyProgram $program;
    protected AcademyCourse $coursePhysics;
    protected AcademyCourse $courseChemistry;
    protected AcademyBatch $batchPhysics;
    protected AcademyBatch $batchChemistry;
    protected AcademyEnrollmentService $enrollmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enrollmentService = app(AcademyEnrollmentService::class);

        $this->tenant = Tenant::create([
            'name' => 'KIPS Academy Lahore',
            'slug' => 'kips-lhr',
            'type' => 'academy',
            'status' => 'active',
        ]);

        $this->branch = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Johar Town Campus',
            'code' => 'JT-01',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        // Program
        $this->program = AcademyProgram::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'MDCAT Entry Test Preparation',
            'category' => 'entry_test',
        ]);

        // Courses
        $this->coursePhysics = AcademyCourse::create([
            'tenant_id' => $this->tenant->id,
            'academy_program_id' => $this->program->id,
            'name' => 'MDCAT Physics Crash Course',
            'base_fee' => 25000.00,
            'duration_weeks' => 12,
        ]);

        $this->courseChemistry = AcademyCourse::create([
            'tenant_id' => $this->tenant->id,
            'academy_program_id' => $this->program->id,
            'name' => 'MDCAT Chemistry Crash Course',
            'base_fee' => 25000.00,
            'duration_weeks' => 12,
        ]);

        // Batches
        $this->batchPhysics = AcademyBatch::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academy_course_id' => $this->coursePhysics->id,
            'name' => 'Physics Morning Batch A',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'max_capacity' => 2, // Small for capacity testing
            'status' => 'in_progress',
        ]);

        $this->batchChemistry = AcademyBatch::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academy_course_id' => $this->courseChemistry->id,
            'name' => 'Chemistry Evening Batch B',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'max_capacity' => 50,
            'status' => 'in_progress',
        ]);
    }

    public function test_batch_schedules_and_course_structure(): void
    {
        $schedule = AcademyBatchSchedule::create([
            'tenant_id' => $this->tenant->id,
            'academy_batch_id' => $this->batchPhysics->id,
            'day_of_week' => 1, // Monday
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'room_number' => 'Hall 201',
        ]);

        $this->assertNotNull($schedule);
        $this->assertEquals($this->batchPhysics->id, $schedule->academy_batch_id);
        $this->assertCount(1, $this->batchPhysics->schedules);
        $this->assertEquals('Hall 201', $this->batchPhysics->schedules->first()->room_number);
    }

    public function test_simultaneous_multi_batch_enrollments_allowed_for_academy_students(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'KIPS-2026-0001',
            'first_name' => 'Ayesha',
            'last_name' => 'Malik',
            'gender' => 'female',
            'date_of_birth' => '2008-11-20',
            'admission_date' => '2026-05-15',
        ]);

        // Enroll simultaneously into Physics AND Chemistry batches
        $enrollment1 = $this->enrollmentService->enroll(
            student: $student,
            batch: $this->batchPhysics,
            agreedFee: 22000.00
        );

        $enrollment2 = $this->enrollmentService->enroll(
            student: $student,
            batch: $this->batchChemistry,
            agreedFee: 25000.00
        );

        $this->assertNotNull($enrollment1);
        $this->assertNotNull($enrollment2);
        $this->assertNotEquals($enrollment1->id, $enrollment2->id);

        $studentEnrollments = AcademyBatchEnrollment::where('student_id', $student->id)->get();
        $this->assertCount(2, $studentEnrollments);
        $this->assertEquals(22000.00, $enrollment1->agreed_fee_amount);
        $this->assertEquals(25000.00, $enrollment2->agreed_fee_amount);
    }

    public function test_batch_seat_capacity_limit_enforcement(): void
    {
        // Batch Physics max_seats is 2
        $this->assertEquals(2, $this->batchPhysics->availableSeats());

        $student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'KIPS-2026-0002',
            'first_name' => 'Student',
            'last_name' => 'One',
            'gender' => 'male',
            'date_of_birth' => '2008-01-01',
            'admission_date' => now()->toDateString(),
        ]);

        $student2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'KIPS-2026-0003',
            'first_name' => 'Student',
            'last_name' => 'Two',
            'gender' => 'male',
            'date_of_birth' => '2008-02-02',
            'admission_date' => now()->toDateString(),
        ]);

        $student3 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'KIPS-2026-0004',
            'first_name' => 'Student',
            'last_name' => 'Three',
            'gender' => 'male',
            'date_of_birth' => '2008-03-03',
            'admission_date' => now()->toDateString(),
        ]);

        // Enroll Student 1 -> 1 seat left
        $this->enrollmentService->enroll($student1, $this->batchPhysics);
        $this->assertEquals(1, $this->batchPhysics->availableSeats());

        // Enroll Student 2 -> 0 seats left
        $this->enrollmentService->enroll($student2, $this->batchPhysics);
        $this->assertEquals(0, $this->batchPhysics->availableSeats());

        // Attempt to enroll Student 3 -> Must throw exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has reached full capacity');

        $this->enrollmentService->enroll($student3, $this->batchPhysics);
    }

    public function test_course_completion_and_certification(): void
    {
        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'KIPS-2026-0005',
            'first_name' => 'Danish',
            'last_name' => 'Iqbal',
            'gender' => 'male',
            'date_of_birth' => '2007-05-18',
            'admission_date' => '2026-01-01',
        ]);

        $completion = AcademyCourseCompletion::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'academy_course_id' => $this->coursePhysics->id,
            'completion_date' => '2026-08-31',
            'final_grade_or_score' => 'A+',
            'certificate_number' => 'CERT-KIPS-2026-0091',
            'issued_at' => now(),
        ]);

        $this->assertNotNull($completion);
        $this->assertEquals('A+', $completion->final_grade_or_score);
        $this->assertEquals('CERT-KIPS-2026-0091', $completion->certificate_number);
        $this->assertEquals($this->coursePhysics->id, $completion->course->id);
    }
}

