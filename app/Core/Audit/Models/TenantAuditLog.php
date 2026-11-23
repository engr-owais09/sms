<?php

namespace App\Core\Audit\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'tenant_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'metadata_json',
        'created_at',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
