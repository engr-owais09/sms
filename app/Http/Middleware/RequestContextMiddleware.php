<?php

namespace App\Http\Middleware;

use App\Core\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());

        if ($request->has('theme') && in_array($request->query('theme'), ['light', 'dark'], true)) {
            session(['ui_theme' => $request->query('theme')]);
        }

        Log::shareContext([
            'request_id' => $requestId,
            'tenant_id' => app(TenantContext::class)->getTenantId(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
