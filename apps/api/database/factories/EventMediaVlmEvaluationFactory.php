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
            'response_schema_version' => 'contextual-v2',
            'mode_applied' => 'enrich_only',
            'decision' => 'approve',
            'review_required' => false,
            'reason' => 'Conteudo compativel com o evento.',
            'reason_code' => 'context.approved',
            'matched_policies_json' => [],
            'matched_exceptions_json' => [],
            'input_scope_used' => 'image_only',
            'input_types_considered_json' => ['image'],
            'confidence_band' => 'high',
            'publish_eligibility' => 'auto_publish',
            'short_caption' => 'Memorias do evento.',
            'reply_text' => 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
            'tags_json' => ['celebracao', 'retrato'],
            'raw_response_json' => [
                'provider' => 'vllm',
            ],
            'request_payload_json' => [
                'model' => 'openai/gpt-4.1-mini',
            ],
            'normalized_text_context' => null,
            'normalized_text_context_mode' => null,
            'prompt_context_json' => [
                'template' => 'Use {nome_do_evento}.',
                'variables' => ['nome_do_evento' => 'Evento Teste'],
                'resolved' => 'Use Evento Teste.',
            ],
            'policy_snapshot_json' => null,
            'policy_sources_json' => null,
            'tokens_input' => 120,
            'tokens_output' => 42,
            'completed_at' => now(),
        ];
    }
}
