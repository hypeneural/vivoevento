<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

class GalleryModelMatrixRegistry
{
    /**
     * @return array<string, array<int, string>>
     */
    public function options(): array
    {
        return [
            'event_type_family' => GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES,
            'style_skin' => GalleryBuilderSchemaRegistry::STYLE_SKINS,
            'behavior_profile' => GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function derive(string $eventTypeFamily, string $styleSkin, string $behaviorProfile): array
    {
        $eventTypeFamily = $this->allowedOrDefault(
            $eventTypeFamily,
            GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES,
            'wedding',
        );
        $styleSkin = $this->allowedOrDefault(
            $styleSkin,
            GalleryBuilderSchemaRegistry::STYLE_SKINS,
            'romantic',
        );
        $behaviorProfile = $this->allowedOrDefault(
            $behaviorProfile,
            GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES,
            'story',
        );

        $layoutKey = $this->layoutKey($eventTypeFamily, $behaviorProfile);
        $themeKey = $this->themeKey($eventTypeFamily, $styleSkin);

        return [
            'event_type_family' => $eventTypeFamily,
            'style_skin' => $styleSkin,
            'behavior_profile' => $behaviorProfile,
            'derived_preset_key' => "{$eventTypeFamily}.{$styleSkin}.{$behaviorProfile}",
            'theme_key' => $themeKey,
            'layout_key' => $layoutKey,
            'grid_layout' => $this->gridLayout($layoutKey),
            'video_mode' => $behaviorProfile === 'live' ? 'inline_preview' : 'poster_to_modal',
            'interstitial_policy' => $behaviorProfile === 'sponsors' ? 'sponsors' : 'disabled',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fixtures(): array
    {
        return [
            'wedding.romantic.story' => $this->derive('wedding', 'romantic', 'story'),
            'wedding.premium.light' => $this->derive('wedding', 'premium', 'light'),
            'quince.modern.live' => $this->derive('quince', 'modern', 'live'),
            'corporate.clean.sponsors' => $this->derive('corporate', 'clean', 'sponsors'),
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function allowedOrDefault(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function themeKey(string $eventTypeFamily, string $styleSkin): string
    {
        if ($eventTypeFamily === 'wedding') {
            return $styleSkin === 'premium' ? 'black-tie' : 'wedding-rose';
        }

        if ($eventTypeFamily === 'quince') {
            return 'quince-glam';
        }

        return $styleSkin === 'premium' ? 'black-tie' : 'corporate-clean';
    }

    private function layoutKey(string $eventTypeFamily, string $behaviorProfile): string
    {
        if ($behaviorProfile === 'live') {
            return 'live-stream';
        }

        if ($behaviorProfile === 'story') {
            return $eventTypeFamily === 'corporate' ? 'timeless-rows' : 'justified-story';
        }

        if ($eventTypeFamily === 'corporate') {
            return 'timeless-rows';
        }

        return 'editorial-masonry';
    }

    private function gridLayout(string $layoutKey): string
    {
        return match ($layoutKey) {
            'timeless-rows' => 'rows',
            'clean-columns' => 'columns',
            'justified-story' => 'justified',
            default => 'masonry',
        };
    }
}
