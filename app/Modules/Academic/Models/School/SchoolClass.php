<?php

namespace App\Modules\Academic\Models\School;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\Subject;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'school_classes';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'numeric_level',
        'display_order',
    ];

    protected $casts = [
        'numeric_level' => 'integer',
        'display_order' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SchoolSection::class, 'school_class_id')->orderBy('name');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(SchoolClassSubject::class, 'school_class_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'school_class_subjects', 'school_class_id', 'subject_id')
            ->withPivot(['id', 'is_elective', 'credit_hours', 'pass_marks', 'total_marks'])
            ->withTimestamps();
    }
}

