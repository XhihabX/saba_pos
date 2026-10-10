<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HealthController extends Controller
{
    public function check(Request $request)
    {
        $startTime = microtime(true);
        $status = 200;
        $checks = [];

        // 1. Database Check
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbTimeMs = round((microtime(true) - $dbStart) * 1000, 2);
            $checks['database'] = [
                'status' => 'healthy',
                'connection' => config('database.default'),
                'latency_ms' => $dbTimeMs,
            ];
        } catch (\Throwable $e) {
            $status = 503;
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // 2. Cache Check
        try {
            $cacheKey = 'health_check_' . time();
            Cache::put($cacheKey, 'ok', 10);
            $cacheVal = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            $checks['cache'] = [
                'status' => ($cacheVal === 'ok') ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            $status = 503;
            $checks['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // 3. Queue Configuration Check
        $queueDriver = config('queue.default');
        $isProduction = config('app.env') === 'production';
        $isSyncInProd = $isProduction && $queueDriver === 'sync';
        $checks['queue'] = [
            'status' => $isSyncInProd ? 'warning' : (in_array($queueDriver, ['database', 'redis', 'sqs']) ? 'healthy' : 'warning'),
            'driver' => $queueDriver,
            'note' => $isSyncInProd
                ? 'CRITICAL WARNING: QUEUE_CONNECTION=sync in production environment! Set QUEUE_CONNECTION=database in .env'
                : (($queueDriver === 'sync') ? 'Sync queue connection in use. Use database or redis in production.' : 'Asynchronous production queue driver active.'),
        ];

        // 4. Storage Write Permission Check
        $storagePaths = [
            'storage_logs' => storage_path('logs'),
            'storage_framework' => storage_path('framework'),
        ];

        $storageWritable = true;
        foreach ($storagePaths as $key => $path) {
            if (!File::exists($path) || !is_writable($path)) {
                $storageWritable = false;
                $checks[$key] = ['status' => 'unhealthy', 'path' => $path];
            } else {
                $checks[$key] = ['status' => 'healthy', 'path' => $path];
            }
        }
        if (!$storageWritable) {
            $status = 503;
        }

        // 5. System Metrics
        $totalTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => ($status === 200) ? 'OK' : 'DEGRADED',
            'timestamp' => date('c'),
            'environment' => config('app.env'),
            'php_version' => PHP_VERSION,
            'response_time_ms' => $totalTimeMs,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'checks' => $checks,
        ], $status);
    }
}
