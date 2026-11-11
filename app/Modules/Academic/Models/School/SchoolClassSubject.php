<?php

namespace App\Modules\Academic\Models\School;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\Subject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolClassSubject extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_class_subjects';

    protected $fillable = [
        'tenant_id',
        'school_class_id',
        'subject_id',
        'is_elective',
        'credit_hours',
        'pass_marks',
        'total_marks',
    ];

    protected $casts = [
        'is_elective' => 'boolean',
        'credit_hours' => 'decimal:2',
        'pass_marks' => 'decimal:2',
        'total_marks' => 'decimal:2',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}

