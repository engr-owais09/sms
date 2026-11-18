<?php

namespace App\Modules\SuperAdmin\Models;

use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionInvoice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscription_invoices';

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'base_amount_pkr',
        'overage_amount_pkr',
        'discount_amount_pkr',
        'total_amount_pkr',
        'active_student_count',
        'due_date',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'due_date' => 'date',
        'base_amount_pkr' => 'decimal:2',
        'overage_amount_pkr' => 'decimal:2',
        'discount_amount_pkr' => 'decimal:2',
        'total_amount_pkr' => 'decimal:2',
        'active_student_count' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_invoice_id');
    }
}
