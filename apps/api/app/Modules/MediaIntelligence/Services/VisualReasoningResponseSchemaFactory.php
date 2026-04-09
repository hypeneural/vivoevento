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
            'foundation-v1' => $this->schema('contextual-v2'),
            'contextual-v2' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'decision',
                    'review',
                    'reason',
                    'reason_code',
                    'matched_policies',
                    'matched_exceptions',
                    'input_scope_used',
                    'input_types_considered',
                    'confidence_band',
                    'publish_eligibility',
                    'short_caption',
                    'reply_text',
                    'tags',
                ],
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
                    'reason_code' => [
                        'type' => 'string',
                        'enum' => [
                            'context.approved',
                            'context.out_of_scope',
                            'policy.alcohol',
                            'policy.tobacco',
                            'policy.blocked_term',
                            'policy.uncertain',
                        ],
                    ],
                    'matched_policies' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 12,
                    ],
                    'matched_exceptions' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 12,
                    ],
                    'input_scope_used' => [
                        'type' => 'string',
                        'enum' => ['image_only', 'image_and_text_context'],
                    ],
                    'input_types_considered' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['image', 'text'],
                        ],
                        'maxItems' => 2,
                    ],
                    'confidence_band' => [
                        'type' => 'string',
                        'enum' => ['high', 'medium', 'low'],
                    ],
                    'publish_eligibility' => [
                        'type' => 'string',
                        'enum' => ['auto_publish', 'review_only', 'reject'],
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
            default => $this->schema('contextual-v2'),
        };
    }
}
