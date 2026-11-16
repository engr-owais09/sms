<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Finance\Jobs\GenerateSchoolMonthlyVouchersJob;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Livewire\Component;

class VoucherGenerationConsole extends Component
{
    public ?string $branchId = null;
    public ?string $sessionId = null;
    public ?string $classId = null;

    public string $billingPeriod = '';
    public string $issueDate = '';
    public string $dueDate = '';

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? ($tenant ? Branch::where('tenant_id', $tenant->id)->first() : null);

        if ($branch) {
            $this->branchId = $branch->id;
        }

        $session = AcademicSession::where('tenant_id', $tenant ? $tenant->id : null)->where('is_current', true)->first();
        if ($session) {
            $this->sessionId = $session->id;
        }

        $class = SchoolClass::where('tenant_id', $tenant ? $tenant->id : null)->first();
        if ($class) {
            $this->classId = $class->id;
        }

        $this->billingPeriod = now()->format('Y-m');
        $this->issueDate = now()->startOfMonth()->format('Y-m-d');
        $this->dueDate = now()->startOfMonth()->addDays(9)->format('Y-m-d');
    }

    public function generateBatch(): void
    {
        $this->resetFeedback();
        $tenant = app(TenantContext::class)->getTenant();

        if (!$this->branchId || !$this->classId || !$this->billingPeriod || !$this->issueDate || !$this->dueDate) {
            $this->errorMessage = 'Campus, Class, Billing Period, Issue Date, and Due Date are all required.';
            return;
        }

        try {
            // Run batch synchronously for immediate feedback
            GenerateSchoolMonthlyVouchersJob::dispatchSync(
                (string) $tenant->id,
                (string) $this->branchId,
                (string) $this->classId,
                $this->billingPeriod,
                $this->issueDate,
                $this->dueDate,
                $this->sessionId ? (string) $this->sessionId : null
            );

            $count = FeeInvoice::where('tenant_id', $tenant->id)
                ->where('billing_period', $this->billingPeriod)
                ->count();

            $this->successMessage = "Batch voucher generation complete! Invoices for period {$this->billingPeriod} processed. Total vouchers in period: {$count}.";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Voucher generation failed: ' . $e->getMessage();
        }
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $branches = Branch::where('tenant_id', $tenant ? $tenant->id : null)->get();
        $sessions = AcademicSession::where('tenant_id', $tenant ? $tenant->id : null)->get();
        $classes = SchoolClass::where('tenant_id', $tenant ? $tenant->id : null)->get();

        $recentInvoices = FeeInvoice::with(['student'])
            ->where('tenant_id', $tenant ? $tenant->id : null)
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.finance.voucher-generation-console', [
            'tenant' => $tenant,
            'branches' => $branches,
            'sessions' => $sessions,
            'classes' => $classes,
            'recentInvoices' => $recentInvoices,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
