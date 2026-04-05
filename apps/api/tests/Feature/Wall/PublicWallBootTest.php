<?php

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\Wall\Models\EventWallSetting;
use Illuminate\Support\Facades\Storage;

it('returns the public wall boot payload with media settings and sender identity', function () {
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'title' => 'Evento Wall',
    ]);

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
        'interval_ms' => 9000,
        'queue_limit' => 12,
        'selection_mode' => 'custom',
        'event_phase' => 'party',
        'selection_policy' => [
            'max_eligible_items_per_sender' => 4,
            'max_replays_per_item' => 2,
            'low_volume_max_items' => 5,
            'medium_volume_max_items' => 10,
            'replay_interval_low_minutes' => 6,
            'replay_interval_medium_minutes' => 11,
            'replay_interval_high_minutes' => 18,
            'sender_cooldown_seconds' => 60,
            'sender_window_limit' => 3,
            'sender_window_minutes' => 10,
            'avoid_same_sender_if_alternative_exists' => true,
            'avoid_same_duplicate_cluster_if_alternative_exists' => true,
        ],
        'show_qr' => false,
        'instructions_text' => 'Envie sua foto',
    ]);

    $message = InboundMessage::query()->create([
        'event_id' => $domainEvent->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-123',
        'message_type' => 'image',
        'sender_phone' => '5511999999999',
        'sender_name' => 'Maria',
        'status' => 'received',
        'received_at' => now(),
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'inbound_message_id' => $message->id,
        'source_type' => 'whatsapp',
        'caption' => 'Foto principal',
        'duplicate_group_key' => 'dup-group-001',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'wall',
        'disk' => 'public',
        'path' => "wall/{$media->id}.jpg",
        'width' => 1920,
        'height' => 1080,
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/boot");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.event.wall_code', $settings->wall_code)
        ->assertJsonPath('data.event.status', 'live')
        ->assertJsonPath('data.settings.interval_ms', 8000)
        ->assertJsonPath('data.settings.queue_limit', 12)
        ->assertJsonPath('data.settings.selection_mode', 'custom')
        ->assertJsonPath('data.settings.event_phase', 'party')
        ->assertJsonPath('data.settings.selection_policy.max_eligible_items_per_sender', 5)
        ->assertJsonPath('data.settings.selection_policy.max_replays_per_item', 3)
        ->assertJsonPath('data.settings.selection_policy.low_volume_max_items', 5)
        ->assertJsonPath('data.settings.selection_policy.medium_volume_max_items', 10)
        ->assertJsonPath('data.settings.selection_policy.replay_interval_low_minutes', 4)
        ->assertJsonPath('data.settings.selection_policy.sender_cooldown_seconds', 45)
        ->assertJsonPath('data.settings.show_qr', false)
        ->assertJsonPath('data.settings.instructions_text', 'Envie sua foto')
        ->assertJsonPath('data.files.0.id', 'media_'.$media->id)
        ->assertJsonPath('data.files.0.sender_name', 'Maria')
        ->assertJsonPath('data.files.0.sender_key', 'whatsapp:5511999999999')
        ->assertJsonPath('data.files.0.source_type', 'whatsapp')
        ->assertJsonPath('data.files.0.duplicate_cluster_key', 'dup-group-001')
        ->assertJsonPath('data.files.0.caption', 'Foto principal');
});
