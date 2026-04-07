<?php

namespace App\Modules\Dashboard\Queries;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertsQuery
{
    /**
     * Returns dynamic alerts based on current system state.
     */
    public function execute(int $organizationId): array
    {
        $alerts = [];
        $today = CarbonImmutable::today()->toDateString();

        if (! Schema::hasTable('event_media')) {
            return $alerts;
        }

        // 1. Events approaching photo limit (90%+)
        $events = DB::select("
            SELECT e.id, e.title, COUNT(em.id) AS photo_count, e.purchased_plan_snapshot_json
            FROM events e
            LEFT JOIN event_media em ON em.event_id = e.id
            WHERE e.organization_id = ?
              AND e.status = 'active'
              AND e.deleted_at IS NULL
            GROUP BY e.id, e.title, e.purchased_plan_snapshot_json
        ", [$organizationId]);

        foreach ($events as $event) {
            $snapshot = json_decode($event->purchased_plan_snapshot_json ?? '{}', true);
            $maxPhotos = $snapshot['max_photos'] ?? null;

            if ($maxPhotos && $event->photo_count >= $maxPhotos * 0.9) {
                $pct = round($event->photo_count / $maxPhotos * 100);
                $alerts[] = [
                    'type'        => 'warning',
                    'icon'        => 'alert-triangle',
                    'message'     => "Evento \"{$event->title}\" atingiu {$pct}% do limite de fotos",
                    'entity_type' => 'event',
                    'entity_id'   => $event->id,
                ];
            }
        }

        // 2. Media with processing errors
        $errorCount = DB::selectOne("
            SELECT COUNT(*) AS c
            FROM event_media em
            JOIN events e ON em.event_id = e.id
            WHERE e.organization_id = ?
              AND e.deleted_at IS NULL
              AND em.processing_status = ?
        ", [$organizationId, MediaProcessingStatus::Failed->value]);

        if ($errorCount && (int) $errorCount->c > 0) {
            $count = (int) $errorCount->c;
            $alerts[] = [
                'type'        => 'error',
                'icon'        => 'clock',
                'message'     => "{$count} foto(s) com erro de processamento",
                'entity_type' => 'media',
                'entity_id'   => null,
            ];
        }

        // 3. Events starting today without media
        $todayEvents = DB::select("
            SELECT e.id, e.title
            FROM events e
            WHERE e.organization_id = ?
              AND e.deleted_at IS NULL
              AND e.status = 'active'
              AND DATE(e.starts_at) = ?
              AND (SELECT COUNT(*) FROM event_media WHERE event_id = e.id) = 0
        ", [$organizationId, $today]);

        foreach ($todayEvents as $event) {
            $alerts[] = [
                'type'        => 'info',
                'icon'        => 'calendar',
                'message'     => "Evento \"{$event->title}\" começa hoje e ainda não recebeu mídias",
                'entity_type' => 'event',
                'entity_id'   => $event->id,
            ];
        }

        return $alerts;
    }
}
