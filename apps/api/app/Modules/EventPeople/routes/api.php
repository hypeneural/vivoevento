<?php

use App\Modules\EventPeople\Http\Controllers\EventMediaPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleReviewQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/people', [EventPeopleController::class, 'index']);
    Route::get('events/{event}/people/review-queue', [EventPeopleReviewQueueController::class, 'index']);
    Route::get('events/{event}/people/{person}', [EventPeopleController::class, 'show']);
    Route::get('events/{event}/media/{media}/people', [EventMediaPeopleController::class, 'show']);
});
