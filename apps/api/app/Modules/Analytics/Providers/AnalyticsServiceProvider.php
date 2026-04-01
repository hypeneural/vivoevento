<?php
namespace App\Modules\Analytics\Providers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])->group($routeFile);
        }
    }
}
