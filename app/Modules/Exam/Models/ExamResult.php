<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'exam_results';

    protected $fillable = [
        'tenant_id',
        'exam_term_id',
        'school_section_id',
        'student_id',
        'total_max_marks',
        'total_obtained_marks',
        'percentage',
        'grade_letter',
        'gpa',
        'section_rank',
        'result_status',
        'teacher_remarks',
        'published_at',
    ];

    protected $casts = [
        'total_max_marks' => 'decimal:2',
        'total_obtained_marks' => 'decimal:2',
        'percentage' => 'decimal:2',
        'gpa' => 'decimal:2',
        'section_rank' => 'integer',
        'published_at' => 'datetime',
    ];

    public function examTerm(): BelongsTo
    {
        return $this->belongsTo(ExamTerm::class, 'exam_term_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
