<?php

namespace App\Core\Tenancy;

use Illuminate\Support\Facades\DB;
use Throwable;

class PostgresTenantContext
{
    /**
     * Sets the tenant context safely within the active database connection/transaction.
     * When is_local = true, PostgreSQL automatically reverts the setting at COMMIT or ROLLBACK.
     */
    public static function set(string $tenantId, ?string $connection = null): void
    {
        $conn = DB::connection($connection);

        if ($conn->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $conn->statement(
                "SELECT set_config('app.current_tenant_id', ?, true)",
                [$tenantId]
            );
        } catch (Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Resets the tenant context to empty/null within the connection.
     */
    public static function reset(?string $connection = null): void
    {
        $conn = DB::connection($connection);

        if ($conn->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $conn->statement(
                "SELECT set_config('app.current_tenant_id', '', true)"
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Read the current active database setting from PostgreSQL.
     */
    public static function get(?string $connection = null): ?string
    {
        $conn = DB::connection($connection);

        if ($conn->getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $result = $conn->selectOne(
                "SELECT current_setting('app.current_tenant_id', true) as tenant_id"
            );

            return !empty($result?->tenant_id) ? $result->tenant_id : null;
        } catch (Throwable) {
            return null;
        }
    }
}
