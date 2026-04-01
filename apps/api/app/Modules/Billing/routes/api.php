<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Subscription management
    Route::get('billing/subscription', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'current']);
    Route::get('billing/invoices', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'invoices']);
    Route::post('billing/checkout', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'checkout']);

    // Plan info
    Route::get('plans/current', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'currentPlan']);

    // Admin subscriptions listing
    Route::get('subscriptions', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'index']);
    Route::get('subscriptions/{subscription}', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'show']);
});
