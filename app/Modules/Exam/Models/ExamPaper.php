<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\Subject;
use App\Modules\Attendance\Models\CampusRoom;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamPaper extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'exam_papers';

    protected $fillable = [
        'tenant_id',
        'exam_term_id',
        'school_class_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'max_marks',
        'passing_marks',
        'campus_room_id',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'max_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2',
    ];

    public function examTerm(): BelongsTo
    {
        return $this->belongsTo(ExamTerm::class, 'exam_term_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(CampusRoom::class, 'campus_room_id');
    }
}
