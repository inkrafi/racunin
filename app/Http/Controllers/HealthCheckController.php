<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    /**
     * Perform system health check for load balancers and monitoring tools.
     *
     * The endpoint is public, so environment details (versions, drivers, error
     * messages) are only included when debug mode is enabled.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $verbose = (bool) config('app.debug');
        $status = 'healthy';
        $checks = [];

        // 1. Database Connectivity & Latency Check
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'connected'];

            if ($verbose) {
                $checks['database']['driver'] = DB::connection()->getDriverName();
                $checks['database']['latency_ms'] = round((microtime(true) - $dbStart) * 1000, 2);
            }
        } catch (\Exception $e) {
            $status = 'unhealthy';
            $checks['database'] = ['status' => 'disconnected'];
            Log::error('Health Check Database Failure', ['exception' => $e->getMessage()]);

            if ($verbose) {
                $checks['database']['error'] = $e->getMessage();
            }
        }

        // 2. Cache Store Check
        try {
            $cacheKey = 'health_check_' . time();
            Cache::put($cacheKey, true, 10);
            $cached = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            $checks['cache'] = ['status' => $cached ? 'active' : 'failed'];
            if (! $cached) {
                $status = $status === 'unhealthy' ? $status : 'degraded';
            }
        } catch (\Exception $e) {
            $status = $status === 'unhealthy' ? $status : 'degraded';
            $checks['cache'] = ['status' => 'failed'];
            Log::error('Health Check Cache Failure', ['exception' => $e->getMessage()]);

            if ($verbose) {
                $checks['cache']['error'] = $e->getMessage();
            }
        }

        if ($verbose) {
            $checks['cache']['store'] = config('cache.default');
        }

        // 3. Storage Write Permission Check
        $storageWritable = is_writable(storage_path('framework/views')) && is_writable(storage_path('logs'));
        $checks['storage'] = ['status' => $storageWritable ? 'ok' : 'permission_denied'];
        if (! $storageWritable) {
            $status = $status === 'unhealthy' ? $status : 'degraded';
        }

        $payload = [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];

        if ($verbose) {
            $payload['app'] = config('app.name', 'Racunin');
            $payload['environment'] = config('app.env');
            $payload['php_version'] = PHP_VERSION;
            $payload['laravel_version'] = app()->version();
            $payload['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        return response()->json($payload, $status === 'unhealthy' ? 503 : 200);
    }
}
