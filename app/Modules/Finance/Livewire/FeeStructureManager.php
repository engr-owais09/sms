<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Services\FeeStructureService;
use App\Modules\Institute\Models\Branch;
use Livewire\Component;

class FeeStructureManager extends Component
{
    public string $activeView = 'structures'; // 'structures' | 'categories'

    // Fee Category Form
    public string $categoryCode = '';
    public string $categoryName = '';
    public string $categoryDescription = '';
    public ?string $categoryRevenueAccountId = null;

    // Fee Structure Form
    public string $structureName = '';
    public ?string $structureBranchId = null;
    public ?string $structureSessionId = null;
    public ?string $structureClassId = null;
    public string $currency = 'PKR';
    public array $structureItems = [];

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $branch = app(TenantContext::class)->getBranch() ?? ($tenant ? Branch::where('tenant_id', $tenant->id)->first() : null);

        if ($branch) {
            $this->structureBranchId = $branch->id;
        }

        $revAcc = ChartOfAccount::where('account_code', '4010')->first();
        if ($revAcc) {
            $this->categoryRevenueAccountId = $revAcc->id;
        }

        $this->addItemRow();
    }

    public function addItemRow(): void
    {
        $firstCat = FeeCategory::where('is_active', true)->first();
        $this->structureItems[] = [
            'fee_category_id' => $firstCat ? $firstCat->id : null,
            'amount' => 1000.00,
            'is_optional' => false,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->structureItems[$index]);
        $this->structureItems = array_values($this->structureItems);
    }

    public function createCategory(): void
    {
        $this->resetFeedback();
        $tenant = app(TenantContext::class)->getTenant();

        if (!$this->categoryCode || !$this->categoryName) {
            $this->errorMessage = 'Category Code and Name are required.';
            return;
        }

        try {
            FeeCategory::create([
                'tenant_id' => $tenant->id,
                'code' => strtoupper(trim($this->categoryCode)),
                'name' => trim($this->categoryName),
                'description' => $this->categoryDescription ?: null,
                'default_revenue_account_id' => $this->categoryRevenueAccountId,
                'is_active' => true,
            ]);

            $this->categoryCode = '';
            $this->categoryName = '';
            $this->categoryDescription = '';
            $this->successMessage = 'Fee Category created successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create fee category: ' . $e->getMessage();
        }
    }

    public function createStructure(): void
    {
        $this->resetFeedback();
        $tenant = app(TenantContext::class)->getTenant();

        if (!$this->structureName || !$this->structureBranchId || empty($this->structureItems)) {
            $this->errorMessage = 'Structure Name, Branch, and at least one Fee Item are required.';
            return;
        }

        try {
            $service = app(FeeStructureService::class);
            $service->createStructure([
                'tenant_id' => $tenant->id,
                'branch_id' => $this->structureBranchId,
                'academic_session_id' => $this->structureSessionId,
                'school_class_id' => $this->structureClassId,
                'name' => $this->structureName,
                'currency' => $this->currency,
                'is_active' => true,
            ], $this->structureItems);

            $this->structureName = '';
            $this->structureItems = [];
            $this->addItemRow();
            $this->successMessage = 'Fee Structure created successfully!';
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create fee structure: ' . $e->getMessage();
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
        $categories = FeeCategory::with('defaultRevenueAccount')
            ->where('tenant_id', $tenant ? $tenant->id : null)
            ->get();

        $structures = FeeStructure::with(['branch', 'schoolClass', 'academicSession', 'items.feeCategory'])
            ->where('tenant_id', $tenant ? $tenant->id : null)
            ->latest()
            ->get();

        $branches = Branch::where('tenant_id', $tenant ? $tenant->id : null)->get();
        $classes = SchoolClass::where('tenant_id', $tenant ? $tenant->id : null)->get();
        $sessions = AcademicSession::where('tenant_id', $tenant ? $tenant->id : null)->get();
        $revenueAccounts = ChartOfAccount::where('account_type', 'revenue')->get();

        return view('livewire.finance.fee-structure-manager', [
            'tenant' => $tenant,
            'categories' => $categories,
            'structures' => $structures,
            'branches' => $branches,
            'classes' => $classes,
            'sessions' => $sessions,
            'revenueAccounts' => $revenueAccounts,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
