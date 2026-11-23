<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'payment_gateway_transactions';

    protected $fillable = [
        'tenant_id',
        'fee_invoice_id',
        'provider',
        'provider_reference',
        'amount_pkr',
        'status',
        'payload_json',
    ];

    protected $casts = [
        'amount_pkr' => 'decimal:2',
        'payload_json' => 'array',
    ];
}
