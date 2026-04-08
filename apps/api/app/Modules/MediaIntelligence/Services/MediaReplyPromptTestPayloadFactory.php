<?php

namespace App\Modules\MediaIntelligence\Services;

class MediaReplyPromptTestPayloadFactory
{
    /**
     * @param array<int, array{data_url:string}> $imageInputs
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function build(
        string $modelKey,
        string $resolvedPrompt,
        array $imageInputs,
        array $config,
    ): array {
        $content = [
            [
                'type' => 'text',
                'text' => $resolvedPrompt,
            ],
        ];

        foreach ($imageInputs as $imageInput) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $imageInput['data_url'],
                    'detail' => 'high',
                ],
            ];
        }

        return [
            'model' => $modelKey,
            'temperature' => (float) ($config['temperature'] ?? 0.1),
            'max_completion_tokens' => (int) ($config['max_completion_tokens'] ?? 250),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Voce responde apenas em JSON valido, seguindo exatamente o schema solicitado.',
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'eventovivo_media_reply_test',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['reply_text'],
                        'properties' => [
                            'reply_text' => [
                                'type' => 'string',
                                'maxLength' => 160,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $imagesMetadata
     * @return array<string, mixed>
     */
    public function sanitized(array $payload, array $imagesMetadata): array
    {
        $sanitized = $payload;
        $imageCursor = 0;

        foreach ($sanitized['messages'] ?? [] as $messageIndex => $message) {
            if (! is_array($message) || ! is_array($message['content'] ?? null)) {
                continue;
            }

            foreach ($message['content'] as $contentIndex => $contentItem) {
                if (! is_array($contentItem) || ($contentItem['type'] ?? null) !== 'image_url') {
                    continue;
                }

                $imageMetadata = $imagesMetadata[$imageCursor] ?? null;

                $sanitized['messages'][$messageIndex]['content'][$contentIndex] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'path_used' => 'data_url',
                        'original_name' => $imageMetadata['original_name'] ?? null,
                        'mime_type' => $imageMetadata['mime_type'] ?? null,
                        'size_bytes' => $imageMetadata['size_bytes'] ?? null,
                        'sha256' => $imageMetadata['sha256'] ?? null,
                    ],
                ];

                $imageCursor++;
            }
        }

        return $sanitized;
    }
}
