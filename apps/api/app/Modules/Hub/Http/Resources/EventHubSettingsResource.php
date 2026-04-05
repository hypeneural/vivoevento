<?php

namespace App\Modules\Hub\Http\Resources;

use App\Modules\Events\Models\Event;
use App\Modules\Hub\Support\HubPayloadFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventHubSettingsResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly Event $event,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return app(HubPayloadFactory::class)->admin($this->event, $this->resource);
    }
}
