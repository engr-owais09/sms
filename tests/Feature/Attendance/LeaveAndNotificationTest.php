<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Attendance\Jobs\DispatchAbsenteeNotificationsJob;
use App\Modules\Attendance\Models\AttendanceNotification;
use App\Modules\Attendance\Services\LeaveManagementService;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolSection $section;
    protected Student $student;
    protected Guardian $guardian;
    protected User $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Roots Millennium',
            'slug' => 'roots-att-leave',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'DHA Campus',
            'code' => 'RM-DHA',
            'city' => 'Islamabad',
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

        $class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 7',
            'numeric_level' => 7,
            'display_order' => 7,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class->id,
            'name' => 'Rose',
            'capacity' => 30,
        ]);

        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'RM-01',
            'first_name' => 'Bilal',
            'last_name' => 'Ahmed',
            'date_of_birth' => '2014-02-14',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $this->guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Tariq',
            'last_name' => 'Ahmed',
            'phone' => '+923001234567',
            'email' => 'tariq@example.com',
        ]);

        $this->student->guardians()->attach($this->guardian->id, [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'enrolled_at' => '2026-04-01',
            'enrollment_status' => 'active',
        ]);

        $this->principal = User::factory()->create(['name' => 'Principal Khan']);
    }

    public function test_leave_approval_retroactively_excuses_attendance_and_prefills_future_roster(): void
    {
        $attService = new SchoolAttendanceService();
        $leaveService = new LeaveManagementService();

        // Mark student absent on 2026-11-17
        $attSession = $attService->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-17'
        );
        $attService->markBulkAttendance($attSession, [
            $this->student->id => ['status' => 'absent'],
        ]);

        // Submit & approve sick leave covering 2026-11-17 to 2026-11-18
        $leave = $leaveService->submitLeave([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'leave_type' => 'sick',
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-18',
            'reason' => 'High fever',
        ]);

        $leaveService->approveLeave($leave, $this->principal, 'Medical certificate attached');

        // 1. Existing record on 2026-11-17 should now be 'excused'
        $this->assertDatabaseHas('school_attendance_records', [
            'school_attendance_session_id' => $attSession->id,
            'student_id' => $this->student->id,
            'status' => 'excused',
        ]);

        // 2. Next day's roster (2026-11-18) should pre-fill 'excused'
        $nextDay = $attService->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-18'
        );
        $roster = $attService->getSectionRoster($nextDay);
        $this->assertEquals('excused', $roster->first()['status']);
    }

    public function test_absentee_notifications_are_idempotent_across_repeated_dispatches(): void
    {
        $attService = new SchoolAttendanceService();
        $attSession = $attService->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-17'
        );
        $attService->markBulkAttendance($attSession, [
            $this->student->id => ['status' => 'absent'],
        ]);

        // Dispatch twice synchronously
        DispatchAbsenteeNotificationsJob::dispatchSync($this->tenant->id, 'school_attendance', $attSession->id, 'sms');
        DispatchAbsenteeNotificationsJob::dispatchSync($this->tenant->id, 'school_attendance', $attSession->id, 'sms');

        // Must have exactly 1 notification record
        $this->assertEquals(1, AttendanceNotification::where('source_session_id', $attSession->id)->count());
        $this->assertDatabaseHas('attendance_notifications', [
            'student_id' => $this->student->id,
            'guardian_id' => $this->guardian->id,
            'recipient_address' => '+923001234567',
            'status' => 'sent',
        ]);
    }
}
