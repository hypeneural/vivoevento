<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadEventGalleryAssetAction
{
    public function __construct(
        private readonly UpdateEventGallerySettingsAction $updateSettingsAction,
    ) {}

    /**
     * @return array{kind: string, path: string, url: string, settings: EventGallerySetting}
     */
    public function execute(
        Event $event,
        EventGallerySetting $settings,
        UploadedFile $file,
        string $kind,
        ?string $previousPath = null,
        ?User $user = null,
    ): array {
        [$directory, $width, $height] = $this->assetProfile($event->id, $kind);
        $filename = Str::random(24).'.webp';
        $storedPath = "{$directory}/{$filename}";
        $currentPath = $this->currentAssetPath($settings, $kind);

        $image = Image::decode($file)
            ->cover($width, $height)
            ->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $image);

        try {
            $settings = $this->updateSettingsAction->execute(
                $event,
                $settings,
                [
                    'page_schema' => [
                        'blocks' => [
                            $this->blockKey($kind) => [
                                'enabled' => true,
                                'image_path' => $storedPath,
                            ],
                        ],
                    ],
                ],
                $user,
            );
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPath);

            throw $exception;
        }

        $this->deletePreviousAsset(
            $event->id,
            $kind,
            $previousPath ?? $currentPath,
            $storedPath,
        );

        return [
            'kind' => $kind,
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
            'settings' => $settings,
        ];
    }

    private function blockKey(string $kind): string
    {
        return $kind === 'banner' ? 'banner_strip' : 'hero';
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function assetProfile(int $eventId, string $kind): array
    {
        return match ($kind) {
            'banner' => ["events/gallery/{$eventId}/banner", 1600, 480],
            default => ["events/gallery/{$eventId}/hero", 1600, 900],
        };
    }

    private function currentAssetPath(EventGallerySetting $settings, string $kind): ?string
    {
        $path = data_get($settings->page_schema_json, 'blocks.'.$this->blockKey($kind).'.image_path');

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    private function deletePreviousAsset(int $eventId, string $kind, ?string $previousPath, string $storedPath): void
    {
        if (blank($previousPath) || $previousPath === $storedPath) {
            return;
        }

        if (preg_match('/^https?:\/\//i', $previousPath) === 1) {
            return;
        }

        $expectedDirectory = "events/gallery/{$eventId}/{$kind}/";

        if (! str_starts_with($previousPath, $expectedDirectory)) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
