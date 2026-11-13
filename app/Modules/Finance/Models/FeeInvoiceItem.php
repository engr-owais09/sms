<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeInvoiceItem extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'fee_invoice_items';

    protected $fillable = [
        'tenant_id',
        'fee_invoice_id',
        'fee_category_id',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function feeCategory(): BelongsTo
    {
        return $this->belongsTo(FeeCategory::class, 'fee_category_id');
    }
}

