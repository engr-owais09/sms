<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'fee_payments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'cashier_session_id',
        'received_by_user_id',
        'receipt_number',
        'payment_date',
        'payment_method',
        'amount_tendered',
        'allocated_to_invoices',
        'allocated_to_advances',
        'credited_to_wallet',
        'instrument_reference',
        'status',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_tendered' => 'decimal:2',
        'allocated_to_invoices' => 'decimal:2',
        'allocated_to_advances' => 'decimal:2',
        'credited_to_wallet' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashierSession(): BelongsTo
    {
        return $this->belongsTo(CashierSession::class, 'cashier_session_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class, 'fee_payment_id');
    }
}

