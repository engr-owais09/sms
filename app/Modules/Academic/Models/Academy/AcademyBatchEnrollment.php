<?php

namespace App\Modules\Academic\Models\Academy;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyBatchEnrollment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_batch_enrollments';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academy_batch_id',
        'fee_schedule_type',
        'agreed_fee_amount',
        'enrollment_status',
        'enrolled_at',
    ];

    protected $casts = [
        'agreed_fee_amount' => 'decimal:2',
        'enrolled_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AcademyBatch::class, 'academy_batch_id');
    }
}

