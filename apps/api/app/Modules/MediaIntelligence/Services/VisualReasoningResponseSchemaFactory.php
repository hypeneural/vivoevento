<?php

namespace App\Modules\MediaIntelligence\Services;

class VisualReasoningResponseSchemaFactory
{
    /**
     * @return array<string, mixed>
     */
    public function schema(string $version): array
    {
        return match ($version) {
            'foundation-v1' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['decision', 'review', 'reason', 'short_caption', 'reply_text', 'tags'],
                'properties' => [
                    'decision' => [
                        'type' => 'string',
                        'enum' => ['approve', 'review', 'reject'],
                    ],
                    'review' => [
                        'type' => 'boolean',
                    ],
                    'reason' => [
                        'type' => 'string',
                    ],
                    'short_caption' => [
                        'type' => 'string',
                    ],
                    'reply_text' => [
                        'type' => 'string',
                        'maxLength' => 160,
                    ],
                    'tags' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                        'maxItems' => 8,
                    ],
                ],
            ],
            default => $this->schema('foundation-v1'),
        };
    }
}
