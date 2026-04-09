<?php

namespace App\Modules\Wall\Services;

use App\Modules\Wall\Enums\WallEventPhase;
use App\Modules\Wall\Enums\WallSelectionMode;
use App\Modules\Wall\Models\EventWallSetting;
use App\Modules\Wall\Support\WallLayoutHintResolver;
use App\Modules\Wall\Support\WallSourceNormalizer;
use App\Modules\Wall\Support\WallSelectionPreset;
use App\Modules\Wall\Support\WallVideoPolicyLabelResolver;
use Carbon\CarbonImmutable;
use Throwable;

class WallSimulationService
{
    public function __construct(
        private readonly WallPayloadFactory $payloads,
        private readonly WallRuntimeMediaService $runtimeMedia,
        private readonly WallLayoutHintResolver $layoutHintResolver,
    ) {}

    public function simulate(
        EventWallSetting $settings,
        array $draft = [],
        int $previewLength = 12,
    ): array {
        $resolvedSettings = $this->resolveSimulationSettings($settings, $draft);
        $policy = $this->resolvePolicy($resolvedSettings);
        $intervalMs = WallSelectionPreset::applyPhaseInterval(
            (int) ($resolvedSettings['interval_ms'] ?? 8000),
            $resolvedSettings['event_phase'] ?? null,
        );
        $simulationSettings = $this->hydrateSimulationSettingsModel($settings, $resolvedSettings);
        $media = $this->runtimeMedia->loadPlayableMedia(
            $simulationSettings,
            queueLimit: (int) $resolvedSettings['queue_limit'],
        );

        $items = $media
            ->map(fn ($item) => $this->toSimulationItem(
                $this->payloads->media($item, $simulationSettings),
                (string) ($resolvedSettings['layout'] ?? 'auto'),
            ))
            ->values()
            ->all();

        $preview = [];
        $senderStats = [];
        $clock = CarbonImmutable::now();
        $currentItemId = $this->resolveInitialItemId($items, null, $policy, $senderStats, $clock);

        if ($currentItemId) {
            [$items, $senderStats] = $this->markPlayback($items, $senderStats, $currentItemId, $clock);
        }

        for ($position = 1; $position <= $previewLength && $currentItemId; $position++) {
            $currentItem = $this->findItem($items, $currentItemId);

            if (! $currentItem) {
                break;
            }

            $preview[] = [
                'position' => $position,
                'eta_seconds' => (int) round((($position - 1) * $intervalMs) / 1000),
                'item_id' => $currentItem['id'],
                'preview_url' => $currentItem['preview_url'],
                'sender_name' => $currentItem['sender_name'] ?: 'Convidado',
                'sender_key' => $currentItem['senderKey'],
                'source_type' => $currentItem['source_type'],
                'caption' => $currentItem['caption'],
                'layout_hint' => $currentItem['layout_hint'],
                'duplicate_cluster_key' => $currentItem['duplicateClusterKey'],
                'is_featured' => (bool) $currentItem['is_featured'],
                'is_video' => (bool) ($currentItem['is_video'] ?? false),
                'duration_seconds' => $currentItem['duration_seconds'] ?? null,
                'video_policy_label' => $currentItem['video_policy_label'] ?? null,
                'video_admission' => $currentItem['video_admission'] ?? null,
                'served_variant_key' => $currentItem['served_variant_key'] ?? null,
                'preview_variant_key' => $currentItem['preview_variant_key'] ?? null,
                'is_replay' => (int) $currentItem['play_count'] > 1,
                'created_at' => $currentItem['created_at'],
            ];

            $clock = $clock->addMilliseconds($intervalMs);
            $currentItemId = $this->pickNextItemId($items, $currentItem['id'], $policy, $senderStats, $clock);

            if (! $currentItemId) {
                break;
            }

            [$items, $senderStats] = $this->markPlayback($items, $senderStats, $currentItemId, $clock);
        }

        $summary = $this->buildSummary($resolvedSettings, $items, $preview, $intervalMs);

        return [
            'summary' => $summary,
            'sequence_preview' => $preview,
            'explanation' => $this->buildExplanation($summary, $resolvedSettings, $policy),
        ];
    }

    private function resolveSimulationSettings(EventWallSetting $settings, array $draft): array
    {
        $current = $this->payloads->settings($settings);
        $resolved = array_merge($current, $draft);

        if (array_key_exists('selection_policy', $draft)) {
            $resolved['selection_policy'] = array_merge(
                $current['selection_policy'] ?? [],
                is_array($draft['selection_policy']) ? $draft['selection_policy'] : [],
            );
        }

        if (
            array_key_exists('selection_mode', $draft)
            || array_key_exists('selection_policy', $draft)
            || array_key_exists('event_phase', $draft)
        ) {
            $resolved['selection_policy'] = WallSelectionPreset::normalizePolicy(
                $resolved['selection_policy'] ?? null,
                $resolved['selection_mode'] ?? null,
            );
        }

        $resolved['queue_limit'] = max(5, min(500, (int) ($resolved['queue_limit'] ?? $current['queue_limit'] ?? 100)));
        $resolved['interval_ms'] = max(2000, min(60000, (int) ($resolved['interval_ms'] ?? $current['interval_ms'] ?? 8000)));

        return $resolved;
    }

    private function hydrateSimulationSettingsModel(EventWallSetting $settings, array $resolvedSettings): EventWallSetting
    {
        $simulationSettings = $settings->replicate();
        $simulationSettings->exists = true;
        $simulationSettings->event_id = $settings->event_id;
        $simulationSettings->setRelation('event', $settings->relationLoaded('event')
            ? $settings->getRelation('event')
            : $settings->event()->first());

        $simulationSettings->forceFill([
            'interval_ms' => $resolvedSettings['interval_ms'] ?? $settings->interval_ms,
            'queue_limit' => $resolvedSettings['queue_limit'] ?? $settings->queue_limit,
            'selection_mode' => $resolvedSettings['selection_mode'] ?? $settings->selection_mode?->value,
            'event_phase' => $resolvedSettings['event_phase'] ?? $settings->event_phase?->value,
            'selection_policy' => $resolvedSettings['selection_policy'] ?? $settings->selection_policy,
            'layout' => $resolvedSettings['layout'] ?? $settings->layout?->value,
            'transition_effect' => $resolvedSettings['transition_effect'] ?? $settings->transition_effect?->value,
            'accepted_orientation' => $resolvedSettings['accepted_orientation'] ?? $settings->accepted_orientation?->value,
            'video_enabled' => $resolvedSettings['video_enabled'] ?? $settings->resolvedVideoEnabled(),
            'video_playback_mode' => $resolvedSettings['video_playback_mode'] ?? $settings->resolvedVideoPlaybackMode(),
            'video_max_seconds' => $resolvedSettings['video_max_seconds'] ?? $settings->resolvedVideoMaxSeconds(),
            'video_resume_mode' => $resolvedSettings['video_resume_mode'] ?? $settings->resolvedVideoResumeMode(),
            'video_audio_policy' => $resolvedSettings['video_audio_policy'] ?? $settings->resolvedVideoAudioPolicy(),
            'video_multi_layout_policy' => $resolvedSettings['video_multi_layout_policy'] ?? $settings->resolvedVideoMultiLayoutPolicy(),
            'video_preferred_variant' => $resolvedSettings['video_preferred_variant'] ?? $settings->resolvedVideoPreferredVariant(),
        ]);

        return $simulationSettings;
    }

    private function resolvePolicy(array $settings): array
    {
        $policy = WallSelectionPreset::normalizePolicy(
            $settings['selection_policy'] ?? null,
            $settings['selection_mode'] ?? null,
        );
        $effectivePolicy = WallSelectionPreset::applyPhasePolicy(
            $policy,
            $settings['event_phase'] ?? null,
        );

        return [
            'max_eligible_items_per_sender' => (int) $effectivePolicy['max_eligible_items_per_sender'],
            'max_replays_per_item' => (int) $effectivePolicy['max_replays_per_item'],
            'low_volume_max_items' => (int) $effectivePolicy['low_volume_max_items'],
            'medium_volume_max_items' => (int) $effectivePolicy['medium_volume_max_items'],
            'replay_interval_low_ms' => (int) $effectivePolicy['replay_interval_low_minutes'] * 60000,
            'replay_interval_medium_ms' => (int) $effectivePolicy['replay_interval_medium_minutes'] * 60000,
            'replay_interval_high_ms' => (int) $effectivePolicy['replay_interval_high_minutes'] * 60000,
            'avoid_same_sender_if_alternative_exists' => (bool) $effectivePolicy['avoid_same_sender_if_alternative_exists'],
            'avoid_same_duplicate_cluster_if_alternative_exists' => (bool) $effectivePolicy['avoid_same_duplicate_cluster_if_alternative_exists'],
            'sender_cooldown_ms' => (int) $effectivePolicy['sender_cooldown_seconds'] * 1000,
            'sender_window_limit' => (int) $effectivePolicy['sender_window_limit'],
            'sender_window_ms' => (int) $effectivePolicy['sender_window_minutes'] * 60000,
        ];
    }

    private function toSimulationItem(array $payload, string $requestedLayout): array
    {
        return [
            'id' => $payload['id'],
            'url' => $payload['url'] ?? null,
            'preview_url' => $payload['preview_url'] ?? null,
            'type' => $payload['type'] ?? 'image',
            'sender_name' => $payload['sender_name'] ?? null,
            'source_type' => WallSourceNormalizer::normalize($payload['source_type'] ?? null),
            'caption' => $payload['caption'] ?? null,
            'layout_hint' => $this->layoutHintResolver->resolve($requestedLayout, $payload),
            'sender_key' => $payload['sender_key'] ?? null,
            'senderKey' => $payload['sender_key'] ?? $payload['id'],
            'duplicateClusterKey' => $payload['duplicate_cluster_key'] ?? null,
            'is_featured' => (bool) ($payload['is_featured'] ?? false),
            'is_video' => ($payload['type'] ?? 'image') === 'video',
            'duration_seconds' => $payload['duration_seconds'] ?? null,
            'video_policy_label' => WallVideoPolicyLabelResolver::fromPayload($payload),
            'video_admission' => $payload['video_admission'] ?? null,
            'served_variant_key' => $payload['served_variant_key'] ?? null,
            'preview_variant_key' => $payload['preview_variant_key'] ?? null,
            'created_at' => $payload['created_at'] ?? null,
            'asset_status' => 'ready',
            'played_at' => null,
            'play_count' => 0,
        ];
    }

    private function buildSummary(
        array $settings,
        array $items,
        array $preview,
        int $intervalMs,
    ): array {
        $activeSenders = [];
        foreach ($items as $item) {
            $activeSenders[$item['senderKey']] = true;
        }

        $firstAppearancePositions = [];
        $senderCounts = [];
        $maxConsecutive = 0;
        $currentSenderKey = null;
        $currentRun = 0;

        foreach ($preview as $slide) {
            $senderKey = $slide['sender_key'];

            if (! array_key_exists($senderKey, $firstAppearancePositions)) {
                $firstAppearancePositions[$senderKey] = (int) $slide['position'];
            }

            $senderCounts[$senderKey] = ($senderCounts[$senderKey] ?? 0) + 1;

            if ($senderKey === $currentSenderKey) {
                $currentRun++;
            } else {
                $currentSenderKey = $senderKey;
                $currentRun = 1;
            }

            $maxConsecutive = max($maxConsecutive, $currentRun);
        }

        $appearanceOffsets = array_map(
            fn (int $position): int => max(0, (int) round((($position - 1) * $intervalMs) / 1000)),
            array_values($firstAppearancePositions),
        );
        $estimatedFirstAppearance = count($appearanceOffsets) > 0
            ? (int) round(array_sum($appearanceOffsets) / count($appearanceOffsets))
            : null;
        $maxShare = count($preview) > 0 && count($senderCounts) > 0
            ? (max($senderCounts) / count($preview))
            : 0.0;

        return [
            'selection_mode' => (string) ($settings['selection_mode'] ?? 'balanced'),
            'selection_mode_label' => $this->selectionModeLabel($settings['selection_mode'] ?? 'balanced'),
            'event_phase' => (string) ($settings['event_phase'] ?? 'flow'),
            'event_phase_label' => $this->eventPhaseLabel($settings['event_phase'] ?? 'flow'),
            'queue_items' => count($items),
            'active_senders' => count($activeSenders),
            'estimated_first_appearance_seconds' => $estimatedFirstAppearance,
            'monopolization_risk' => $this->monopolizationRisk($maxShare, $maxConsecutive),
            'freshness_intensity' => $this->freshnessIntensity($settings),
            'fairness_level' => $this->fairnessLevel($settings),
        ];
    }

    private function buildExplanation(array $summary, array $settings, array $policy): array
    {
        $modeLabel = $summary['selection_mode_label'];
        $phaseLabel = $summary['event_phase_label'];
        $cooldown = (int) round($policy['sender_cooldown_ms'] / 1000);

        return [
            "{$modeLabel} em {$phaseLabel}: a simulacao usou a fila real atual do evento com o draft das configuracoes do wall.",
            "A fila limita {$policy['max_eligible_items_per_sender']} midias elegiveis por remetente, com cooldown de {$cooldown}s e janela de {$policy['sender_window_limit']} aparicoes por remetente.",
            $policy['avoid_same_sender_if_alternative_exists']
                ? 'O selector evita repetir o mesmo remetente em sequencia quando existe alternativa pronta.'
                : 'O selector aceita repeticao imediata do mesmo remetente quando a prioridade restante da fila pedir isso.',
        ];
    }

    private function monopolizationRisk(float $maxShare, int $maxConsecutive): string
    {
        if ($maxShare >= 0.5 || $maxConsecutive >= 3) {
            return 'high';
        }

        if ($maxShare >= 0.34 || $maxConsecutive >= 2) {
            return 'medium';
        }

        return 'low';
    }

    private function freshnessIntensity(array $settings): string
    {
        $mode = (string) ($settings['selection_mode'] ?? 'balanced');
        $phase = (string) ($settings['event_phase'] ?? 'flow');

        if ($phase === 'party') {
            return 'high';
        }

        if ($phase === 'reception') {
            return $mode === 'live' ? 'medium' : 'low';
        }

        return match ($mode) {
            'live' => 'high',
            'inclusive' => 'low',
            default => 'medium',
        };
    }

    private function fairnessLevel(array $settings): string
    {
        $policy = WallSelectionPreset::applyPhasePolicy(
            WallSelectionPreset::normalizePolicy(
                $settings['selection_policy'] ?? null,
                $settings['selection_mode'] ?? null,
            ),
            $settings['event_phase'] ?? null,
        );

        if (
            ! empty($policy['avoid_same_sender_if_alternative_exists'])
            && (int) $policy['sender_cooldown_seconds'] >= 60
            && (int) $policy['sender_window_limit'] <= 3
        ) {
            return 'high';
        }

        if (! empty($policy['avoid_same_sender_if_alternative_exists'])) {
            return 'medium';
        }

        return 'low';
    }

    private function selectionModeLabel(string $mode): string
    {
        return WallSelectionMode::tryFrom($mode)?->label() ?? 'Equilibrado';
    }

    private function eventPhaseLabel(string $phase): string
    {
        return WallEventPhase::tryFrom($phase)?->label() ?? 'Fluxo';
    }

    private function resolveInitialItemId(
        array $items,
        ?string $preferredCurrentItemId,
        array $policy,
        array $senderStats,
        CarbonImmutable $referenceTime,
    ): ?string {
        if ($preferredCurrentItemId) {
            $existing = $this->findItem($items, $preferredCurrentItemId);
            if ($existing && $this->isRenderable($existing)) {
                return $existing['id'];
            }
        }

        return $this->pickNextItemId($items, null, $policy, $senderStats, $referenceTime);
    }

    private function pickNextItemId(
        array $items,
        ?string $currentItemId,
        array $policy,
        array $senderStats,
        CarbonImmutable $referenceTime,
    ): ?string {
        $candidatePool = $this->selectEligibleItems($items, $policy, $senderStats, $referenceTime);

        if ($candidatePool === []) {
            return null;
        }

        $currentItem = $currentItemId ? $this->findItem($candidatePool, $currentItemId) : null;
        $currentDuplicateClusterKey = $currentItem['duplicateClusterKey'] ?? null;
        $candidatesBySender = [];

        foreach ($candidatePool as $item) {
            $candidatesBySender[$item['senderKey']][] = $item;
        }

        $senderCandidates = [];
        foreach ($candidatesBySender as $senderKey => $senderItems) {
            $candidate = $this->selectBestItemWithinSender($senderItems, $currentDuplicateClusterKey, $policy);

            if (! $candidate) {
                continue;
            }

            $lastPlayedAt = null;
            foreach ($senderItems as $senderItem) {
                if ($senderItem['played_at'] && ($lastPlayedAt === null || $this->compareNullableIsoDesc($senderItem['played_at'], $lastPlayedAt) < 0)) {
                    $lastPlayedAt = $senderItem['played_at'];
                }
            }

            $senderCandidates[] = [
                'senderKey' => $senderKey,
                'candidate' => $candidate,
                'totalPlayCount' => array_sum(array_map(
                    fn (array $item): int => (int) $item['play_count'],
                    $senderItems,
                )),
                'lastPlayedAt' => $lastPlayedAt,
            ];
        }

        if ($senderCandidates === []) {
            return null;
        }

        $currentSenderKey = $currentItem['senderKey'] ?? null;
        $fairPool = (
            $policy['avoid_same_sender_if_alternative_exists']
            && $currentSenderKey
            && count($senderCandidates) > 1
        )
            ? array_values(array_filter(
                $senderCandidates,
                fn (array $entry): bool => $entry['senderKey'] !== $currentSenderKey,
            ))
            : $senderCandidates;

        usort($fairPool, function (array $left, array $right) use ($currentDuplicateClusterKey): int {
            $leftDifferentCluster = $currentDuplicateClusterKey !== null
                && $left['candidate']['duplicateClusterKey'] !== $currentDuplicateClusterKey;
            $rightDifferentCluster = $currentDuplicateClusterKey !== null
                && $right['candidate']['duplicateClusterKey'] !== $currentDuplicateClusterKey;

            if ($leftDifferentCluster !== $rightDifferentCluster) {
                return $rightDifferentCluster <=> $leftDifferentCluster;
            }

            $leftUnseen = $left['candidate']['played_at'] === null;
            $rightUnseen = $right['candidate']['played_at'] === null;
            if ($leftUnseen !== $rightUnseen) {
                return $rightUnseen <=> $leftUnseen;
            }

            $playedCompare = $this->compareNullableIsoAsc($left['lastPlayedAt'], $right['lastPlayedAt']);
            if ($playedCompare !== 0) {
                return $playedCompare;
            }

            if ($left['totalPlayCount'] !== $right['totalPlayCount']) {
                return $left['totalPlayCount'] <=> $right['totalPlayCount'];
            }

            if ($left['candidate']['is_featured'] !== $right['candidate']['is_featured']) {
                return (int) $right['candidate']['is_featured'] <=> (int) $left['candidate']['is_featured'];
            }

            $createdCompare = $this->compareNullableIsoDesc(
                $left['candidate']['created_at'],
                $right['candidate']['created_at'],
            );
            if ($createdCompare !== 0) {
                return $createdCompare;
            }

            return strcmp($left['senderKey'], $right['senderKey']);
        });

        return $fairPool[0]['candidate']['id'] ?? null;
    }

    private function selectEligibleItems(
        array $items,
        array $policy,
        array $senderStats,
        CarbonImmutable $referenceTime,
    ): array {
        $renderable = array_values(array_filter($items, fn (array $item): bool => $this->isRenderable($item)));
        if ($renderable === []) {
            return [];
        }

        $hasReadyItems = count(array_filter(
            $renderable,
            fn (array $item): bool => $item['asset_status'] === 'ready',
        )) > 0;
        $candidatePool = $hasReadyItems
            ? array_values(array_filter($renderable, fn (array $item): bool => $item['asset_status'] === 'ready'))
            : $renderable;
        $replayBudgetPool = array_values(array_filter(
            $candidatePool,
            fn (array $item): bool => $this->hasReplayBudget($item, $policy),
        ));
        $budgetAwarePool = $replayBudgetPool !== [] ? $replayBudgetPool : $candidatePool;
        $unseenItems = array_values(array_filter(
            $budgetAwarePool,
            fn (array $item): bool => (int) $item['play_count'] === 0 || ! $item['played_at'],
        ));
        $matureReplayItems = array_values(array_filter($budgetAwarePool, function (array $item) use ($budgetAwarePool, $policy, $referenceTime): bool {
            return (int) $item['play_count'] > 0
                && ! empty($item['played_at'])
                && $this->isReplayMature($item, count($budgetAwarePool), $policy, $referenceTime);
        }));
        $replayAwarePool = $unseenItems !== []
            ? $unseenItems
            : ($matureReplayItems !== [] ? $matureReplayItems : $budgetAwarePool);

        $candidatesBySender = [];
        foreach ($replayAwarePool as $item) {
            $candidatesBySender[$item['senderKey']][] = $item;
        }

        $availableSenders = [];
        $hasUnblockedSender = false;
        foreach ($candidatesBySender as $senderKey => $senderItems) {
            $blocked = $this->isSenderBlocked($senderKey, $senderStats, $policy, $referenceTime);
            $availableSenders[] = [
                'senderKey' => $senderKey,
                'items' => $senderItems,
                'blocked' => $blocked,
            ];
            $hasUnblockedSender = $hasUnblockedSender || ! $blocked;
        }

        if ($hasUnblockedSender) {
            $availableSenders = array_values(array_filter(
                $availableSenders,
                fn (array $entry): bool => ! $entry['blocked'],
            ));
        }

        $result = [];
        foreach ($availableSenders as $entry) {
            array_push($result, ...$this->selectItemsWithinSenderWindow($entry['items'], $policy));
        }

        return $result;
    }

    private function selectBestItemWithinSender(
        array $items,
        ?string $currentDuplicateClusterKey,
        array $policy,
    ): ?array {
        if ($items === []) {
            return null;
        }

        $ordered = $this->selectItemsWithinSenderWindow($items, $policy);

        if (! $policy['avoid_same_duplicate_cluster_if_alternative_exists'] || ! $currentDuplicateClusterKey) {
            return $ordered[0] ?? null;
        }

        foreach ($ordered as $item) {
            if (($item['duplicateClusterKey'] ?? null) !== $currentDuplicateClusterKey) {
                return $item;
            }
        }

        return $ordered[0] ?? null;
    }

    private function selectItemsWithinSenderWindow(array $items, array $policy): array
    {
        $ordered = $items;
        usort($ordered, fn (array $left, array $right): int => $this->compareItemsWithinSender($left, $right));

        return array_slice($ordered, 0, max(1, (int) $policy['max_eligible_items_per_sender']));
    }

    private function compareItemsWithinSender(array $left, array $right): int
    {
        if ($left['is_featured'] !== $right['is_featured']) {
            return (int) $right['is_featured'] <=> (int) $left['is_featured'];
        }

        $leftUnseen = $left['played_at'] === null;
        $rightUnseen = $right['played_at'] === null;
        if ($leftUnseen !== $rightUnseen) {
            return $rightUnseen <=> $leftUnseen;
        }

        if ((int) $left['play_count'] !== (int) $right['play_count']) {
            return (int) $left['play_count'] <=> (int) $right['play_count'];
        }

        $playedCompare = $this->compareNullableIsoAsc($left['played_at'], $right['played_at']);
        if ($playedCompare !== 0) {
            return $playedCompare;
        }

        $createdCompare = $this->compareNullableIsoDesc($left['created_at'], $right['created_at']);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return strcmp($left['id'], $right['id']);
    }

    private function markPlayback(
        array $items,
        array $senderStats,
        string $itemId,
        CarbonImmutable $playedAt,
    ): array {
        $playedItem = $this->findItem($items, $itemId);

        if (! $playedItem) {
            return [$items, $senderStats];
        }

        $playedAtIso = $playedAt->toIso8601String();
        $nextItems = array_map(function (array $item) use ($itemId, $playedAtIso): array {
            if ($item['id'] !== $itemId) {
                return $item;
            }

            $item['played_at'] = $playedAtIso;
            $item['play_count'] = (int) $item['play_count'] + 1;

            return $item;
        }, $items);

        $stats = $senderStats[$playedItem['senderKey']] ?? [
            'last_played_at' => null,
            'recent_play_timestamps' => [],
            'total_play_count' => 0,
        ];
        $stats['last_played_at'] = $playedAtIso;
        $stats['recent_play_timestamps'][] = $playedAtIso;
        $stats['recent_play_timestamps'] = array_slice($stats['recent_play_timestamps'], -20);
        $stats['total_play_count'] = (int) $stats['total_play_count'] + 1;
        $senderStats[$playedItem['senderKey']] = $stats;

        return [$nextItems, $senderStats];
    }

    private function isRenderable(array $item): bool
    {
        return ! empty($item['url']) && ($item['asset_status'] ?? 'ready') !== 'error';
    }

    private function hasReplayBudget(array $item, array $policy): bool
    {
        $maxDisplaysPerItem = max(1, (int) $policy['max_replays_per_item'] + 1);

        if ((int) $item['play_count'] <= 0) {
            return true;
        }

        return (int) $item['play_count'] < $maxDisplaysPerItem;
    }

    private function isReplayMature(
        array $item,
        int $itemCount,
        array $policy,
        CarbonImmutable $referenceTime,
    ): bool {
        if (! $item['played_at'] || (int) $item['play_count'] === 0) {
            return true;
        }

        return ($this->timestampMs($referenceTime->toIso8601String()) - $this->timestampMs($item['played_at']))
            >= $this->resolveReplayIntervalMs($itemCount, $policy);
    }

    private function resolveReplayIntervalMs(int $itemCount, array $policy): int
    {
        if ($itemCount <= (int) $policy['low_volume_max_items']) {
            return (int) $policy['replay_interval_low_ms'];
        }

        if ($itemCount <= (int) $policy['medium_volume_max_items']) {
            return (int) $policy['replay_interval_medium_ms'];
        }

        return (int) $policy['replay_interval_high_ms'];
    }

    private function isSenderBlocked(
        string $senderKey,
        array $senderStats,
        array $policy,
        CarbonImmutable $referenceTime,
    ): bool {
        $stats = $senderStats[$senderKey] ?? null;
        if (! $stats) {
            return false;
        }

        $blockedByCooldown = ! empty($stats['last_played_at'])
            && ($this->timestampMs($referenceTime->toIso8601String()) - $this->timestampMs($stats['last_played_at']))
                < (int) $policy['sender_cooldown_ms'];

        $recentCount = 0;
        foreach ($stats['recent_play_timestamps'] ?? [] as $value) {
            if (($this->timestampMs($referenceTime->toIso8601String()) - $this->timestampMs($value)) <= (int) $policy['sender_window_ms']) {
                $recentCount++;
            }
        }

        return $blockedByCooldown || $recentCount >= (int) $policy['sender_window_limit'];
    }

    private function compareNullableIsoAsc(?string $left, ?string $right): int
    {
        if (! $left && ! $right) {
            return 0;
        }

        if (! $left) {
            return -1;
        }

        if (! $right) {
            return 1;
        }

        return $this->timestampMs($left) <=> $this->timestampMs($right);
    }

    private function compareNullableIsoDesc(?string $left, ?string $right): int
    {
        return $this->compareNullableIsoAsc($right, $left);
    }

    private function timestampMs(?string $value): int
    {
        if (! $value) {
            return 0;
        }

        try {
            return CarbonImmutable::parse($value)->getTimestampMs();
        } catch (Throwable) {
            return 0;
        }
    }

    private function findItem(array $items, string $itemId): ?array
    {
        foreach ($items as $item) {
            if ($item['id'] === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
