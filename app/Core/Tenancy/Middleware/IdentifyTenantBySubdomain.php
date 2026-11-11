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

            // Set tenant in application singleton
            app(TenantContext::class)->setTenant($tenant);

            // Set main branch if available
            $mainBranch = $tenant->branches()->where('is_main', true)->first();
            if ($mainBranch) {
                app(TenantContext::class)->setBranch($mainBranch);
            }
        }

        return $next($request);
    }

    /**
     * Clean up context after response has been sent to client.
     */
    public function terminate(Request $request, Response $response): void
    {
        PostgresTenantContext::reset();
        app(TenantContext::class)->clear();
    }

    /**
     * Resolve the Tenant from host.
     */
    protected function resolveTenant(string $host): ?Tenant
    {
        // 1. Check custom domains first
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
