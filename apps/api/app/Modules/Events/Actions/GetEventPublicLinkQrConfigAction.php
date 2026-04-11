<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Support\EventPublicLinkQrStateBuilder;

class GetEventPublicLinkQrConfigAction
{
    public function __construct(
        private readonly EventPublicLinkQrStateBuilder $builder,
    ) {}

    public function execute(Event $event, string $linkKey): array
    {
        return $this->builder->build($event, $linkKey);
    }

    public function list(Event $event): array
    {
        return $this->builder->buildAll($event);
    }
}
