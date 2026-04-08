<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;

it('marks a media item as rejected when a blocking vlm gate rejected it even if it was published before', function () {
    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->gate()->create([
        'event_id' => $event->id,
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'safety_status' => 'pass',
        'vlm_status' => 'rejected',
    ]);

    $state = app(MediaEffectiveStateResolver::class)->resolve($media->fresh(['event.contentModerationSettings', 'event.mediaIntelligenceSettings']));

    expect($state['effective_media_state'])->toBe('rejected')
        ->and($state['safety_decision'])->toBe('approved')
        ->and($state['context_decision'])->toBe('rejected')
        ->and($state['context_is_blocking'])->toBeTrue()
        ->and($state['publication_decision'])->toBe('published');
});

it('keeps safety findings as non-blocking when content moderation is observe only', function () {
    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventContentModerationSettingFactory::new()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'observe_only',
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'safety_status' => 'block',
        'vlm_status' => 'skipped',
    ]);

    $state = app(MediaEffectiveStateResolver::class)->resolve($media->fresh(['event.contentModerationSettings', 'event.mediaIntelligenceSettings']));

    expect($state['effective_media_state'])->toBe('approved')
        ->and($state['safety_decision'])->toBe('rejected')
        ->and($state['safety_is_blocking'])->toBeFalse();
});

it('keeps enrich only vlm findings as non-blocking for the effective state', function () {
    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    \Database\Factories\EventMediaIntelligenceSettingFactory::new()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'mode' => 'enrich_only',
    ]);

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'safety_status' => 'pass',
        'vlm_status' => 'rejected',
    ]);

    $state = app(MediaEffectiveStateResolver::class)->resolve($media->fresh(['event.contentModerationSettings', 'event.mediaIntelligenceSettings']));

    expect($state['effective_media_state'])->toBe('published')
        ->and($state['context_decision'])->toBe('rejected')
        ->and($state['context_is_blocking'])->toBeFalse();
});
