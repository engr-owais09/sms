<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'journal_entries';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'entry_number',
        'entry_date',
        'accounting_period_id',
        'status',
        'source_type',
        'source_id',
        'reverses_entry_id',
        'reversed_by_entry_id',
        'posted_at',
        'posted_by_user_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('line_number', 'asc');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function reversedByEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function totalDebits(): float
    {
        return (float) $this->lines()->where('entry_type', 'debit')->sum('amount');
    }

    public function totalCredits(): float
    {
        return (float) $this->lines()->where('entry_type', 'credit')->sum('amount');
    }
}

