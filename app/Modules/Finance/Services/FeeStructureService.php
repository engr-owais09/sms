<?php

namespace App\Modules\Finance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeStructure;
use App\Modules\Finance\Models\FeeStructureItem;
use Illuminate\Support\Collection;

class FeeStructureService
{
    /**
     * Create a complete fee structure with nested items.
     */
    public function createStructure(array $headerData, array $items): FeeStructure
    {
        return TenantTransactionManager::run(function () use ($headerData, $items) {
            $structure = FeeStructure::create($headerData);

            $sortOrder = 1;
            foreach ($items as $item) {
                FeeStructureItem::create([
                    'tenant_id' => $structure->tenant_id,
                    'fee_structure_id' => $structure->id,
                    'fee_category_id' => $item['fee_category_id'],
                    'amount' => $item['amount'],
                    'sort_order' => $item['sort_order'] ?? $sortOrder++,
                    'is_optional' => $item['is_optional'] ?? false,
                ]);
            }

            return $structure->fresh(['items.feeCategory.defaultRevenueAccount']);
        });
    }

    /**
     * Resolve the active fee structure for a given school class and optional session.
     */
    public function resolveActiveStructureForClass(
        string $tenantId,
        string $branchId,
        string $classId,
        ?string $sessionId = null
    ): ?FeeStructure {
        $query = FeeStructure::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('school_class_id', $classId)
            ->where('is_active', true);

        if ($sessionId !== null) {
            $query->where(function ($q) use ($sessionId) {
                $q->where('academic_session_id', $sessionId)
                  ->orWhereNull('academic_session_id');
            })->orderByRaw('academic_session_id IS NULL ASC'); // Exact session match first
        }

        return $query->with(['items.feeCategory.defaultRevenueAccount'])->first();
    }

    /**
     * Resolve the active fee structure for an academy course.
     */
    public function resolveActiveStructureForCourse(
        string $tenantId,
        string $branchId,
        string $courseId
    ): ?FeeStructure {
        return FeeStructure::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('academy_course_id', $courseId)
            ->where('is_active', true)
            ->with(['items.feeCategory.defaultRevenueAccount'])
            ->first();
    }
}

