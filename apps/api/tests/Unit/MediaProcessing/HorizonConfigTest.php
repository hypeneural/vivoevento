<?php

it('configures dedicated variants and audit lanes in horizon', function () {
    expect(config('horizon.waits.redis:media-variants'))->toBe(45)
        ->and(config('horizon.defaults.supervisor-media-variants.queue'))->toBe(['media-variants'])
        ->and(config('horizon.defaults.supervisor-media-variants.balance'))->toBeFalse()
        ->and(config('horizon.defaults.supervisor-media-variants.timeout'))->toBe(120)
        ->and(config('horizon.defaults.supervisor-media-variants.maxTime'))->toBe(1800)
        ->and(config('horizon.defaults.supervisor-media-variants.maxJobs'))->toBe(1000)
        ->and(config('horizon.environments.production.supervisor-media-variants.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.local.supervisor-media-variants.maxProcesses'))->toBe(1)
        ->and(config('horizon.waits.redis:media-audit'))->toBe(45)
        ->and(config('horizon.defaults.supervisor-media-audit.queue'))->toBe(['media-audit'])
        ->and(config('horizon.defaults.supervisor-media-audit.balance'))->toBeFalse()
        ->and(config('horizon.defaults.supervisor-media-audit.timeout'))->toBe(60)
        ->and(config('horizon.defaults.supervisor-media-audit.maxJobs'))->toBe(1000)
        ->and(config('horizon.environments.production.supervisor-media-audit.maxProcesses'))->toBe(2)
        ->and(config('horizon.environments.local.supervisor-media-audit.maxProcesses'))->toBe(1);
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
