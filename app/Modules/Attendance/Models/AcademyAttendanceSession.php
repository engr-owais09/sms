<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Academic\Models\Academy\AcademyBatchSchedule;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyAttendanceSession extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_attendance_sessions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academy_batch_id',
        'academy_batch_schedule_id',
        'session_date',
        'topic_covered',
        'marked_by_user_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'session_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AcademyBatch::class, 'academy_batch_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AcademyBatchSchedule::class, 'academy_batch_schedule_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AcademyAttendanceRecord::class, 'academy_attendance_session_id');
    }
}
