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
