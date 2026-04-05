<?php

namespace App\Modules\Hub\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Hub\Models\HubPreset;
use App\Modules\Users\Models\User;

class CreateHubPresetAction
{
    public function execute(
        User $user,
        int $organizationId,
        array $data,
        ?Event $sourceEvent = null,
    ): HubPreset {
        return HubPreset::query()->create([
            'organization_id' => $organizationId,
            'created_by_user_id' => $user->id,
            'source_event_id' => $sourceEvent?->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'theme_key' => data_get($data, 'builder_config.theme_key', 'midnight'),
            'layout_key' => data_get($data, 'builder_config.layout_key', 'classic-cover'),
            'preset_payload_json' => [
                'button_style' => $data['button_style'],
                'builder_config' => $data['builder_config'],
                'buttons' => $data['buttons'],
            ],
        ]);
    }
}
