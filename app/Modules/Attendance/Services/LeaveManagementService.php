<?php

namespace App\Modules\Attendance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Attendance\Models\AcademyAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use InvalidArgumentException;
use RuntimeException;

class LeaveManagementService
{
    public function submitLeave(array $data): StudentLeaveRequest
    {
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            throw new InvalidArgumentException("End date cannot be earlier than start date.");
        }

        return TenantTransactionManager::run(function () use ($data) {
            return StudentLeaveRequest::create([
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $data['branch_id'],
                'student_id' => $data['student_id'],
                'leave_type' => $data['leave_type'] ?? 'sick',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => 'pending',
            ]);
        });
    }

    public function approveLeave(StudentLeaveRequest $leave, User $reviewer, ?string $notes = null): StudentLeaveRequest
    {
        return TenantTransactionManager::run(function () use ($leave, $reviewer, $notes) {
            $locked = StudentLeaveRequest::where('id', $leave->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException("Leave request is already {$locked->status}.");
            }

            $locked->update([
                'status' => 'approved',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $startDate = $locked->start_date->toDateString();
            $endDate = $locked->end_date->toDateString();
            $remarkText = "Approved {$locked->leave_type} leave";

            // Retroactively excuse non-locked School attendance records
            SchoolAttendanceRecord::where('tenant_id', $locked->tenant_id)
                ->where('student_id', $locked->student_id)
                ->whereHas('session', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('attendance_date', [$startDate, $endDate])
                      ->whereIn('status', ['draft', 'submitted']);
                })
                ->update([
                    'status' => 'excused',
                    'remarks' => $remarkText,
                ]);

            // Retroactively excuse non-locked Academy attendance records
            AcademyAttendanceRecord::where('tenant_id', $locked->tenant_id)
                ->where('student_id', $locked->student_id)
                ->whereHas('session', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('session_date', [$startDate, $endDate])
                      ->whereIn('status', ['draft', 'submitted']);
                })
                ->update([
                    'status' => 'excused',
                    'remarks' => $remarkText,
                ]);

            return $locked->fresh();
        });
    }

    public function rejectLeave(StudentLeaveRequest $leave, User $reviewer, ?string $notes = null): StudentLeaveRequest
    {
        return TenantTransactionManager::run(function () use ($leave, $reviewer, $notes) {
            $locked = StudentLeaveRequest::where('id', $leave->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new RuntimeException("Leave request is already {$locked->status}.");
            }

            $locked->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            return $locked->fresh();
        });
    }
}
