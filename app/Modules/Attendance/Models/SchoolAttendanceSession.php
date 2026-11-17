<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolAttendanceSession extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_attendance_sessions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'school_section_id',
        'attendance_date',
        'attendance_mode',
        'school_timetable_entry_id',
        'marked_by_user_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(SchoolTimetableEntry::class, 'school_timetable_entry_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(SchoolAttendanceRecord::class, 'school_attendance_session_id');
    }
}
