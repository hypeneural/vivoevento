<?php

use Illuminate\Support\Facades\Artisan;

it('documents testing overrides and production-oriented config fallbacks', function () {
    $cacheConfig = file_get_contents(config_path('cache.php'));
    $queueConfig = file_get_contents(config_path('queue.php'));

    expect(app()->environment())->toBe('testing')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and($cacheConfig)->toContain("'default' => env('CACHE_STORE', 'database')")
        ->and($queueConfig)->toContain("'default' => env('QUEUE_CONNECTION', 'database')")
        ->and(config('cache.stores.redis.connection'))->toBe('cache')
        ->and(config('queue.connections.redis.connection'))->toBe('default');
});

it('keeps cache and default redis connections logically separated', function () {
    expect(config('database.redis.default.database'))->not->toBe(config('database.redis.cache.database'))
        ->and(config('database.redis.default.database'))->toBe('0')
        ->and(config('database.redis.cache.database'))->toBe('1');
});

it('schedules horizon snapshots and queue monitors for operational telemetry', function () {
    Artisan::call('schedule:list', ['--timezone' => 'America/Sao_Paulo']);

    $output = Artisan::output();

    expect($output)->toContain('php artisan horizon:snapshot')
        ->toContain('php artisan queue:monitor redis:webhooks --max=25')
        ->toContain('php artisan queue:monitor redis:media-variants --max=50')
        ->toContain('php artisan queue:monitor redis:media-audit --max=50')
        ->toContain('php artisan queue:monitor redis:media-publish --max=25')
        ->toContain('php artisan queue:monitor redis:broadcasts --max=50');
});
