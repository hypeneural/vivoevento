<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventPublicLinkQrConfig;
use App\Modules\Events\Support\EventPublicLinkQrConfigSchema;

class UpsertEventPublicLinkQrConfigAction
{
    public function __construct(
        private readonly EventPublicLinkQrConfigSchema $schema,
    ) {}

    public function execute(Event $event, string $linkKey, array $config, ?int $userId = null): EventPublicLinkQrConfig
    {
        $this->schema->assertValidLinkKey($linkKey);

        $normalized = $this->schema->normalize(
            input: $config,
            linkKey: $linkKey,
            effectiveBranding: null,
            applyBrandingDefaults: false,
        );

        $record = EventPublicLinkQrConfig::query()->firstOrNew([
            'event_id' => $event->id,
            'link_key' => $linkKey,
        ]);

        if (! $record->exists) {
            $record->created_by = $userId;
        }

        $record->fill([
            'config_version' => $normalized['config_version'],
            'config_json' => $normalized,
            'updated_by' => $userId,
        ]);
        $record->save();

        return $record->fresh();
    }
}
