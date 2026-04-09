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
        'original_filename' => 'original-photo.jpg',
        'width' => 1080,
        'height' => 1920,
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
        ->assertJsonPath('data.settings.video_enabled', true)
        ->assertJsonPath('data.settings.video_playback_mode', 'play_to_end_if_short_else_cap')
        ->assertJsonPath('data.settings.video_max_seconds', 30)
        ->assertJsonPath('data.settings.video_resume_mode', 'resume_if_same_item_else_restart')
        ->assertJsonPath('data.settings.video_audio_policy', 'muted')
        ->assertJsonPath('data.settings.video_multi_layout_policy', 'disallow')
        ->assertJsonPath('data.settings.video_preferred_variant', 'wall_video_720p')
        ->assertJsonPath('data.settings.instructions_text', 'Envie sua foto')
        ->assertJsonPath('data.files.0.id', 'media_'.$media->id)
        ->assertJsonPath('data.files.0.sender_name', 'Maria')
        ->assertJsonPath('data.files.0.sender_key', 'whatsapp:5511999999999')
        ->assertJsonPath('data.files.0.source_type', 'whatsapp')
        ->assertJsonPath('data.files.0.duplicate_cluster_key', 'dup-group-001')
        ->assertJsonPath('data.files.0.caption', 'Foto principal')
        ->assertJsonPath('data.files.0.url', rtrim((string) config('app.url'), '/')."/storage/wall/{$media->id}.jpg")
        ->assertJsonPath('data.files.0.original_url', rtrim((string) config('app.url'), '/')."/storage/events/{$domainEvent->id}/originals/original-photo.jpg")
        ->assertJsonPath('data.files.0.width', 1080)
        ->assertJsonPath('data.files.0.height', 1920)
        ->assertJsonPath('data.files.0.orientation', 'vertical');
});

it('excludes orientation-mismatched media from the public boot payload', function () {
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'title' => 'Evento Wall Landscape',
    ]);

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
        'accepted_orientation' => 'landscape',
    ]);

    $portraitMedia = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'original_filename' => 'portrait-video.mp4',
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'width' => 1080,
        'height' => 1920,
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $portraitMedia->id,
        'variant_key' => 'wall',
        'disk' => 'public',
        'path' => "wall/{$portraitMedia->id}.mp4",
        'width' => 1080,
        'height' => 1920,
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/boot");

    $this->assertApiSuccess($response);

    $response->assertJsonCount(0, 'data.files')
        ->assertJsonPath('data.settings.accepted_orientation', 'landscape');
});

it('includes explicit video admission metadata in the public wall boot payload when original fallback is explicitly enabled', function () {
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'title' => 'Evento Wall Video',
    ]);

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
        'video_preferred_variant' => 'original',
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'video-entrada.mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 20,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 880000,
        'container' => 'mp4',
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/boot");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.files.0.type', 'video')
        ->assertJsonPath('data.files.0.duration_seconds', 20)
        ->assertJsonPath('data.files.0.has_audio', true)
        ->assertJsonPath('data.files.0.video_codec', 'h264')
        ->assertJsonPath('data.files.0.audio_codec', 'aac')
        ->assertJsonPath('data.files.0.bitrate', 880000)
        ->assertJsonPath('data.files.0.container', 'mp4')
        ->assertJsonPath('data.files.0.video_admission.state', 'eligible_with_fallback')
        ->assertJsonPath('data.files.0.video_admission.reasons.0', 'poster_missing')
        ->assertJsonPath('data.files.0.video_admission.asset_source', 'original')
        ->assertJsonPath('data.files.0.video_admission.preferred_variant_key', 'original')
        ->assertJsonPath('data.files.0.video_admission.duration_limit_seconds', 30)
        ->assertJsonPath('data.files.0.served_variant_key', null)
        ->assertJsonPath('data.files.0.preview_variant_key', null);
});

it('excludes original-only videos from the public wall boot payload when strict wall video gate is enabled', function () {
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'title' => 'Evento Wall Video Estrito',
    ]);

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'video-original.mp4',
        'width' => 1920,
        'height' => 1080,
        'duration_seconds' => 20,
        'video_codec' => 'h264',
        'container' => 'mp4',
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/boot");

    $this->assertApiSuccess($response);
    $response->assertJsonCount(0, 'data.files');
});

it('prefers wall video variants and poster in the public wall boot payload when available', function () {
    Storage::fake('public');

    $domainEvent = Event::factory()->active()->create([
        'title' => 'Evento Wall Video Processado',
    ]);

    EventModule::query()->create([
        'event_id' => $domainEvent->id,
        'module_key' => 'wall',
        'is_enabled' => true,
    ]);

    $settings = EventWallSetting::factory()->live()->create([
        'event_id' => $domainEvent->id,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $domainEvent->id,
        'media_type' => 'video',
        'mime_type' => 'video/mp4',
        'original_filename' => 'video-processado.mp4',
        'width' => 1280,
        'height' => 720,
        'duration_seconds' => 18,
        'has_audio' => true,
        'video_codec' => 'h264',
        'audio_codec' => 'aac',
        'bitrate' => 820000,
        'container' => 'mp4',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'wall_video_720p',
        'disk' => 'public',
        'path' => "events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4",
        'width' => 1280,
        'height' => 720,
        'mime_type' => 'video/mp4',
    ]);

    EventMediaVariant::query()->create([
        'event_media_id' => $media->id,
        'variant_key' => 'wall_video_poster',
        'disk' => 'public',
        'path' => "events/{$domainEvent->id}/variants/{$media->id}/wall_video_poster.jpg",
        'width' => 1280,
        'height' => 720,
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->apiGet("/public/wall/{$settings->wall_code}/boot");

    $this->assertApiSuccess($response);

    $response->assertJsonPath(
        'data.files.0.url',
        rtrim((string) config('app.url'), '/')."/storage/events/{$domainEvent->id}/variants/{$media->id}/wall_video_720p.mp4"
    )->assertJsonPath(
        'data.files.0.preview_url',
        rtrim((string) config('app.url'), '/')."/storage/events/{$domainEvent->id}/variants/{$media->id}/wall_video_poster.jpg"
    )->assertJsonPath(
        'data.files.0.served_variant_key',
        'wall_video_720p'
    )->assertJsonPath(
        'data.files.0.preview_variant_key',
        'wall_video_poster'
    )->assertJsonPath('data.files.0.video_admission.state', 'eligible')
        ->assertJsonCount(0, 'data.files.0.video_admission.reasons')
        ->assertJsonPath('data.files.0.video_admission.asset_source', 'wall_variant')
        ->assertJsonPath('data.files.0.video_admission.preferred_variant_key', 'wall_video_720p')
        ->assertJsonPath('data.files.0.video_admission.poster_variant_key', 'wall_video_poster');
});
