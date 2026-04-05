<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaPipelineTelemetryService;
use Carbon\Carbon;

it('builds inbound and upload publish latency metrics for telemetry', function () {
    $base = Carbon::parse('2026-04-04 10:00:00');

    $event = Event::factory()->active()->create();

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-latency-1',
        'message_type' => 'image',
        'status' => 'received',
        'received_at' => $base,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'created_at' => $base->copy()->addSeconds(12),
        'published_at' => $base->copy()->addSeconds(42),
        'source_type' => 'channel',
        'source_label' => 'WhatsApp',
    ]);

    $payload = app(MediaPipelineTelemetryService::class)->publishPayload(
        $media->fresh(['inboundMessage'])
    );

    expect($payload['event_id'])->toBe($event->id)
        ->and($payload['event_media_id'])->toBe($media->id)
        ->and($payload['inbound_message_id'])->toBe($inboundMessage->id)
        ->and($payload['source_type'])->toBe('channel')
        ->and($payload['source_label'])->toBe('WhatsApp')
        ->and($payload['upload_to_publish_seconds'])->toBe(30)
        ->and($payload['inbound_to_publish_seconds'])->toBe(42);
});
