<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamTerm extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'exam_terms';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'grading_scale_id',
        'name',
        'term_type',
        'weightage_percentage',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'weightage_percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }

    public function papers(): HasMany
    {
        return $this->hasMany(ExamPaper::class, 'exam_term_id');
    }
}
