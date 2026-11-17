<?php

namespace App\Modules\Attendance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Attendance\Models\AcademicHoliday;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceSession;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class SchoolAttendanceService
{
    /**
     * Open or fetch an existing attendance session for a school section and date.
     */
    public function openOrFetchSession(
        string $tenantId,
        string $branchId,
        string $academicSessionId,
        string $sectionId,
        string $date,
        string $mode = 'daily',
        ?string $timetableEntryId = null,
        ?User $actor = null,
        bool $allowHolidayOverride = false
    ): SchoolAttendanceSession {
        if ($mode === 'period' && empty($timetableEntryId)) {
            throw new InvalidArgumentException("Timetable entry ID is required for period-wise attendance.");
        }

        if (!$allowHolidayOverride) {
            $holiday = AcademicHoliday::resolveForDate($tenantId, $branchId, $date);
            if ($holiday) {
                throw new RuntimeException("Cannot mark attendance on holiday '{$holiday->title}' ({$date}).");
            }
        }

        return TenantTransactionManager::run(function () use (
            $tenantId,
            $branchId,
            $academicSessionId,
            $sectionId,
            $date,
            $mode,
            $timetableEntryId,
            $actor
        ) {
            $query = SchoolAttendanceSession::where('tenant_id', $tenantId)
                ->where('attendance_date', $date)
                ->where('attendance_mode', $mode);

            if ($mode === 'daily') {
                $query->where('school_section_id', $sectionId);
            } else {
                $query->where('school_timetable_entry_id', $timetableEntryId);
            }

            $existing = $query->first();
            if ($existing) {
                return $existing;
            }

            return SchoolAttendanceSession::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'academic_session_id' => $academicSessionId,
                'school_section_id' => $sectionId,
                'attendance_date' => $date,
                'attendance_mode' => $mode,
                'school_timetable_entry_id' => $mode === 'period' ? $timetableEntryId : null,
                'marked_by_user_id' => $actor?->id,
                'status' => 'draft',
            ]);
        });
    }

    /**
     * Get active enrolled students for the session's section, pre-annotated with
     * existing attendance records or approved leave status.
     */
    public function getSectionRoster(SchoolAttendanceSession $session): Collection
    {
        $enrollments = SchoolStudentEnrollment::with('student')
            ->where('tenant_id', $session->tenant_id)
            ->where('academic_session_id', $session->academic_session_id)
            ->where('school_section_id', $session->school_section_id)
            ->where('enrollment_status', 'active')
            ->get();

        $existingRecords = SchoolAttendanceRecord::where('school_attendance_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        $approvedLeaves = collect();
        if (Schema::hasTable('student_leave_requests')) {
            $approvedLeaves = DB::table('student_leave_requests')
                ->where('tenant_id', $session->tenant_id)
                ->where('status', 'approved')
                ->where('start_date', '<=', $session->attendance_date->toDateString())
                ->where('end_date', '>=', $session->attendance_date->toDateString())
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

    /**
     * Bulk upsert attendance statuses for students in a session.
     *
     * @param array<string, array{status: string, remarks?: string}> $studentStatuses
     */
    public function markBulkAttendance(
        SchoolAttendanceSession $session,
        array $studentStatuses,
        bool $submit = true,
        ?User $actor = null
    ): SchoolAttendanceSession {
        return TenantTransactionManager::run(function () use ($session, $studentStatuses, $submit, $actor) {
            $lockedSession = SchoolAttendanceSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if ($lockedSession->status === 'locked') {
                throw new RuntimeException("Attendance session is locked and cannot be modified.");
            }

            $now = now();

            foreach ($studentStatuses as $studentId => $payload) {
                $status = is_array($payload) ? ($payload['status'] ?? 'present') : (string) $payload;
                $remarks = is_array($payload) ? ($payload['remarks'] ?? null) : null;

                SchoolAttendanceRecord::updateOrCreate(
                    [
                        'tenant_id' => $lockedSession->tenant_id,
                        'school_attendance_session_id' => $lockedSession->id,
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

    public function lockSession(SchoolAttendanceSession $session): SchoolAttendanceSession
    {
        $session->update(['status' => 'locked']);
        return $session->fresh();
    }
}
