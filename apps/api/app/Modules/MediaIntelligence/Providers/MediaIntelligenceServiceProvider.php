<?php

namespace App\Modules\MediaIntelligence\Providers;

use App\Modules\MediaIntelligence\Services\NullVisualReasoningProvider;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderInterface;
use App\Modules\MediaIntelligence\Services\VisualReasoningProviderManager;
use App\Modules\MediaIntelligence\Services\VllmVisualReasoningProvider;
use App\Modules\MediaProcessing\Services\ProviderCircuitBreaker;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MediaIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NullVisualReasoningProvider::class);
        $this->app->singleton(VllmVisualReasoningProvider::class);
        $this->app->singleton(VisualReasoningProviderInterface::class, function ($app) {
            return new VisualReasoningProviderManager([
                'noop' => $app->make(NullVisualReasoningProvider::class),
                'vllm' => $app->make(VllmVisualReasoningProvider::class),
            ], $app->make(ProviderCircuitBreaker::class));
        });
    }

    public function boot(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }
}
