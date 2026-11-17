<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLeaveRequest extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_leave_requests';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
