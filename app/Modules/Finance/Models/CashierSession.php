<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierSession extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'cashier_sessions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cashier_user_id',
        'session_code',
        'status',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash_balance',
        'actual_counted_cash',
        'variance_amount',
        'denominations',
        'closing_notes',
        'reconciled_by_user_id',
        'reconciled_at',
        'closing_journal_entry_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'opening_float' => 'decimal:2',
        'expected_cash_balance' => 'decimal:2',
        'actual_counted_cash' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'denominations' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function closingJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_journal_entry_id');
    }
}

