<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCreditTransaction extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_credit_transactions';

    protected $fillable = [
        'tenant_id',
        'student_credit_wallet_id',
        'transaction_type',
        'amount',
        'balance_after',
        'source_type',
        'source_id',
        'journal_entry_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentCreditWallet::class, 'student_credit_wallet_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}

