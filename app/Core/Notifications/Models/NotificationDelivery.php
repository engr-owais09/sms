<?php

namespace App\Core\Notifications\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'tenant_id',
        'channel',
        'recipient',
        'locale',
        'message',
        'provider_id',
        'status',
        'cost_pkr',
    ];
}
