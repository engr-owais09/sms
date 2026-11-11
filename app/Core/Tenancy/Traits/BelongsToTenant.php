<?php

namespace App\Core\Tenancy\Traits;

use App\Core\Tenancy\Scopes\TenantScope;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait and apply the global tenant scope.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantId = app(TenantContext::class)->getTenantId();
                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }

            if (in_array('branch_id', $model->getFillable()) && empty($model->branch_id)) {
                $branchId = app(TenantContext::class)->getBranchId();
                if ($branchId) {
                    $model->branch_id = $branchId;
                }
            }
        });
    }

    /**
     * Relationship to the Tenant model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
