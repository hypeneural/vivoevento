<?php

namespace App\Modules\EventPeople\Queries;

use App\Modules\EventPeople\Models\EventCoverageAlert;
use App\Modules\EventPeople\Models\EventCoverageTarget;
use App\Modules\EventPeople\Models\EventCoverageTargetStat;
use App\Modules\Events\Models\Event;
use Illuminate\Support\Collection;

class ListEventPeopleCoverageQuery
{
    /**
     * @return array<string, mixed>
     */
    public function get(Event $event): array
    {
        $targets = EventCoverageTarget::query()
            ->forEvent($event->id)
            ->with([
                'personA',
                'personB',
                'group',
                'stat',
            ])
            ->orderByDesc('importance_rank')
            ->orderBy('id')
            ->get();

        $alerts = EventCoverageAlert::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->with('target.stat')
            ->orderByDesc('severity')
            ->orderBy('id')
            ->get();

        return [
            'summary' => $this->summary($event, $targets, $alerts),
            'targets' => $targets->map(fn (EventCoverageTarget $target): array => $this->targetPayload($target))->all(),
            'alerts' => $alerts->map(fn (EventCoverageAlert $alert): array => $this->alertPayload($alert))->all(),
        ];
    }

    /**
     * @param  Collection<int, EventCoverageTarget>  $targets
     * @param  Collection<int, EventCoverageAlert>  $alerts
     * @return array<string, mixed>
     */
    private function summary(Event $event, Collection $targets, Collection $alerts): array
    {
        $states = $targets
            ->filter(fn (EventCoverageTarget $target): bool => $this->isResolvedTarget($target))
            ->map(fn (EventCoverageTarget $target): string => $target->stat?->coverage_state?->value ?? $target->stat?->coverage_state ?? 'missing')
            ->countBy();

        $lastEvaluatedAt = $targets
            ->map(fn (EventCoverageTarget $target) => $target->last_evaluated_at)
            ->filter()
            ->sort()
            ->last();

        return [
            'missing' => (int) ($states['missing'] ?? 0),
            'weak' => (int) ($states['weak'] ?? 0),
            'ok' => (int) ($states['ok'] ?? 0),
            'strong' => (int) ($states['strong'] ?? 0),
            'active_alerts' => $alerts->count(),
            'last_evaluated_at' => $lastEvaluatedAt?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function targetPayload(EventCoverageTarget $target): array
    {
        return [
            'id' => $target->id,
            'key' => $target->key,
            'label' => $target->label,
            'target_type' => $target->target_type?->value ?? $target->target_type,
            'status' => $target->status,
            'importance_rank' => $target->importance_rank,
            'required_media_count' => $target->required_media_count,
            'required_published_media_count' => $target->required_published_media_count,
            'last_evaluated_at' => $target->last_evaluated_at?->toIso8601String(),
            'person_a' => $target->personA ? [
                'id' => $target->personA->id,
                'display_name' => $target->personA->display_name,
                'type' => $target->personA->type?->value ?? $target->personA->type,
                'side' => $target->personA->side?->value ?? $target->personA->side,
                'status' => $target->personA->status?->value ?? $target->personA->status,
            ] : null,
            'person_b' => $target->personB ? [
                'id' => $target->personB->id,
                'display_name' => $target->personB->display_name,
                'type' => $target->personB->type?->value ?? $target->personB->type,
                'side' => $target->personB->side?->value ?? $target->personB->side,
                'status' => $target->personB->status?->value ?? $target->personB->status,
            ] : null,
            'group' => $target->group ? [
                'id' => $target->group->id,
                'display_name' => $target->group->display_name,
                'slug' => $target->group->slug,
                'importance_rank' => $target->group->importance_rank,
            ] : null,
            'stat' => $target->stat ? $this->statPayload($target->stat) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statPayload(EventCoverageTargetStat $stat): array
    {
        return [
            'coverage_state' => $stat->coverage_state?->value ?? $stat->coverage_state,
            'score' => $stat->score,
            'resolved_entity_count' => $stat->resolved_entity_count,
            'media_count' => $stat->media_count,
            'published_media_count' => $stat->published_media_count,
            'joint_media_count' => $stat->joint_media_count,
            'people_with_primary_photo_count' => $stat->people_with_primary_photo_count,
            'reason_codes' => $stat->reason_codes ?? [],
            'projected_at' => $stat->projected_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function alertPayload(EventCoverageAlert $alert): array
    {
        $target = $alert->relationLoaded('target') ? $alert->target : null;

        return [
            'id' => $alert->id,
            'alert_key' => $alert->alert_key,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'summary' => $alert->summary,
            'status' => $alert->status?->value ?? $alert->status,
            'last_evaluated_at' => $alert->last_evaluated_at?->toIso8601String(),
            'target' => $target ? [
                'id' => $target->id,
                'key' => $target->key,
                'label' => $target->label,
                'coverage_state' => $target->stat?->coverage_state?->value ?? $target->stat?->coverage_state,
            ] : null,
        ];
    }

    private function isResolvedTarget(EventCoverageTarget $target): bool
    {
        $resolvedEntityCount = (int) ($target->stat?->resolved_entity_count ?? 0);

        return match ($target->target_type?->value ?? $target->target_type) {
            'pair' => $resolvedEntityCount >= 2,
            default => $resolvedEntityCount >= 1,
        };
    }
}
