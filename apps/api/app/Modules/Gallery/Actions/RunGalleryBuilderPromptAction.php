<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Models\GalleryBuilderPromptRun;
use App\Modules\Gallery\Support\GalleryAiPatchApplier;
use App\Modules\Gallery\Support\GalleryAiProposalGenerator;
use App\Modules\Gallery\Support\GalleryBuilderPromptSchemaFactory;
use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use App\Modules\Gallery\Support\GalleryModelMatrixRegistry;
use App\Modules\Users\Models\User;
use Illuminate\Support\Arr;

class RunGalleryBuilderPromptAction
{
    public function __construct(
        private readonly GalleryAiProposalGenerator $generator,
        private readonly GalleryAiPatchApplier $patchApplier,
        private readonly GalleryBuilderPromptSchemaFactory $schemaFactory,
        private readonly GalleryBuilderSchemaRegistry $schemaRegistry,
        private readonly GalleryModelMatrixRegistry $matrixRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   run: GalleryBuilderPromptRun,
     *   variations: array<int, array<string, mixed>>
     * }
     */
    public function execute(
        Event $event,
        EventGallerySetting $settings,
        array $payload,
        ?User $user = null,
    ): array {
        $providerKey = (string) config('gallery_builder.ai.provider_key', 'local-guardrailed');
        $modelKey = (string) config('gallery_builder.ai.model_key', 'gallery-builder-local-v1');
        $targetLayer = (string) ($payload['target_layer'] ?? 'mixed');
        $current = array_merge(
            ['is_enabled' => (bool) $settings->is_enabled],
            $settings->toBuilderPayload(),
        );

        $requestPayload = $this->buildOpenAiCompatiblePayload($event, $settings, $payload, $modelKey, $targetLayer);
        $candidateVariations = $this->generator->generate($event, $current, $payload);
        $variations = [];

        foreach ($candidateVariations as $candidate) {
            $normalized = $this->patchApplier->applyPatch(
                $event,
                $current,
                is_array($candidate['patch'] ?? null) ? $candidate['patch'] : [],
                is_array($candidate['model_matrix'] ?? null) ? $candidate['model_matrix'] : [],
                $targetLayer,
            );

            $variations[] = [
                'id' => (string) ($candidate['id'] ?? 'variation'),
                'label' => (string) ($candidate['label'] ?? 'Variacao'),
                'summary' => (string) ($candidate['summary'] ?? 'Ajuste guardrailed para a galeria.'),
                'scope' => $normalized['scope'],
                'available_layers' => $normalized['available_layers'],
                'model_matrix' => $normalized['model_matrix'],
                'patch' => $normalized['patch'],
            ];
        }

        $responsePayload = [
            'response_schema_version' => $this->schemaFactory->version(),
            'target_layer' => $targetLayer,
            'variations' => $variations,
        ];

        $run = GalleryBuilderPromptRun::query()->create([
            'event_id' => $event->id,
            'organization_id' => $event->organization_id,
            'user_id' => $user?->id,
            'prompt_text' => (string) $payload['prompt_text'],
            'persona_key' => $payload['persona_key'] ?? 'operator',
            'event_type_key' => $settings->event_type_family,
            'target_layer' => $targetLayer,
            'base_preset_key' => $payload['base_preset_key'] ?? $settings->event_type_family.'.'.$settings->style_skin.'.'.$settings->behavior_profile,
            'request_payload_json' => $requestPayload,
            'response_payload_json' => $responsePayload,
            'selected_variation_id' => null,
            'response_schema_version' => $this->schemaFactory->version(),
            'status' => 'success',
            'provider_key' => $providerKey,
            'model_key' => $modelKey,
        ]);

        return [
            'run' => $run,
            'variations' => $variations,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildOpenAiCompatiblePayload(
        Event $event,
        EventGallerySetting $settings,
        array $payload,
        string $modelKey,
        string $targetLayer,
    ): array {
        $context = [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'primary_color' => $event->primary_color,
                'secondary_color' => $event->secondary_color,
            ],
            'current_draft' => $settings->toBuilderPayload(),
            'matrix_options' => $this->matrixRegistry->fixtures(),
            'guardrails' => [
                'allowed_theme_keys' => GalleryBuilderSchemaRegistry::THEME_KEYS,
                'allowed_layout_keys' => GalleryBuilderSchemaRegistry::LAYOUT_KEYS,
                'allowed_blocks' => GalleryBuilderSchemaRegistry::BLOCK_KEYS,
                'allowed_video_modes' => GalleryBuilderSchemaRegistry::VIDEO_MODES,
                'allowed_interstitial_policies' => GalleryBuilderSchemaRegistry::INTERSTITIAL_POLICIES,
                'mobile_budget' => $this->schemaRegistry->mobileBudget(),
                'responsive_contract' => $this->schemaRegistry->responsiveSourceContract(),
                'instructions' => [
                    'retorne apenas JSON valido',
                    'nunca retorne HTML, CSS, JSX ou campos fora do catalogo',
                    'gere exatamente 3 variacoes seguras',
                    'cada variacao deve ser aplicavel total ou parcialmente',
                ],
            ],
            'user_request' => [
                'prompt_text' => (string) $payload['prompt_text'],
                'persona_key' => $payload['persona_key'] ?? 'operator',
                'target_layer' => $targetLayer,
                'base_preset_key' => $payload['base_preset_key'] ?? null,
            ],
        ];

        return [
            'model' => $modelKey,
            'temperature' => (float) config('gallery_builder.ai.temperature', 0.2),
            'max_completion_tokens' => (int) config('gallery_builder.ai.max_completion_tokens', 1200),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Voce responde apenas em JSON valido, com 3 variacoes seguras para o gallery builder. Nunca escreva HTML, CSS, JSX ou codigo livre.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'eventovivo_gallery_builder_proposals',
                    'strict' => true,
                    'schema' => $this->schemaFactory->schema(),
                ],
            ],
        ];
    }
}
