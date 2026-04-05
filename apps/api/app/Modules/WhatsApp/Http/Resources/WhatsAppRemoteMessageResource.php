<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppRemoteMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'key.id') ?? $this['id'] ?? null,
            'remote_jid' => data_get($this->resource, 'key.remoteJid') ?? $this['remoteJid'] ?? null,
            'from_me' => (bool) (data_get($this->resource, 'key.fromMe') ?? $this['fromMe'] ?? false),
            'push_name' => $this['pushName'] ?? null,
            'timestamp' => $this['messageTimestamp'] ?? $this['timestamp'] ?? null,
            'message' => $this['message'] ?? null,
            'raw' => $this->resource,
        ];
    }
}
