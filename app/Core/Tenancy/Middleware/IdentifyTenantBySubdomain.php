<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Domain;
use App\Modules\Institute\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantBySubdomain
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $tenant = $this->resolveTenant($host);

        if ($tenant) {
            if ($tenant->isSuspended()) {
                abort(403, 'This educational workspace has been suspended. Please contact support.');
            }

            // Enforce authenticated user belongs to this tenant (unless Super Admin)
            if (auth()->check()) {
                $user = auth()->user();
                if (!$user->isSuperAdmin() && $user->tenant_id !== $tenant->id) {
                    abort(403, 'You do not belong to this educational workspace.');
                }
            }

            // Set tenant in application singleton, PostgreSQL RLS session context, and Spatie team context
            app(TenantContext::class)->setTenant($tenant);
            PostgresTenantContext::set($tenant->id, isLocal: false);
            setPermissionsTeamId($tenant->id);
            if (auth()->check()) {
                auth()->user()->unsetRelation('roles')->unsetRelation('permissions');
            }

            // Set main branch if available
            $mainBranch = $tenant->branches()->where('is_main', true)->first();
            if ($mainBranch) {
                app(TenantContext::class)->setBranch($mainBranch);
            }
        } else {
            app(TenantContext::class)->clear();
            PostgresTenantContext::reset();
            setPermissionsTeamId(null);
            if (auth()->check()) {
                auth()->user()->unsetRelation('roles')->unsetRelation('permissions');
            }
        }

        return $next($request);
    }

    /**
     * Clean up context after response has been sent to client.
     */
    public function terminate(Request $request, Response $response): void
    {
        setPermissionsTeamId(null);
        PostgresTenantContext::reset();
        app(TenantContext::class)->clear();
    }

    /**
     * Resolve the Tenant from host.
     */
    protected function resolveTenant(string $host): ?Tenant
    {
        // 1. Check custom domains first (supports both superuser and RLS-enforced educore_app via security definer lookup)
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            try {
                $row = \Illuminate\Support\Facades\DB::selectOne(
                    'SELECT public.resolve_tenant_id_by_domain(?) AS tenant_id',
                    [$host]
                );
                if (!empty($row?->tenant_id)) {
                    return Tenant::query()->find($row->tenant_id);
                }
            } catch (\Throwable) {
                // Fallback to Eloquent lookup if helper function is not yet migrated
            }
        }

        $domainRecord = Domain::query()->where('domain', $host)->where('is_verified', true)->first();
        if ($domainRecord) {
            return $domainRecord->tenant;
        }

        // 2. Check subdomain against base domain (educore.test or app.url host)
        $baseDomain = config('app.base_domain', 'educore.test');
        
        // Handle port or local IP
        if (str_contains($host, ':')) {
            $host = explode(':', $host)[0];
        }

        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = substr($host, 0, -strlen('.' . $baseDomain));

            // Exclude platform subdomains
            if (in_array($subdomain, ['www', 'admin', 'super-admin', 'api', 'central'])) {
                return null;
            }

            return Tenant::query()->where('slug', $subdomain)->first();
        }

        return null;
    }
}
