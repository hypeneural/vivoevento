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
    Route::post('organizations/current/branding/logo', [OrganizationController::class, 'uploadBrandingLogo']);

    // Team management
    Route::get('organizations/current/team', [OrganizationController::class, 'team']);
    Route::post('organizations/current/team', [OrganizationController::class, 'inviteTeamMember']);
    Route::delete('organizations/current/team/{member}', [OrganizationController::class, 'removeTeamMember']);

    // Admin CRUD
    Route::apiResource('organizations', OrganizationController::class);
});
