<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchSchedule;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\Academy\AcademyProgram;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Exceptions\TimetableConflictException;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Services\TimetableConflictService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TimetableConflictTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolSection $sectionA;
    protected SchoolSection $sectionB;
    protected SchoolPeriod $period1;
    protected SchoolPeriod $breakPeriod;
    protected Subject $math;
    protected Subject $physics;
    protected CampusRoom $room101;
    protected CampusRoom $room102;
    protected User $teacher1;
    protected User $teacher2;
    protected TimetableConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'KIPS Hybrid Campus',
            'slug' => 'kips-tt-1',
            'type' => 'both',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Johar Town',
            'code' => 'KIPS-JT',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $class9 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
            'display_order' => 9,
        ]);

        $this->sectionA = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class9->id,
            'name' => 'Section A',
            'capacity' => 40,
        ]);

        $this->sectionB = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class9->id,
            'name' => 'Section B',
            'capacity' => 40,
        ]);

        $this->period1 = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'is_break' => false,
        ]);

        $this->breakPeriod = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 2,
            'label' => 'Break',
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'is_break' => true,
        ]);

        $this->math = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mathematics',
            'code' => 'MATH-9',
            'type' => 'theory',
        ]);

        $this->physics = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Physics',
            'code' => 'PHY-9',
            'type' => 'theory',
        ]);

        $this->room101 = CampusRoom::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'room_code' => 'R-101',
            'name' => 'Room 101',
            'capacity' => 45,
        ]);

        $this->room102 = CampusRoom::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'room_code' => 'R-102',
            'name' => 'Room 102',
            'capacity' => 45,
        ]);

        $this->teacher1 = User::factory()->create(['name' => 'Prof. Tariq']);
        $this->teacher2 = User::factory()->create(['name' => 'Prof. Bilal']);

        $this->service = new TimetableConflictService();
    }

    public function test_prevents_teacher_and_room_double_booking_in_school_timetable(): void
    {
        $this->service->assignSchoolSlot([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->sectionA->id,
            'school_period_id' => $this->period1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'campus_room_id' => $this->room101->id,
            'day_of_week' => 1,
        ]);

        // Attempt to assign same teacher1 to Section B at Period 1 on Monday
        try {
            $this->service->assignSchoolSlot([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'academic_session_id' => $this->session->id,
                'school_section_id' => $this->sectionB->id,
                'school_period_id' => $this->period1->id,
                'subject_id' => $this->physics->id,
                'teacher_id' => $this->teacher1->id,
                'campus_room_id' => $this->room102->id,
                'day_of_week' => 1,
            ]);
            $this->fail('Expected TimetableConflictException for teacher double-booking.');
        } catch (TimetableConflictException $e) {
            $this->assertStringContainsString('Teacher clash detected', $e->getMessage());
        }

        // Attempt to assign teacher2 to same room101 at Period 1 on Monday
        try {
            $this->service->assignSchoolSlot([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'academic_session_id' => $this->session->id,
                'school_section_id' => $this->sectionB->id,
                'school_period_id' => $this->period1->id,
                'subject_id' => $this->physics->id,
                'teacher_id' => $this->teacher2->id,
                'campus_room_id' => $this->room101->id,
                'day_of_week' => 1,
            ]);
            $this->fail('Expected TimetableConflictException for room double-booking.');
        } catch (TimetableConflictException $e) {
            $this->assertStringContainsString('Room clash detected', $e->getMessage());
        }
    }

    public function test_prevents_hybrid_teacher_clash_across_school_and_academy_batches(): void
    {
        $program = AcademyProgram::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'MDCAT',
            'code' => 'MDCAT-26',
        ]);

        $course = AcademyCourse::create([
            'tenant_id' => $this->tenant->id,
            'academy_program_id' => $program->id,
            'name' => 'MDCAT Physics',
            'code' => 'M-PHY',
            'duration_weeks' => 12,
            'base_fee' => 25000.00,
        ]);

        $batch = AcademyBatch::create([
            'tenant_id' => $this->tenant->id,
            'academy_course_id' => $course->id,
            'branch_id' => $this->branch->id,
            'name' => 'Morning Batch M1',
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-01',
            'max_capacity' => 50,
            'instructor_id' => $this->teacher1->id,
            'status' => 'in_progress',
        ]);

        AcademyBatchSchedule::create([
            'tenant_id' => $this->tenant->id,
            'academy_batch_id' => $batch->id,
            'day_of_week' => 1, // Monday
            'start_time' => '08:15:00', // Overlaps 08:00-08:45
            'end_time' => '09:15:00',
            'room_number' => 'HALL-1',
        ]);

        $this->expectException(TimetableConflictException::class);
        $this->expectExceptionMessage('Hybrid teacher clash detected');

        $this->service->assignSchoolSlot([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->sectionA->id,
            'school_period_id' => $this->period1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'campus_room_id' => $this->room101->id,
            'day_of_week' => 1,
        ]);
    }

    public function test_rejects_assignment_to_break_period(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('break period');

        $this->service->assignSchoolSlot([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->sectionA->id,
            'school_period_id' => $this->breakPeriod->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'campus_room_id' => $this->room101->id,
            'day_of_week' => 1,
        ]);
    }
}
