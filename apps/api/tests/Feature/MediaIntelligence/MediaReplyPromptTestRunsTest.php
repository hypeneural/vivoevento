<?php

use App\Modules\ContentModeration\DTOs\ContentSafetyEvaluationResult;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\DTOs\VisualReasoningEvaluationResult;
use App\Modules\MediaIntelligence\Models\MediaReplyPromptPreset;
use App\Modules\MediaIntelligence\Models\MediaReplyTestRun;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('runs a synchronous media reply laboratory with safety, contextual gate and final eligibility side by side', function () {
    [$user] = $this->actingAsSuperAdmin();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\ContentModeration\Models\EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            return ContentSafetyEvaluationResult::pass(
                categoryScores: ['nudity' => 0.02, 'violence' => 0.01, 'self_harm' => 0.00],
                reasonCodes: ['safety.pass'],
                normalizedTextContextMode: $settings->normalized_text_context_mode,
                modelKey: 'omni-moderation-latest',
            );
        }
    });

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting $settings,
        ): VisualReasoningEvaluationResult {
            return VisualReasoningEvaluationResult::approve(
                reason: 'A imagem representa o contexto da formatura.',
                reasonCode: 'context.match.event',
                matchedPolicies: ['preset:formatura'],
                inputScopeUsed: $settings->context_scope,
                inputTypesConsidered: $settings->context_scope === 'image_and_text_context' ? ['image', 'text'] : ['image'],
                confidenceBand: 'high',
                publishEligibility: 'auto_publish',
                shortCaption: 'Celebracao no palco.',
                replyText: 'Memorias que fazem o coracao sorrir!',
                normalizedTextContextMode: $settings->normalized_text_context_mode,
                modelKey: $settings->model_key,
                promptVersion: $settings->prompt_version,
                responseSchemaVersion: $settings->response_schema_version,
                modeApplied: $settings->mode,
            );
        }
    });

    Http::fake([
        'https://openrouter.test/api/v1/chat/completions' => Http::response([
            'id' => 'resp_123',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reply_text' => 'Memorias que fazem o coracao sorrir!',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ], 200),
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.test/api/v1');

    $event = Event::factory()->create([
        'title' => 'Formatura 2026',
    ]);
    $event->contentModerationSettings()->create([
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'analysis_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
    ]);
    $event->mediaIntelligenceSettings()->create([
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_and_text_context',
        'reply_scope' => 'image_only',
        'normalized_text_context_mode' => 'caption_only',
        'contextual_policy_preset_key' => 'formatura',
        'policy_version' => 'contextual-policy-v1',
    ]);
    $preset = MediaReplyPromptPreset::factory()->create([
        'name' => 'Formaturas',
        'slug' => 'formaturas',
        'prompt_template' => 'Use um tom celebrativo e educado, coerente com a cena.',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'event_id' => $event->id,
            'provider_key' => 'openrouter',
            'model_key' => 'openai/gpt-4.1-mini',
            'preset_id' => $preset->id,
            'prompt_template' => 'Mantenha a resposta curta e com no maximo 2 emojis.',
            'images' => [
                UploadedFile::fake()->image('foto-1.jpg', 800, 600)->size(320),
                UploadedFile::fake()->image('foto-2.jpg', 800, 600)->size(330),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.event_id', $event->id)
        ->assertJsonPath('data.preset_id', $preset->id)
        ->assertJsonPath('data.response_text', 'Memorias que fazem o coracao sorrir!')
        ->assertJsonPath('data.prompt_variables.nome_do_evento', 'Formatura 2026')
        ->assertJsonPath('data.safety_results.0.decision', 'pass')
        ->assertJsonPath('data.contextual_results.0.decision', 'approve')
        ->assertJsonPath('data.final_summary.final_publish_eligibility', 'auto_publish')
        ->assertJsonPath('data.final_summary.final_effective_state', 'approved')
        ->assertJsonPath('data.policy_snapshot.safety.analysis_scope', 'image_only')
        ->assertJsonPath('data.policy_snapshot.context.context_scope', 'image_and_text_context')
        ->assertJsonPath('data.policy_sources.context.context_scope', 'event_setting');

    $runId = $response->json('data.id');
    $testRun = MediaReplyTestRun::query()->findOrFail($runId);

    expect($testRun->status)->toBe('success')
        ->and($testRun->images_json)->toHaveCount(2)
        ->and($testRun->safety_results_json)->toHaveCount(2)
        ->and($testRun->contextual_results_json)->toHaveCount(2)
        ->and(data_get($testRun->final_summary_json, 'final_publish_eligibility'))->toBe('auto_publish')
        ->and(data_get($testRun->policy_snapshot_json, 'context.context_scope'))->toBe('image_and_text_context')
        ->and($testRun->request_payload_json)->toBeArray()
        ->and(data_get($testRun->request_payload_json, 'messages.1.content.1.image_url.sha256'))->not->toBeNull()
        ->and(data_get($testRun->request_payload_json, 'messages.1.content.2.image_url.sha256'))->not->toBeNull();
});

it('applies controlled laboratory overrides and projects a rejected final state when a blocking layer rejects', function () {
    [$user] = $this->actingAsSuperAdmin();

    $safetyCalls = new \ArrayObject();
    $contextCalls = new \ArrayObject();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class($safetyCalls) implements ContentModerationProviderInterface
    {
        public function __construct(
            private readonly ArrayObject $calls,
        ) {}

        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\ContentModeration\Models\EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            $this->calls[] = [
                'analysis_scope' => $settings->analysis_scope,
                'normalized_text_context_mode' => $settings->normalized_text_context_mode,
            ];

            return ContentSafetyEvaluationResult::pass(
                reasonCodes: ['safety.pass'],
                normalizedTextContextMode: $settings->normalized_text_context_mode,
            );
        }
    });

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class($contextCalls) implements VisualReasoningProviderInterface
    {
        public function __construct(
            private readonly ArrayObject $calls,
        ) {}

        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting $settings,
        ): VisualReasoningEvaluationResult {
            $this->calls[] = [
                'context_scope' => $settings->context_scope,
                'reply_scope' => $settings->reply_scope,
                'normalized_text_context_mode' => $settings->normalized_text_context_mode,
            ];

            return VisualReasoningEvaluationResult::reject(
                reason: 'A imagem nao representa o contexto esperado.',
                reasonCode: 'context.out_of_scope',
                matchedPolicies: ['blocked:objeto_sem_pessoas'],
                inputScopeUsed: $settings->context_scope,
                inputTypesConsidered: ['image'],
                confidenceBand: 'high',
                publishEligibility: 'reject',
                normalizedTextContextMode: $settings->normalized_text_context_mode,
                modeApplied: $settings->mode,
            );
        }
    });

    Http::fake([
        'https://openrouter.test/api/v1/chat/completions' => Http::response([
            'id' => 'resp_999',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reply_text' => 'Teste de homologacao executado.',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ], 200),
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.test/api/v1');

    $event = Event::factory()->create([
        'title' => 'Congresso Tech 2026',
    ]);
    $event->contentModerationSettings()->create([
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'analysis_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_plus_caption',
    ]);
    $event->mediaIntelligenceSettings()->create([
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_and_text_context',
        'reply_scope' => 'image_and_text_context',
        'normalized_text_context_mode' => 'body_plus_caption',
        'contextual_policy_preset_key' => 'corporativo_restrito',
        'policy_version' => 'contextual-policy-v1',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'event_id' => $event->id,
            'provider_key' => 'openrouter',
            'model_key' => 'openai/gpt-4.1-mini',
            'context_scope_override' => 'image_only',
            'reply_scope_override' => 'image_only',
            'objective_safety_scope_override' => 'image_only',
            'normalized_text_context_mode_override' => 'caption_only',
            'images' => [
                UploadedFile::fake()->image('camera.jpg', 800, 600)->size(300),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.policy_snapshot.safety.analysis_scope', 'image_only')
        ->assertJsonPath('data.policy_snapshot.context.context_scope', 'image_only')
        ->assertJsonPath('data.policy_snapshot.context.reply_scope', 'image_only')
        ->assertJsonPath('data.policy_sources.safety.analysis_scope', 'runtime_override')
        ->assertJsonPath('data.policy_sources.context.context_scope', 'runtime_override')
        ->assertJsonPath('data.final_summary.final_publish_eligibility', 'reject')
        ->assertJsonPath('data.final_summary.final_effective_state', 'rejected')
        ->assertJsonPath('data.final_summary.blocking_layers.0', 'context');

    expect($safetyCalls[0]['analysis_scope'])->toBe('image_only')
        ->and($safetyCalls[0]['normalized_text_context_mode'])->toBe('caption_only')
        ->and($contextCalls[0]['context_scope'])->toBe('image_only')
        ->and($contextCalls[0]['reply_scope'])->toBe('image_only')
        ->and($contextCalls[0]['normalized_text_context_mode'])->toBe('caption_only');
});

it('rejects prompt tests with more than three images', function () {
    [$user] = $this->actingAsSuperAdmin();

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'provider_key' => 'vllm',
            'model_key' => 'Qwen/Qwen2.5-VL-3B-Instruct',
            'images' => [
                UploadedFile::fake()->image('foto-1.jpg'),
                UploadedFile::fake()->image('foto-2.jpg'),
                UploadedFile::fake()->image('foto-3.jpg'),
                UploadedFile::fake()->image('foto-4.jpg'),
            ],
        ]);

    $this->assertApiValidationError($response, ['images']);
});

it('keeps the laboratory observable when a policy layer fails and returns a partial result', function () {
    [$user] = $this->actingAsSuperAdmin();

    app()->bind(ContentModerationProviderInterface::class, fn () => new class implements ContentModerationProviderInterface
    {
        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\ContentModeration\Models\EventContentModerationSetting $settings,
        ): ContentSafetyEvaluationResult {
            throw new RuntimeException('Safety provider indisponivel.');
        }
    });

    app()->bind(VisualReasoningProviderInterface::class, fn () => new class implements VisualReasoningProviderInterface
    {
        public function evaluate(
            \App\Modules\MediaProcessing\Models\EventMedia $media,
            \App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting $settings,
        ): VisualReasoningEvaluationResult {
            return VisualReasoningEvaluationResult::review(
                reason: 'A imagem precisa de revisao manual.',
                reasonCode: 'context.review.manual',
                inputScopeUsed: $settings->context_scope,
                inputTypesConsidered: ['image'],
                confidenceBand: 'medium',
                publishEligibility: 'review_only',
                modeApplied: $settings->mode,
            );
        }
    });

    Http::fake([
        'https://openrouter.test/api/v1/chat/completions' => Http::response([
            'id' => 'resp_partial',
            'model' => 'openai/gpt-4.1-mini',
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reply_text' => 'Resposta disponivel apesar da falha de safety.',
                    ], JSON_THROW_ON_ERROR),
                ],
            ]],
        ], 200),
    ]);

    config()->set('media_intelligence.providers.openrouter.base_url', 'https://openrouter.test/api/v1');

    $event = Event::factory()->create([
        'title' => 'Evento de teste',
    ]);
    $event->contentModerationSettings()->create([
        'enabled' => true,
        'provider_key' => 'openai',
        'mode' => 'enforced',
        'analysis_scope' => 'image_only',
    ]);
    $event->mediaIntelligenceSettings()->create([
        'enabled' => true,
        'provider_key' => 'openrouter',
        'model_key' => 'openai/gpt-4.1-mini',
        'mode' => 'gate',
        'context_scope' => 'image_only',
        'reply_scope' => 'image_only',
    ]);

    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/ia/respostas-de-midia/testes', [
            'event_id' => $event->id,
            'provider_key' => 'openrouter',
            'model_key' => 'openai/gpt-4.1-mini',
            'images' => [
                UploadedFile::fake()->image('foto-1.jpg', 800, 600)->size(320),
            ],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'partial')
        ->assertJsonPath('data.safety_results.0.decision', 'error')
        ->assertJsonPath('data.safety_results.0.error_message', 'Safety provider indisponivel.')
        ->assertJsonPath('data.contextual_results.0.decision', 'review')
        ->assertJsonPath('data.final_summary.final_publish_eligibility', 'review_only')
        ->assertJsonPath('data.final_summary.final_effective_state', 'pending_moderation')
        ->assertJsonPath('data.final_summary.evaluation_errors_count', 1);
});

it('lists and shows stored media reply prompt test runs', function () {
    [$user] = $this->actingAsSuperAdmin();

    $latest = MediaReplyTestRun::factory()->create([
        'provider_key' => 'openrouter',
        'status' => 'success',
        'final_summary_json' => [
            'final_publish_eligibility' => 'auto_publish',
            'final_effective_state' => 'approved',
        ],
    ]);
    MediaReplyTestRun::factory()->create([
        'provider_key' => 'vllm',
        'status' => 'failed',
    ]);

    $listResponse = $this->apiGet('/ia/respostas-de-midia/testes?provider_key=openrouter&status=success&per_page=10');

    $this->assertApiPaginated($listResponse);
    expect($listResponse->json('data'))->toHaveCount(1);
    $listResponse->assertJsonPath('data.0.id', $latest->id);

    $showResponse = $this->apiGet("/ia/respostas-de-midia/testes/{$latest->id}");

    $this->assertApiSuccess($showResponse);
    $showResponse->assertJsonPath('data.trace_id', $latest->trace_id)
        ->assertJsonPath('data.provider_key', 'openrouter')
        ->assertJsonPath('data.final_summary.final_publish_eligibility', 'auto_publish');
});
