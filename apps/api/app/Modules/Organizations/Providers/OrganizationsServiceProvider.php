<?php

namespace App\Modules\Organizations\Providers;

use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrganizationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);

        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }
}
