<?php

namespace App\Modules\Hub\Actions;

use App\Modules\Hub\Models\EventHubSetting;
use App\Modules\Hub\Support\HubPayloadFactory;

class UpdateEventHubSettingsAction
{
    public function __construct(
        private readonly HubPayloadFactory $payloads,
    ) {}

    public function execute(EventHubSetting $settings, array $data): EventHubSetting
    {
        $buttons = $data['buttons'] ?? null;

        if (array_key_exists('button_style', $data)) {
            $data['button_style_json'] = $data['button_style'];
            unset($data['button_style']);
        }

        if ($buttons !== null) {
            $data['buttons_json'] = $buttons;
            unset($data['buttons']);
            $data = array_merge($data, $this->payloads->presetVisibility($buttons));
        }

        if (array_key_exists('builder_config', $data)) {
            $data['builder_config_json'] = $data['builder_config'];
            unset($data['builder_config']);
        }

        $settings->fill($data);
        $settings->save();

        return $settings->fresh();
    }
}
