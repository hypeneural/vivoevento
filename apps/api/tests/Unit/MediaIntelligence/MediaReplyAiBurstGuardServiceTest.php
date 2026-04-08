<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaIntelligence\Services\MediaReplyAiBurstGuardService;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\RateLimiter;

it('returns null when the ai reply rate limit is disabled', function () {
    $event = Event::factory()->create();

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $inbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-disabled-1',
        'provider_message_id' => 'wamid-disabled-1',
        'message_type' => 'image',
        'sender_phone' => '554899991111',
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_direct',
                'sender_external_id' => '554899991111',
            ],
        ],
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
    ]);

    expect(app(MediaReplyAiBurstGuardService::class)->consume($media))->toBeNull();
});

it('blocks attempts above the configured ai reply window for the same sender and event', function () {
    $event = Event::factory()->create();

    MediaIntelligenceGlobalSetting::query()->updateOrCreate(
        ['id' => 1],
        [
            'reply_text_prompt' => MediaIntelligenceGlobalSetting::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [],
            'reply_ai_rate_limit_enabled' => true,
            'reply_ai_rate_limit_max_messages' => 1,
            'reply_ai_rate_limit_window_minutes' => 10,
        ],
    );

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'reply_text_mode' => 'ai',
        'reply_text_enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $firstInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-burst-1',
        'provider_message_id' => 'wamid-burst-1',
        'message_type' => 'image',
        'sender_phone' => '554899992222',
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_direct',
                'sender_external_id' => '554899992222',
            ],
        ],
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $secondInbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-burst-2',
        'provider_message_id' => 'wamid-burst-2',
        'message_type' => 'image',
        'sender_phone' => '554899992222',
        'normalized_payload_json' => [
            '_event_context' => [
                'intake_source' => 'whatsapp_direct',
                'sender_external_id' => '554899992222',
            ],
        ],
        'status' => 'processed',
        'received_at' => now(),
    ]);

    $firstMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $firstInbound->id,
    ]);
    $secondMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $secondInbound->id,
    ]);

    $guard = app(MediaReplyAiBurstGuardService::class);
    $firstAttempt = $guard->consume($firstMedia);
    $secondAttempt = $guard->consume($secondMedia);

    expect($firstAttempt)->not->toBeNull()
        ->and($firstAttempt['allowed'])->toBeTrue()
        ->and($firstAttempt['remaining'])->toBe(0);

    expect($secondAttempt)->not->toBeNull()
        ->and($secondAttempt['allowed'])->toBeFalse()
        ->and($secondAttempt['available_in_seconds'])->toBeGreaterThan(0)
        ->and($secondAttempt['sender_key'])->toBe('554899992222');

    RateLimiter::clear($secondAttempt['limiter_key']);
});
