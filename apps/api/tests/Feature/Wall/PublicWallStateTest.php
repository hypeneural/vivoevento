<?php

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Enums\WallStatus;
use App\Modules\Wall\Models\EventWallSetting;

it('returns disabled in the public state endpoint when the wall is not enabled', function () {
    $domainEvent = Event::factory()->active()->create();

    $settings = EventWallSetting::factory()->create([
        'event_id' => $domainEvent->id,
        'is_enabled' => false,
        'status' => WallStatus::Stopped->value,
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/state");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.is_live', false)
        ->assertJsonPath('data.wall_code', $settings->wall_code);
});
