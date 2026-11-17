<?php

namespace App\Modules\Attendance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Attendance\Models\AcademyAttendanceRecord;
use App\Modules\Attendance\Models\AcademyAttendanceSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AcademyAttendanceService
{
    public function openOrFetchSession(
        string $tenantId,
        string $branchId,
        string $batchId,
        string $date,
        ?string $scheduleId = null,
        ?string $topic = null,
        ?User $actor = null
    ): AcademyAttendanceSession {
        return TenantTransactionManager::run(function () use (
            $tenantId,
            $branchId,
            $batchId,
            $date,
            $scheduleId,
            $topic,
            $actor
        ) {
            if ($scheduleId !== null) {
                $existing = AcademyAttendanceSession::where('tenant_id', $tenantId)
                    ->where('academy_batch_id', $batchId)
                    ->where('academy_batch_schedule_id', $scheduleId)
                    ->where('session_date', $date)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            return AcademyAttendanceSession::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'academy_batch_id' => $batchId,
                'academy_batch_schedule_id' => $scheduleId,
                'session_date' => $date,
                'topic_covered' => $topic,
                'marked_by_user_id' => $actor?->id,
                'status' => 'draft',
            ]);
        });
    }

    public function getBatchRoster(AcademyAttendanceSession $session): Collection
    {
        $enrollments = AcademyBatchEnrollment::with('student')
            ->where('tenant_id', $session->tenant_id)
            ->where('academy_batch_id', $session->academy_batch_id)
            ->where('enrollment_status', 'active')
            ->get();

        $existingRecords = AcademyAttendanceRecord::where('academy_attendance_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $approvedLeaves = collect();
        if (Schema::hasTable('student_leave_requests')) {
            $approvedLeaves = DB::table('student_leave_requests')
                ->where('tenant_id', $session->tenant_id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $session->session_date->toDateString())
                ->where('end_date', '>=', $session->session_date->toDateString())
                ->pluck('leave_type', 'student_id');
        }

        return $enrollments->map(function ($enrollment) use ($existingRecords, $approvedLeaves) {
            $student = $enrollment->student;
            $record = $existingRecords->get($student->id);
            $leaveType = $approvedLeaves->get($student->id);

            return [
                'student_id' => $student->id,
                'admission_number' => $student->admission_number,
                'full_name' => trim($student->first_name . ' ' . $student->last_name),
                'status' => $record?->status ?? ($leaveType ? 'excused' : 'present'),
                'remarks' => $record?->remarks ?? ($leaveType ? "Approved {$leaveType} leave" : ''),
            ];
        });
    }

    public function markBulkAttendance(
        AcademyAttendanceSession $session,
        array $studentStatuses,
        bool $submit = true,
        ?User $actor = null
    ): AcademyAttendanceSession {
        return TenantTransactionManager::run(function () use ($session, $studentStatuses, $submit, $actor) {
            $lockedSession = AcademyAttendanceSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status === 'locked') {
                throw new RuntimeException("Academy attendance session is locked and cannot be modified.");
            }

            $now = now();

            foreach ($studentStatuses as $studentId => $payload) {
                $status = is_array($payload) ? ($payload['status'] ?? 'present') : (string) $payload;
                $remarks = is_array($payload) ? ($payload['remarks'] ?? null) : null;

                AcademyAttendanceRecord::updateOrCreate(
                    [
                        'tenant_id' => $lockedSession->tenant_id,
                        'academy_attendance_session_id' => $lockedSession->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'status' => $status,
                        'remarks' => $remarks,
                        'marked_at' => $now,
                    ]
                );
            }

            $updateData = [
                'marked_by_user_id' => $actor?->id ?? $lockedSession->marked_by_user_id,
            ];

            if ($submit) {
                $updateData['status'] = 'submitted';
                $updateData['submitted_at'] = $lockedSession->submitted_at ?? $now;
            }

            $lockedSession->update($updateData);

            return $lockedSession->fresh(['records']);
        });
    }

    public function calculateStudentAttendanceRate(string $tenantId, string $batchId, string $studentId): array
    {
        $sessionIds = AcademyAttendanceSession::where('tenant_id', $tenantId)
            ->where('academy_batch_id', $batchId)
            ->whereIn('status', ['submitted', 'locked'])
            ->pluck('id');

        $totalSessions = $sessionIds->count();
        if ($totalSessions === 0) {
            return ['total_sessions' => 0, 'attended_sessions' => 0, 'percentage' => 100.00];
        }

        $attended = AcademyAttendanceRecord::where('tenant_id', $tenantId)
            ->whereIn('academy_attendance_session_id', $sessionIds)
            ->where('student_id', $studentId)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return [
            'total_sessions' => $totalSessions,
            'attended_sessions' => $attended,
            'percentage' => round(($attended / $totalSessions) * 100, 2),
        ];
    }
}
