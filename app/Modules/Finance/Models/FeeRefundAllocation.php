<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeRefundAllocation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'fee_refund_allocations';

    protected $fillable = [
        'tenant_id',
        'fee_refund_id',
        'fee_payment_allocation_id',
        'fee_invoice_item_id',
        'refunded_amount',
        'concession_clawback',
    ];

    protected $casts = [
        'refunded_amount' => 'decimal:2',
        'concession_clawback' => 'decimal:2',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(FeeRefund::class, 'fee_refund_id');
    }

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(FeePaymentAllocation::class, 'fee_payment_allocation_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(FeeInvoiceItem::class, 'fee_invoice_item_id');
    }
}

