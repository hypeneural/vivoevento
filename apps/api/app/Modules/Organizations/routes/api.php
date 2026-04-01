<?php

use App\Modules\Organizations\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organizations Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Current organization (for the authenticated user)
    Route::get('organizations/current', [OrganizationController::class, 'current']);
    Route::patch('organizations/current', [OrganizationController::class, 'updateCurrent']);
    Route::patch('organizations/current/branding', [OrganizationController::class, 'updateBranding']);

    // Team management
    Route::get('organizations/current/team', [OrganizationController::class, 'team']);
    Route::post('organizations/current/team', [OrganizationController::class, 'inviteTeamMember']);

    // Admin CRUD
    Route::apiResource('organizations', OrganizationController::class);
});
