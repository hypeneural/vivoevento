<?php

namespace App\Modules\Wall\Services;

use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Models\WallDisplayCounter;
use App\Modules\Wall\Models\WallPlayerRuntimeStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WallDisplayCounterService
{
    public function recordDisplayedMedia(EventWallSetting $settings, WallPlayerRuntimeStatus $player): void
    {
        if (! $player->current_item_id || ! $player->current_item_started_at) {
            return;
        }

        DB::transaction(function () use ($settings, $player): void {
            /** @var WallDisplayCounter|null $counter */
            $counter = WallDisplayCounter::query()
                ->where('event_wall_setting_id', $settings->id)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                WallDisplayCounter::query()->create([
                    'event_wall_setting_id' => $settings->id,
                    'displayed_count' => 1,
                    'current_item_id' => $player->current_item_id,
                    'current_item_started_at' => $player->current_item_started_at,
                    'last_player_instance_id' => $player->player_instance_id,
                    'last_counted_at' => now(),
                ]);

                return;
            }

            $startedAt = $player->current_item_started_at;
            $currentStartedAt = $counter->current_item_started_at;

            if (
                $currentStartedAt
                && $startedAt->lt($currentStartedAt)
                && ! $this->isSameDisplay($settings, $counter, $player)
            ) {
                return;
            }

            if ($this->isSameDisplay($settings, $counter, $player)) {
                $counter->forceFill([
                    'last_player_instance_id' => $player->player_instance_id,
                ])->save();

                return;
            }

            $counter->forceFill([
                'displayed_count' => (int) $counter->displayed_count + 1,
                'current_item_id' => $player->current_item_id,
                'current_item_started_at' => $player->current_item_started_at,
                'last_player_instance_id' => $player->player_instance_id,
                'last_counted_at' => now(),
            ])->save();
        });
    }

    private function isSameDisplay(
        EventWallSetting $settings,
        WallDisplayCounter $counter,
        WallPlayerRuntimeStatus $player,
    ): bool {
        if (
            ! $counter->current_item_id
            || ! $counter->current_item_started_at
            || ! $player->current_item_id
            || ! $player->current_item_started_at
            || $counter->current_item_id !== $player->current_item_id
        ) {
            return false;
        }

        return abs($this->timestampMs($counter->current_item_started_at) - $this->timestampMs($player->current_item_started_at))
            <= $this->sameDisplayToleranceMs($settings);
    }

    private function sameDisplayToleranceMs(EventWallSetting $settings): int
    {
        return max(2000, min(5000, (int) floor(((int) $settings->interval_ms) / 2)));
    }

    private function timestampMs(Carbon $value): int
    {
        return ((int) $value->getTimestamp() * 1000) + (int) $value->format('v');
    }
}
