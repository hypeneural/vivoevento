<?php
namespace App\Modules\Partners\Providers;
use App\Modules\Billing\Models\EventAccessGrant;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Clients\Models\Client;
use App\Modules\Events\Models\Event;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Partners\Jobs\RebuildPartnerStatsJob;
use App\Modules\Partners\Policies\PartnerPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class PartnersServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        Route::bind('partner', function (string $value) {
            return Organization::query()
                ->whereKey($value)
                ->where('type', OrganizationType::Partner->value)
                ->firstOrFail();
        });

        Gate::define('viewAnyPartners', [PartnerPolicy::class, 'viewAny']);
        Gate::define('viewPartner', [PartnerPolicy::class, 'view']);
        Gate::define('createPartner', [PartnerPolicy::class, 'create']);
        Gate::define('updatePartner', [PartnerPolicy::class, 'update']);
        Gate::define('suspendPartner', [PartnerPolicy::class, 'suspend']);
        Gate::define('deletePartner', [PartnerPolicy::class, 'delete']);
        Gate::define('managePartnerStaff', [PartnerPolicy::class, 'manageStaff']);
        Gate::define('managePartnerGrants', [PartnerPolicy::class, 'manageGrants']);

        $this->registerPartnerStatsProjectionHooks();

        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])->group($routeFile);
        }
    }

    private function registerPartnerStatsProjectionHooks(): void
    {
        Client::saved(function (Client $client) {
            $this->queuePartnerStatsRefresh(
                $client->organization_id,
                $client->wasChanged('organization_id') ? (int) $client->getOriginal('organization_id') : null,
            );
        });

        Client::deleted(fn (Client $client) => $this->queuePartnerStatsRefresh($client->organization_id));

        Event::saved(function (Event $event) {
            $this->queuePartnerStatsRefresh(
                $event->organization_id,
                $event->wasChanged('organization_id') ? (int) $event->getOriginal('organization_id') : null,
            );
        });

        Event::deleted(fn (Event $event) => $this->queuePartnerStatsRefresh($event->organization_id));

        OrganizationMember::saved(function (OrganizationMember $member) {
            $this->queuePartnerStatsRefresh(
                $member->organization_id,
                $member->wasChanged('organization_id') ? (int) $member->getOriginal('organization_id') : null,
            );
        });

        OrganizationMember::deleted(fn (OrganizationMember $member) => $this->queuePartnerStatsRefresh($member->organization_id));

        Subscription::saved(function (Subscription $subscription) {
            $this->queuePartnerStatsRefresh(
                $subscription->organization_id,
                $subscription->wasChanged('organization_id') ? (int) $subscription->getOriginal('organization_id') : null,
            );
        });

        Subscription::deleted(fn (Subscription $subscription) => $this->queuePartnerStatsRefresh($subscription->organization_id));

        Invoice::saved(function (Invoice $invoice) {
            $this->queuePartnerStatsRefresh(
                $invoice->organization_id,
                $invoice->wasChanged('organization_id') ? (int) $invoice->getOriginal('organization_id') : null,
            );
        });

        Invoice::deleted(fn (Invoice $invoice) => $this->queuePartnerStatsRefresh($invoice->organization_id));

        EventAccessGrant::saved(function (EventAccessGrant $grant) {
            $this->queuePartnerStatsRefresh(
                $grant->organization_id,
                $grant->wasChanged('organization_id') ? (int) $grant->getOriginal('organization_id') : null,
            );
        });

        EventAccessGrant::deleted(fn (EventAccessGrant $grant) => $this->queuePartnerStatsRefresh($grant->organization_id));
    }

    private function queuePartnerStatsRefresh(?int ...$organizationIds): void
    {
        if (! config('partners.stats.async_updates', false)) {
            return;
        }

        $ids = array_values(array_unique(array_filter($organizationIds)));

        foreach ($ids as $organizationId) {
            $dispatch = fn () => RebuildPartnerStatsJob::dispatch($organizationId);

            if (DB::transactionLevel() > 0) {
                DB::afterCommit($dispatch);

                continue;
            }

            $dispatch();
        }
    }
}
