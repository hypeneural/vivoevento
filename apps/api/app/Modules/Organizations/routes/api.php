<?php

use App\Modules\Organizations\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organizations Module Routes
|--------------------------------------------------------------------------
*/

Route::get('public/organization-invitations/{token}', [\App\Modules\Organizations\Http\Controllers\PublicOrganizationInvitationController::class, 'show']);
Route::post('public/organization-invitations/{token}/accept', [\App\Modules\Organizations\Http\Controllers\PublicOrganizationInvitationController::class, 'accept']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('organization-invitations/{token}/accept', [\App\Modules\Organizations\Http\Controllers\AuthenticatedOrganizationInvitationController::class, 'accept']);

    // Current organization (for the authenticated user)
    Route::get('organizations/current', [OrganizationController::class, 'current']);
    Route::patch('organizations/current', [OrganizationController::class, 'updateCurrent']);
    Route::patch('organizations/current/branding', [OrganizationController::class, 'updateBranding']);
    Route::post('organizations/current/branding/logo', [OrganizationController::class, 'uploadBrandingLogo']);
    Route::post('organizations/current/branding/assets', [OrganizationController::class, 'uploadBrandingAsset']);

    // Team management
    Route::get('organizations/current/team', [OrganizationController::class, 'team']);
    Route::post('organizations/current/team/ownership-transfer', [OrganizationController::class, 'transferOwnership']);
    Route::delete('organizations/current/team/{member}', [OrganizationController::class, 'removeTeamMember']);
    Route::get('organizations/current/team/invitations', [\App\Modules\Organizations\Http\Controllers\CurrentOrganizationTeamInvitationController::class, 'index']);
    Route::post('organizations/current/team', [\App\Modules\Organizations\Http\Controllers\CurrentOrganizationTeamInvitationController::class, 'store']);
    Route::post('organizations/current/team/invitations/{invitation}/resend', [\App\Modules\Organizations\Http\Controllers\CurrentOrganizationTeamInvitationController::class, 'resend']);
    Route::post('organizations/current/team/invitations/{invitation}/revoke', [\App\Modules\Organizations\Http\Controllers\CurrentOrganizationTeamInvitationController::class, 'revoke']);

    // Admin CRUD
    Route::apiResource('organizations', OrganizationController::class);
});
