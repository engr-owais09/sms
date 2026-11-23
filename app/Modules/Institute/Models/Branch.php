<?php

namespace App\Modules\Institute\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'city',
        'address',
        'phone',
        'is_main',
        'status',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (!$branch->tenant_id) {
                return;
            }

            $tenant = Tenant::query()->find($branch->tenant_id);
            if (!$tenant) {
                return;
            }

            if ($tenant->isSuspended()) {
                throw new \App\Modules\SuperAdmin\Exceptions\QuotaExceededException(
                    "Tenant '{$tenant->name}' is suspended and cannot create branches."
                );
            }

            $hasSubscriptionRecord = \App\Modules\SuperAdmin\Models\TenantSubscription::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->exists();

            if ($hasSubscriptionRecord) {
                app(\App\Modules\SuperAdmin\Services\QuotaEnforcementService::class)
                    ->assertCanCreateBranch($tenant);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function isMainBranch(): bool
    {
        return $this->is_main;
    }
}
