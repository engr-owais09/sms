<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleBand extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'grading_scale_bands';

    protected $fillable = [
        'tenant_id',
        'grading_scale_id',
        'grade_letter',
        'min_percentage',
        'max_percentage',
        'gpa_points',
        'remarks',
        'is_passing',
    ];

    protected $casts = [
        'min_percentage' => 'decimal:2',
        'max_percentage' => 'decimal:2',
        'gpa_points' => 'decimal:2',
        'is_passing' => 'boolean',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }
}
