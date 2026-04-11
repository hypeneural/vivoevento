<?php

namespace App\Modules\Play\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Models\EventMediaVariant;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Modules\Play\Enums\PlayGameTypeKey;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Support\RuntimeAssetProfile;
use Illuminate\Support\Collection;

class GameAssetResolverService
{
    public function __construct(
        private readonly MediaAssetUrlService $mediaAssets,
    ) {}

    public function resolve(PlayEventGame $game, ?RuntimeAssetProfile $profile = null): array
    {
        $game->loadMissing([
            'assets.media.variants',
            'gameType',
            'event.media.variants',
        ]);
        $gameKey = $game->gameType?->key?->value ?? $game->gameType?->key;

        if ($game->assets->isNotEmpty()) {
            return $game->assets
                ->filter(fn ($asset) => $asset->media !== null)
                ->filter(fn ($asset) => $this->supportsGameMedia($asset->media, $gameKey))
                ->map(fn ($asset) => $this->buildAssetPayload(
                    media: $asset->media,
                    gameKey: $gameKey,
                    profile: $profile,
                    role: $asset->role,
                    sortOrder: $asset->sort_order,
                ))
            ->values()
            ->all();
        }

        return $this->fallbackMedia($game, $gameKey)
            ->map(fn (EventMedia $media) => $this->buildAssetPayload(
                media: $media,
                gameKey: $gameKey,
                profile: $profile,
                role: 'primary',
                sortOrder: 0,
            ))
            ->values()
            ->all();
    }

    public function hasPlayableAssets(PlayEventGame $game, ?RuntimeAssetProfile $profile = null): bool
    {
        return count($this->resolve($game, $profile)) >= $this->minimumPlayableAssetCount($game);
    }

    public function unavailableReason(PlayEventGame $game, ?RuntimeAssetProfile $profile = null): ?string
    {
        if ($this->hasPlayableAssets($game, $profile)) {
            return null;
        }

        $gameKey = $game->gameType?->key?->value ?? $game->gameType?->key;

        return match ($gameKey) {
            PlayGameTypeKey::Puzzle->value => 'puzzle.no_image_available',
            PlayGameTypeKey::Memory->value => 'memory.not_enough_images',
            default => 'play.no_playable_assets',
        };
    }

    /**
     * @return Collection<int, EventMedia>
     */
    private function fallbackMedia(PlayEventGame $game, ?string $gameKey): Collection
    {
        $limit = $this->fallbackLimit($game);

        return $game->event->media()
            ->approved()
            ->published()
            ->with('variants')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(max($limit * 6, 12))
            ->get()
            ->filter(fn (EventMedia $media) => $this->supportsGameMedia($media, $gameKey))
            ->sortByDesc(fn (EventMedia $media) => $this->fallbackScore($media, $game))
            ->take($limit)
            ->values();
    }

    private function fallbackScore(EventMedia $media, PlayEventGame $game): int
    {
        $longEdge = max((int) ($media->width ?? 0), (int) ($media->height ?? 0));
        $isPortrait = (int) ($media->height ?? 0) > (int) ($media->width ?? 0);
        $isSquare = (int) ($media->height ?? 0) === (int) ($media->width ?? 0);
        $gameKey = $game->gameType?->key?->value ?? $game->gameType?->key;

        $score = $media->is_featured ? 30 : 0;
        $score += $longEdge >= 1600 ? 18 : ($longEdge >= 1200 ? 12 : 0);

        if ($gameKey === PlayGameTypeKey::Puzzle->value) {
            $score += $isPortrait ? 12 : 6;
        } else {
            $score += $isSquare ? 10 : ($isPortrait ? 8 : 4);
        }

        return $score;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAssetPayload(
        EventMedia $media,
        ?string $gameKey,
        ?RuntimeAssetProfile $profile,
        string $role,
        int $sortOrder,
    ): array {
        $variant = $this->resolveVariant($media, $gameKey, $profile);
        $url = $variant?->path
            ? $this->mediaAssets->toPublicUrl($variant->path, $variant->disk ?: 'public')
            : $this->mediaAssets->resolve($media);
        $width = $variant?->width ?? $media->width;
        $height = $variant?->height ?? $media->height;

        return [
            'id' => (string) $media->id,
            'url' => $url,
            'width' => $width,
            'height' => $height,
            'mimeType' => $variant?->mime_type ?? $media->mime_type,
            'role' => $role,
            'sortOrder' => $sortOrder,
            'orientation' => $this->orientation($width, $height),
            'variantKey' => $variant?->variant_key,
            'deliveryProfile' => $profile?->bucket() ?? 'default',
            'sourceWidth' => $media->width,
            'sourceHeight' => $media->height,
        ];
    }

    private function resolveVariant(
        EventMedia $media,
        ?string $gameKey,
        ?RuntimeAssetProfile $profile,
    ): ?EventMediaVariant {
        if (! $media->relationLoaded('variants')) {
            $media->load('variants');
        }

        $variants = $media->variants;

        if ($variants->isEmpty()) {
            return null;
        }

        $preferredKeys = $profile?->preferredVariantKeys($gameKey)
            ?? $this->defaultVariantKeys($gameKey);

        foreach ($preferredKeys as $variantKey) {
            $variant = $variants->firstWhere('variant_key', $variantKey);

            if ($variant?->path) {
                return $variant;
            }
        }

        return $variants
            ->filter(fn (EventMediaVariant $variant) => ! empty($variant->path))
            ->sortByDesc(fn (EventMediaVariant $variant) => (int) max($variant->width ?? 0, $variant->height ?? 0))
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function defaultVariantKeys(?string $gameKey): array
    {
        return match ($gameKey) {
            PlayGameTypeKey::Puzzle->value => ['gallery', 'wall', 'fast_preview', 'thumb'],
            PlayGameTypeKey::Memory->value => ['gallery', 'fast_preview', 'thumb', 'wall'],
            default => ['gallery', 'fast_preview', 'wall', 'thumb'],
        };
    }

    private function orientation(?int $width, ?int $height): ?string
    {
        if (! $width || ! $height) {
            return null;
        }

        if ($height > $width) {
            return 'portrait';
        }

        if ($width > $height) {
            return 'landscape';
        }

        return 'square';
    }

    private function fallbackLimit(PlayEventGame $game): int
    {
        $gameKey = $game->gameType?->key?->value ?? $game->gameType?->key;
        $settings = $game->settings_json ?? [];

        return match ($gameKey) {
            PlayGameTypeKey::Memory->value => max(2, (int) ($settings['pairsCount'] ?? 6)),
            PlayGameTypeKey::Puzzle->value => 1,
            default => 8,
        };
    }

    private function minimumPlayableAssetCount(PlayEventGame $game): int
    {
        $gameKey = $game->gameType?->key?->value ?? $game->gameType?->key;
        $settings = $game->settings_json ?? [];

        return match ($gameKey) {
            PlayGameTypeKey::Memory->value => max(2, (int) ($settings['pairsCount'] ?? 6)),
            PlayGameTypeKey::Puzzle->value => 1,
            default => 1,
        };
    }

    private function supportsGameMedia(EventMedia $media, ?string $gameKey): bool
    {
        return match ($gameKey) {
            PlayGameTypeKey::Memory->value,
            PlayGameTypeKey::Puzzle->value => $this->isImageMedia($media),
            default => true,
        };
    }

    private function isImageMedia(EventMedia $media): bool
    {
        $mediaType = strtolower(trim((string) ($media->media_type ?? '')));
        $mimeType = strtolower(trim((string) ($media->mime_type ?? '')));

        return $mediaType === 'image' || str_starts_with($mimeType, 'image/');
    }
}
