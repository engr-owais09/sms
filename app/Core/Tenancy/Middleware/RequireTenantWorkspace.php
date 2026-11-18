<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->getTenant();
        abort_unless($tenant, 404, 'Educational workspace not found');

        return $next($request);
    }
}
