<?php

namespace App\Modules\MediaProcessing\Providers;

use App\Modules\MediaProcessing\Console\BackfillWallVideoVariantsCommand;
use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Listeners\QueueCleanupDeletedMediaArtifactsOnMediaDeleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MediaProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            BackfillWallVideoVariantsCommand::class,
        ]);
    }

    public function boot(): void
    {
        Event::listen(MediaDeleted::class, QueueCleanupDeletedMediaArtifactsOnMediaDeleted::class);

        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])->group($routeFile);
        }
    }
}
