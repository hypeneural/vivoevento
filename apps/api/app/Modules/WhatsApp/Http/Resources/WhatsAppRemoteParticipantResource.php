<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppRemoteParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'] ?? $this['jid'] ?? null,
            'admin' => $this['admin'] ?? null,
            'name' => $this['name'] ?? $this['pushName'] ?? null,
            'notify' => $this['notify'] ?? null,
            'raw' => $this->resource,
        ];
    }
}
