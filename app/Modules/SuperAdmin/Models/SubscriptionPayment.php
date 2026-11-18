<?php

namespace App\Modules\SuperAdmin\Models;

use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscription_payments';

    protected $fillable = [
        'tenant_id',
        'subscription_invoice_id',
        'payment_reference',
        'payment_method',
        'amount_pkr',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount_pkr' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
