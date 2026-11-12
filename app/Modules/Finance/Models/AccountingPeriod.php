<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPeriod extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'accounting_periods';

    protected $fillable = [
        'tenant_id',
        'fiscal_year_id',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'period_number' => 'integer',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'accounting_period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}

