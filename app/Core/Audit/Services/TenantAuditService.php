<?php

namespace App\Core\Audit\Services;

use App\Core\Audit\Models\TenantAuditLog;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

class TenantAuditService
{
    public static function record(
        string $event,
        ?string $auditableType = null,
        ?string $auditableId = null,
        array $metadata = [],
        ?string $tenantId = null,
        ?int $actorUserId = null
    ): ?TenantAuditLog {
        $resolvedTenantId = $tenantId ?? app(TenantContext::class)->getTenantId();
        if (empty($resolvedTenantId)) {
            return null;
        }

        return TenantAuditLog::create([
            'tenant_id' => $resolvedTenantId,
            'actor_user_id' => $actorUserId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'ip_address' => request()?->ip(),
            'metadata_json' => $metadata,
            'created_at' => now(),
        ]);
    }
}
