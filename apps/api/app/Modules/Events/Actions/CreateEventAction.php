<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Shared\Support\Helpers;

class CreateEventAction
{
    /**
     * Create a new event with branding, modules, and links.
     */
    public function execute(array $data, int $userId): Event
    {
        // Extract nested objects
        $branding = $data['branding'] ?? [];
        $modules = $data['modules'] ?? [];
        $privacy = $data['privacy'] ?? [];

        // Remove nested from main data
        unset($data['branding'], $data['modules'], $data['privacy']);

        // Build event data
        $data['slug'] = $data['slug'] ?? Helpers::generateUniqueSlug($data['title'], Event::class);
        $data['status'] = EventStatus::Draft;
        $data['created_by'] = $userId;

        // Apply branding
        if (!empty($branding)) {
            $data['primary_color'] = $branding['primary_color'] ?? null;
            $data['secondary_color'] = $branding['secondary_color'] ?? null;
            $data['logo_path'] = $branding['logo_path'] ?? null;
        }

        // Apply privacy
        $data['visibility'] = $privacy['visibility'] ?? 'public';
        $data['moderation_mode'] = $privacy['moderation_mode'] ?? 'manual';
        $data['retention_days'] = $privacy['retention_days'] ?? 30;

        // Create the event (uuid and upload_slug auto-generated in boot)
        $event = Event::create($data);

        // Generate URLs
        $baseUrl = rtrim((string) config('app.url'), '/');
        $event->update([
            'public_url' => $baseUrl . '/e/' . $event->slug,
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

        // Log activity
        $user = \App\Modules\Users\Models\User::find($userId);
        if ($user) {
            activity()
                ->performedOn($event)
                ->causedBy($user)
                ->withProperties(['event_id' => $event->id])
                ->log('Evento criado');
        }

        return $event->fresh()->load('modules');
    }
}
