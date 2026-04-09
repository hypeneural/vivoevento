<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Support\NormalizedTextContextBuilder;

class OpenAiCompatibleVisualReasoningPayloadFactory
{
    public function __construct(
        private readonly VisualReasoningResponseSchemaFactory $schemas,
        private readonly OpenAiCompatibleMultimodalPayloadNormalizer $normalizer,
        private readonly MediaReplyTextPromptResolver $replyPrompts,
        private readonly NormalizedTextContextBuilder $textContexts,
        private readonly ContextualModerationPromptBuilder $contextualPrompts,
    ) {}

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function build(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
        string $imageUrl,
        array $config,
    ): array {
        $promptContext = $this->promptContext($media, $settings);
        $userInstructions = $this->userInstructions($promptContext);

        $payload = [
            'model' => (string) ($settings->model_key ?: ($config['model'] ?? '')),
            'temperature' => (float) ($config['temperature'] ?? 0.1),
            'max_completion_tokens' => (int) ($config['max_completion_tokens'] ?? 300),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Voce responde apenas em JSON valido, seguindo exatamente o schema solicitado.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $userInstructions,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageUrl,
                                'detail' => 'high',
                            ],
                            'uuid' => sprintf('media-%s', $media->id),
                        ],
                    ],
                ],
            ],
        ];

        if ($settings->require_json_output) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'eventovivo_media_intelligence',
                    'strict' => true,
                    'schema' => $this->schemas->schema($settings->response_schema_version ?: EventMediaIntelligenceSetting::DEFAULT_RESPONSE_SCHEMA_VERSION),
                ],
            ];
        }

        return $this->normalizer->normalize($payload);
    }

    /**
     * @return array{
     *   template?:string,
     *   variables?:array<string, string>,
     *   resolved?:string,
     *   preset_name?:string|null,
     *   preset_id?:int|null,
     *   preset_source?:string|null,
     *   instruction_source?:string|null,
     *   context_scope:string,
     *   reply_scope:string,
     *   normalized_text_context_mode:string,
     *   normalized_text_context:?string,
     *   context_text_context:?string,
     *   reply_text_context:?string,
     *   policy_json?:array<string, mixed>
     * }|null
     */
    public function promptContext(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
    ): ?array {
        $replyPrompt = $this->replyPrompts->promptContext($settings, $media->event?->title) ?? [];
        $normalized = $this->textContexts->build(
            (string) ($settings->normalized_text_context_mode ?? 'body_plus_caption'),
            caption: $media->caption,
            bodyText: $media->inboundMessage?->body_text,
            operatorSummary: null,
        );

        $contextScope = (string) ($settings->context_scope ?? 'image_and_text_context');
        $replyScope = (string) ($settings->reply_scope ?? 'image_and_text_context');
        $policySnapshot = [
            'contextual_policy_preset_key' => $settings->contextual_policy_preset_key,
            'contextual_policy_preset_label' => app(ContextualModerationPresetCatalog::class)
                ->resolve((string) ($settings->contextual_policy_preset_key ?? 'homologacao_livre'))['label'],
            'policy_version' => $settings->policy_version,
            'context_scope' => $contextScope,
            'allow_alcohol' => (bool) ($settings->allow_alcohol ?? false),
            'allow_tobacco' => (bool) ($settings->allow_tobacco ?? false),
            'required_people_context' => $settings->required_people_context,
            'blocked_terms_json' => $settings->blocked_terms_json ?? [],
            'allowed_exceptions_json' => $settings->allowed_exceptions_json ?? [],
            'freeform_instruction' => $settings->contextualFreeformInstruction(),
            'caption_style_prompt' => $settings->caption_style_prompt,
        ];
        $contextText = $contextScope === 'image_and_text_context' ? $normalized['text'] : null;
        $replyTextContext = $replyScope === 'image_and_text_context' ? $normalized['text'] : null;
        $contextPrompt = $this->contextualPrompts->build(
            eventName: (string) ($media->event?->title ?? ''),
            policySnapshot: $policySnapshot,
            contextTextContext: $contextText,
            replyInstruction: data_get($replyPrompt, 'resolved'),
            replyTextContext: $replyTextContext,
        );

        return array_merge($replyPrompt, [
            'template' => $contextPrompt['prompt_template'],
            'variables' => array_merge(
                data_get($replyPrompt, 'variables', []),
                $contextPrompt['variables_json'],
            ),
            'resolved' => $contextPrompt['prompt_resolved'],
            'context_scope' => $contextScope,
            'reply_scope' => $replyScope,
            'normalized_text_context_mode' => $normalized['mode'],
            'normalized_text_context' => $normalized['text'],
            'context_text_context' => $contextText,
            'reply_text_context' => $replyTextContext,
            'policy_json' => $contextPrompt['policy_json'],
        ]);
    }

    /**
     * @param array<string, mixed>|null $promptContext
     */
    private function userInstructions(?array $promptContext): string
    {
        return trim((string) data_get($promptContext, 'resolved', ''));
    }
}
