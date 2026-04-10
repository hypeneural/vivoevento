<?php

namespace App\Modules\Wall\Enums;

enum WallLayout: string
{
    case Auto = 'auto';
    case Polaroid = 'polaroid';
    case Fullscreen = 'fullscreen';
    case Split = 'split';
    case Cinematic = 'cinematic';
    case KenBurns = 'kenburns';
    case Spotlight = 'spotlight';
    case Gallery = 'gallery';
    case Carousel = 'carousel';
    case Mosaic = 'mosaic';
    case Grid = 'grid';
    case Puzzle = 'puzzle';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automático',
            self::Polaroid => 'Polaroid',
            self::Fullscreen => 'Tela cheia',
            self::Split => 'Dividido',
            self::Cinematic => 'Cinematográfico',
            self::KenBurns => 'Ken Burns',
            self::Spotlight => 'Holofote',
            self::Gallery => 'Galeria de arte',
            self::Carousel => 'Carrossel',
            self::Mosaic => 'Mosaico',
            self::Grid => 'Grade',
            self::Puzzle => 'Quebra Cabeca',
        };
    }

    public function isEnabled(): bool
    {
        return match ($this) {
            self::Puzzle => (bool) config('wall.layouts.puzzle.enabled', false),
            default => true,
        };
    }

    public function capabilities(): array
    {
        return match ($this) {
            self::Puzzle => [
                'supports_video_playback' => false,
                'supports_video_poster_only' => false,
                'supports_multi_video' => false,
                'max_simultaneous_videos' => 0,
                'fallback_video_layout' => self::Cinematic->value,
                'supports_side_thumbnails' => false,
                'supports_floating_caption' => false,
                'supports_theme_config' => true,
            ],
            self::Carousel, self::Mosaic, self::Grid => [
                'supports_video_playback' => false,
                'supports_video_poster_only' => true,
                'supports_multi_video' => false,
                'max_simultaneous_videos' => 0,
                'fallback_video_layout' => self::Fullscreen->value,
                'supports_side_thumbnails' => false,
                'supports_floating_caption' => false,
                'supports_theme_config' => false,
            ],
            default => [
                'supports_video_playback' => true,
                'supports_video_poster_only' => false,
                'supports_multi_video' => false,
                'max_simultaneous_videos' => 1,
                'fallback_video_layout' => null,
                'supports_side_thumbnails' => true,
                'supports_floating_caption' => true,
                'supports_theme_config' => false,
            ],
        };
    }

    public function defaults(): array
    {
        return match ($this) {
            self::Puzzle => [
                'theme_config' => [
                    'preset' => 'standard',
                    'anchor_mode' => 'event_brand',
                    'burst_intensity' => 'normal',
                    'hero_enabled' => true,
                    'video_behavior' => 'fallback_single_item',
                ],
            ],
            default => [
                'theme_config' => [],
            ],
        };
    }

    public static function enabledCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $layout): bool => $layout->isEnabled(),
        ));
    }
}
