<?php

namespace App\Modules\Academic\Models\Academy;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademyCourse extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'academy_courses';

    protected $fillable = [
        'tenant_id',
        'academy_program_id',
        'name',
        'duration_weeks',
        'billing_type',
        'base_fee',
    ];

    protected $casts = [
        'duration_weeks' => 'integer',
        'base_fee' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(AcademyProgram::class, 'academy_program_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(AcademyBatch::class, 'academy_course_id');
    }
}

