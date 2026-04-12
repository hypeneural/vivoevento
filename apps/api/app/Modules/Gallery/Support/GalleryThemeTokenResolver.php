<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

class GalleryThemeTokenResolver
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function resolve(
        string $themeKey = 'event-brand',
        ?string $primaryColor = null,
        ?string $secondaryColor = null,
        array $overrides = [],
    ): array {
        $tokens = $this->defaultsForTheme($themeKey, $primaryColor, $secondaryColor);

        return $this->mergeValidOverrides($tokens, $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsForTheme(string $themeKey, ?string $primaryColor, ?string $secondaryColor): array
    {
        $primary = $this->colorOrDefault($primaryColor, '#0f172a');
        $secondary = $this->colorOrDefault($secondaryColor, '#2563eb');

        $palette = match ($themeKey) {
            'wedding-rose' => [
                'page_background' => '#fff7f5',
                'surface_background' => '#ffffff',
                'surface_border' => '#f5d0d6',
                'text_primary' => '#4c0519',
                'text_secondary' => '#9f1239',
                'accent' => '#d97786',
                'button_fill' => '#be185d',
                'button_text' => '#ffffff',
            ],
            'black-tie' => [
                'page_background' => '#020617',
                'surface_background' => '#0f172a',
                'surface_border' => '#334155',
                'text_primary' => '#f8fafc',
                'text_secondary' => '#cbd5e1',
                'accent' => '#f8fafc',
                'button_fill' => '#f8fafc',
                'button_text' => '#020617',
            ],
            'quince-glam' => [
                'page_background' => '#3b0764',
                'surface_background' => '#581c87',
                'surface_border' => '#22d3ee',
                'text_primary' => '#faf5ff',
                'text_secondary' => '#e9d5ff',
                'accent' => '#ec4899',
                'button_fill' => '#ec4899',
                'button_text' => '#ffffff',
            ],
            'corporate-clean' => [
                'page_background' => '#f8fafc',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#334155',
                'accent' => '#0f766e',
                'button_fill' => '#0f766e',
                'button_text' => '#ffffff',
            ],
            'pearl' => [
                'page_background' => '#f8fafc',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#475569',
                'accent' => '#475569',
                'button_fill' => '#0f172a',
                'button_text' => '#ffffff',
            ],
            default => [
                'page_background' => '#f8fafc',
                'surface_background' => '#ffffff',
                'surface_border' => '#cbd5e1',
                'text_primary' => '#0f172a',
                'text_secondary' => '#475569',
                'accent' => $secondary,
                'button_fill' => $primary,
                'button_text' => '#ffffff',
            ],
        };

        return [
            'palette' => $palette,
            'typography' => [
                'display_family_key' => in_array($themeKey, ['black-tie', 'wedding-rose'], true)
                    ? 'editorial-serif'
                    : 'clean-sans',
                'body_family_key' => 'clean-sans',
                'title_scale' => $themeKey === 'black-tie' ? 'lg' : 'md',
            ],
            'radius' => [
                'card' => 'xl',
                'button' => 'pill',
                'media' => 'lg',
            ],
            'borders' => [
                'surface' => 'soft',
                'media' => 'none',
            ],
            'shadows' => [
                'card' => $themeKey === 'corporate-clean' ? 'none' : 'soft',
                'hero' => 'overlay-soft',
            ],
            'contrast_rules' => [
                'body_text_min_ratio' => 4.5,
                'large_text_min_ratio' => 3,
                'ui_min_ratio' => 3,
            ],
            'motion' => [
                'respect_user_preference' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeValidOverrides(array $tokens, array $overrides): array
    {
        foreach (['palette', 'typography', 'radius', 'borders', 'shadows', 'contrast_rules', 'motion'] as $section) {
            if (! is_array($overrides[$section] ?? null)) {
                continue;
            }

            foreach ($overrides[$section] as $key => $value) {
                if (! array_key_exists($key, $tokens[$section] ?? [])) {
                    continue;
                }

                if ($section === 'palette') {
                    $tokens[$section][$key] = $this->colorOrDefault($value, $tokens[$section][$key]);

                    continue;
                }

                $tokens[$section][$key] = $value;
            }
        }

        return $tokens;
    }

    private function colorOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
            ? $value
            : $default;
    }
}
