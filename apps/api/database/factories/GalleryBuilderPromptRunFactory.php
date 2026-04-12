<?php

namespace Database\Factories;

use App\Modules\Gallery\Models\GalleryBuilderPromptRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryBuilderPromptRunFactory extends Factory
{
    protected $model = GalleryBuilderPromptRun::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'organization_id' => OrganizationFactory::new(),
            'user_id' => UserFactory::new(),
            'prompt_text' => 'quero uma galeria romantica em tons rose com hero editorial',
            'persona_key' => 'operator',
            'event_type_key' => 'wedding',
            'target_layer' => 'mixed',
            'base_preset_key' => 'wedding.romantic.story',
            'request_payload_json' => [
                'model' => 'gallery-builder-local-v1',
                'response_format' => [
                    'type' => 'json_schema',
                ],
            ],
            'response_payload_json' => [
                'response_schema_version' => 1,
                'target_layer' => 'mixed',
                'variations' => [
                    [
                        'id' => 'romantic-soft',
                        'label' => 'Romantico suave',
                        'summary' => 'Rose claro e hero mais editorial.',
                        'scope' => 'mixed',
                        'available_layers' => ['theme_tokens', 'page_schema', 'media_behavior'],
                        'model_matrix' => [
                            'event_type_family' => 'wedding',
                            'style_skin' => 'romantic',
                            'behavior_profile' => 'story',
                            'theme_key' => 'wedding-rose',
                            'layout_key' => 'justified-story',
                        ],
                        'patch' => [
                            'theme_tokens' => [
                                'palette' => [
                                    'accent' => '#d97786',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'selected_variation_id' => null,
            'response_schema_version' => 1,
            'status' => 'success',
            'provider_key' => 'local-guardrailed',
            'model_key' => 'gallery-builder-local-v1',
        ];
    }
}
