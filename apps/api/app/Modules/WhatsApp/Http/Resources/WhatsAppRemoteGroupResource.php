<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppRemoteGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participants = $this['participants'] ?? $this['participant'] ?? [];

        return [
            'id' => $this['id'] ?? $this['jid'] ?? null,
            'subject' => $this['subject'] ?? $this['name'] ?? null,
            'description' => $this['desc'] ?? $this['description'] ?? null,
            'owner' => $this['owner'] ?? null,
            'size' => $this['size'] ?? (is_array($participants) ? count($participants) : null),
            'announce' => $this['announce'] ?? null,
            'restrict' => $this['restrict'] ?? null,
            'creation' => $this['creation'] ?? null,
            'invite_code' => $this['inviteCode'] ?? null,
            'participants_count' => is_array($participants) ? count($participants) : 0,
            'participants' => is_array($participants)
                ? WhatsAppRemoteParticipantResource::collection(collect($participants))->resolve()
                : [],
            'raw' => $this->resource,
        ];
    }
}
