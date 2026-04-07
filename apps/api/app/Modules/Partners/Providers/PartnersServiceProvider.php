<?php
namespace App\Modules\Partners\Providers;
use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Partners\Policies\PartnerPolicy;
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

        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])->group($routeFile);
        }
    }
}
