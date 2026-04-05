<?php

namespace App\Modules\Hub\Actions;

use App\Modules\Analytics\Services\AnalyticsTracker;
use App\Modules\Events\Models\Event;
use Illuminate\Http\Request;

class TrackPublicHubButtonClickAction
{
    public function __construct(
        private readonly AnalyticsTracker $analytics,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $buttons
     */
    public function execute(Event $event, Request $request, array $buttons, ?string $buttonId): void
    {
        if (! filled($buttonId)) {
            return;
        }

        $buttonList = collect($buttons)->values();
        $buttonIndex = $buttonList->search(
            fn (array $button) => (string) ($button['id'] ?? '') === $buttonId
        );

        if ($buttonIndex === false) {
            return;
        }

        $button = $buttonList->get($buttonIndex);

        if (! is_array($button) || ! filled($button['resolved_url'] ?? null)) {
            return;
        }

        $buttonType = (string) ($button['type'] ?? 'custom');
        $eventName = match ($buttonType) {
            'social' => 'hub.social_click',
            'sponsor' => 'hub.sponsor_click',
            default => 'hub.button_click',
        };

        $this->analytics->trackEvent(
            $event,
            $eventName,
            $request,
            [
                'surface' => 'hub',
                'button_id' => (string) ($button['id'] ?? ''),
                'button_label' => (string) ($button['label'] ?? ''),
                'button_type' => $buttonType,
                'preset_key' => $button['preset_key'] ?? null,
                'button_icon' => (string) ($button['icon'] ?? 'link'),
                'button_position' => $buttonIndex + 1,
                'resolved_url' => (string) ($button['resolved_url'] ?? ''),
                'opens_in_new_tab' => (bool) ($button['opens_in_new_tab'] ?? false),
            ],
            channel: 'hub',
        );
    }
}
