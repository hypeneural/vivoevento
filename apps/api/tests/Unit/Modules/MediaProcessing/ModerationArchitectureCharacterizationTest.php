<?php

it('dispatches moderation broadcasting events with toOthers suppression', function () {
    $broadcasterSource = file_get_contents(app_path('Modules/MediaProcessing/Services/ModerationBroadcasterService.php'));
    $eventSource = file_get_contents(app_path('Modules/MediaProcessing/Events/AbstractModerationBroadcastEvent.php'));

    expect($eventSource)->toContain('InteractsWithSockets')
        ->and($broadcasterSource)->toContain('broadcast(new ModerationMediaCreated')
        ->and($broadcasterSource)->toContain('broadcast(new ModerationMediaUpdated')
        ->and($broadcasterSource)->toContain('toOthers()');
});

it('exposes moderation feed stats through a dedicated controller endpoint', function () {
    $controllerSource = file_get_contents(app_path('Modules/MediaProcessing/Http/Controllers/EventMediaController.php'));

    expect($controllerSource)->toContain('public function moderationFeedStats')
        ->toContain("'stats' => null");
});

it('keeps the moderation search hot path on a dedicated search document and ships feed indexes', function () {
    $querySource = file_get_contents(app_path('Modules/MediaProcessing/Queries/ListModerationMediaQuery.php'));
    $migrationSource = file_get_contents(database_path('migrations/2026_04_09_230000_add_moderation_feed_indexes.php'));
    $searchDocumentMigrationSource = file_get_contents(database_path('migrations/2026_04_09_232000_add_moderation_search_document_to_event_media.php'));

    expect($querySource)->toContain("where('event_media.moderation_search_document'")
        ->and($querySource)->toContain('eventIdsMatchingExactSearchTitle')
        ->and($querySource)->not->toContain("leftJoin('inbound_messages as moderation_search_messages'")
        ->and($querySource)->not->toContain("orWhereHas('inboundMessage'")
        ->and($migrationSource)->toContain('CREATE EXTENSION IF NOT EXISTS pg_trgm')
        ->and($migrationSource)->toContain('event_media_moderation_feed_event_sort_moderation_created_idx')
        ->and($migrationSource)->toContain('event_media_moderation_feed_event_publication_processing_create')
        ->and($migrationSource)->toContain('event_media_moderation_search_trgm_idx')
        ->and($migrationSource)->toContain('inbound_messages_moderation_search_trgm_idx')
        ->and($searchDocumentMigrationSource)->toContain('moderation_search_document')
        ->and($searchDocumentMigrationSource)->toContain('event_media_moderation_search_document_trgm_idx');
});

it('registers the moderation explain command for homolog and high-volume validation', function () {
    $providerSource = file_get_contents(app_path('Modules/MediaProcessing/Providers/MediaProcessingServiceProvider.php'));
    $commandSource = file_get_contents(app_path('Modules/MediaProcessing/Console/RunModerationFeedExplainCommand.php'));
    $serviceSource = file_get_contents(app_path('Modules/MediaProcessing/Services/ModerationFeedExplainAnalyzeService.php'));

    expect($providerSource)->toContain('RunModerationFeedExplainCommand::class')
        ->and($commandSource)->toContain("media:moderation-feed-explain")
        ->and($commandSource)->toContain('--fail-on-budget')
        ->and($commandSource)->toContain('--search-budget-ms=500')
        ->and($commandSource)->toContain('--synthetic-media=0')
        ->and($commandSource)->toContain('--disable-jit')
        ->and($commandSource)->toContain('search_document.requires_follow_up')
        ->and($serviceSource)->toContain('seedSyntheticModerationVolume')
        ->and($serviceSource)->toContain('search_document_present_but_budget_exceeded')
        ->and($serviceSource)->toContain('SET LOCAL jit = off')
        ->and($serviceSource)->toContain('rollBack()');
});

it('registers duplicate-cluster lookup and manual decision undo in the moderation controller', function () {
    $routesSource = file_get_contents(app_path('Modules/MediaProcessing/routes/api.php'));
    $controllerSource = file_get_contents(app_path('Modules/MediaProcessing/Http/Controllers/EventMediaController.php'));

    expect($routesSource)->toContain("Route::get('media/{eventMedia}/duplicates'")
        ->and($routesSource)->toContain("Route::post('media/{eventMedia}/undo-decision'")
        ->and($controllerSource)->toContain('public function duplicateCluster')
        ->and($controllerSource)->toContain('public function undoDecision');
});
