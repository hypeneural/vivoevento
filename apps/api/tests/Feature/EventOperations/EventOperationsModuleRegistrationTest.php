<?php

use App\Modules\EventOperations\Providers\EventOperationsServiceProvider;

it('registers the event operations module and its backend scaffolding artifacts', function () {
    expect(config('modules.modules.EventOperations'))
        ->toBe(EventOperationsServiceProvider::class);

    expect(app_path('Modules/EventOperations/Providers/EventOperationsServiceProvider.php'))->toBeFile()
        ->and(app_path('Modules/EventOperations/routes/api.php'))->toBeFile()
        ->and(app_path('Modules/EventOperations/README.md'))->toBeFile()
        ->and(database_path('migrations/2026_04_12_210000_create_event_operation_events_table.php'))->toBeFile()
        ->and(database_path('migrations/2026_04_12_210100_create_event_operation_snapshots_table.php'))->toBeFile();

    $moduleMap = file_get_contents(dirname(base_path(), 2) . '/docs/modules/module-map.md');

    expect($moduleMap)->toContain('EventOperations')
        ->and($moduleMap)->toContain('Sala operacional realtime por evento');
});
