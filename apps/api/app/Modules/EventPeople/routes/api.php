<?php

use App\Modules\EventPeople\Http\Controllers\EventMediaPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleController;
use App\Modules\EventPeople\Http\Controllers\EventPeoplePresetsController;
use App\Modules\EventPeople\Http\Controllers\EventPeopleReviewQueueController;
use App\Modules\EventPeople\Http\Controllers\EventPersonReferencePhotosController;
use App\Modules\EventPeople\Http\Controllers\EventPersonRelationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/people', [EventPeopleController::class, 'index']);
    Route::post('events/{event}/people', [EventPeopleController::class, 'store']);
    Route::get('events/{event}/people/operational-status', [EventPeopleController::class, 'operationalStatus']);
    Route::get('events/{event}/people/presets', [EventPeoplePresetsController::class, 'show']);
    Route::get('events/{event}/people/review-queue', [EventPeopleReviewQueueController::class, 'index']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/confirm', [EventPeopleReviewQueueController::class, 'confirm']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/ignore', [EventPeopleReviewQueueController::class, 'ignore']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/reject', [EventPeopleReviewQueueController::class, 'reject']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/merge', [EventPeopleReviewQueueController::class, 'merge']);
    Route::post('events/{event}/people/review-queue/{reviewItem}/split', [EventPeopleReviewQueueController::class, 'split']);
    Route::post('events/{event}/people/relations', [EventPersonRelationsController::class, 'store']);
    Route::patch('events/{event}/people/relations/{relation}', [EventPersonRelationsController::class, 'update']);
    Route::delete('events/{event}/people/relations/{relation}', [EventPersonRelationsController::class, 'destroy']);
    Route::get('events/{event}/people/{person}/reference-photo-candidates', [EventPersonReferencePhotosController::class, 'candidates']);
    Route::post('events/{event}/people/{person}/reference-photos/gallery-face', [EventPersonReferencePhotosController::class, 'storeFromGallery']);
    Route::post('events/{event}/people/{person}/reference-photos/upload', [EventPersonReferencePhotosController::class, 'upload']);
    Route::post('events/{event}/people/{person}/reference-photos/{referencePhoto}/primary', [EventPersonReferencePhotosController::class, 'setPrimary']);
    Route::get('events/{event}/people/{person}', [EventPeopleController::class, 'show']);
    Route::patch('events/{event}/people/{person}', [EventPeopleController::class, 'update']);
    Route::get('events/{event}/media/{media}/people', [EventMediaPeopleController::class, 'show']);
});
