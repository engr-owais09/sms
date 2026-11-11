<?php

namespace App\Modules\Academic\Models\School;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPromotion extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_promotions';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'from_session_id',
        'to_session_id',
        'from_section_id',
        'to_section_id',
        'result_summary',
        'promoted_by_user_id',
        'promoted_at',
        'remarks',
    ];

    protected $casts = [
        'promoted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function fromSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'from_session_id');
    }

    public function toSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'to_session_id');
    }

    public function fromSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'from_section_id');
    }

    public function toSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'to_section_id');
    }

    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by_user_id');
    }
}

