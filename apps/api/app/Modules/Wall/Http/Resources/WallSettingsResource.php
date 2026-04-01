<?php

namespace App\Modules\Wall\Http\Resources;

use App\Modules\Wall\Services\WallBroadcasterService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for wall settings (admin response).
 *
 * @mixin \App\Modules\Wall\Models\EventWallSetting
 */
class WallSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $broadcaster = app(WallBroadcasterService::class);

        return [
            'id'              => $this->id,
            'event_id'        => $this->event_id,
            'wall_code'       => $this->wall_code,
            'is_enabled'      => (bool) $this->is_enabled,
            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'public_url'      => $this->publicUrl(),
            'settings'        => $broadcaster->settingsPayload($this->resource),
            'expires_at'      => $this->expires_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
