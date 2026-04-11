<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadCurrentOrganizationBrandingAssetAction
{
    private const COLUMN_BY_KIND = [
        'logo' => 'logo_path',
        'logo_dark' => 'logo_dark_path',
        'favicon' => 'favicon_path',
        'watermark' => 'watermark_path',
        'cover' => 'cover_path',
    ];

    public function execute(Organization $organization, User $actor, string $kind, UploadedFile $file): array
    {
        $column = self::COLUMN_BY_KIND[$kind];
        $previousPath = $organization->{$column};
        $directory = "organizations/branding/{$organization->id}/{$kind}";
        $storedPath = "{$directory}/" . Str::random(24) . '.webp';
        [$width, $height] = $this->targetSize($kind);

        $encodedImage = Image::decode($file)
            ->scaleDown(width: $width, height: $height)
            ->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $encodedImage);

        $organization->update([
            $column => $storedPath,
        ]);

        $this->deletePreviousAsset($organization->id, $kind, $previousPath);

        activity()
            ->performedOn($organization)
            ->causedBy($actor)
            ->withProperties([
                'organization_id' => $organization->id,
                'kind' => $kind,
                'path' => $storedPath,
            ])
            ->log('Ativo de branding da organizacao atualizado');

        return [
            'kind' => $kind,
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
        ];
    }

    private function targetSize(string $kind): array
    {
        return match ($kind) {
            'cover' => [1920, 1080],
            'watermark' => [1200, 1200],
            'favicon' => [512, 512],
            default => [720, 720],
        };
    }

    private function deletePreviousAsset(int $organizationId, string $kind, ?string $previousPath): void
    {
        if (blank($previousPath)) {
            return;
        }

        if (preg_match('/^https?:\/\//i', $previousPath) === 1) {
            return;
        }

        $expectedDirectory = "organizations/branding/{$organizationId}/{$kind}/";

        if (! str_starts_with($previousPath, $expectedDirectory)) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
