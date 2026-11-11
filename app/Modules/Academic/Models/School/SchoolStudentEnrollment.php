<?php

namespace App\Modules\Academic\Models\School;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolStudentEnrollment extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_student_enrollments';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academic_session_id',
        'school_section_id',
        'roll_number',
        'enrollment_status',
        'enrolled_at',
    ];

    protected $casts = [
        'enrolled_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'school_section_id');
    }
}

