<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Support\EventCommercialStatusService;
use App\Modules\FaceSearch\Actions\UpsertEventFaceSearchSettingsAction;
use App\Modules\Hub\Support\HubPayloadFactory;
use App\Shared\Support\Helpers;

class UpdateEventAction
{
    public function __construct(
        private readonly HubPayloadFactory $hubPayloads,
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly UpsertEventFaceSearchSettingsAction $upsertFaceSearchSettings,
    ) {}

    public function execute(Event $event, array $data): Event
    {
        $branding = $data['branding'] ?? [];
        $modules = $data['modules'] ?? [];
        $privacy = $data['privacy'] ?? [];
        $faceSearch = $data['face_search'] ?? null;

        unset($data['branding'], $data['modules'], $data['privacy'], $data['face_search']);

        if (! empty($branding)) {
            if (array_key_exists('primary_color', $branding)) {
                $data['primary_color'] = $branding['primary_color'];
            }

            if (array_key_exists('secondary_color', $branding)) {
                $data['secondary_color'] = $branding['secondary_color'];
            }

            if (array_key_exists('cover_image_path', $branding)) {
                $data['cover_image_path'] = $branding['cover_image_path'];
            }

            if (array_key_exists('logo_path', $branding)) {
                $data['logo_path'] = $branding['logo_path'];
            }
        }

        if (! empty($privacy)) {
            if (array_key_exists('visibility', $privacy)) {
                $data['visibility'] = $privacy['visibility'];
            }

            if (array_key_exists('moderation_mode', $privacy)) {
                $data['moderation_mode'] = $privacy['moderation_mode'];
            }

            if (array_key_exists('retention_days', $privacy)) {
                $data['retention_days'] = $privacy['retention_days'];
            }
        }

        if (array_key_exists('slug', $data) && blank($data['slug'])) {
            $data['slug'] = Helpers::generateUniqueSlug(
                $data['title'] ?? $event->title,
                Event::class,
                'slug',
                $event->id,
            );
        }

        $event->update($data);

        foreach (['live', 'wall', 'play', 'hub'] as $moduleKey) {
            if (! array_key_exists($moduleKey, $modules)) {
                continue;
            }

            EventModule::query()->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'module_key' => $moduleKey,
                ],
                [
                    'is_enabled' => (bool) $modules[$moduleKey],
                ]
            );
        }

        if (is_array($faceSearch)) {
            $this->upsertFaceSearchSettings->execute($event, $faceSearch);
        }

        $event->update([
            'public_url' => $event->publicHubUrl(),
            'upload_url' => $event->publicUploadUrl(),
        ]);

        $event = $event->fresh()->loadMissing(['modules', 'wallSettings']);
        $this->hubPayloads->ensureSettings($event);

        return $this->commercialStatus->sync(
            $event->fresh()->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings'])
        )->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings']);
    }
}
