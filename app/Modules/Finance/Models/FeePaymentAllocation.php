<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentAllocation extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'fee_payment_allocations';

    protected $fillable = [
        'tenant_id',
        'fee_payment_id',
        'fee_invoice_id',
        'fee_invoice_item_id',
        'allocated_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(FeeInvoiceItem::class, 'fee_invoice_item_id');
    }
}

