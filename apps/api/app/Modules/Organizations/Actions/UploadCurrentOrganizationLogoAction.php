<?php

namespace App\Modules\Organizations\Actions;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UploadCurrentOrganizationLogoAction
{
    public function execute(Organization $organization, User $actor, UploadedFile $file): array
    {
        $previousPath = $organization->logo_path;
        $directory = "organizations/branding/{$organization->id}/logo";
        $filename = Str::random(24) . '.webp';
        $storedPath = "{$directory}/{$filename}";

        $encodedImage = Image::decode($file)
            ->scaleDown(width: 720, height: 720)
            ->encodeUsingMediaType('image/webp', 85);

        Storage::disk('public')->put($storedPath, (string) $encodedImage);

        $organization->update([
            'logo_path' => $storedPath,
        ]);

        $this->deletePreviousLogo($organization->id, $previousPath);

        activity()
            ->performedOn($organization)
            ->causedBy($actor)
            ->withProperties([
                'organization_id' => $organization->id,
                'logo_path' => $storedPath,
            ])
            ->log('Logo da organizacao atualizado');

        return [
            'logo_path' => $storedPath,
            'logo_url' => Storage::disk('public')->url($storedPath),
        ];
    }

    private function deletePreviousLogo(int $organizationId, ?string $previousPath): void
    {
        if (blank($previousPath)) {
            return;
        }

        if (preg_match('/^https?:\/\//i', $previousPath) === 1) {
            return;
        }

        $expectedDirectory = "organizations/branding/{$organizationId}/logo/";

        if (! str_starts_with($previousPath, $expectedDirectory)) {
            return;
        }

        Storage::disk('public')->delete($previousPath);
    }
}
