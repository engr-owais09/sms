<?php

namespace App\Core\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

class TenantCache
{
    public static function key(string $tenantId, string $suffix): string
    {
        return "tenant:{$tenantId}:{$suffix}";
    }

    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public static function remember(string $tenantId, string $suffix, int $ttlSeconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($tenantId, $suffix), $ttlSeconds, $callback);
    }

    public static function forget(string $tenantId, string $suffix): bool
    {
        return Cache::forget(self::key($tenantId, $suffix));
    }
}
