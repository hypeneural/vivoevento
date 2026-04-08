<?php

use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Models\EventMedia;
use Carbon\CarbonInterface;

it('casts media intelligence settings and evaluation attributes correctly', function () {
    $event = Event::factory()->create();
    $media = EventMedia::factory()->create([
        'event_id' => $event->id,
    ]);

    $settings = EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'require_json_output' => true,
        'timeout_ms' => 11000,
        'reply_fixed_templates_json' => ['Memorias que fazem o coracao sorrir! 🎉📸'],
    ]);

    $evaluation = EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $media->id,
        'tags_json' => ['celebracao', 'retrato'],
        'raw_response_json' => ['provider' => 'vllm'],
        'request_payload_json' => ['model' => 'openai/gpt-4.1-mini'],
        'prompt_context_json' => [
            'template' => 'Use {nome_do_evento}.',
            'variables' => ['nome_do_evento' => 'Evento Teste'],
            'resolved' => 'Use Evento Teste.',
        ],
    ]);

    expect($settings->enabled)->toBeTrue()
        ->and($settings->require_json_output)->toBeTrue()
        ->and($settings->timeout_ms)->toBeInt()
        ->and($settings->reply_fixed_templates_json)->toBeArray()
        ->and($evaluation->review_required)->toBeBool()
        ->and($evaluation->tags_json)->toBeArray()
        ->and($evaluation->raw_response_json)->toBeArray()
        ->and($evaluation->request_payload_json)->toBeArray()
        ->and($evaluation->prompt_context_json)->toBeArray()
        ->and($evaluation->completed_at)->toBeInstanceOf(CarbonInterface::class);
});
