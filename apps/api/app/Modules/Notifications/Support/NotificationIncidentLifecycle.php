<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\NotificationStatus;
use Carbon\CarbonInterface;

final class NotificationIncidentLifecycle
{
    /**
     * Apply a new occurrence to an existing or new notification incident snapshot.
     *
     * @param  array<string, mixed>  $incident
     * @return array<string, mixed>
     */
    public static function applyOccurrence(array $incident, CarbonInterface $occurredAt): array
    {
        $occurrenceCount = max(0, (int) ($incident['occurrence_count'] ?? 0));

        return array_merge($incident, [
            'status' => NotificationStatus::Active->value,
            'occurrence_count' => $occurrenceCount + 1,
            'first_occurred_at' => $incident['first_occurred_at'] ?? $occurredAt,
            'last_occurred_at' => $occurredAt,
            'read_at' => null,
            'dismissed_at' => null,
            'resolved_at' => null,
        ]);
    }
}
