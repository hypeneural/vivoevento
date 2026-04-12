<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

class GalleryBuilderPromptSchemaFactory
{
    public function version(): int
    {
        return (int) config('gallery_builder.ai.response_schema_version', 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['response_schema_version', 'target_layer', 'variations'],
            'properties' => [
                'response_schema_version' => [
                    'type' => 'integer',
                    'enum' => [$this->version()],
                ],
                'target_layer' => [
                    'type' => 'string',
                    'enum' => ['mixed', 'theme_tokens', 'page_schema', 'media_behavior'],
                ],
                'variations' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'label', 'summary', 'scope', 'model_matrix', 'patch'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'summary' => ['type' => 'string'],
                            'scope' => [
                                'type' => 'string',
                                'enum' => ['mixed', 'theme_tokens', 'page_schema', 'media_behavior'],
                            ],
                            'model_matrix' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => [
                                    'event_type_family',
                                    'style_skin',
                                    'behavior_profile',
                                    'theme_key',
                                    'layout_key',
                                ],
                                'properties' => [
                                    'event_type_family' => [
                                        'type' => 'string',
                                        'enum' => GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES,
                                    ],
                                    'style_skin' => [
                                        'type' => 'string',
                                        'enum' => GalleryBuilderSchemaRegistry::STYLE_SKINS,
                                    ],
                                    'behavior_profile' => [
                                        'type' => 'string',
                                        'enum' => GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES,
                                    ],
                                    'theme_key' => [
                                        'type' => 'string',
                                        'enum' => GalleryBuilderSchemaRegistry::THEME_KEYS,
                                    ],
                                    'layout_key' => [
                                        'type' => 'string',
                                        'enum' => GalleryBuilderSchemaRegistry::LAYOUT_KEYS,
                                    ],
                                ],
                            ],
                            'patch' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'theme_tokens' => ['type' => 'object'],
                                    'page_schema' => ['type' => 'object'],
                                    'media_behavior' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
