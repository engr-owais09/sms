<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\NoActiveTenantException;
use Closure;
use Illuminate\Support\Facades\DB;

class TenantTransactionManager
{
    /**
     * Executes a callback within a managed transaction that guarantees
     * RLS parameter-safe scoping and automatic rollback cleanup.
     *
     * @template T
     * @param Closure(): T $callback
     * @param string|null $connection
     * @return T
     * @throws NoActiveTenantException
     */
    public static function run(Closure $callback, ?string $connection = null): mixed
    {
        $tenantId = app(TenantContext::class)->getTenantId();

        if (empty($tenantId)) {
            throw new NoActiveTenantException('Cannot execute tenant transaction without an active TenantContext.');
        }

        return DB::connection($connection)->transaction(function () use ($tenantId, $callback, $connection) {
            PostgresTenantContext::set($tenantId, $connection);

            return $callback();
        });
    }
}
