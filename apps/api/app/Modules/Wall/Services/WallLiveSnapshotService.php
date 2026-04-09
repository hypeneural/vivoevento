<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use App\Modules\Wall\Support\WallLayoutHintResolver;
use App\Modules\Wall\Support\WallSourceNormalizer;
use Illuminate\Support\Str;

class WallLiveSnapshotService
{
    public function __construct(
        private readonly WallDiagnosticsService $diagnostics,
        private readonly WallPayloadFactory $payloads,
        private readonly WallLayoutHintResolver $layoutHintResolver,
    ) {}

    public function buildSnapshot(EventWallSetting $settings): array
    {
        $player = $this->resolveCurrentPlayer($settings);
        $playerPayload = $player ? $this->diagnostics->playerPayload($player) : null;
        $currentItem = $player ? $this->resolveCurrentItem($settings, $player) : null;

        return [
            'wallStatus' => $settings->status->value,
            'wallStatusLabel' => $settings->status->label(),
            'layout' => $settings->layout->value,
            'transitionEffect' => $settings->transition_effect->value,
            'currentPlayer' => $playerPayload ? [
                'playerInstanceId' => $playerPayload['player_instance_id'],
                'healthStatus' => $playerPayload['health_status'],
                'runtimeStatus' => $playerPayload['runtime_status'],
                'connectionStatus' => $playerPayload['connection_status'],
                'lastSeenAt' => $playerPayload['last_seen_at'],
            ] : null,
            'currentItem' => $currentItem,
            'advancedAt' => $player?->current_item_started_at?->toIso8601String(),
            'updatedAt' => $playerPayload['updated_at'] ?? $settings->updated_at?->toIso8601String(),
        ];
    }

    private function resolveCurrentPlayer(EventWallSetting $settings): ?WallPlayerRuntimeStatus
    {
        $players = $settings->playerRuntimeStatuses()
            ->orderByDesc('last_heartbeat_at')
            ->orderBy('player_instance_id')
            ->get();

        foreach ($players as $player) {
            $payload = $this->diagnostics->playerPayload($player);

            if (($payload['health_status'] ?? 'offline') !== 'offline') {
                return $player;
            }
        }

        return $players->first();
    }

    private function resolveCurrentItem(EventWallSetting $settings, WallPlayerRuntimeStatus $player): ?array
    {
        $mediaId = $this->parseMediaId($player->current_item_id);

        if ($mediaId === null) {
            return null;
        }

        $media = EventMedia::query()
            ->with(['inboundMessage', 'variants'])
            ->where('event_id', $settings->event_id)
            ->find($mediaId);

        if (! $media) {
            return null;
        }

        $mediaPayload = $this->payloads->media($media);

        return [
            'id' => $mediaPayload['id'],
            'previewUrl' => $mediaPayload['preview_url'] ?? null,
            'senderName' => $mediaPayload['sender_name'] ?: 'Convidado',
            'senderKey' => $mediaPayload['sender_key'],
            'source' => WallSourceNormalizer::normalize($mediaPayload['source_type'] ?? null),
            'caption' => $mediaPayload['caption'] ?? null,
            'layoutHint' => $this->layoutHintResolver->resolve($settings->layout->value, $mediaPayload),
            'isFeatured' => (bool) ($mediaPayload['is_featured'] ?? false),
            'createdAt' => $mediaPayload['created_at'] ?? null,
        ];
    }

    private function parseMediaId(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        if (Str::startsWith($value, 'media_')) {
            $value = Str::after($value, 'media_');
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
