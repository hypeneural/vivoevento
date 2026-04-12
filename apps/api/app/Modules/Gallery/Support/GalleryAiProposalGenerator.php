<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use App\Modules\Events\Models\Event;

class GalleryAiProposalGenerator
{
    public function __construct(
        private readonly GalleryModelMatrixRegistry $matrixRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $currentPayload
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    public function generate(Event $event, array $currentPayload, array $input): array
    {
        $prompt = mb_strtolower(trim((string) ($input['prompt_text'] ?? '')));
        $currentFamily = (string) ($currentPayload['event_type_family'] ?? 'wedding');

        $focus = $this->focusVariation($prompt, $currentFamily);
        $clean = $currentFamily === 'corporate'
            ? $this->definition('corporate-clean', 'Corporativo clean', 'Mais respiracao, hierarquia clara e leitura mais objetiva.', 'corporate', 'clean', 'light')
            : $this->definition('modern-clean', 'Moderno clean', 'Menos ornamento, mais respiro visual e leitura limpa.', $currentFamily, 'modern', 'light');
        $premium = $this->definition(
            'premium-album',
            'Premium album',
            'Mais contraste editorial, peso visual elegante e ritmo de album.',
            $currentFamily,
            'premium',
            $currentFamily === 'quince' ? 'live' : 'light',
        );

        return [$focus, $clean, $premium];
    }

    /**
     * @return array<string, mixed>
     */
    private function focusVariation(string $prompt, string $currentFamily): array
    {
        if (str_contains($prompt, 'corporat') || str_contains($prompt, 'patrocin')) {
            return $this->definition(
                'corporate-sponsors',
                'Corporativo com patrocinio',
                'Faixa de patrocinio visivel, hierarquia limpa e leitura mais institucional.',
                'corporate',
                'clean',
                'sponsors',
            );
        }

        if (str_contains($prompt, '15 anos') || str_contains($prompt, 'quince') || str_contains($prompt, 'debut')) {
            return $this->definition(
                'quince-live',
                '15 anos ao vivo',
                'Linguagem mais vibrante, grade viva e hero com energia de festa.',
                'quince',
                'modern',
                'live',
            );
        }

        if (str_contains($prompt, 'premium') || str_contains($prompt, 'album') || str_contains($prompt, 'editorial') || str_contains($prompt, 'luxo')) {
            return $this->definition(
                'premium-focus',
                'Premium editorial',
                'Contraste mais forte, serif elegante e hero com leitura de album.',
                $currentFamily,
                'premium',
                'light',
            );
        }

        return $this->definition(
            'romantic-soft',
            'Romantico suave',
            'Rose claro, hero mais editorial e ritmo mais caloroso.',
            $currentFamily === 'corporate' ? 'wedding' : $currentFamily,
            'romantic',
            'story',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(
        string $id,
        string $label,
        string $summary,
        string $eventTypeFamily,
        string $styleSkin,
        string $behaviorProfile,
    ): array {
        $derived = $this->matrixRegistry->derive($eventTypeFamily, $styleSkin, $behaviorProfile);

        $paletteOverride = match ($id) {
            'romantic-soft' => [
                'accent' => '#d97786',
                'page_background' => '#fff7f5',
                'surface_border' => '#f5d0d6',
            ],
            'modern-clean', 'corporate-clean', 'corporate-sponsors' => [
                'accent' => '#0f766e',
                'page_background' => '#f8fafc',
                'surface_border' => '#cbd5e1',
            ],
            default => [
                'accent' => '#f8fafc',
            ],
        };

        return [
            'id' => $id,
            'label' => $label,
            'summary' => $summary,
            'model_matrix' => [
                'event_type_family' => $eventTypeFamily,
                'style_skin' => $styleSkin,
                'behavior_profile' => $behaviorProfile,
                'theme_key' => $derived['theme_key'],
                'layout_key' => $derived['layout_key'],
            ],
            'patch' => [
                'theme_tokens' => [
                    'palette' => $paletteOverride,
                    'typography' => [
                        'title_scale' => $id === 'premium-album' || $id === 'premium-focus' ? 'lg' : 'md',
                    ],
                ],
                'page_schema' => [
                    'blocks' => [
                        'hero' => [
                            'enabled' => true,
                            'variant' => $eventTypeFamily,
                            'show_logo' => true,
                            'show_face_search_cta' => true,
                        ],
                        'banner_strip' => [
                            'enabled' => $behaviorProfile === 'sponsors',
                            'positions' => $behaviorProfile === 'sponsors' ? ['after_12'] : [],
                        ],
                        'quote' => [
                            'enabled' => in_array($behaviorProfile, ['story', 'live'], true),
                        ],
                    ],
                ],
                'media_behavior' => [
                    'grid' => [
                        'layout' => $derived['grid_layout'],
                        'density' => $id === 'premium-album' || $id === 'premium-focus'
                            ? 'immersive'
                            : ($behaviorProfile === 'live' ? 'compact' : 'comfortable'),
                    ],
                    'video' => [
                        'mode' => $derived['video_mode'],
                        'allow_inline_preview' => $derived['video_mode'] === 'inline_preview',
                    ],
                    'interstitials' => [
                        'enabled' => $behaviorProfile === 'sponsors',
                        'policy' => $derived['interstitial_policy'],
                        'max_per_24_items' => $behaviorProfile === 'sponsors' ? 1 : 0,
                    ],
                ],
            ],
        ];
    }
}
