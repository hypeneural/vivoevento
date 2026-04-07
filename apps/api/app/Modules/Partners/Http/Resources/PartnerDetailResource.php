<?php

namespace App\Modules\Partners\Http\Resources;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PartnerDetailResource extends JsonResource
{
    public function __construct(
        Organization $resource,
        private readonly array $eventsSummary,
        private readonly array $clientsSummary,
        private readonly array $staffSummary,
        private readonly array $grantsSummary,
        private readonly Collection $latestActivity,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(
            (new PartnerResource($this->resource))->toArray($request),
            [
                'events_summary' => $this->eventsSummary,
                'clients_summary' => $this->clientsSummary,
                'staff_summary' => $this->staffSummary,
                'grants_summary' => $this->grantsSummary,
                'latest_activity' => PartnerActivityResource::collection($this->latestActivity)->resolve(),
            ],
        );
    }
}
