<?php

namespace App\Modules\Events\Support;

use App\Modules\Events\Models\Event;
use App\Modules\Wall\Models\EventWallSetting;
use App\Shared\Support\Helpers;
use Illuminate\Validation\ValidationException;

class EventPublicLinksService
{
    /**
     * Keep persisted public URLs aligned with the current identifiers.
     */
    public function sync(Event $event): Event
    {
        $event->forceFill([
            'public_url' => $event->publicHubUrl(),
            'upload_url' => $event->publicUploadUrl(),
        ])->save();

        return $event->fresh();
    }

    /**
     * @param array{slug?: string|null, upload_slug?: string|null} $data
     */
    public function updateIdentifiers(Event $event, array $data): Event
    {
        $payload = [];

        if (array_key_exists('slug', $data)) {
            $payload['slug'] = $this->normalizeEventSlug((string) $data['slug'], $event->id);
        }

        if (array_key_exists('upload_slug', $data)) {
            $payload['upload_slug'] = $this->normalizeUploadSlug((string) $data['upload_slug'], $event->id);
        }

        if ($payload !== []) {
            $event->fill($payload);
            $event->save();
        }

        return $this->sync($event);
    }

    /**
     * @param array<int, string> $fields
     */
    public function regenerate(Event $event, array $fields): Event
    {
        $fields = array_values(array_unique($fields));

        if (in_array('slug', $fields, true)) {
            $event->slug = Helpers::generateUniqueSlug(
                $event->title,
                Event::class,
                'slug',
                $event->id,
            );
        }

        if (in_array('upload_slug', $fields, true)) {
            $event->upload_slug = $this->generateUniqueUploadSlug($event->id);
        }

        if ($event->isDirty()) {
            $event->save();
            $event = $this->sync($event);
        }

        if (in_array('wall_code', $fields, true)) {
            $settings = EventWallSetting::query()->firstOrCreate(['event_id' => $event->id]);
            $settings->forceFill([
                'wall_code' => EventWallSetting::generateUniqueCode(),
            ])->save();
        }

        return $event->fresh(['modules', 'wallSettings']);
    }

    /**
     * Build the structured set of public/admin links for the event detail screen.
     */
    public function links(Event $event): array
    {
        $event->loadMissing(['modules', 'wallSettings']);

        $liveEnabled = $event->isModuleEnabled('live');
        $wallEnabled = $event->isModuleEnabled('wall');
        $playEnabled = $event->isModuleEnabled('play');
        $hubEnabled = $event->isModuleEnabled('hub');

        $wallSettings = $event->wallSettings;

        if ($wallEnabled && ! $wallSettings) {
            $wallSettings = EventWallSetting::query()->firstOrCreate(['event_id' => $event->id]);
            $event->setRelation('wallSettings', $wallSettings);
        }

        $links = [
            'gallery' => [
                'key' => 'gallery',
                'label' => 'Galeria Publica',
                'enabled' => $liveEnabled,
                'identifier_type' => 'slug',
                'identifier' => $event->slug,
                'url' => $event->publicGalleryUrl(),
                'api_url' => $event->publicGalleryApiUrl(),
                'qr_value' => $event->publicGalleryUrl(),
            ],
            'upload' => [
                'key' => 'upload',
                'label' => 'Controle Remoto / Upload',
                'enabled' => $liveEnabled,
                'identifier_type' => 'upload_slug',
                'identifier' => $event->upload_slug,
                'url' => $event->publicUploadUrl(),
                'api_url' => $event->publicUploadApiUrl(),
                'qr_value' => $event->publicUploadUrl(),
            ],
            'wall' => [
                'key' => 'wall',
                'label' => 'Telao / Wall',
                'enabled' => $wallEnabled && $wallSettings !== null,
                'identifier_type' => 'wall_code',
                'identifier' => $wallSettings?->wall_code,
                'url' => $wallSettings?->publicUrl(),
                'api_url' => $wallSettings?->wall_code
                    ? url("/api/v1/public/wall/{$wallSettings->wall_code}/boot")
                    : null,
                'qr_value' => $wallSettings?->publicUrl(),
            ],
            'hub' => [
                'key' => 'hub',
                'label' => 'Hub Publico',
                'enabled' => $hubEnabled,
                'identifier_type' => 'slug',
                'identifier' => $event->slug,
                'url' => $event->publicHubUrl(),
                'api_url' => $event->publicHubApiUrl(),
                'qr_value' => $event->publicHubUrl(),
            ],
            'play' => [
                'key' => 'play',
                'label' => 'Play Publico',
                'enabled' => $playEnabled,
                'identifier_type' => 'slug',
                'identifier' => $event->slug,
                'url' => $event->publicPlayUrl(),
                'api_url' => $event->publicPlayApiUrl(),
                'qr_value' => $event->publicPlayUrl(),
            ],
            'find_me' => [
                'key' => 'find_me',
                'label' => 'Buscar Minhas Fotos',
                'enabled' => $liveEnabled && $event->allowsPublicSelfieSearch(),
                'identifier_type' => 'slug',
                'identifier' => $event->slug,
                'url' => $event->publicFindMeUrl(),
                'api_url' => $event->publicFindMeApiUrl(),
                'qr_value' => $event->publicFindMeUrl(),
            ],
        ];

        return [
            'links' => $links,
            'identifiers' => [
                'slug' => [
                    'value' => $event->slug,
                    'editable' => true,
                    'regenerates' => ['gallery', 'hub', 'play'],
                ],
                'upload_slug' => [
                    'value' => $event->upload_slug,
                    'editable' => true,
                    'regenerates' => ['upload'],
                ],
                'wall_code' => [
                    'value' => $wallSettings?->wall_code,
                    'editable' => false,
                    'regenerates' => ['wall'],
                ],
            ],
        ];
    }

    private function normalizeEventSlug(string $value, int $ignoreId): string
    {
        $slug = Helpers::generateSlug($value);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => ['Informe um slug valido.'],
            ]);
        }

        if (Event::query()
            ->where('slug', $slug)
            ->where('id', '!=', $ignoreId)
            ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['Este slug ja esta em uso.'],
            ]);
        }

        return $slug;
    }

    private function normalizeUploadSlug(string $value, int $ignoreId): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw ValidationException::withMessages([
                'upload_slug' => ['Informe um slug de envio valido.'],
            ]);
        }

        if (Event::query()
            ->where('upload_slug', $slug)
            ->where('id', '!=', $ignoreId)
            ->exists()) {
            throw ValidationException::withMessages([
                'upload_slug' => ['Este slug de envio ja esta em uso.'],
            ]);
        }

        return $slug;
    }

    private function generateUniqueUploadSlug(int $ignoreId): string
    {
        do {
            $candidate = strtolower(str()->random(12));
        } while (
            Event::query()
                ->where('upload_slug', $candidate)
                ->where('id', '!=', $ignoreId)
                ->exists()
        );

        return $candidate;
    }
}
