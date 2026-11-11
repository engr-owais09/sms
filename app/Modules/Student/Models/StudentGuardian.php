<?php

namespace App\Modules\Student\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentGuardian extends Pivot
{
    use HasUuids, BelongsToTenant;

    protected $table = 'student_guardians';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'student_id',
        'guardian_id',
        'relationship_type',
        'is_primary',
        'is_emergency_contact',
        'is_financial_sponsor',
        'can_pickup',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_emergency_contact' => 'boolean',
        'is_financial_sponsor' => 'boolean',
        'can_pickup' => 'boolean',
    ];
}

