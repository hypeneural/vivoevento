<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\Events\Support\EventCommercialStatusService;
use App\Modules\FaceSearch\Actions\UpsertEventFaceSearchSettingsAction;
use App\Modules\Hub\Support\HubPayloadFactory;
use App\Shared\Support\Helpers;
use Illuminate\Support\Facades\DB;

class CreateEventAction
{
    public function __construct(
        private readonly HubPayloadFactory $hubPayloads,
        private readonly EventCommercialStatusService $commercialStatus,
        private readonly UpsertEventFaceSearchSettingsAction $upsertFaceSearchSettings,
        private readonly SyncEventIntakeChannelsAction $syncEventIntakeChannels,
        private readonly SyncEventIntakeBlacklistAction $syncEventIntakeBlacklist,
    ) {}

    /**
     * Create a new event with branding, modules, and links.
     */
    public function execute(array $data, int $userId): Event
    {
        return DB::transaction(function () use ($data, $userId) {
            $intakeChannels = $data['intake_channels'] ?? null;
            $intakeDefaults = $data['intake_defaults'] ?? null;
            $intakeBlacklist = $data['intake_blacklist'] ?? null;

            // Extract nested objects
            $branding = $data['branding'] ?? [];
            $modules = $data['modules'] ?? [];
            $privacy = $data['privacy'] ?? [];
            $faceSearch = $data['face_search'] ?? null;

            // Remove nested from main data
            unset($data['branding'], $data['modules'], $data['privacy'], $data['face_search'], $data['intake_channels'], $data['intake_defaults'], $data['intake_blacklist']);

            // Build event data
            $data['slug'] = $data['slug'] ?? Helpers::generateUniqueSlug($data['title'], Event::class);
            $data['status'] = EventStatus::Draft;
            $data['created_by'] = $userId;

            // Apply branding
            if (!empty($branding)) {
                $data['primary_color'] = $branding['primary_color'] ?? null;
                $data['secondary_color'] = $branding['secondary_color'] ?? null;
                $data['cover_image_path'] = $branding['cover_image_path'] ?? null;
                $data['logo_path'] = $branding['logo_path'] ?? null;
            }

            $data['inherit_branding'] = array_key_exists('inherit_branding', $branding)
                ? (bool) $branding['inherit_branding']
                : (bool) ($data['inherit_branding'] ?? true);

            // Apply privacy
            $data['visibility'] = $privacy['visibility'] ?? 'public';
            $data['moderation_mode'] = $privacy['moderation_mode'] ?? 'manual';
            $data['retention_days'] = $privacy['retention_days'] ?? 30;

            // Create the event (uuid and upload_slug auto-generated in boot)
            $event = Event::create($data);

            // Generate URLs
            $event->update([
                'public_url' => $event->publicHubUrl(),
                'upload_url' => $event->publicUploadUrl(),
            ]);

            // Create modules
            $moduleKeys = [
                'live' => $modules['live'] ?? true,
                'wall' => $modules['wall'] ?? false,
                'play' => $modules['play'] ?? false,
                'hub' => $modules['hub'] ?? true,
            ];

            foreach ($moduleKeys as $key => $enabled) {
                EventModule::create([
                    'event_id' => $event->id,
                    'module_key' => $key,
                    'is_enabled' => $enabled,
                ]);
            }

            if (is_array($faceSearch)) {
                $this->upsertFaceSearchSettings->execute($event, $faceSearch);
            }

            $this->hubPayloads->ensureSettings(
                $event->fresh()->loadMissing(['modules', 'wallSettings'])
            );

            // Log activity
            $user = \App\Modules\Users\Models\User::find($userId);
            if ($user) {
                activity()
                    ->performedOn($event)
                    ->causedBy($user)
                    ->withProperties(['event_id' => $event->id])
                    ->log('Evento criado');
            }

            $event = $this->commercialStatus->sync(
                $event->fresh()->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings'])
            )->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings']);

            $event = $this->syncEventIntakeChannels->execute($event, $intakeChannels, $intakeDefaults);
            $event = $this->syncEventIntakeBlacklist->execute($event, $intakeBlacklist);

            return $event->load(['modules', 'faceSearchSettings', 'mediaIntelligenceSettings', 'channels', 'whatsappGroupBindings', 'defaultWhatsAppInstance', 'mediaSenderBlacklists']);
        });
    }
}
