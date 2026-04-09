<?php

use App\Modules\ContentModeration\Models\EventMediaSafetyEvaluation;
use App\Modules\ContentModeration\Models\EventContentModerationSetting;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaIntelligence\Services\ContextualModerationPresetCatalog;
use App\Modules\MediaIntelligence\Services\MediaOperationalHistorySummaryService;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;

it('summarizes operational history using the evaluation reason and policy metadata', function () {
    $event = Event::factory()->aiModeration()->create();

    $media = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'safety_status' => 'pass',
        'vlm_status' => 'completed',
    ]);

    EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'reason' => 'A imagem combina com o contexto do evento.',
        'input_scope_used' => 'image_and_text_context',
        'normalized_text_context' => 'Legenda do convidado',
        'policy_sources_json' => [
            'allow_alcohol' => 'preset',
        ],
        'prompt_context_json' => [
            'preset_name' => 'Casamento romantico',
            'normalized_text_context' => 'Legenda do convidado',
        ],
    ]);

    $summary = app(MediaOperationalHistorySummaryService::class)->summarize($media->fresh());

    expect($summary['effective_media_state'])->toBe('published')
        ->and($summary['human_reason'])->toBe('A imagem combina com o contexto do evento.')
        ->and($summary['policy_label'])->toBe('Casamento romantico')
        ->and($summary['policy_inheritance_mode'])->toBe('preset')
        ->and($summary['text_context_summary'])->toBe('A decisao usou imagem + texto normalizado.');
});

it('summarizes blocking reasons when safety rejects the media without explicit human reason', function () {
    $event = Event::factory()->aiModeration()->create();

    EventContentModerationSetting::factory()->create([
        'event_id' => $event->id,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'enabled' => true,
    ]);

    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
        'safety_status' => 'block',
        'vlm_status' => 'skipped',
        'moderation_status' => 'pending',
        'publication_status' => 'draft',
    ]);

    EventMediaSafetyEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'decision' => 'block',
        'blocked' => true,
        'reason_codes_json' => ['safety.nudity'],
    ]);

    $summary = (new MediaOperationalHistorySummaryService(
        new MediaEffectiveStateResolver(),
        new ContextualModerationPresetCatalog(),
    ))->summarize($media->fresh());

    expect($summary['effective_media_state'])->toBe('rejected')
        ->and($summary['human_reason'])->toBe('Bloqueada pela Safety objetiva: safety.nudity.')
        ->and($summary['safety_decision'])->toBe('rejected');
});
