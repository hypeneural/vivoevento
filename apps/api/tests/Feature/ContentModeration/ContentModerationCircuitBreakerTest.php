<?php

use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Models\MediaProcessingRun;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('opens the content moderation circuit after repeated failures and keeps processing healthy media', function () {
    Queue::fake();

    config()->set('content_moderation.providers.openai.api_key', 'test-key');
    config()->set('content_moderation.providers.openai.circuit_breaker.failure_threshold', 2);
    config()->set('content_moderation.providers.openai.circuit_breaker.open_seconds', 60);

    $requests = 0;

    Http::fake(function () use (&$requests) {
        $requests++;

        throw new ConnectionException('timeout');
    });

    $event = Event::factory()->active()->create([
        'moderation_mode' => 'ai',
    ]);

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'enabled' => true,
        'fallback_mode' => 'review',
    ]);

    $mediaItems = collect(range(1, 3))->map(function (int $index) use ($event) {
        $media = EventMedia::factory()->create([
            'event_id' => $event->id,
            'safety_status' => 'queued',
        ]);

        EventMediaVariant::query()->create([
            'event_media_id' => $media->id,
            'variant_key' => 'fast_preview',
            'disk' => 'public',
            'path' => "events/{$event->id}/variants/{$media->id}/fast_preview-{$index}.webp",
            'mime_type' => 'image/webp',
            'width' => 512,
            'height' => 512,
            'size_bytes' => 2048,
        ]);

        return $media;
    });

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $mediaItems[0]->id])->handle();
    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $mediaItems[1]->id])->handle();
    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $mediaItems[2]->id])->handle();

    expect($requests)->toBe(2);

    $thirdRun = MediaProcessingRun::query()
        ->where('event_media_id', $mediaItems[2]->id)
        ->where('stage_key', 'safety')
        ->latest('id')
        ->first();

    expect($mediaItems[2]->fresh()->safety_status)->toBe('failed')
        ->and($thirdRun?->failure_class)->toBe('transient');

    $healthyEvent = Event::factory()->active()->create([
        'moderation_mode' => 'none',
    ]);

    $healthyMedia = EventMedia::factory()->create([
        'event_id' => $healthyEvent->id,
        'safety_status' => 'queued',
    ]);

    app(AnalyzeContentSafetyJob::class, ['eventMediaId' => $healthyMedia->id])->handle();

    expect($healthyMedia->fresh()->safety_status)->toBe('skipped');
});
