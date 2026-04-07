<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class OpenAiCompatibleVisualReasoningPayloadFactory
{
    public function __construct(
        private readonly VisualReasoningResponseSchemaFactory $schemas,
        private readonly OpenAiCompatibleMultimodalPayloadNormalizer $normalizer,
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
        $userInstructions = implode("\n\n", array_filter([
            trim((string) $settings->approval_prompt),
            $media->event?->title ? 'Contexto adicional do evento: ' . $media->event->title : null,
            $media->caption ? 'Legenda original enviada: ' . $media->caption : null,
            $media->inboundMessage?->body_text ? 'Texto associado ao envio: ' . $media->inboundMessage->body_text : null,
            trim((string) $settings->caption_style_prompt),
        ]));

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
}
