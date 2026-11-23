<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => false,
            'cache' => false,
            'storage' => false,
        ];

        try {
            DB::select('SELECT 1');
            $checks['database'] = true;
        } catch (Throwable) {
        }

        try {
            $key = 'health_probe_'.bin2hex(random_bytes(4));
            Cache::put($key, 'ok', 10);
            $checks['cache'] = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (Throwable) {
        }

        try {
            $checks['storage'] = is_writable(Storage::disk('local')->path(''));
        } catch (Throwable) {
        }

        $healthy = !in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
