<?php
use Illuminate\Support\Facades\Route;

Route::get('public/event-packages', [\App\Modules\Billing\Http\Controllers\EventPackageController::class, 'publicIndex']);
Route::post('public/trial-events', [\App\Modules\Billing\Http\Controllers\PublicTrialEventController::class, 'store']);
Route::post('public/checkout-identity/check', [\App\Modules\Billing\Http\Controllers\PublicCheckoutIdentityController::class, 'check'])
    ->middleware('throttle:public-checkout-identity');
Route::post('public/event-checkouts', [\App\Modules\Billing\Http\Controllers\PublicEventCheckoutController::class, 'store']);
Route::get('public/event-checkouts/{billingOrder:uuid}', [\App\Modules\Billing\Http\Controllers\PublicEventCheckoutController::class, 'show']);
Route::post('public/event-checkouts/{billingOrder:uuid}/confirm', [\App\Modules\Billing\Http\Controllers\PublicEventCheckoutController::class, 'confirm']);
Route::post('webhooks/billing/{provider}', [\App\Modules\Billing\Http\Controllers\BillingWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('admin/quick-events', [\App\Modules\Billing\Http\Controllers\AdminQuickEventController::class, 'store']);

    // Subscription management
    Route::get('billing/subscription', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'current']);
    Route::get('billing/subscription/cards', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'cards']);
    Route::patch('billing/subscription/card', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'updateCard']);
    Route::post('billing/subscription/reconcile', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'reconcile']);
    Route::post('billing/subscription/cancel', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'cancel']);
    Route::get('billing/invoices', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'invoices']);
    Route::post('billing/checkout', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'checkout']);
    Route::post('billing/orders/{billingOrder:uuid}/cancel', [\App\Modules\Billing\Http\Controllers\BillingOrderController::class, 'cancel']);
    Route::post('billing/orders/{billingOrder:uuid}/refresh', [\App\Modules\Billing\Http\Controllers\BillingOrderController::class, 'refresh']);
    Route::post('billing/orders/{billingOrder:uuid}/retry', [\App\Modules\Billing\Http\Controllers\BillingOrderController::class, 'retry']);

    Route::get('event-packages', [\App\Modules\Billing\Http\Controllers\EventPackageController::class, 'index']);
    Route::get('event-packages/{eventPackage}', [\App\Modules\Billing\Http\Controllers\EventPackageController::class, 'show']);

    // plans/current is registered in Plans module routes (before apiResource)

    // Admin subscriptions listing
    Route::get('subscriptions', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'index']);
    Route::get('subscriptions/{subscription}', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'show']);
});
