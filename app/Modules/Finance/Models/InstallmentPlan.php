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

class InstallmentPlan extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'installment_plans';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academy_batch_enrollment_id',
        'total_amount',
        'number_of_installments',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'number_of_installments' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(InstallmentMilestone::class, 'installment_plan_id')->orderBy('sequence_number', 'asc');
    }
}

