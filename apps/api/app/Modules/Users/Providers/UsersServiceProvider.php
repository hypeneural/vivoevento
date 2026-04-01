<?php

namespace App\Modules\Users\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
