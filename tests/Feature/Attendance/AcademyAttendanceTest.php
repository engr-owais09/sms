<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Academic\Models\Academy\AcademyBatchSchedule;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Attendance\Services\AcademyAttendanceService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademyBatch $batch;
    protected AcademyBatchSchedule $schedule;
    protected Student $student1;
    protected Student $student2;
    protected User $instructor;
    protected AcademyAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Step Academy',
            'slug' => 'step-acad-att',
            'type' => 'academy',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Garden Town',
            'code' => 'STEP-GT',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        $this->instructor = User::factory()->create(['name' => 'Sir Usman']);

        $program = AcademyProgram::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'ECAT Prep',
            'code' => 'ECAT-26',
        ]);

        $course = AcademyCourse::create([
            'tenant_id' => $this->tenant->id,
            'academy_program_id' => $program->id,
            'name' => 'ECAT Mathematics',
            'code' => 'ECAT-MATH',
            'duration_weeks' => 10,
            'base_fee' => 20000.00,
        ]);

        $this->batch = AcademyBatch::create([
            'tenant_id' => $this->tenant->id,
            'academy_course_id' => $course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Evening Batch E1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-01',
            'max_capacity' => 60,
            'instructor_id' => $this->instructor->id,
            'status' => 'in_progress',
        ]);

        $this->schedule = AcademyBatchSchedule::create([
            'tenant_id' => $this->tenant->id,
            'academy_batch_id' => $this->batch->id,
            'day_of_week' => 2,
            'start_time' => '16:00:00',
            'end_time' => '17:30:00',
            'room_number' => 'AUD-1',
        ]);

        $this->student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ACAD-01',
            'first_name' => 'Hamza',
            'last_name' => 'Ali',
            'date_of_birth' => '2008-01-15',
            'admission_date' => '2026-09-01',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ACAD-02',
            'first_name' => 'Sara',
            'last_name' => 'Noor',
            'date_of_birth' => '2008-05-20',
            'admission_date' => '2026-09-01',
            'status' => 'active',
        ]);

        AcademyBatchEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student1->id,
            'academy_batch_id' => $this->batch->id,
            'fee_schedule_type' => 'lump_sum',
            'agreed_fee_amount' => 20000.00,
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-09-01',
        ]);

        AcademyBatchEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student2->id,
            'academy_batch_id' => $this->batch->id,
            'fee_schedule_type' => 'lump_sum',
            'agreed_fee_amount' => 20000.00,
            'enrollment_status' => 'active',
            'enrolled_at' => '2026-09-01',
        ]);

        $this->service = new AcademyAttendanceService();
    }

    public function test_can_mark_batch_lecture_attendance_and_calculate_student_rate(): void
    {
        // Lecture 1 (scheduled slot)
        $session1 = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->batch->id,
            '2026-11-10',
            $this->schedule->id,
            'Vectors & Matrices',
            $this->instructor
        );

        $roster = $this->service->getBatchRoster($session1);
        $this->assertCount(2, $roster);

        $this->service->markBulkAttendance($session1, [
            $this->student1->id => ['status' => 'present'],
            $this->student2->id => ['status' => 'absent'],
        ], true, $this->instructor);

        // Lecture 2 (ad-hoc extra revision class, null schedule_id)
        $session2 = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->batch->id,
            '2026-11-14',
            null,
            'Extra Past Paper Revision',
            $this->instructor
        );

        $this->service->markBulkAttendance($session2, [
            $this->student1->id => ['status' => 'late'],
            $this->student2->id => ['status' => 'present'],
        ], true, $this->instructor);

        // Student 1: present + late = 2 / 2 = 100%
        $rate1 = $this->service->calculateStudentAttendanceRate($this->tenant->id, $this->batch->id, $this->student1->id);
        $this->assertEquals(2, $rate1['total_sessions']);
        $this->assertEquals(2, $rate1['attended_sessions']);
        $this->assertEquals(100.00, $rate1['percentage']);

        // Student 2: absent + present = 1 / 2 = 50%
        $rate2 = $this->service->calculateStudentAttendanceRate($this->tenant->id, $this->batch->id, $this->student2->id);
        $this->assertEquals(2, $rate2['total_sessions']);
        $this->assertEquals(1, $rate2['attended_sessions']);
        $this->assertEquals(50.00, $rate2['percentage']);
    }
}
