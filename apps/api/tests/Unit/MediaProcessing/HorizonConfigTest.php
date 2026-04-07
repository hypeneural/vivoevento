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

it('configures dedicated whatsapp supervisors for production webhooks', function () {
    expect(config('horizon.waits.redis:whatsapp-inbound'))->toBe(30)
        ->and(config('horizon.defaults.supervisor-whatsapp-inbound.queue'))->toBe(['whatsapp-inbound'])
        ->and(config('horizon.defaults.supervisor-whatsapp-inbound.balance'))->toBeFalse()
        ->and(config('horizon.defaults.supervisor-whatsapp-inbound.timeout'))->toBe(45)
        ->and(config('horizon.defaults.supervisor-whatsapp-inbound.maxJobs'))->toBe(1500)
        ->and(config('horizon.defaults.supervisor-whatsapp-send.queue'))->toBe(['whatsapp-send'])
        ->and(config('horizon.defaults.supervisor-whatsapp-sync.queue'))->toBe(['whatsapp-sync'])
        ->and(config('horizon.environments.production.supervisor-whatsapp-inbound.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.production.supervisor-whatsapp-sync.maxProcesses'))->toBe(1)
        ->and(config('horizon.environments.local.supervisor-whatsapp-inbound.maxProcesses'))->toBe(1);
});

it('configures a dedicated telegram send supervisor for outbound feedback', function () {
    expect(config('horizon.waits.redis:telegram-send'))->toBe(60)
        ->and(config('horizon.defaults.supervisor-telegram-send.queue'))->toBe(['telegram-send'])
        ->and(config('horizon.defaults.supervisor-telegram-send.balance'))->toBe('auto')
        ->and(config('horizon.defaults.supervisor-telegram-send.timeout'))->toBe(90)
        ->and(config('horizon.defaults.supervisor-telegram-send.maxJobs'))->toBe(800)
        ->and(config('horizon.environments.production.supervisor-telegram-send.minProcesses'))->toBe(1)
        ->and(config('horizon.environments.production.supervisor-telegram-send.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.local.supervisor-telegram-send.maxProcesses'))->toBe(1);
});
