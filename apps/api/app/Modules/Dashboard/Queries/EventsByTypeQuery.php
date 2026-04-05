<?php

namespace App\Modules\Dashboard\Queries;

use Illuminate\Support\Facades\DB;

class EventsByTypeQuery
{
    private const TYPE_LABELS = [
        'wedding'    => 'Casamento',
        'corporate'  => 'Corporativo',
        'birthday'   => 'Aniversário',
        'fifteen'    => '15 Anos',
        'fair'       => 'Feira',
        'graduation' => 'Formatura',
        'conference' => 'Conferência',
        'party'      => 'Festa',
        'festival'   => 'Festival',
        'other'      => 'Outro',
    ];

    private const TYPE_COLORS = [
        'wedding'    => 'hsl(258, 65%, 52%)',
        'corporate'  => 'hsl(215, 75%, 50%)',
        'birthday'   => 'hsl(152, 60%, 38%)',
        'festival'   => 'hsl(38, 92%, 50%)',
        'party'      => 'hsl(330, 65%, 48%)',
        'conference' => 'hsl(190, 75%, 42%)',
        'fifteen'    => 'hsl(280, 60%, 55%)',
        'fair'       => 'hsl(170, 55%, 40%)',
        'graduation' => 'hsl(45, 85%, 48%)',
        'other'      => 'hsl(0, 72%, 51%)',
    ];

    /**
     * Returns event counts grouped by event_type for the pie chart.
     */
    public function execute(int $organizationId): array
    {
        $rows = DB::select("
            SELECT event_type AS type, COUNT(*) AS count
            FROM events
            WHERE organization_id = ?
              AND deleted_at IS NULL
            GROUP BY event_type
            ORDER BY count DESC
        ", [$organizationId]);

        return collect($rows)->map(fn ($row) => [
            'type'  => $row->type,
            'label' => self::TYPE_LABELS[$row->type] ?? ucfirst($row->type),
            'count' => (int) $row->count,
            'fill'  => self::TYPE_COLORS[$row->type] ?? 'hsl(220, 15%, 55%)',
        ])->all();
    }
}
