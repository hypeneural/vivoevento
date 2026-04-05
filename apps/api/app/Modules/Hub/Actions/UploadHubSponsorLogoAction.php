<?php

namespace App\Modules\Hub\Actions;

use App\Modules\Events\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadHubSponsorLogoAction
{
    public function execute(
        Event $event,
        UploadedFile $file,
        ?string $previousPath = null,
    ): array {
        $directory = "events/hub/{$event->id}/sponsors";
        $filename = Str::random(24) . '.webp';
        $storedPath = "{$directory}/{$filename}";

        $image = Image::decode($file)
            ->scaleDown(width: 720, height: 720)
            ->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $image);

        $this->deletePreviousAsset($event->id, $previousPath);

        return [
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
        ];
    }

    private function deletePreviousAsset(int $eventId, ?string $previousPath): void
    {
        if (blank($previousPath)) {
            return;
        }

        if (preg_match('/^https?:\/\//i', $previousPath) === 1) {
            return;
        }

        $expectedDirectory = "events/hub/{$eventId}/sponsors/";

        if (! str_starts_with($previousPath, $expectedDirectory)) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
