<?php

namespace App\Modules\Wall\Services;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use App\Modules\Wall\Support\WallLayoutHintResolver;
use App\Modules\Wall\Support\WallSourceNormalizer;
use App\Modules\Wall\Support\WallVideoPolicyLabelResolver;
use Illuminate\Support\Str;

class WallLiveSnapshotService
{
    public function __construct(
        private readonly WallDiagnosticsService $diagnostics,
        private readonly WallPayloadFactory $payloads,
        private readonly WallLayoutHintResolver $layoutHintResolver,
        private readonly WallSimulationService $simulation,
    ) {}

    public function buildSnapshot(EventWallSetting $settings): array
    {
        $player = $this->resolveCurrentPlayer($settings);
        $playerPayload = $player ? $this->diagnostics->playerPayload($player) : null;
        $currentItem = $player ? $this->resolveCurrentItem($settings, $player) : null;
        $nextItem = $currentItem ? $this->resolveNextItem($settings, $currentItem['id']) : null;

        return [
            'wallStatus' => $settings->status->value,
            'wallStatusLabel' => $settings->status->label(),
            'layout' => $settings->layout->value,
            'transitionEffect' => $settings->transition_effect->value,
            'transitionMode' => $settings->resolvedTransitionMode()->value,
            'currentPlayer' => $playerPayload ? [
                'playerInstanceId' => $playerPayload['player_instance_id'],
                'healthStatus' => $playerPayload['health_status'],
                'runtimeStatus' => $playerPayload['runtime_status'],
                'connectionStatus' => $playerPayload['connection_status'],
                'lastSeenAt' => $playerPayload['last_seen_at'],
            ] : null,
            'currentItem' => $currentItem,
            'nextItem' => $nextItem,
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

        $mediaPayload = $this->payloads->media($media, $settings);

        return [
            'id' => $mediaPayload['id'],
            'previewUrl' => $mediaPayload['preview_url'] ?? null,
            'senderName' => $mediaPayload['sender_name'] ?: 'Convidado',
            'senderKey' => $mediaPayload['sender_key'],
            'source' => WallSourceNormalizer::normalize($mediaPayload['source_type'] ?? null),
            'caption' => $mediaPayload['caption'] ?? null,
            'layoutHint' => $this->layoutHintResolver->resolve($settings->layout->value, $mediaPayload),
            'isFeatured' => (bool) ($mediaPayload['is_featured'] ?? false),
            'isVideo' => ($mediaPayload['type'] ?? 'image') === 'video',
            'durationSeconds' => $mediaPayload['duration_seconds'] ?? null,
            'videoPolicyLabel' => WallVideoPolicyLabelResolver::fromPayload($mediaPayload),
            'videoAdmission' => $mediaPayload['video_admission'] ?? null,
            'servedVariantKey' => $mediaPayload['served_variant_key'] ?? null,
            'previewVariantKey' => $mediaPayload['preview_variant_key'] ?? null,
            'createdAt' => $mediaPayload['created_at'] ?? null,
        ];
    }

    private function resolveNextItem(EventWallSetting $settings, string $currentItemId): ?array
    {
        $simulation = $this->simulation->simulate($settings, [], 2);
        $preview = collect($simulation['sequence_preview'] ?? [])->values();

        if ($preview->count() < 2) {
            return null;
        }

        $firstPredictedItem = $preview->first();
        $nextPredictedItem = $preview->get(1);

        if (($firstPredictedItem['item_id'] ?? null) !== $currentItemId || ! is_array($nextPredictedItem)) {
            return null;
        }

        return [
            'id' => $nextPredictedItem['item_id'],
            'previewUrl' => $nextPredictedItem['preview_url'] ?? null,
            'senderName' => $nextPredictedItem['sender_name'] ?: 'Convidado',
            'senderKey' => $nextPredictedItem['sender_key'] ?? $nextPredictedItem['item_id'],
            'source' => WallSourceNormalizer::normalize($nextPredictedItem['source_type'] ?? null),
            'caption' => $nextPredictedItem['caption'] ?? null,
            'layoutHint' => $nextPredictedItem['layout_hint'] ?? null,
            'isFeatured' => (bool) ($nextPredictedItem['is_featured'] ?? false),
            'isVideo' => (bool) ($nextPredictedItem['is_video'] ?? false),
            'durationSeconds' => $nextPredictedItem['duration_seconds'] ?? null,
            'videoPolicyLabel' => $nextPredictedItem['video_policy_label'] ?? null,
            'videoAdmission' => $nextPredictedItem['video_admission'] ?? null,
            'servedVariantKey' => $nextPredictedItem['served_variant_key'] ?? null,
            'previewVariantKey' => $nextPredictedItem['preview_variant_key'] ?? null,
            'createdAt' => $nextPredictedItem['created_at'] ?? null,
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
