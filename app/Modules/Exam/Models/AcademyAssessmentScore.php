<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyAssessmentScore extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_assessment_scores';

    protected $fillable = [
        'tenant_id',
        'academy_assessment_id',
        'student_id',
        'marks_obtained',
        'is_absent',
        'percentage',
        'rank',
        'remarks',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'is_absent' => 'boolean',
        'percentage' => 'decimal:2',
        'rank' => 'integer',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(AcademyAssessment::class, 'academy_assessment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
