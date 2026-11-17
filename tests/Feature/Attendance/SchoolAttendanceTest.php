<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\AcademicHoliday;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Attendance\Services\TimetableConflictService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SchoolAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolSection $section;
    protected Student $student1;
    protected Student $student2;
    protected User $teacher;
    protected SchoolAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Lahore Grammar School',
            'slug' => 'lgs-att-1',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg',
            'code' => 'LGS-G',
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

        $class8 = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 8',
            'numeric_level' => 8,
            'display_order' => 8,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class8->id,
            'name' => 'Blue',
            'capacity' => 35,
        ]);

        $this->student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-01',
            'first_name' => 'Zain',
            'last_name' => 'Malik',
            'date_of_birth' => '2013-03-10',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $this->student2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-02',
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'date_of_birth' => '2013-07-12',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student1->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'enrolled_at' => '2026-04-01',
            'enrollment_status' => 'active',
        ]);

        SchoolStudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student2->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'enrolled_at' => '2026-04-01',
            'enrollment_status' => 'active',
        ]);

        $this->teacher = User::factory()->create(['name' => 'Ms. Sadia']);
        $this->service = new SchoolAttendanceService();
    }

    public function test_can_mark_daily_roll_call_and_prevent_edits_once_locked(): void
    {
        $attSession = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-17',
            'daily',
            null,
            $this->teacher
        );

        $roster = $this->service->getSectionRoster($attSession);
        $this->assertCount(2, $roster);

        $updated = $this->service->markBulkAttendance($attSession, [
            $this->student1->id => ['status' => 'present'],
            $this->student2->id => ['status' => 'absent', 'remarks' => 'Uninformed absence'],
        ], true, $this->teacher);

        $this->assertEquals('submitted', $updated->status);
        $this->assertCount(2, $updated->records);

        $this->assertDatabaseHas('school_attendance_records', [
            'school_attendance_session_id' => $attSession->id,
            'student_id' => $this->student2->id,
            'status' => 'absent',
        ]);

        // Idempotent fetch returns same session
        $sameSession = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-17',
            'daily'
        );
        $this->assertEquals($attSession->id, $sameSession->id);

        // Lock session and ensure edits are blocked
        $this->service->lockSession($updated);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('locked');
        $this->service->markBulkAttendance($updated, [
            $this->student2->id => ['status' => 'present'],
        ]);
    }

    public function test_blocks_attendance_on_academic_holiday_unless_overridden(): void
    {
        AcademicHoliday::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => null,
            'title' => 'Iqbal Day',
            'holiday_date' => '2026-11-09',
            'holiday_type' => 'gazetted',
        ]);

        try {
            $this->service->openOrFetchSession(
                $this->tenant->id,
                $this->branch->id,
                $this->session->id,
                $this->section->id,
                '2026-11-09',
                'daily'
            );
            $this->fail('Expected RuntimeException when marking attendance on holiday.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Iqbal Day', $e->getMessage());
        }

        // Override allowed for makeup class
        $session = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-09',
            'daily',
            null,
            $this->teacher,
            true
        );
        $this->assertNotNull($session->id);
    }

    public function test_can_mark_period_wise_attendance_linked_to_timetable_entry(): void
    {
        $period = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
        ]);

        $subject = Subject::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'English',
            'code' => 'ENG-8',
            'type' => 'theory',
        ]);

        $ttEntry = (new TimetableConflictService())->assignSchoolSlot([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'school_period_id' => $period->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 2,
        ]);

        $periodAtt = $this->service->openOrFetchSession(
            $this->tenant->id,
            $this->branch->id,
            $this->session->id,
            $this->section->id,
            '2026-11-17',
            'period',
            $ttEntry->id,
            $this->teacher
        );

        $this->assertEquals('period', $periodAtt->attendance_mode);
        $this->assertEquals($ttEntry->id, $periodAtt->school_timetable_entry_id);
    }
}
