<?php

namespace App\Modules\Attendance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Attendance\Services\TimetableConflictService;
use App\Modules\Institute\Models\Branch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;

class TimetableBuilder extends Component
{
    public ?string $academicSessionId = null;
    public ?string $schoolSectionId = null;
    public int $dayOfWeek = 1;
    public ?string $schoolPeriodId = null;
    public ?string $subjectId = null;
    public ?int $teacherId = null;
    public ?string $campusRoomId = null;

    // Teacher Substitution fields
    public ?string $substitutionEntryId = null;
    public ?int $substituteTeacherId = null;
    public string $substitutionDate = '';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->substitutionDate = now()->toDateString();
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            return;
        }

        $session = AcademicSession::where('tenant_id', $tenant->id)->where('is_current', true)->first();
        $this->academicSessionId = $session?->id;

        $section = SchoolSection::where('tenant_id', $tenant->id)->first();
        $this->schoolSectionId = $section?->id;

        $period = SchoolPeriod::where('tenant_id', $tenant->id)->where('is_break', false)->orderBy('period_number')->first();
        $this->schoolPeriodId = $period?->id;

        $subject = Subject::where('tenant_id', $tenant->id)->first();
        $this->subjectId = $subject?->id;
    }

    public function assignSubstitute(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            throw new AuthorizationException('No active tenant workspace.');
        }
        if (!$actor->isSuperAdmin()) {
            if ($actor->tenant_id !== $tenant->id) {
                throw new AuthorizationException('You do not belong to this workspace.');
            }
            if (!$actor->can('attendance.mark')) {
                throw new AuthorizationException('Insufficient permissions to assign substitute teacher.');
            }
        }

        if (!$this->substitutionEntryId || !$this->substituteTeacherId || !$this->substitutionDate) {
            $this->errorMessage = 'Timetable slot, substitute teacher, and date are required.';
            return;
        }

        try {
            $entry = SchoolTimetableEntry::where('tenant_id', $tenant->id)->find($this->substitutionEntryId);
            if (!$entry) {
                $this->errorMessage = 'Selected timetable slot does not belong to this workspace.';
                return;
            }

            $subTeacher = User::where('tenant_id', $tenant->id)->where('status', 'active')->find($this->substituteTeacherId);
            if (!$subTeacher) {
                $this->errorMessage = 'Selected substitute teacher does not belong to this workspace.';
                return;
            }

            app(SchoolAttendanceService::class)->assignSubstituteTeacher(
                $entry,
                $this->substitutionDate,
                $subTeacher,
                $actor
            );

            $this->successMessage = "Substitute teacher {$subTeacher->name} assigned for {$this->substitutionDate}!";
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function assignSlot(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();

        if (!$this->academicSessionId || !$this->schoolSectionId || !$this->schoolPeriodId || !$this->subjectId) {
            $this->errorMessage = 'Session, Section, Period, and Subject are required.';
            return;
        }

        if ($this->teacherId) {
            $validTeacher = User::where('tenant_id', $tenant->id)
                ->where('id', $this->teacherId)
                ->exists();
            if (!$validTeacher) {
                $this->errorMessage = 'Selected teacher does not belong to this workspace.';
                return;
            }
        }

        try {
            $service = app(TimetableConflictService::class);
            $service->assignSchoolSlot([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'academic_session_id' => $this->academicSessionId,
                'school_section_id' => $this->schoolSectionId,
                'school_period_id' => $this->schoolPeriodId,
                'subject_id' => $this->subjectId,
                'teacher_id' => $this->teacherId ?: null,
                'campus_room_id' => $this->campusRoomId ?: null,
                'day_of_week' => $this->dayOfWeek,
            ]);

            $this->successMessage = 'Timetable slot assigned successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $sessions = $tenant ? AcademicSession::where('tenant_id', $tenant->id)->get() : collect();
        $sections = $tenant ? SchoolSection::with('schoolClass')->where('tenant_id', $tenant->id)->get() : collect();
        $periods = $tenant ? SchoolPeriod::where('tenant_id', $tenant->id)->orderBy('period_number')->get() : collect();
        $subjects = $tenant ? Subject::where('tenant_id', $tenant->id)->get() : collect();
        $rooms = $tenant ? CampusRoom::where('tenant_id', $tenant->id)->where('is_active', true)->get() : collect();
        $teachers = $tenant
            ? User::where('tenant_id', $tenant->id)->where('status', 'active')->orderBy('name')->get()
            : collect();

        $entries = ($tenant && $this->academicSessionId && $this->schoolSectionId)
            ? SchoolTimetableEntry::with(['period', 'subject', 'teacher', 'room'])
                ->where('tenant_id', $tenant->id)
                ->where('academic_session_id', $this->academicSessionId)
                ->where('school_section_id', $this->schoolSectionId)
                ->where('is_active', true)
                ->get()
            : collect();

        $substitutions = ($tenant && $this->schoolSectionId && $this->substitutionDate)
            ? SchoolAttendanceSession::with(['timetableEntry.period', 'timetableEntry.subject', 'markedBy'])
                ->where('tenant_id', $tenant->id)
                ->where('school_section_id', $this->schoolSectionId)
                ->where('attendance_mode', 'period')
                ->where('attendance_date', $this->substitutionDate)
                ->get()
            : collect();

        return view('livewire.attendance.timetable-builder', [
            'sessions' => $sessions,
            'sections' => $sections,
            'periods' => $periods,
            'subjects' => $subjects,
            'rooms' => $rooms,
            'teachers' => $teachers,
            'entries' => $entries,
            'substitutions' => $substitutions,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
