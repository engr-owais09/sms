<?php

namespace App\Modules\Attendance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Models\User;
use App\Modules\Attendance\Models\AcademyAttendanceRecord;
use App\Modules\Attendance\Models\SchoolAttendanceRecord;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use App\Modules\Student\Models\Student;
use InvalidArgumentException;
use RuntimeException;

class LeaveManagementService
{
    public function submitLeave(array $data): StudentLeaveRequest
    {
        if (empty($data['tenant_id']) || empty($data['branch_id']) || empty($data['student_id'])) {
            throw new InvalidArgumentException('Tenant, branch, and student are required to submit a leave request.');
        }

        if (empty($data['start_date']) || empty($data['end_date'])) {
            throw new InvalidArgumentException('Start date and end date are required.');
        }

        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            throw new InvalidArgumentException("End date cannot be earlier than start date.");
        }

        $leaveType = $data['leave_type'] ?? 'sick';
        if ($leaveType === 'emergency') {
            $leaveType = 'family';
        }
        if (!in_array($leaveType, ['sick', 'casual', 'family', 'other'], true)) {
            throw new InvalidArgumentException("Invalid leave type [{$leaveType}].");
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Leave reason is required.');
        }

        return TenantTransactionManager::run(function () use ($data, $leaveType, $reason) {
            $student = Student::withoutGlobalScopes()
                ->where('tenant_id', $data['tenant_id'])
                ->where('id', $data['student_id'])
                ->first();

            if (!$student) {
                throw new InvalidArgumentException('Selected student does not belong to this workspace.');
            }

            $overlapping = StudentLeaveRequest::where('tenant_id', $data['tenant_id'])
                ->where('student_id', $student->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $data['end_date'])
                ->whereDate('end_date', '>=', $data['start_date'])
                ->exists();

            if ($overlapping) {
                throw new InvalidArgumentException('An overlapping pending or approved leave request already exists for this student.');
            }

            return StudentLeaveRequest::create([
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $data['branch_id'],
                'student_id' => $student->id,
                'leave_type' => $leaveType,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $reason,
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
