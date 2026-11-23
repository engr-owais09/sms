<?php

namespace App\Modules\Attendance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Attendance\Jobs\DispatchAbsenteeNotificationsJob;
use App\Modules\Attendance\Services\AcademyAttendanceService;
use App\Modules\Attendance\Services\SchoolAttendanceService;
use App\Modules\Institute\Models\Branch;
use Livewire\Component;

class RollCallRegister extends Component
{
    public string $domainMode = 'school'; // 'school' | 'academy'
    public ?string $academicSessionId = null;
    public ?string $schoolSectionId = null;
    public ?string $academyBatchId = null;
    public string $attendanceDate = '';
    public string $topicCovered = '';

    public array $roster = [];
    public ?string $activeSessionId = null;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $this->attendanceDate = now()->toDateString();

        if (!$tenant) {
            return;
        }

        if ($tenant->type === 'academy') {
            $this->domainMode = 'academy';
        }

        $session = AcademicSession::where('tenant_id', $tenant->id)->where('is_current', true)->first();
        $this->academicSessionId = $session?->id;

        $section = SchoolSection::where('tenant_id', $tenant->id)->first();
        $this->schoolSectionId = $section?->id;

        $batch = AcademyBatch::where('tenant_id', $tenant->id)->first();
        $this->academyBatchId = $batch?->id;

        $this->loadRoster();
    }

    public function loadRoster(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $this->roster = [];
        $this->activeSessionId = null;

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || !$this->attendanceDate) {
            return;
        }

        $branch = app(TenantContext::class)->getBranch() ?? Branch::where('tenant_id', $tenant->id)->first();
        $actor = auth()->user();
        if (!$actor) {
            return;
        }

        try {
            if ($this->domainMode === 'school') {
                if (!$this->academicSessionId || !$this->schoolSectionId) {
                    return;
                }
                $service = app(SchoolAttendanceService::class);
                $attSession = $service->openOrFetchSession(
                    $tenant->id,
                    $branch->id,
                    $this->academicSessionId,
                    $this->schoolSectionId,
                    $this->attendanceDate,
                    'daily',
                    null,
                    $actor
                );
                $this->activeSessionId = $attSession->id;
                $this->roster = $service->getSectionRoster($attSession)->toArray();
            } else {
                if (!$this->academyBatchId) {
                    return;
                }
                $service = app(AcademyAttendanceService::class);
                $attSession = $service->openOrFetchSession(
                    $tenant->id,
                    $branch->id,
                    $this->academyBatchId,
                    $this->attendanceDate,
                    null,
                    $this->topicCovered ?: null,
                    $actor
                );
                $this->activeSessionId = $attSession->id;
                $this->roster = $service->getBatchRoster($attSession)->toArray();
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $idx => $row) {
            if ($row['status'] !== 'excused') {
                $this->roster[$idx]['status'] = 'present';
            }
        }
    }

    public function saveAttendance(bool $dispatchNotifications = true): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || !$this->activeSessionId) {
            $this->errorMessage = 'No active attendance session loaded.';
            return;
        }

        $actor = auth()->user();
        if (!$actor) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }
        $statuses = [];
        foreach ($this->roster as $row) {
            $statuses[$row['student_id']] = [
                'status' => $row['status'],
                'remarks' => $row['remarks'] ?? null,
            ];
        }

        try {
            if ($this->domainMode === 'school') {
                $service = app(SchoolAttendanceService::class);
                $session = \App\Modules\Attendance\Models\SchoolAttendanceSession::findOrFail($this->activeSessionId);
                $service->markBulkAttendance($session, $statuses, true, $actor);

                if ($dispatchNotifications) {
                    DispatchAbsenteeNotificationsJob::dispatch($tenant->id, 'school_attendance', $session->id, 'sms');
                }
            } else {
                $service = app(AcademyAttendanceService::class);
                $session = \App\Modules\Attendance\Models\AcademyAttendanceSession::findOrFail($this->activeSessionId);
                $service->markBulkAttendance($session, $statuses, true, $actor);

                if ($dispatchNotifications) {
                    DispatchAbsenteeNotificationsJob::dispatch($tenant->id, 'academy_attendance', $session->id, 'sms');
                }
            }

            $this->successMessage = 'Attendance submitted successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to save attendance: ' . $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $sections = $tenant ? SchoolSection::with('schoolClass')->where('tenant_id', $tenant->id)->get() : collect();
        $batches = $tenant ? AcademyBatch::where('tenant_id', $tenant->id)->get() : collect();

        return view('livewire.attendance.roll-call-register', [
            'sections' => $sections,
            'batches' => $batches,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
