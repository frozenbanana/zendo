<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkCache(): bool
    {
        try {
            cache()->put('health:check', true, 10);
            $result = cache()->get('health:check');

            return $result === true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkQueue(): bool
    {
        try {
            $connection = config('queue.default');

            if ($connection === 'sync') {
                return true;
            }

            if ($connection === 'redis') {
                Redis::ping();

                return true;
            }

            if ($connection === 'database') {
                DB::connection()->getPdo();

                return true;
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
