<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstallmentMilestone extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'installment_milestones';

    protected $fillable = [
        'tenant_id',
        'installment_plan_id',
        'milestone_name',
        'sequence_number',
        'due_date',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sequence_number' => 'integer',
        'due_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(FeeInvoice::class, 'installment_milestone_id');
    }
}

