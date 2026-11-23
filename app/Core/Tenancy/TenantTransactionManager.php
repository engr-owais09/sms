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
            PostgresTenantContext::set($tenantId, $connection, true);

            return $callback();
        });
    }

    /**
     * Executes a financial transaction with bounded retry handling for transient
     * PostgreSQL deadlock (40P01) and serialization failure (40001).
     *
     * @template T
     * @param Closure(): T $callback
     * @param int $maxAttempts Maximum retry attempts (default 3)
     * @param string|null $connection
     * @return T
     */
    public static function runFinancialTransaction(Closure $callback, int $maxAttempts = 3, ?string $connection = null): mixed
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                if (class_exists(\App\Modules\Finance\Services\FinancialLockManager::class)) {
                    \App\Modules\Finance\Services\FinancialLockManager::reset();
                    \App\Modules\Finance\Services\FinancialLockManager::enterStage(
                        \App\Modules\Finance\Services\FinancialLockManager::STAGE_TENANT_BRANCH,
                        'runFinancialTransaction'
                    );
                }

                $result = self::run($callback, $connection);

                if (class_exists(\App\Modules\Finance\Services\FinancialLockManager::class)) {
                    \App\Modules\Finance\Services\FinancialLockManager::reset();
                }

                return $result;
            } catch (\Throwable $e) {
                if (class_exists(\App\Modules\Finance\Services\FinancialLockManager::class)) {
                    \App\Modules\Finance\Services\FinancialLockManager::reset();
                }

                $isTransient = self::isTransientConcurrencyError($e);

                if ($isTransient && $attempt < $maxAttempts) {
                    $jitter = random_int(-25, 25);
                    $sleepMs = max(20, (100 * $attempt) + $jitter);
                    usleep($sleepMs * 1000);
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Exhausted financial transaction retry attempts.');
    }

    /**
     * Determine if the exception represents a transient PostgreSQL concurrency error (40P01 / 40001).
     */
    protected static function isTransientConcurrencyError(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        $code = (string)$e->getCode();

        if (str_contains($msg, '40P01') || str_contains($msg, 'deadlock detected')) {
            return true;
        }

        if (str_contains($msg, '40001') || str_contains($msg, 'could not serialize access')) {
            return true;
        }

        if (isset($e->errorInfo) && is_array($e->errorInfo)) {
            $sqlState = (string)($e->errorInfo[0] ?? '');
            if ($sqlState === '40P01' || $sqlState === '40001') {
                return true;
            }
        }

        return $code === '40P01' || $code === '40001';
    }
}
