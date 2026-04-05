<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventMediaVlmEvaluationFactory extends Factory
{
    protected $model = EventMediaVlmEvaluation::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'event_media_id' => EventMediaFactory::new(),
            'provider_key' => 'vllm',
            'provider_version' => 'vllm-openai-v1',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'model_snapshot' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'prompt_version' => 'foundation-v1',
            'response_schema_version' => 'foundation-v1',
            'mode_applied' => 'enrich_only',
            'decision' => 'approve',
            'review_required' => false,
            'reason' => 'Conteudo compativel com o evento.',
            'short_caption' => 'Memorias do evento.',
            'tags_json' => ['celebracao', 'retrato'],
            'raw_response_json' => [
                'provider' => 'vllm',
            ],
            'tokens_input' => 120,
            'tokens_output' => 42,
            'completed_at' => now(),
        ];
    }
}
