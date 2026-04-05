<?php

namespace App\Modules\Events\Http\Resources;

use App\Modules\Events\Support\EventPublicLinksService;
use App\Modules\Hub\Support\HubPayloadFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = (new EventResource($this->resource))->toArray($request);
        $links = app(EventPublicLinksService::class)->links($this->resource);

        return array_merge($base, [
            'module_flags' => [
                'live' => $this->isModuleEnabled('live'),
                'wall' => $this->isModuleEnabled('wall'),
                'play' => $this->isModuleEnabled('play'),
                'hub' => $this->isModuleEnabled('hub'),
            ],
            'menu' => [
                ['key' => 'overview', 'label' => 'Visao Geral', 'visible' => true],
                ['key' => 'uploads', 'label' => 'Uploads', 'visible' => $this->isModuleEnabled('live')],
                ['key' => 'moderation', 'label' => 'Moderacao', 'visible' => $this->isModuleEnabled('live')],
                ['key' => 'gallery', 'label' => 'Galeria', 'visible' => $this->isModuleEnabled('live')],
                ['key' => 'wall', 'label' => 'Wall', 'visible' => $this->isModuleEnabled('wall')],
                ['key' => 'play', 'label' => 'Play', 'visible' => $this->isModuleEnabled('play')],
                ['key' => 'hub', 'label' => 'Hub', 'visible' => $this->isModuleEnabled('hub')],
                ['key' => 'analytics', 'label' => 'Analytics', 'visible' => $this->isModuleEnabled('live')],
            ],
            'stats' => [
                'media_total' => $this->media_count ?? 0,
                'media_pending' => $this->pending_media_count ?? 0,
                'media_approved' => $this->approved_media_count ?? 0,
                'media_published' => $this->published_media_count ?? 0,
                'active_modules' => $this->module_count ?? 0,
            ],
            'public_links' => $links['links'],
            'public_identifiers' => $links['identifiers'],
            'wall' => $this->whenLoaded('wallSettings', fn () => $this->wallSettings ? [
                'id' => $this->wallSettings->id,
                'wall_code' => $this->wallSettings->wall_code,
                'is_enabled' => (bool) $this->wallSettings->is_enabled,
                'status' => $this->wallSettings->status?->value,
                'public_url' => $this->wallSettings->publicUrl(),
            ] : null),
            'play' => $this->whenLoaded('playSettings', fn () => $this->playSettings ? [
                'id' => $this->playSettings->id,
                'is_enabled' => (bool) $this->playSettings->is_enabled,
                'memory_enabled' => (bool) $this->playSettings->memory_enabled,
                'puzzle_enabled' => (bool) $this->playSettings->puzzle_enabled,
                'ranking_enabled' => (bool) $this->playSettings->ranking_enabled,
            ] : null),
            'hub' => $this->whenLoaded('hubSettings', function () {
                if (! $this->hubSettings) {
                    return null;
                }

                $hubSettings = app(HubPayloadFactory::class)->admin($this->resource, $this->hubSettings)['settings'];

                return [
                    'id' => $this->hubSettings->id,
                    'is_enabled' => (bool) $this->hubSettings->is_enabled,
                    'headline' => $this->hubSettings->headline,
                    'subheadline' => $this->hubSettings->subheadline,
                    'welcome_text' => $this->hubSettings->welcome_text,
                    'hero_image_path' => $this->hubSettings->hero_image_path,
                    'hero_image_url' => $hubSettings['hero_image_url'],
                    'button_style' => $hubSettings['button_style'],
                    'buttons' => $hubSettings['buttons'],
                    'builder_config' => $hubSettings['builder_config'],
                    'show_gallery_button' => (bool) $this->hubSettings->show_gallery_button,
                    'show_upload_button' => (bool) $this->hubSettings->show_upload_button,
                    'show_wall_button' => (bool) $this->hubSettings->show_wall_button,
                    'show_play_button' => (bool) $this->hubSettings->show_play_button,
                ];
            }),
        ]);
    }
}
