<?php

namespace App\Modules\EventOperations\Support;

class EventOperationsAttentionPriority
{
    public const CRITICAL_IMMEDIATE = 'critical_immediate';
    public const OPERATIONAL_NORMAL = 'operational_normal';
    public const TIMELINE_COALESCIBLE = 'timeline_coalescible';

    public function resolve(
        string $severity,
        string $urgency,
        ?string $eventKey = null,
    ): string {
        if ($severity === 'critical' || $urgency === 'critical') {
            return self::CRITICAL_IMMEDIATE;
        }

        if ($severity === 'warning' || $urgency === 'high') {
            return self::OPERATIONAL_NORMAL;
        }

        if (in_array($eventKey, [
            'media.card.arrived',
            'media.download.completed',
            'media.variants.generated',
            'media.moderation.approved',
            'feedback.sent',
        ], true)) {
            return self::TIMELINE_COALESCIBLE;
        }

        return self::OPERATIONAL_NORMAL;
    }

    public function score(string $priority): int
    {
        return match ($priority) {
            self::CRITICAL_IMMEDIATE => 300,
            self::OPERATIONAL_NORMAL => 200,
            default => 100,
        };
    }
}
