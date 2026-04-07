<?php

namespace App\Modules\ContentModeration\Providers;

use App\Modules\ContentModeration\Console\RunOpenAiContentModerationSmokeCommand;
use App\Modules\ContentModeration\Listeners\QueueSafetyAnalysisOnMediaVariantsGenerated;
use App\Modules\ContentModeration\Services\ContentModerationProviderManager;
use App\Modules\ContentModeration\Services\ContentModerationProviderInterface;
use App\Modules\ContentModeration\Services\NullContentModerationProvider;
use App\Modules\ContentModeration\Services\OpenAiContentModerationProvider;
use App\Modules\ContentModeration\Services\OpenAiContentModerationSmokeService;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ContentModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NullContentModerationProvider::class);
        $this->app->singleton(OpenAiContentModerationProvider::class);
        $this->app->singleton(OpenAiContentModerationSmokeService::class);
        $this->app->singleton(ContentModerationProviderInterface::class, function ($app) {
            return new ContentModerationProviderManager([
                'noop' => $app->make(NullContentModerationProvider::class),
                'openai' => $app->make(OpenAiContentModerationProvider::class),
            ], $app->make(ProviderCircuitBreaker::class));
        });
    }

    public function boot(): void
    {
        Event::listen(MediaVariantsGenerated::class, QueueSafetyAnalysisOnMediaVariantsGenerated::class);

        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunOpenAiContentModerationSmokeCommand::class,
            ]);
        }
    }
}
