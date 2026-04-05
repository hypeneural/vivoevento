<?php

it('configures a dedicated fast lane in horizon', function () {
    expect(config('horizon.waits.redis:media-fast'))->toBe(45)
        ->and(config('horizon.defaults.supervisor-media-fast.queue'))->toBe(['media-fast'])
        ->and(config('horizon.defaults.supervisor-media-fast.balance'))->toBeFalse()
        ->and(config('horizon.defaults.supervisor-media-fast.timeout'))->toBe(120)
        ->and(config('horizon.defaults.supervisor-media-fast.maxTime'))->toBe(1800)
        ->and(config('horizon.defaults.supervisor-media-fast.maxJobs'))->toBe(1000)
        ->and(config('horizon.environments.production.supervisor-media-fast.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.local.supervisor-media-fast.maxProcesses'))->toBe(1);
});
