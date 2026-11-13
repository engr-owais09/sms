<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'fee_categories';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'category_scope',
        'default_revenue_account_id',
        'unearned_liability_account_id',
        'is_refundable',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_refundable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function defaultRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_revenue_account_id');
    }

    public function unearnedLiabilityAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unearned_liability_account_id');
    }

    public function structureItems(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class, 'fee_category_id');
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(FeeDiscount::class, 'applicable_category_id');
    }
}

