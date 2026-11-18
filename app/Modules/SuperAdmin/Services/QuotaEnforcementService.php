<?php

namespace App\Modules\SuperAdmin\Services;

use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use App\Modules\SuperAdmin\Exceptions\QuotaExceededException;
use App\Modules\SuperAdmin\Models\TenantSubscription;

class QuotaEnforcementService
{
    public function getActiveSubscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::with('plan')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->first();
    }

    public function assertCanCreateBranch(Tenant $tenant): void
    {
        $sub = $this->getActiveSubscription($tenant);
        if (!$sub) {
            throw new QuotaExceededException("Tenant '{$tenant->name}' does not have an active subscription.");
        }

        $currentCount = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $limit = $sub->effectiveMaxBranches();

        if ($currentCount >= $limit) {
            throw new QuotaExceededException("Branch quota exceeded ({$currentCount}/{$limit}). Upgrade subscription plan.");
        }
    }

    public function assertCanAdmitStudent(Tenant $tenant): void
    {
        $sub = $this->getActiveSubscription($tenant);
        if (!$sub) {
            throw new QuotaExceededException("Tenant '{$tenant->name}' does not have an active subscription.");
        }

        $currentCount = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->count();
        $limit = $sub->effectiveMaxStudents();

        if ($currentCount >= $limit) {
            throw new QuotaExceededException("Active student quota exceeded ({$currentCount}/{$limit}). Upgrade subscription plan.");
        }
    }

    public function hasFeature(Tenant $tenant, string $featureKey): bool
    {
        $sub = $this->getActiveSubscription($tenant);
        if (!$sub || !$sub->plan) {
            return false;
        }

        return (bool) ($sub->plan->features_json[$featureKey] ?? false);
    }
}
