<?php

namespace App\Modules\Attendance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use App\Modules\Attendance\Services\LeaveManagementService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Livewire\Component;

class LeaveApprovalConsole extends Component
{
    public string $statusFilter = 'pending';
    public string $reviewNotes = '';

    // Leave submission form fields
    public ?string $studentId = null;
    public string $leaveType = 'sick';
    public string $startDate = '';
    public string $endDate = '';
    public string $reason = '';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
    }

    protected function authorizeActor(string $requiredPermission = 'attendance.view'): User
    {
        $actor = auth()->user();
        if (!$actor) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $tenant = app(TenantContext::class)->getTenant();
        if (!$actor->isSuperAdmin() && $actor->tenant_id !== null && $tenant && $actor->tenant_id !== $tenant->id) {
            throw new AuthorizationException('You do not belong to this workspace.');
        }

        if (!$actor->isSuperAdmin() && ($actor->roles()->exists() || $actor->permissions()->exists())) {
            if (!$actor->can($requiredPermission) && !$actor->can('attendance.mark') && !$actor->can('attendance.view')) {
                throw new AuthorizationException('Insufficient permissions for leave management.');
            }
        }

        return $actor;
    }

    public function submitLeaveRequest(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        $this->authorizeActor('attendance.mark');

        $this->validate([
            'studentId' => 'required|uuid',
            'leaveType' => 'required|in:sick,casual,family,emergency,other',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            $this->errorMessage = 'No active tenant workspace.';
            return;
        }

        $branch = app(TenantContext::class)->getBranch()
            ?? Branch::where('tenant_id', $tenant->id)->where('is_main', true)->first()
            ?? Branch::where('tenant_id', $tenant->id)->first();

        try {
            $student = Student::where('tenant_id', $tenant->id)->find($this->studentId);
            if (!$student) {
                $this->errorMessage = 'Selected student does not belong to this workspace.';
                return;
            }

            app(LeaveManagementService::class)->submitLeave([
                'tenant_id' => $tenant->id,
                'branch_id' => $student->branch_id ?? $branch?->id,
                'student_id' => $student->id,
                'leave_type' => $this->leaveType,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'reason' => $this->reason,
            ]);

            $this->reset(['studentId', 'reason']);
            $this->leaveType = 'sick';
            $this->successMessage = 'Leave request submitted successfully.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function approve(string $leaveId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $reviewer = $this->authorizeActor('attendance.mark');

        try {
            $leave = StudentLeaveRequest::findOrFail($leaveId);
            app(LeaveManagementService::class)->approveLeave($leave, $reviewer, $this->reviewNotes ?: null);
            $this->reviewNotes = '';
            $this->successMessage = 'Leave request approved and attendance records synchronized.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reject(string $leaveId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $reviewer = $this->authorizeActor('attendance.mark');

        try {
            $leave = StudentLeaveRequest::findOrFail($leaveId);
            app(LeaveManagementService::class)->rejectLeave($leave, $reviewer, $this->reviewNotes ?: null);
            $this->reviewNotes = '';
            $this->successMessage = 'Leave request rejected.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();

        $leaves = $tenant
            ? StudentLeaveRequest::with('student')
                ->where('tenant_id', $tenant->id)
                ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                ->latest()
                ->get()
            : collect();

        $students = $tenant
            ? Student::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get()
            : collect();

        return view('livewire.attendance.leave-approval-console', [
            'leaves' => $leaves,
            'students' => $students,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
