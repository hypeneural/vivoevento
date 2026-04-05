<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    public function live(): array
    {
        return [
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function ready(): array
    {
        $checks = [
            'database' => $this->database(),
            'redis' => $this->redis(),
            'storage' => $this->storage(),
            'queue' => $this->queue(),
        ];

        $isReady = collect($checks)->every(fn (array $check): bool => $check['ok'] === true);

        return [
            'status' => $isReady ? 'ready' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    private function database(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'ok' => true,
                'connection' => config('database.default'),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'connection' => config('database.default'),
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function redis(): array
    {
        $queueConnection = config('queue.connections.' . config('queue.default'), []);
        $queueDriver = $queueConnection['driver'] ?? null;
        $redisConnection = $queueDriver === 'redis'
            ? ($queueConnection['connection'] ?? 'default')
            : 'default';

        try {
            Redis::connection($redisConnection)->ping();

            return [
                'ok' => true,
                'connection' => $redisConnection,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'connection' => $redisConnection,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function storage(): array
    {
        $disk = (string) config('filesystems.default', 'local');
        $diskConfig = config("filesystems.disks.{$disk}", []);
        $driver = $diskConfig['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            return [
                'ok' => false,
                'disk' => $disk,
                'error' => 'Storage disk is not configured.',
            ];
        }

        if ($driver === 'local') {
            $root = $diskConfig['root'] ?? null;
            $ok = is_string($root) && is_dir($root) && is_readable($root) && is_writable($root);

            return [
                'ok' => $ok,
                'disk' => $disk,
                'driver' => $driver,
                'root' => $root,
                'error' => $ok ? null : 'Local storage root is missing or not writable.',
            ];
        }

        try {
            Storage::disk($disk)->exists('.healthcheck/probe');

            return [
                'ok' => true,
                'disk' => $disk,
                'driver' => $driver,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'disk' => $disk,
                'driver' => $driver,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function queue(): array
    {
        $connectionName = (string) config('queue.default', 'sync');
        $connection = config("queue.connections.{$connectionName}", []);
        $driver = $connection['driver'] ?? null;
        $ok = is_string($driver) && $driver !== '';

        return [
            'ok' => $ok,
            'connection' => $connectionName,
            'driver' => $driver,
            'retry_after' => $connection['retry_after'] ?? null,
            'block_for' => $connection['block_for'] ?? null,
            'after_commit' => $connection['after_commit'] ?? null,
            'retry_after_scope' => $driver === 'redis'
                ? 'connection'
                : 'driver-specific',
            'error' => $ok ? null : 'Queue connection is not configured.',
        ];
    }
}
