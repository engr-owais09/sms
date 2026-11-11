<?php

namespace App\Modules\Academic\Models\Academy;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademyBatch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'academy_batches';

    protected $fillable = [
        'tenant_id',
        'academy_course_id',
        'branch_id',
        'name',
        'start_date',
        'end_date',
        'max_capacity',
        'instructor_id',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_capacity' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'academy_course_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(AcademyBatchSchedule::class, 'academy_batch_id')->orderBy('day_of_week')->orderBy('start_time');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(AcademyBatchEnrollment::class, 'academy_batch_id');
    }

    public function availableSeats(): int
    {
        $enrolledCount = $this->enrollments()->where('enrollment_status', 'active')->count();
        return max(0, $this->max_capacity - $enrolledCount);
    }
}

