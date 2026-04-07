<?php
namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Actions\SyncEventEntitlementsAction;
use App\Modules\Billing\Actions\SyncOrganizationEventEntitlementsAction;
use App\Modules\Billing\Console\Commands\PagarmeHomologationCommand;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\EventPurchase;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingGatewayInterface;
use App\Modules\Billing\Services\BillingGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingGatewayManager::class, fn ($app) => new BillingGatewayManager($app));
        $this->app->bind(BillingGatewayInterface::class, fn ($app) => $app->make(BillingGatewayManager::class)->default());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PagarmeHomologationCommand::class,
            ]);
        }

        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])->group($routeFile);
        }

        $this->registerEntitlementSyncHooks();
    }

    private function registerEntitlementSyncHooks(): void
    {
        Subscription::saved(function (Subscription $subscription) {
            $organizationIds = array_values(array_unique(array_filter([
                $subscription->organization_id,
                $subscription->wasChanged('organization_id') ? (int) $subscription->getOriginal('organization_id') : null,
            ])));

            foreach ($organizationIds as $organizationId) {
                $this->afterCommit(
                    fn () => app(SyncOrganizationEventEntitlementsAction::class)
                        ->execute($organizationId, 'subscription.saved')
                );
            }
        });

        Subscription::deleted(function (Subscription $subscription) {
            $this->afterCommit(
                fn () => app(SyncOrganizationEventEntitlementsAction::class)
                    ->execute($subscription->organization_id, 'subscription.deleted')
            );
        });

        EventPurchase::saved(function (EventPurchase $purchase) {
            $eventIds = array_values(array_unique(array_filter([
                $purchase->event_id,
                $purchase->wasChanged('event_id') ? (int) $purchase->getOriginal('event_id') : null,
            ])));

            foreach ($eventIds as $eventId) {
                $this->afterCommit(
                    fn () => app(SyncEventEntitlementsAction::class)
                        ->execute($eventId, 'event_purchase.saved')
                );
            }
        });

        EventPurchase::deleted(function (EventPurchase $purchase) {
            if (! $purchase->event_id) {
                return;
            }

            $this->afterCommit(
                fn () => app(SyncEventEntitlementsAction::class)
                    ->execute($purchase->event_id, 'event_purchase.deleted')
            );
        });

        EventAccessGrant::saved(function (EventAccessGrant $grant) {
            $eventIds = array_values(array_unique(array_filter([
                $grant->event_id,
                $grant->wasChanged('event_id') ? (int) $grant->getOriginal('event_id') : null,
            ])));

            foreach ($eventIds as $eventId) {
                $this->afterCommit(
                    fn () => app(SyncEventEntitlementsAction::class)
                        ->execute($eventId, 'event_access_grant.saved')
                );
            }
        });

        EventAccessGrant::deleted(function (EventAccessGrant $grant) {
            if (! $grant->event_id) {
                return;
            }

            $this->afterCommit(
                fn () => app(SyncEventEntitlementsAction::class)
                    ->execute($grant->event_id, 'event_access_grant.deleted')
            );
        });
    }

    private function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
