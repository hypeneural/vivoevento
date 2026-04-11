<?php

namespace App\Modules\Events\Support;

use App\Modules\Events\Models\Event;
use Illuminate\Support\Facades\Storage;

class EventBrandingResolver
{
    public function resolve(Event $event): array
    {
        $event->loadMissing('organization');

        $organization = $event->organization;
        $inherits = (bool) ($event->inherit_branding ?? true);

        $eventBranding = [
            'logo_path' => $event->logo_path,
            'cover_image_path' => $event->cover_image_path,
            'primary_color' => $event->primary_color,
            'secondary_color' => $event->secondary_color,
        ];

        if (! $inherits || ! $organization) {
            return $this->withUrls($eventBranding + [
                'source' => 'event',
                'inherits_from_organization' => false,
            ]);
        }

        $organizationBranding = [
            'logo_path' => $organization->logo_path,
            'cover_image_path' => $organization->cover_path,
            'primary_color' => $organization->primary_color,
            'secondary_color' => $organization->secondary_color,
        ];

        $resolved = [
            'logo_path' => $eventBranding['logo_path'] ?: $organizationBranding['logo_path'],
            'cover_image_path' => $eventBranding['cover_image_path'] ?: $organizationBranding['cover_image_path'],
            'primary_color' => $eventBranding['primary_color'] ?: $organizationBranding['primary_color'],
            'secondary_color' => $eventBranding['secondary_color'] ?: $organizationBranding['secondary_color'],
        ];

        $hasEventValue = collect($eventBranding)->filter(fn ($value) => filled($value))->isNotEmpty();
        $hasOrganizationFallback = collect($resolved)
            ->filter(fn ($value, $key) => blank($eventBranding[$key]) && filled($value))
            ->isNotEmpty();

        $source = match (true) {
            $hasEventValue && $hasOrganizationFallback => 'mixed',
            $hasEventValue => 'event',
            default => 'organization',
        };

        return $this->withUrls($resolved + [
            'source' => $source,
            'inherits_from_organization' => true,
        ]);
    }

    private function withUrls(array $branding): array
    {
        $branding['logo_url'] = $this->assetUrl($branding['logo_path'] ?? null);
        $branding['cover_image_url'] = $this->assetUrl($branding['cover_image_path'] ?? null);

        return $branding;
    }

    private function assetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $url = Storage::disk('public')->url($path);

        return preg_match('/^https?:\/\//i', $url) === 1
            ? $url
            : url($url);
    }
}
