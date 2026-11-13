<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeDiscount extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'fee_discounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'discount_type',
        'discount_value',
        'contra_account_id',
        'applicable_category_id',
        'stacking_rule',
        'max_discount_cap',
        'requires_approval',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_cap' => 'decimal:2',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'contra_account_id');
    }

    public function applicableCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'applicable_category_id');
    }

    public function studentDiscounts(): HasMany
    {
        return $this->hasMany(StudentFeeDiscount::class, 'fee_discount_id');
    }
}

