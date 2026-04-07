<?php

use App\Modules\Events\Models\Event;

it('defaults package-less manual override events to 30 retention days when retention is omitted, while photo limit remains undefined', function () {
    [$admin] = $this->actingAsSuperAdmin();

    $response = $this->apiPost('/admin/quick-events', [
        'responsible_name' => 'Bianca Lima',
        'whatsapp' => '(48) 99933-1122',
        'email' => 'bianca@example.com',
        'organization_name' => 'Bianca Eventos',
        'organization_type' => 'direct_customer',
        'event' => [
            'title' => 'Evento Bianca Default',
            'event_type' => 'birthday',
        ],
        'grant' => [
            'source_type' => 'manual_override',
            'reason' => 'Homologacao sem limites explicitos',
            'features' => [
                'wall' => ['enabled' => true],
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $event = Event::query()->findOrFail($response->json('data.event.id'));

    expect($event->retention_days)->toBe(30)
        ->and(data_get($event->current_entitlements_json, 'limits.retention_days'))->toBe(30)
        ->and(data_get($event->current_entitlements_json, 'limits.max_photos'))->toBeNull();
});
