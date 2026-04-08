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
        $userInstructions = $this->userInstructions($media, $settings, $promptContext);

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
                    'schema' => $this->schemas->schema($settings->response_schema_version ?: 'foundation-v1'),
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
     *   reply_text_context:?string
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

        return array_merge($replyPrompt, [
            'context_scope' => $contextScope,
            'reply_scope' => $replyScope,
            'normalized_text_context_mode' => $normalized['mode'],
            'normalized_text_context' => $normalized['text'],
            'context_text_context' => $contextScope === 'image_and_text_context' ? $normalized['text'] : null,
            'reply_text_context' => $replyScope === 'image_and_text_context' ? $normalized['text'] : null,
        ]);
    }

    /**
     * @param array{
     *   resolved?:string,
     *   context_text_context:?string,
     *   reply_text_context:?string
     * }|null $promptContext
     */
    private function userInstructions(
        EventMedia $media,
        EventMediaIntelligenceSetting $settings,
        ?array $promptContext,
    ): string {
        return implode("\n\n", array_filter([
            trim((string) $settings->approval_prompt),
            $media->event?->title ? 'Contexto adicional do evento: ' . $media->event->title : null,
            data_get($promptContext, 'context_text_context') ? 'Texto associado ao envio considerado na analise: ' . data_get($promptContext, 'context_text_context') : null,
            trim((string) $settings->caption_style_prompt),
            data_get($promptContext, 'reply_text_context') ? 'Contexto textual disponivel para orientar a resposta automatica: ' . data_get($promptContext, 'reply_text_context') : null,
            data_get($promptContext, 'resolved') ? 'Instrucao adicional para resposta automatica baseada na imagem: ' . data_get($promptContext, 'resolved') : null,
        ]));
    }
}
