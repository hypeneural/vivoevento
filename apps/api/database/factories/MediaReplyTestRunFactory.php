<?php

namespace Database\Factories;

use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaReplyTestRunFactory extends Factory
{
    protected $model = MediaReplyTestRun::class;

    public function definition(): array
    {
        return [
            'trace_id' => (string) Str::uuid(),
            'user_id' => UserFactory::new(),
            'event_id' => EventFactory::new(),
            'preset_id' => null,
            'provider_key' => 'openrouter',
            'model_key' => 'openai/gpt-4.1-mini',
            'status' => 'success',
            'prompt_template' => 'Use {nome_do_evento} apenas quando combinar com a imagem.',
            'prompt_resolved' => 'Use Evento Teste apenas quando combinar com a imagem.',
            'prompt_variables_json' => [
                'nome_do_evento' => 'Evento Teste',
            ],
            'images_json' => [
                [
                    'index' => 0,
                    'original_name' => 'teste.jpg',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 120000,
                    'sha256' => hash('sha256', 'teste'),
                ],
            ],
            'safety_results_json' => [
                [
                    'image_index' => 0,
                    'decision' => 'pass',
                    'blocked' => false,
                    'review_required' => false,
                    'category_scores' => ['nudity' => 0.01, 'violence' => 0.0, 'self_harm' => 0.0],
                    'reason_codes' => ['safety.pass'],
                ],
            ],
            'contextual_results_json' => [
                [
                    'image_index' => 0,
                    'decision' => 'approve',
                    'reason' => 'A imagem combina com o evento.',
                    'reason_code' => 'context.match.event',
                    'publish_eligibility' => 'auto_publish',
                    'confidence_band' => 'high',
                ],
            ],
            'final_summary_json' => [
                'final_publish_eligibility' => 'auto_publish',
                'final_effective_state' => 'approved',
                'human_reason' => 'A homologacao sugere publicacao automatica com a politica atual.',
            ],
            'policy_snapshot_json' => [
                'safety' => ['analysis_scope' => 'image_only'],
                'context' => ['context_scope' => 'image_only', 'reply_scope' => 'image_only'],
            ],
            'policy_sources_json' => [
                'safety' => ['analysis_scope' => 'default_config'],
                'context' => ['context_scope' => 'default_config', 'reply_scope' => 'default_config'],
            ],
            'request_payload_json' => [
                'model' => 'openai/gpt-4.1-mini',
            ],
            'response_payload_json' => [
                'reply_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
            ],
            'response_text' => 'Memorias que fazem o coracao sorrir! 🎉📸',
            'latency_ms' => 820,
            'error_message' => null,
        ];
    }
}
