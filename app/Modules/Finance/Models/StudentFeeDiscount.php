<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeDiscount extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_fee_discounts';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'fee_discount_id',
        'custom_value',
        'valid_from',
        'valid_until',
        'status',
        'approved_by_user_id',
        'approved_at',
        'reason',
    ];

    protected $casts = [
        'custom_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'approved_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function feeDiscount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class, 'fee_discount_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isValidOn(string $date): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->valid_from > $date) {
            return false;
        }

        if ($this->valid_until !== null && $this->valid_until < $date) {
            return false;
        }

        return true;
    }
}

