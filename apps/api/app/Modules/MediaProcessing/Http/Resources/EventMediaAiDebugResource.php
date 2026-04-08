<?php

namespace App\Modules\MediaProcessing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaAiDebugResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'media_id' => $this['media_id'],
            'event_id' => $this['event_id'],
            'trace_id' => $this['trace_id'],
            'inbound' => [
                'message' => $this['inbound_message'],
                'webhook_logs' => $this['webhook_logs'],
                'whatsapp_events' => $this['whatsapp_events'],
            ],
            'safety' => $this['safety'],
            'vlm' => $this['vlm'],
            'feedback' => [
                'whatsapp' => $this['whatsapp_feedbacks'],
                'telegram' => $this['telegram_feedbacks'],
                'whatsapp_dispatch_logs' => $this['whatsapp_dispatch_logs'],
            ],
        ];
    }
}
