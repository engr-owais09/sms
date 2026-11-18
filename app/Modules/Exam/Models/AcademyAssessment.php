<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\Academy\AcademyBatch;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyAssessment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academy_assessments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academy_batch_id',
        'title',
        'assessment_type',
        'assessment_date',
        'max_marks',
        'passing_marks',
        'status',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'max_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AcademyBatch::class, 'academy_batch_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AcademyAssessmentScore::class, 'academy_assessment_id');
    }
}
