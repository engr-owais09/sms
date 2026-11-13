<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentFeeAdvance extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'student_fee_advances';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'fee_payment_id',
        'fee_category_id',
        'gl_account_id',
        'target_billing_period',
        'receipt_date',
        'receipt_number',
        'amount',
        'applied_amount',
        'refunded_amount',
        'status',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'decimal:2',
        'applied_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StudentFeeAdvanceAllocation::class, 'student_fee_advance_id');
    }
}

