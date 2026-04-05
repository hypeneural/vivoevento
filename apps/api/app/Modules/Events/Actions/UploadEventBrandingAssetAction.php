<?php

namespace App\Modules\Events\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadEventBrandingAssetAction
{
    public function execute(
        User $user,
        UploadedFile $file,
        string $kind,
        ?string $previousPath = null,
    ): array {
        $organization = $user->currentOrganization();

        if ($organization === null) {
            abort(422, 'Nenhuma organizacao ativa encontrada para salvar o arquivo.');
        }

        $directory = "events/branding/{$organization->id}/{$kind}";
        $filename = Str::random(24) . '.webp';
        $storedPath = "{$directory}/{$filename}";

        $image = Image::decode($file);

        if ($kind === 'cover') {
            $image = $image->cover(1600, 900);
        } else {
            $image = $image->scaleDown(width: 720, height: 720);
        }

        $encodedImage = $image->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $encodedImage);

        $this->deletePreviousAsset($organization->id, $kind, $previousPath);

        return [
            'kind' => $kind,
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
        ];
    }

    private function deletePreviousAsset(int $organizationId, string $kind, ?string $previousPath): void
    {
        if (blank($previousPath)) {
            return;
        }

        if (preg_match('/^https?:\/\//i', $previousPath) === 1) {
            return;
        }

        $expectedDirectory = "events/branding/{$organizationId}/{$kind}/";

        if (! str_starts_with($previousPath, $expectedDirectory)) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
