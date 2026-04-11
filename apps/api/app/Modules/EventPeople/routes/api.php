<?php

use App\Modules\EventPeople\Http\Controllers\EventMediaPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleReviewQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/people', [EventPeopleController::class, 'index']);
    Route::get('events/{event}/people/review-queue', [EventPeopleReviewQueueController::class, 'index']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/confirm', [EventPeopleReviewQueueController::class, 'confirm']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/ignore', [EventPeopleReviewQueueController::class, 'ignore']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/reject', [EventPeopleReviewQueueController::class, 'reject']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/merge', [EventPeopleReviewQueueController::class, 'merge']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/split', [EventPeopleReviewQueueController::class, 'split']);
    Route::get('events/{event}/people/{person}', [EventPeopleController::class, 'show']);
    Route::get('events/{event}/media/{media}/people', [EventMediaPeopleController::class, 'show']);
});
