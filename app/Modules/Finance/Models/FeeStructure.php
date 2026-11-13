<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\Academy\AcademyCourse;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeStructure extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'fee_structures';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'school_class_id',
        'academy_course_id',
        'name',
        'frequency',
        'currency',
        'is_active',
    ];

    protected $casts = [
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

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function academyCourse(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'academy_course_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class, 'fee_structure_id')->orderBy('sort_order', 'asc');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->items()->where('is_optional', false)->sum('amount');
    }
}

