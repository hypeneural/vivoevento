<?php

use Illuminate\Support\Facades\Route;

Route::get('public/event-invitations/{token}', [\App\Modules\EventTeam\Http\Controllers\PublicEventTeamInvitationController::class, 'show']);
Route::post('public/event-invitations/{token}/accept', [\App\Modules\EventTeam\Http\Controllers\PublicEventTeamInvitationController::class, 'accept']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('event-invitations/{token}/accept', [\App\Modules\EventTeam\Http\Controllers\AuthenticatedEventTeamInvitationController::class, 'accept']);
    Route::get('events/{event}/access/invitations', [\App\Modules\EventTeam\Http\Controllers\EventTeamInvitationController::class, 'index']);
    Route::post('events/{event}/access/invitations', [\App\Modules\EventTeam\Http\Controllers\EventTeamInvitationController::class, 'store']);
    Route::post('events/{event}/access/invitations/{invitation}/resend', [\App\Modules\EventTeam\Http\Controllers\EventTeamInvitationController::class, 'resend']);
    Route::post('events/{event}/access/invitations/{invitation}/revoke', [\App\Modules\EventTeam\Http\Controllers\EventTeamInvitationController::class, 'revoke']);
    Route::get('events/{event}/team', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'index']);
    Route::post('events/{event}/team', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'store']);
    Route::patch('events/{event}/team/{member}', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'update']);
    Route::delete('events/{event}/team/{member}', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'destroy']);
});
