<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventCoverageAlertStatus;
use App\Modules\EventPeople\Enums\EventCoverageState;
use App\Modules\EventPeople\Models\EventCoverageAlert;
use App\Modules\EventPeople\Models\EventCoverageTarget;
use App\Modules\EventPeople\Models\EventCoverageTargetStat;
use App\Modules\Events\Models\Event;

class ProjectEventCoverageAlertsAction
{
    public function executeForEvent(Event $event): void
    {
        EventCoverageTarget::query()
            ->forEvent($event->id)
            ->with('stat')
            ->orderBy('id')
            ->chunk(200, function ($targets): void {
                foreach ($targets as $target) {
                    $this->executeForTarget($target);
                }
            });
    }

    public function executeForTarget(EventCoverageTarget $target): void
    {
        $stat = $target->stat;

        if (! $stat) {
            return;
        }

        $coverageState = $stat->coverage_state?->value ?? $stat->coverage_state;
        $isResolvedTarget = $this->isResolvedTarget($target, (int) $stat->resolved_entity_count);
        $shouldAlert = $isResolvedTarget
            && in_array($coverageState, [EventCoverageState::Missing->value, EventCoverageState::Weak->value], true);

        $alert = EventCoverageAlert::query()->firstOrNew([
            'event_id' => $target->event_id,
            'alert_key' => $this->alertKey($target),
        ]);

        if (! $shouldAlert) {
            if ($alert->exists && ($alert->status?->value ?? $alert->status) === EventCoverageAlertStatus::Active->value) {
                $alert->forceFill([
                    'status' => EventCoverageAlertStatus::Resolved->value,
                    'last_evaluated_at' => now(),
                ])->save();
            }

            return;
        }

        $severity = $coverageState === EventCoverageState::Missing->value ? 'missing' : 'weak';
        $alert->fill([
            'event_coverage_target_id' => $target->id,
            'severity' => $severity,
            'title' => "Cobertura: {$target->label}",
            'summary' => $this->summaryFor($coverageState, $stat),
            'status' => EventCoverageAlertStatus::Active->value,
            'payload' => [
                'coverage_state' => $coverageState,
                'reason_codes' => $stat->reason_codes ?? [],
            ],
            'last_evaluated_at' => now(),
        ]);

        $alert->save();
    }

    private function alertKey(EventCoverageTarget $target): string
    {
        return 'coverage:' . $target->key;
    }

    private function isResolvedTarget(EventCoverageTarget $target, int $resolvedEntityCount): bool
    {
        return match ($target->target_type?->value ?? $target->target_type) {
            'pair' => $resolvedEntityCount >= 2,
            default => $resolvedEntityCount >= 1,
        };
    }

    private function summaryFor(string $coverageState, EventCoverageTargetStat $stat): string
    {
        $reason = collect($stat->reason_codes ?? [])
            ->map(fn (string $code): string => str_replace('_', ' ', $code))
            ->join(', ');

        if ($reason !== '') {
            return "Cobertura {$coverageState} ({$reason}).";
        }

        return "Cobertura {$coverageState} para esse alvo.";
    }
}
