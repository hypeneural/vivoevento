<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

it('returns a lightweight liveness payload', function () {
    $response = $this->getJson('/health/live');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.environment', 'testing')
        ->assertHeader('X-Request-Id')
        ->assertHeader('X-Trace-Id');
});

it('returns readiness data when dependencies are healthy', function () {
    $storageRoot = storage_path('framework/testing/health-ready');

    if (! is_dir($storageRoot)) {
        mkdir($storageRoot, 0777, true);
    }

    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $storageRoot);
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.connection', 'default');
    config()->set('queue.connections.redis.retry_after', 240);
    config()->set('queue.connections.redis.block_for', 5);
    config()->set('queue.connections.redis.after_commit', true);

    DB::shouldReceive('connection->getPdo')->once()->andReturn(new stdClass());
    Redis::shouldReceive('connection->ping')->once()->andReturn('PONG');

    $response = $this->getJson('/health/ready');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.checks.database.ok', true)
        ->assertJsonPath('data.checks.redis.ok', true)
        ->assertJsonPath('data.checks.storage.ok', true)
        ->assertJsonPath('data.checks.queue.ok', true)
        ->assertJsonPath('data.checks.queue.retry_after_scope', 'connection');
});

it('returns service unavailable when a readiness dependency fails', function () {
    $storageRoot = storage_path('framework/testing/health-degraded');

    if (! is_dir($storageRoot)) {
        mkdir($storageRoot, 0777, true);
    }

    config()->set('filesystems.default', 'local');
    config()->set('filesystems.disks.local.root', $storageRoot);
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.connection', 'default');

    DB::shouldReceive('connection->getPdo')->once()->andReturn(new stdClass());
    Redis::shouldReceive('connection->ping')->once()->andThrow(new RuntimeException('redis down'));

    $response = $this->getJson('/health/ready');

    $response->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.checks.redis.ok', false);
});

it('propagates incoming request context to response headers and meta', function () {
    $response = $this->withHeaders([
        'X-Request-Id' => 'req_external_123',
        'X-Trace-Id' => 'trace_external_456',
    ])->getJson('/health/live');

    $response->assertOk()
        ->assertHeader('X-Request-Id', 'req_external_123')
        ->assertHeader('X-Trace-Id', 'trace_external_456')
        ->assertJsonPath('meta.request_id', 'req_external_123');
});
