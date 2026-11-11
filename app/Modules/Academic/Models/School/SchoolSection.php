<?php

namespace App\Modules\Academic\Models\School;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolSection extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'school_sections';

    protected $fillable = [
        'tenant_id',
        'school_class_id',
        'name',
        'room_number',
        'max_capacity',
        'class_teacher_id',
    ];

    protected $casts = [
        'max_capacity' => 'integer',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SchoolStudentEnrollment::class, 'school_section_id');
    }
}

