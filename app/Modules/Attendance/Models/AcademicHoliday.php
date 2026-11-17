<?php

namespace App\Modules\Attendance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicHoliday extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'academic_holidays';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'title',
        'holiday_date',
        'holiday_type',
        'is_recurring',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public static function resolveForDate(string $tenantId, ?string $branchId, string $date): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->where('holiday_date', $date)
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->first();
    }
}
