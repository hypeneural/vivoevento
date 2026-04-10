<?php

namespace App\Modules\Wall\Services;

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use Throwable;

class WallVideoAnalyticsService
{
    public function recordFromHeartbeat(
        EventWallSetting $settings,
        ?WallPlayerRuntimeStatus $previous,
        WallPlayerRuntimeStatus $current,
    ): void {
        try {
            $event = $settings->relationLoaded('event')
                ? $settings->getRelation('event')
                : $settings->event()->first();

            if (! $event) {
                return;
            }

            foreach ($this->eventsForTransition($previous, $current) as [$eventName, $mediaId]) {
                AnalyticsEvent::query()->create([
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'event_media_id' => $mediaId,
                    'event_name' => $eventName,
                    'actor_type' => 'system',
                    'actor_id' => $current->player_instance_id,
                    'channel' => 'wall',
                    'metadata_json' => $this->metadata($settings, $current),
                    'occurred_at' => $current->last_heartbeat_at ?? now(),
                    'created_at' => $current->last_heartbeat_at ?? now(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<int, array{0:string,1:int|null}>
     */
    private function eventsForTransition(
        ?WallPlayerRuntimeStatus $previous,
        WallPlayerRuntimeStatus $current,
    ): array {
        $events = [];
        $currentVideoMediaId = $this->activeVideoMediaId($current);
        $previousVideoMediaId = $this->activeVideoMediaId($previous);

        if ($currentVideoMediaId !== null && $currentVideoMediaId !== $previousVideoMediaId) {
            $events[] = ['wall.video_start', $currentVideoMediaId];
        }

        if (
            $currentVideoMediaId !== null
            && $current->current_video_first_frame_ready === true
            && $previous?->current_video_first_frame_ready !== true
        ) {
            $events[] = ['wall.video_first_frame', $currentVideoMediaId];
        }

        if (
            $currentVideoMediaId !== null
            && $current->current_video_phase === 'waiting'
            && $previous?->current_video_phase !== 'waiting'
        ) {
            $events[] = ['wall.video_waiting', $currentVideoMediaId];
        }

        if (
            $currentVideoMediaId !== null
            && $current->current_video_phase === 'stalled'
            && $previous?->current_video_phase !== 'stalled'
        ) {
            $events[] = ['wall.video_stalled', $currentVideoMediaId];
        }

        $exitReason = $current->current_video_exit_reason;
        $previousExitReason = $previous?->current_video_exit_reason;
        $completedMediaId = $currentVideoMediaId ?? $previousVideoMediaId ?? $this->mediaIdFromIdentifier($current->current_item_id);

        if ($exitReason !== null && $exitReason !== $previousExitReason) {
            $analyticsEvent = match ($exitReason) {
                'ended' => 'wall.video_complete',
                'cap_reached' => 'wall.video_interrupted_by_cap',
                'paused_by_operator' => 'wall.video_interrupted_by_pause',
                'play_rejected', 'startup_play_rejected' => 'wall.video_play_rejected',
                default => null,
            };

            if ($analyticsEvent !== null) {
                $events[] = [$analyticsEvent, $completedMediaId];
            }
        }

        return $events;
    }

    private function activeVideoMediaId(?WallPlayerRuntimeStatus $status): ?int
    {
        if (! $status || $status->current_media_type !== 'video') {
            return null;
        }

        return $this->mediaIdFromIdentifier($status->current_item_id);
    }

    private function mediaIdFromIdentifier(?string $itemId): ?int
    {
        if (! is_string($itemId)) {
            return null;
        }

        if (preg_match('/^media_(\d+)$/', $itemId, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(EventWallSetting $settings, WallPlayerRuntimeStatus $player): array
    {
        return array_filter([
            'surface' => 'wall',
            'wall_code' => $settings->wall_code,
            'player_instance_id' => $player->player_instance_id,
            'runtime_status' => $player->runtime_status,
            'connection_status' => $player->connection_status,
            'current_video_phase' => $player->current_video_phase,
            'current_video_exit_reason' => $player->current_video_exit_reason,
            'current_video_failure_reason' => $player->current_video_failure_reason,
            'current_video_position_seconds' => $player->current_video_position_seconds,
            'current_video_duration_seconds' => $player->current_video_duration_seconds,
            'current_video_ready_state' => $player->current_video_ready_state,
            'current_video_stall_count' => $player->current_video_stall_count,
            'current_video_startup_degraded' => $player->current_video_startup_degraded,
            'hardware_concurrency' => $player->hardware_concurrency,
            'device_memory_gb' => $player->device_memory_gb,
            'network_effective_type' => $player->network_effective_type,
            'network_save_data' => $player->network_save_data,
            'network_downlink_mbps' => $player->network_downlink_mbps,
            'network_rtt_ms' => $player->network_rtt_ms,
            'prefers_reduced_motion' => $player->prefers_reduced_motion,
            'document_visibility_state' => $player->document_visibility_state,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
