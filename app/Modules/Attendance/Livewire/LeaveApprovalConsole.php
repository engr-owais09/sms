<?php

namespace App\Modules\Attendance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Attendance\Models\StudentLeaveRequest;
use App\Modules\Attendance\Services\LeaveManagementService;
use Livewire\Component;

class LeaveApprovalConsole extends Component
{
    public string $statusFilter = 'pending';
    public string $reviewNotes = '';
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function approve(string $leaveId): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
        $reviewer = auth()->user() ?? User::first();

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
        $reviewer = auth()->user() ?? User::first();

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

        return view('livewire.attendance.leave-approval-console', [
            'leaves' => $leaves,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
