<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceNotification extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'attendance_notifications';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'guardian_id',
        'source_type',
        'source_session_id',
        'attendance_date',
        'channel',
        'recipient_address',
        'status',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }
}
