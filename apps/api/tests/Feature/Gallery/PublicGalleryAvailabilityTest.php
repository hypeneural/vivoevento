<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;

it('blocks public gallery access when the live module is disabled', function () {
    $event = Event::factory()->active()->create();

    EventModule::query()->create(['event_id' => $event->id, 'module_key' => 'live', 'is_enabled' => false]);

    $response = $this->apiGet("/public/events/{$event->slug}/gallery");

    $response->assertStatus(404)
        ->assertJsonPath('success', false);
});
