<?php

namespace App\Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'code',
        'name',
        'description',
        'billing_cycle',
        'price_pkr',
        'per_student_rate_pkr',
        'max_branches',
        'max_students',
        'max_staff',
        'features_json',
        'is_active',
    ];

    protected $casts = [
        'price_pkr' => 'decimal:2',
        'per_student_rate_pkr' => 'decimal:2',
        'max_branches' => 'integer',
        'max_students' => 'integer',
        'max_staff' => 'integer',
        'features_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'subscription_plan_id');
    }
}
