<?php

namespace App\Modules\SuperAdmin\Models;

use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tenant_subscriptions';

    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'custom_max_branches',
        'custom_max_students',
        'auto_renew',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'date',
        'current_period_end' => 'date',
        'custom_max_branches' => 'integer',
        'custom_max_students' => 'integer',
        'auto_renew' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function effectiveMaxBranches(): int
    {
        return $this->custom_max_branches ?? $this->plan->max_branches;
    }

    public function effectiveMaxStudents(): int
    {
        return $this->custom_max_students ?? $this->plan->max_students;
    }
}
