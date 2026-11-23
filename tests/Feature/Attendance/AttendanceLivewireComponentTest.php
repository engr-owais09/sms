<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Livewire\AttendanceAnalyticsReport;
use App\Modules\Attendance\Livewire\LeaveApprovalConsole;
use App\Modules\Attendance\Livewire\RollCallRegister;
use App\Modules\Attendance\Livewire\TimetableBuilder;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Services\LeaveManagementService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceLivewireComponentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolSection $section;
    protected SchoolPeriod $period;
    protected Subject $subject;
    protected CampusRoom $room;
    protected Student $student;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Beaconhouse UI Campus',
            'slug' => 'bhs-att-ui',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Campus',
            'code' => 'BHS-UI',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Teacher Ayesha',
            'status' => 'active',
        ]);
        $this->actingAs($this->user);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 10',
            'numeric_level' => 10,
            'display_order' => 10,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class->id,
            'name' => 'Alpha',
            'capacity' => 30,
        ]);

        $this->period = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
        ]);

        $this->subject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Computer Science',
            'code' => 'CS-10',
            'type' => 'theory',
        ]);

        $this->room = CampusRoom::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'room_code' => 'LAB-1',
            'name' => 'Computer Lab 1',
            'room_type' => 'lab',
            'capacity' => 35,
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'BHS-1001',
            'first_name' => 'Omar',
            'last_name' => 'Farooq',
            'date_of_birth' => '2011-08-14',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'enrolled_at' => '2026-04-01',
            'enrollment_status' => 'active',
        ]);
    }

    public function test_timetable_builder_assigns_slot_and_shows_conflict_error(): void
    {
        $component = Livewire::test(TimetableBuilder::class)
            ->set('dayOfWeek', 1)
            ->set('teacherId', $this->user->id)
            ->set('campusRoomId', $this->room->id)
            ->call('assignSlot')
            ->assertSee('Timetable slot assigned successfully');

        $this->assertDatabaseHas('school_timetable_entries', [
            'school_section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        // Duplicate assignment triggers conflict message
        $component->call('assignSlot')
            ->assertSee('Section already has a class assigned');
    }

    public function test_roll_call_register_loads_roster_and_submits_attendance(): void
    {
        Livewire::test(RollCallRegister::class)
            ->assertCount('roster', 1)
            ->set('roster.0.status', 'absent')
            ->call('saveAttendance', false)
            ->assertSee('Attendance submitted successfully');

        $this->assertDatabaseHas('school_attendance_records', [
            'student_id' => $this->student->id,
            'status' => 'absent',
        ]);

        // Analytics report shows Omar below 75% and exports CSV
        $analytics = Livewire::test(AttendanceAnalyticsReport::class);
        $data = $analytics->viewData('analytics');
        $this->assertEquals(1, $data['summary']['absent']);
        $this->assertCount(1, $data['chronicAbsentees']);
        $analytics->call('exportChronicAbsenteesCsv')->assertFileDownloaded();
    }

    public function test_leave_approval_console_approves_pending_leave(): void
    {
        $leave = (new LeaveManagementService())->submitLeave([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'leave_type' => 'casual',
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'reason' => 'Family event',
        ]);

        Livewire::test(LeaveApprovalConsole::class)
            ->call('approve', $leave->id)
            ->assertSee('Leave request approved');

        $leave->refresh();
        $this->assertEquals('approved', $leave->status);
    }

    public function test_timetable_builder_isolates_teachers_by_tenant_and_rejects_cross_tenant_teacher(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other School',
            'slug' => 'other-att-school',
            'type' => 'school',
            'status' => 'active',
        ]);

        $otherTeacher = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Teacher',
            'status' => 'active',
        ]);

        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'name' => 'Platform Super Admin',
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $component = Livewire::test(TimetableBuilder::class);
        $teachers = $component->viewData('teachers');

        $this->assertCount(1, $teachers);
        $this->assertTrue($teachers->contains('id', $this->user->id));
        $this->assertFalse($teachers->contains('id', $otherTeacher->id));
        $this->assertFalse($teachers->contains('id', $superAdmin->id));

        // Attempting to assign a foreign teacher ID must fail closed
        $component
            ->set('teacherId', $otherTeacher->id)
            ->call('assignSlot')
            ->assertSee('Selected teacher does not belong to this workspace.');
    }
}
