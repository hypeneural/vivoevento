<?php

use App\Modules\ContentModeration\Actions\UpsertEventContentModerationSettingsAction;
use App\Modules\Events\Models\Event;

it('normalizes thresholds and persists event content moderation settings', function () {
    $event = Event::factory()->create();

    $settings = app(UpsertEventContentModerationSettingsAction::class)->execute($event, [
        'enabled' => true,
        'provider_key' => 'openai',
        'threshold_version' => 'policy-v2',
        'fallback_mode' => 'review',
        'hard_block_thresholds' => [
            'nudity' => 1.7,
            'violence' => 0.91,
            'self_harm' => -1,
        ],
        'review_thresholds' => [
            'nudity' => 0.63,
            'violence' => 0.58,
            'self_harm' => 0.61,
        ],
    ]);

    expect($settings->enabled)->toBeTrue()
        ->and($settings->provider_key)->toBe('openai')
        ->and($settings->threshold_version)->toBe('policy-v2')
        ->and((float) $settings->hard_block_thresholds_json['nudity'])->toBe(1.0)
        ->and((float) $settings->hard_block_thresholds_json['self_harm'])->toBe(0.0)
        ->and($settings->review_thresholds_json['violence'])->toBe(0.58);
});
