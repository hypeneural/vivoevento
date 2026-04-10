<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MediaProcessing Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Media management
    Route::get('media', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'catalogIndex']);
    Route::get('media/feed', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'moderationFeed']);
    Route::get('media/feed/stats', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'moderationFeedStats']);
    Route::post('media/feed/telemetry', [\App\Modules\MediaProcessing\Http\Controllers\ModerationTelemetryController::class, 'store']);
    Route::get('events/{event}/media', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'index']);
    Route::get('media/{eventMedia}', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'show']);
    Route::get('media/{eventMedia}/duplicates', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'duplicateCluster']);
    Route::get('media/{eventMedia}/ia-debug', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'aiDebug']);
    Route::post('media/bulk/approve', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'bulkApprove']);
    Route::post('media/bulk/reject', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'bulkReject']);
    Route::patch('media/bulk/favorite', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'bulkUpdateFeatured']);
    Route::patch('media/bulk/pin', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'bulkUpdatePinned']);
    Route::post('media/{eventMedia}/approve', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'approve']);
    Route::post('media/{eventMedia}/reject', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'reject']);
    Route::post('media/{eventMedia}/undo-decision', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'undoDecision']);
    Route::post('media/{eventMedia}/reprocess/{stage}', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'reprocess']);
    Route::patch('media/{eventMedia}/favorite', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'updateFeatured']);
    Route::patch('media/{eventMedia}/pin', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'updatePinned']);
    Route::post('media/{eventMedia}/sender-block', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'blockSender']);
    Route::delete('media/{eventMedia}/sender-block', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'unblockSender']);
    Route::delete('media/{eventMedia}', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'destroy']);
    Route::get('events/{event}/media/pipeline-metrics', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'pipelineMetrics']);
});
