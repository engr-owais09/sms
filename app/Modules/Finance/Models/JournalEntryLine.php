<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'tenant_id',
        'journal_entry_id',
        'account_id',
        'entry_type',
        'amount',
        'line_number',
        'memo',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'line_number' => 'integer',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function isDebit(): bool
    {
        return $this->entry_type === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->entry_type === 'credit';
    }
}

