<?php

namespace App\Modules\MediaProcessing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaPipelineMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'event' => $this['event'],
            'summary' => $this['summary'],
            'sla' => $this['sla'],
            'queues' => $this['queues'],
            'failures' => $this['failures'],
        ];
    }
}
