<?php

use App\Modules\MediaIntelligence\Http\Controllers\EventMediaIntelligenceSettingsController;
use App\Modules\MediaIntelligence\Http\Controllers\MediaReplyEventHistoryController;
use App\Modules\MediaIntelligence\Http\Controllers\MediaReplyPromptCategoryController;
use App\Modules\MediaIntelligence\Http\Controllers\MediaReplyPromptPresetController;
use App\Modules\MediaIntelligence\Http\Controllers\MediaReplyPromptTestController;
use App\Modules\MediaIntelligence\Http\Controllers\MediaIntelligenceGlobalSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        'ia/respostas-de-midia/configuracao',
        [MediaIntelligenceGlobalSettingsController::class, 'show'],
    );
    Route::patch(
        'ia/respostas-de-midia/configuracao',
        [MediaIntelligenceGlobalSettingsController::class, 'update'],
    );
    Route::get(
        'ia/respostas-de-midia/categorias',
        [MediaReplyPromptCategoryController::class, 'index'],
    );
    Route::post(
        'ia/respostas-de-midia/categorias',
        [MediaReplyPromptCategoryController::class, 'store'],
    );
    Route::patch(
        'ia/respostas-de-midia/categorias/{category}',
        [MediaReplyPromptCategoryController::class, 'update'],
    );
    Route::delete(
        'ia/respostas-de-midia/categorias/{category}',
        [MediaReplyPromptCategoryController::class, 'destroy'],
    );
    Route::get(
        'ia/respostas-de-midia/presets',
        [MediaReplyPromptPresetController::class, 'index'],
    );
    Route::post(
        'ia/respostas-de-midia/presets',
        [MediaReplyPromptPresetController::class, 'store'],
    );
    Route::patch(
        'ia/respostas-de-midia/presets/{preset}',
        [MediaReplyPromptPresetController::class, 'update'],
    );
    Route::delete(
        'ia/respostas-de-midia/presets/{preset}',
        [MediaReplyPromptPresetController::class, 'destroy'],
    );
    Route::get(
        'ia/respostas-de-midia/testes',
        [MediaReplyPromptTestController::class, 'index'],
    );
    Route::post(
        'ia/respostas-de-midia/testes',
        [MediaReplyPromptTestController::class, 'store'],
    );
    Route::get(
        'ia/respostas-de-midia/testes/{teste}',
        [MediaReplyPromptTestController::class, 'show'],
    );
    Route::get(
        'ia/respostas-de-midia/eventos',
        [MediaReplyEventHistoryController::class, 'events'],
    );
    Route::get(
        'ia/respostas-de-midia/historico-eventos',
        [MediaReplyEventHistoryController::class, 'index'],
    );
    Route::get(
        'ia/respostas-de-midia/historico-eventos/{historicoEvento}',
        [MediaReplyEventHistoryController::class, 'show'],
    );
    Route::get(
        'media-intelligence/global-settings',
        [MediaIntelligenceGlobalSettingsController::class, 'show'],
    );
    Route::patch(
        'media-intelligence/global-settings',
        [MediaIntelligenceGlobalSettingsController::class, 'update'],
    );
    Route::get(
        'events/{event}/media-intelligence/settings',
        [EventMediaIntelligenceSettingsController::class, 'show'],
    );
    Route::patch(
        'events/{event}/media-intelligence/settings',
        [EventMediaIntelligenceSettingsController::class, 'update'],
    );
});
