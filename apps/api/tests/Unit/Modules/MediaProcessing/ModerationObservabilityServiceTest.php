<?php

use App\Modules\MediaProcessing\Services\ModerationObservabilityService;
use Illuminate\Support\Facades\Log;

it('logs moderation feed telemetry through the dedicated observability channel', function () {
    config()->set('observability.moderation_log_channel', 'stack');

    Log::shouldReceive('channel')
        ->once()
        ->with('stack')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $payload): bool {
            expect($message)->toBe('moderation.feed.response');
            expect($payload)->toMatchArray([
                'organization_id' => 25,
                'user_id' => 8,
                'event_id' => 144,
                'cursor_present' => false,
                'per_page' => 24,
                'item_count' => 24,
                'has_more' => true,
                'duration_ms' => 138,
            ]);

            return true;
        });

    app(ModerationObservabilityService::class)->recordFeedResponse([
        'organization_id' => 25,
        'user_id' => 8,
        'event_id' => 144,
        'cursor_present' => false,
        'per_page' => 24,
        'item_count' => 24,
        'has_more' => true,
        'duration_ms' => 138,
    ]);
});
