<?php

use App\Modules\Events\Models\Event;
use App\Shared\Concerns\InteractsWithActivityLogProperties;
use Spatie\Activitylog\Models\Activity;

it('casts activity-log property comparisons to text-compatible bindings', function () {
    $helper = new class
    {
        use InteractsWithActivityLogProperties;

        public function apply()
        {
            $query = Activity::query();
            $eventIds = Event::query()
                ->select('id')
                ->where('organization_id', 9);

            $this->whereActivityPropertyIdEquals($query, 'partner_id', 3);
            $this->whereActivityPropertyIdInQuery($query, 'event_id', $eventIds, 'or');

            return $query;
        }
    };

    $query = $helper->apply();

    expect($query->toSql())->toContain('CAST(id AS TEXT)');
    expect($query->getBindings())->toContain('3');
});
