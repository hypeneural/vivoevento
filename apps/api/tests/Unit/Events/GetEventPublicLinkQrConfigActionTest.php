<?php

use App\Modules\Events\Actions\GetEventPublicLinkQrConfigAction;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Models\EventPublicLinkQrConfig;
use App\Modules\Events\Support\EventPublicLinksService;

it('always resolves qr_value from the current event links instead of the saved JSON payload', function () {
    $event = Event::factory()->active()->create([
        'slug' => 'evento-antigo',
        'upload_slug' => 'upload-antigo',
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    EventPublicLinkQrConfig::factory()->create([
        'event_id' => $event->id,
        'link_key' => 'gallery',
        'config_json' => [
            'style' => [
                'dots' => [
                    'color' => '#123456',
                ],
            ],
            'link' => [
                'qr_value' => 'https://stale.example.com/should-not-win',
            ],
        ],
    ]);

    app(EventPublicLinksService::class)->updateIdentifiers($event, [
        'slug' => 'evento-novo',
    ]);

    $payload = app(GetEventPublicLinkQrConfigAction::class)->execute($event->fresh(), 'gallery');

    expect($payload['config']['style']['dots']['color'])->toBe('#123456')
        ->and($payload['link']['qr_value'])->toContain('/e/evento-novo')
        ->and($payload['link']['qr_value'])->not->toBe('https://stale.example.com/should-not-win');
});
