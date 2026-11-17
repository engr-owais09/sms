<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPeriod extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'school_periods';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'period_number',
        'label',
        'start_time',
        'end_time',
        'is_break',
    ];

    protected $casts = [
        'period_number' => 'integer',
        'is_break' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
