<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Academic\Models\Subject;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolTimetableEntry extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_timetable_entries';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'school_section_id',
        'school_period_id',
        'subject_id',
        'teacher_id',
        'campus_room_id',
        'day_of_week',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
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

    public function period(): BelongsTo
    {
        return $this->belongsTo(SchoolPeriod::class, 'school_period_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(CampusRoom::class, 'campus_room_id');
    }
}
