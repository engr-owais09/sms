<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeAdvanceAllocation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_fee_advance_allocations';

    protected $fillable = [
        'tenant_id',
        'student_fee_advance_id',
        'fee_invoice_id',
        'fee_invoice_item_id',
        'allocation_reference',
        'settlement_journal_entry_id',
        'allocated_amount',
        'allocation_date',
        'status',
        'reversal_journal_entry_id',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'allocation_date' => 'date',
    ];

    public function advance(): BelongsTo
    {
        return $this->belongsTo(StudentFeeAdvance::class, 'student_fee_advance_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(FeeInvoiceItem::class, 'fee_invoice_item_id');
    }

    public function settlementJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'settlement_journal_entry_id');
    }

    public function reversalJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_entry_id');
    }
}

