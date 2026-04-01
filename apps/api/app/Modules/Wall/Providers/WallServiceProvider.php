<?php

namespace App\Modules\Wall\Providers;

use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\Wall\Listeners\BroadcastWallOnMediaDeleted;
use App\Modules\Wall\Listeners\BroadcastWallOnMediaPublished;
use App\Modules\Wall\Listeners\BroadcastWallOnMediaUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WallServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->registerListeners();
    }

    private function loadRoutes(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }

    /**
     * Register event listeners for media pipeline integration.
     *
     * MediaProcessing emits typed domain events and Wall translates them
     * into telão-specific broadcast payloads.
     */
    private function registerListeners(): void
    {
        Event::listen(MediaPublished::class, BroadcastWallOnMediaPublished::class);
        Event::listen(MediaVariantsGenerated::class, BroadcastWallOnMediaUpdated::class);
        Event::listen(MediaDeleted::class, BroadcastWallOnMediaDeleted::class);
        Event::listen(MediaRejected::class, BroadcastWallOnMediaDeleted::class);
    }
}
