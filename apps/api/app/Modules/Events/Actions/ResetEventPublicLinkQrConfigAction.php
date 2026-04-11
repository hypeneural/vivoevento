<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventPublicLinkQrConfig;
use App\Modules\Events\Support\EventPublicLinkQrConfigSchema;

class ResetEventPublicLinkQrConfigAction
{
    public function __construct(
        private readonly EventPublicLinkQrConfigSchema $schema,
    ) {}

    public function execute(Event $event, string $linkKey): void
    {
        $this->schema->assertValidLinkKey($linkKey);

        EventPublicLinkQrConfig::query()
            ->where('event_id', $event->id)
            ->where('link_key', $linkKey)
            ->delete();
    }
}
