<?php

namespace App\Modules\Wall\Support;

use App\Modules\Wall\Enums\WallEventPhase;
use App\Modules\Wall\Enums\WallSelectionMode;

class WallSelectionPreset
{
    public static function defaultsFor(WallSelectionMode|string|null $mode = null): array
    {
        $resolvedMode = self::resolveMode($mode);

        return match ($resolvedMode) {
            WallSelectionMode::Live => [
                'max_eligible_items_per_sender' => 5,
                'max_replays_per_item' => 3,
                'low_volume_max_items' => 6,
                'medium_volume_max_items' => 12,
                'replay_interval_low_minutes' => 5,
                'replay_interval_medium_minutes' => 8,
                'replay_interval_high_minutes' => 12,
                'sender_cooldown_seconds' => 30,
                'sender_window_limit' => 4,
                'sender_window_minutes' => 10,
                'avoid_same_sender_if_alternative_exists' => true,
                'avoid_same_duplicate_cluster_if_alternative_exists' => true,
            ],
            WallSelectionMode::Inclusive => [
                'max_eligible_items_per_sender' => 3,
                'max_replays_per_item' => 1,
                'low_volume_max_items' => 6,
                'medium_volume_max_items' => 12,
                'replay_interval_low_minutes' => 10,
                'replay_interval_medium_minutes' => 14,
                'replay_interval_high_minutes' => 20,
                'sender_cooldown_seconds' => 90,
                'sender_window_limit' => 2,
                'sender_window_minutes' => 10,
                'avoid_same_sender_if_alternative_exists' => true,
                'avoid_same_duplicate_cluster_if_alternative_exists' => true,
            ],
            WallSelectionMode::Editorial => [
                'max_eligible_items_per_sender' => 4,
                'max_replays_per_item' => 2,
                'low_volume_max_items' => 6,
                'medium_volume_max_items' => 12,
                'replay_interval_low_minutes' => 8,
                'replay_interval_medium_minutes' => 12,
                'replay_interval_high_minutes' => 16,
                'sender_cooldown_seconds' => 45,
                'sender_window_limit' => 3,
                'sender_window_minutes' => 10,
                'avoid_same_sender_if_alternative_exists' => true,
                'avoid_same_duplicate_cluster_if_alternative_exists' => true,
            ],
            WallSelectionMode::Custom,
            WallSelectionMode::Balanced => [
                'max_eligible_items_per_sender' => 4,
                'max_replays_per_item' => 2,
                'low_volume_max_items' => 6,
                'medium_volume_max_items' => 12,
                'replay_interval_low_minutes' => 8,
                'replay_interval_medium_minutes' => 12,
                'replay_interval_high_minutes' => 20,
                'sender_cooldown_seconds' => 60,
                'sender_window_limit' => 3,
                'sender_window_minutes' => 10,
                'avoid_same_sender_if_alternative_exists' => true,
                'avoid_same_duplicate_cluster_if_alternative_exists' => true,
            ],
        };
    }

    public static function applyPhasePolicy(
        array $policy,
        WallEventPhase|string|null $phase = null,
    ): array {
        $resolvedPhase = self::resolvePhase($phase);
        $base = self::normalizePolicy($policy);

        return match ($resolvedPhase) {
            WallEventPhase::Reception => [
                ...$base,
                'max_eligible_items_per_sender' => max(1, $base['max_eligible_items_per_sender'] - 1),
                'max_replays_per_item' => max(1, $base['max_replays_per_item'] - 1),
                'replay_interval_low_minutes' => min(60, $base['replay_interval_low_minutes'] + 2),
                'replay_interval_medium_minutes' => min(60, $base['replay_interval_medium_minutes'] + 2),
                'replay_interval_high_minutes' => min(60, $base['replay_interval_high_minutes'] + 4),
                'sender_cooldown_seconds' => min(300, $base['sender_cooldown_seconds'] + 20),
                'sender_window_limit' => max(1, $base['sender_window_limit'] - 1),
            ],
            WallEventPhase::Party => [
                ...$base,
                'max_eligible_items_per_sender' => min(12, $base['max_eligible_items_per_sender'] + 1),
                'max_replays_per_item' => min(6, $base['max_replays_per_item'] + 1),
                'replay_interval_low_minutes' => max(1, $base['replay_interval_low_minutes'] - 2),
                'replay_interval_medium_minutes' => max(1, $base['replay_interval_medium_minutes'] - 2),
                'replay_interval_high_minutes' => max(1, $base['replay_interval_high_minutes'] - 4),
                'sender_cooldown_seconds' => max(0, $base['sender_cooldown_seconds'] - 15),
                'sender_window_limit' => min(12, $base['sender_window_limit'] + 1),
            ],
            WallEventPhase::Closing => [
                ...$base,
                'max_replays_per_item' => min(6, $base['max_replays_per_item'] + 1),
                'replay_interval_low_minutes' => max(1, $base['replay_interval_low_minutes'] - 1),
                'replay_interval_medium_minutes' => max(1, $base['replay_interval_medium_minutes'] - 2),
                'replay_interval_high_minutes' => max(1, $base['replay_interval_high_minutes'] - 2),
                'sender_cooldown_seconds' => max(0, $base['sender_cooldown_seconds'] - 10),
            ],
            WallEventPhase::Flow => $base,
        };
    }

    public static function applyPhaseInterval(
        int $intervalMs,
        WallEventPhase|string|null $phase = null,
    ): int {
        $resolvedPhase = self::resolvePhase($phase);
        $base = max(2000, min(60000, $intervalMs));

        return match ($resolvedPhase) {
            WallEventPhase::Reception => min(60000, $base + 1500),
            WallEventPhase::Party => max(2000, $base - 1000),
            WallEventPhase::Closing => min(60000, $base + 2500),
            WallEventPhase::Flow => $base,
        };
    }

    public static function normalizePolicy(
        ?array $policy,
        WallSelectionMode|string|null $mode = null,
    ): array {
        $defaults = self::defaultsFor($mode);
        $input = $policy ?? [];
        $lowVolumeMaxItems = self::clampInteger(
            $input['low_volume_max_items'] ?? null,
            $defaults['low_volume_max_items'],
            2,
            20,
        );
        $mediumVolumeFallback = max($lowVolumeMaxItems + 1, $defaults['medium_volume_max_items']);
        $mediumVolumeMaxItems = self::clampInteger(
            $input['medium_volume_max_items'] ?? null,
            $mediumVolumeFallback,
            $lowVolumeMaxItems + 1,
            50,
        );

        return [
            'max_eligible_items_per_sender' => self::clampInteger(
                $input['max_eligible_items_per_sender'] ?? null,
                $defaults['max_eligible_items_per_sender'],
                1,
                12,
            ),
            'max_replays_per_item' => self::clampInteger(
                $input['max_replays_per_item'] ?? null,
                $defaults['max_replays_per_item'],
                0,
                6,
            ),
            'low_volume_max_items' => $lowVolumeMaxItems,
            'medium_volume_max_items' => $mediumVolumeMaxItems,
            'replay_interval_low_minutes' => self::clampInteger(
                $input['replay_interval_low_minutes'] ?? null,
                $defaults['replay_interval_low_minutes'],
                1,
                60,
            ),
            'replay_interval_medium_minutes' => self::clampInteger(
                $input['replay_interval_medium_minutes'] ?? null,
                $defaults['replay_interval_medium_minutes'],
                1,
                60,
            ),
            'replay_interval_high_minutes' => self::clampInteger(
                $input['replay_interval_high_minutes'] ?? null,
                $defaults['replay_interval_high_minutes'],
                1,
                60,
            ),
            'sender_cooldown_seconds' => self::clampInteger(
                $input['sender_cooldown_seconds'] ?? null,
                $defaults['sender_cooldown_seconds'],
                0,
                300,
            ),
            'sender_window_limit' => self::clampInteger(
                $input['sender_window_limit'] ?? null,
                $defaults['sender_window_limit'],
                1,
                12,
            ),
            'sender_window_minutes' => self::clampInteger(
                $input['sender_window_minutes'] ?? null,
                $defaults['sender_window_minutes'],
                1,
                30,
            ),
            'avoid_same_sender_if_alternative_exists' => array_key_exists('avoid_same_sender_if_alternative_exists', $input)
                ? (bool) $input['avoid_same_sender_if_alternative_exists']
                : $defaults['avoid_same_sender_if_alternative_exists'],
            'avoid_same_duplicate_cluster_if_alternative_exists' => array_key_exists('avoid_same_duplicate_cluster_if_alternative_exists', $input)
                ? (bool) $input['avoid_same_duplicate_cluster_if_alternative_exists']
                : $defaults['avoid_same_duplicate_cluster_if_alternative_exists'],
        ];
    }

    public static function options(): array
    {
        return array_map(
            fn (WallSelectionMode $mode) => [
                'value' => $mode->value,
                'label' => $mode->label(),
                'description' => $mode->description(),
                'selection_policy' => self::defaultsFor($mode),
            ],
            WallSelectionMode::cases(),
        );
    }

    public static function phaseOptions(): array
    {
        return array_map(
            fn (WallEventPhase $phase) => [
                'value' => $phase->value,
                'label' => $phase->label(),
                'description' => $phase->description(),
            ],
            WallEventPhase::cases(),
        );
    }

    private static function clampInteger(
        mixed $value,
        int $fallback,
        int $min,
        int $max,
    ): int {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return max($min, min($max, (int) $value));
    }

    private static function resolveMode(WallSelectionMode|string|null $mode): WallSelectionMode
    {
        if ($mode instanceof WallSelectionMode) {
            return $mode;
        }

        return WallSelectionMode::tryFrom((string) $mode) ?? WallSelectionMode::Balanced;
    }

    private static function resolvePhase(WallEventPhase|string|null $phase): WallEventPhase
    {
        if ($phase instanceof WallEventPhase) {
            return $phase;
        }

        return WallEventPhase::tryFrom((string) $phase) ?? WallEventPhase::Flow;
    }
}
