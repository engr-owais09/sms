<?php

namespace App\Modules\Academic\Models\Academy;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyBatchSchedule extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_batch_schedules';

    protected $fillable = [
        'tenant_id',
        'academy_batch_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_number',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AcademyBatch::class, 'academy_batch_id');
    }

    public function getDayNameAttribute(): string
    {
        $days = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return $days[$this->day_of_week] ?? 'Unknown';
    }
}

