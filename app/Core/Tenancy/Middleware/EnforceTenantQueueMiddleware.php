<?php

namespace App\Core\Tenancy\Middleware;

use App\Core\Tenancy\Contracts\PlatformSystemJob;
use App\Core\Tenancy\Contracts\TenantAwareJob;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Exceptions\TenantSuspendedException;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnforceTenantQueueMiddleware
{
    /**
     * Process the queued job with fail-closed tenant scoping.
     *
     * @throws MissingTenantContextException
     * @throws TenantSuspendedException
     * @throws Throwable
     */
    public function handle(object $job, callable $next): mixed
    {
        // 1. Explicit Platform/System jobs execute without tenant context
        if ($job instanceof PlatformSystemJob) {
            return $next($job);
        }

        // 2. Strict Fail-Closed Check for Tenant Jobs
        if (!$job instanceof TenantAwareJob) {
            throw new MissingTenantContextException(
                sprintf('Job [%s] must implement TenantAwareJob or PlatformSystemJob.', get_class($job))
            );
        }

        $tenantId = $job->getTenantId();

        if (empty($tenantId)) {
            throw new MissingTenantContextException(
                sprintf('Job [%s] implements TenantAwareJob but supplied an empty tenantId.', get_class($job))
            );
        }

        // 3. Verify Tenant exists and is active
        $tenant = Tenant::query()->find($tenantId);

        if (!$tenant) {
            throw new MissingTenantContextException("Tenant ID [{$tenantId}] does not exist.");
        }

        if ($tenant->isSuspended()) {
            throw new TenantSuspendedException("Tenant [{$tenant->name}] is suspended. Queue execution halted.");
        }

        // 4. Establish Application Singleton
        app(TenantContext::class)->setTenant($tenant);

        // 5. Establish Transaction-Safe Database RLS Context
        return DB::transaction(function () use ($tenant, $job, $next) {
            PostgresTenantContext::set($tenant->id);

            try {
                return $next($job);
            } finally {
                // Guaranteed context cleanup regardless of job success or exception
                PostgresTenantContext::reset();
                app(TenantContext::class)->clear();
            }
        });
    }
}
