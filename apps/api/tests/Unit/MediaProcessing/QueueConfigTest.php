<?php

it('uses redis queue defaults tuned for production throughput', function () {
    expect(config('queue.connections.redis.retry_after'))->toBe(240)
        ->and(config('queue.connections.redis.block_for'))->toBe(5)
        ->and(config('queue.connections.redis.after_commit'))->toBeTrue();
});
