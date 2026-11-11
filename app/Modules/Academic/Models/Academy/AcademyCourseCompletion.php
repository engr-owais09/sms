<?php

namespace App\Modules\Academic\Models\Academy;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyCourseCompletion extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_course_completions';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academy_course_id',
        'completion_date',
        'final_grade_or_score',
        'certificate_number',
        'issued_at',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'issued_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'academy_course_id');
    }
}

