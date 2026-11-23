<?php

namespace App\Modules\Attendance\Jobs;

use App\Core\Tenancy\Contracts\TenantAwareJob;
use App\Core\Tenancy\Middleware\EnforceTenantQueueMiddleware;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Attendance\Models\AcademyAttendanceRecord;
use App\Modules\Attendance\Models\AcademyAttendanceSession;
use App\Modules\Attendance\Models\AttendanceNotification;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchAbsenteeNotificationsJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $sourceType, // 'school_attendance' | 'academy_attendance'
        public string $sourceSessionId,
        public string $channel = 'sms'
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function middleware(): array
    {
        return [new EnforceTenantQueueMiddleware()];
    }

    public function handle(): void
    {
        $tenant = \App\Modules\Institute\Models\Tenant::findOrFail($this->tenantId);
        app(TenantContext::class)->setTenant($tenant);
        PostgresTenantContext::set($this->tenantId);

        if ($this->sourceType === 'school_attendance') {
            $session = SchoolAttendanceSession::find($this->sourceSessionId);
            if (!$session) {
                return;
            }
            $attendanceDate = $session->attendance_date->toDateString();
            $absentRecords = SchoolAttendanceRecord::with('student.guardians')
                ->where('school_attendance_session_id', $session->id)
                ->where('status', 'absent')
                ->get();
        } else {
            $session = AcademyAttendanceSession::find($this->sourceSessionId);
            if (!$session) {
                return;
            }
            $attendanceDate = $session->session_date->toDateString();
            $absentRecords = AcademyAttendanceRecord::with('student.guardians')
                ->where('academy_attendance_session_id', $session->id)
                ->where('status', 'absent')
                ->get();
        }

        foreach ($absentRecords as $record) {
            $student = $record->student;
            if (!$student) {
                continue;
            }

            $guardian = $student->guardians->sortByDesc(fn ($g) => (bool) ($g->pivot->is_primary ?? false))->first();
            $recipient = $guardian?->phone ?? $guardian?->email ?? $student->phone ?? 'N/A';

            AttendanceNotification::firstOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'source_type' => $this->sourceType,
                    'source_session_id' => $this->sourceSessionId,
                    'student_id' => $student->id,
                    'channel' => $this->channel,
                ],
                [
                    'guardian_id' => $guardian?->id,
                    'attendance_date' => $attendanceDate,
                    'recipient_address' => $recipient,
                    'status' => $recipient === 'N/A' ? 'failed' : 'sent',
                    'sent_at' => $recipient === 'N/A' ? null : now(),
                    'error_message' => $recipient === 'N/A' ? 'No guardian contact available' : null,
                ]
            );
        }
    }
}
