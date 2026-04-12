<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

class GalleryAccessibilityGuardService
{
    public function contrastRatio(string $foreground, string $background): ?float
    {
        $foregroundRgb = $this->hexToRgb($foreground);
        $backgroundRgb = $this->hexToRgb($background);

        if ($foregroundRgb === null || $backgroundRgb === null) {
            return null;
        }

        $foregroundLuminance = $this->relativeLuminance($foregroundRgb);
        $backgroundLuminance = $this->relativeLuminance($backgroundRgb);
        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * @param  array<string, mixed>  $themeTokens
     * @return array<string, mixed>
     */
    public function inspectThemeTokens(array $themeTokens): array
    {
        $palette = is_array($themeTokens['palette'] ?? null) ? $themeTokens['palette'] : [];
        $rules = is_array($themeTokens['contrast_rules'] ?? null) ? $themeTokens['contrast_rules'] : [];
        $motion = is_array($themeTokens['motion'] ?? null) ? $themeTokens['motion'] : [];

        $bodyRatio = $this->contrastRatio(
            (string) ($palette['text_primary'] ?? ''),
            (string) ($palette['page_background'] ?? ''),
        );
        $largeTextRatio = $this->contrastRatio(
            (string) ($palette['text_secondary'] ?? ''),
            (string) ($palette['page_background'] ?? ''),
        );
        $uiRatio = $this->contrastRatio(
            (string) ($palette['button_text'] ?? ''),
            (string) ($palette['button_fill'] ?? ''),
        );

        $checks = [
            'body_text' => [
                'ratio' => $bodyRatio,
                'minimum' => (float) ($rules['body_text_min_ratio'] ?? 4.5),
                'passes' => $bodyRatio !== null && $bodyRatio >= (float) ($rules['body_text_min_ratio'] ?? 4.5),
            ],
            'large_text' => [
                'ratio' => $largeTextRatio,
                'minimum' => (float) ($rules['large_text_min_ratio'] ?? 3),
                'passes' => $largeTextRatio !== null && $largeTextRatio >= (float) ($rules['large_text_min_ratio'] ?? 3),
            ],
            'ui' => [
                'ratio' => $uiRatio,
                'minimum' => (float) ($rules['ui_min_ratio'] ?? 3),
                'passes' => $uiRatio !== null && $uiRatio >= (float) ($rules['ui_min_ratio'] ?? 3),
            ],
        ];

        $motionPasses = ($motion['respect_user_preference'] ?? false) === true;

        return [
            'passes' => collect($checks)->every(fn (array $check) => $check['passes']) && $motionPasses,
            'checks' => $checks,
            'motion' => [
                'respect_user_preference' => $motionPasses,
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function hexToRgb(string $hex): ?array
    {
        if (! preg_match('/^#([0-9A-Fa-f]{6})$/', $hex, $matches)) {
            return null;
        }

        return [
            hexdec(substr($matches[1], 0, 2)),
            hexdec(substr($matches[1], 2, 2)),
            hexdec(substr($matches[1], 4, 2)),
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function relativeLuminance(array $rgb): float
    {
        $channels = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
