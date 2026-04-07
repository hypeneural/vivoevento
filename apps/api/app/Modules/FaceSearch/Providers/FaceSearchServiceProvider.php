<?php

namespace App\Modules\FaceSearch\Providers;

use App\Modules\FaceSearch\Console\RunFaceSearchBenchmarkCommand;
use App\Modules\FaceSearch\Console\RunCompreFaceSmokeCommand;
use App\Modules\FaceSearch\Console\RunFaceIndexLaneThroughputCommand;
use App\Modules\FaceSearch\Services\ArtisanQueueFaceIndexLaneExecutor;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceDetectionProviderManager;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderManager;
use App\Modules\FaceSearch\Services\FaceIndexLaneExecutorInterface;
use App\Modules\FaceSearch\Services\FaceIndexLaneThroughputService;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\FaceSearch\Services\FaceSearchBenchmarkService;
use App\Modules\FaceSearch\Services\CompreFaceClient;
use App\Modules\FaceSearch\Services\CompreFaceDetectionProvider;
use App\Modules\FaceSearch\Services\CompreFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\CompreFaceSmokeService;
use App\Modules\FaceSearch\Services\NullFaceDetectionProvider;
use App\Modules\FaceSearch\Services\NullFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\PgvectorFaceVectorStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FaceSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CompreFaceClient::class);
        $this->app->singleton(CompreFaceDetectionProvider::class);
        $this->app->singleton(CompreFaceEmbeddingProvider::class);
        $this->app->singleton(CompreFaceSmokeService::class);
        $this->app->singleton(FaceSearchBenchmarkService::class);
        $this->app->singleton(FaceIndexLaneThroughputService::class);
        $this->app->singleton(NullFaceDetectionProvider::class);
        $this->app->singleton(NullFaceEmbeddingProvider::class);
        $this->app->singleton(PgvectorFaceVectorStore::class);
        $this->app->bind(FaceIndexLaneExecutorInterface::class, fn () => new ArtisanQueueFaceIndexLaneExecutor);
        $this->app->singleton(FaceDetectionProviderInterface::class, function ($app) {
            return new FaceDetectionProviderManager([
                'noop' => $app->make(NullFaceDetectionProvider::class),
                'compreface' => $app->make(CompreFaceDetectionProvider::class),
            ]);
        });
        $this->app->singleton(FaceEmbeddingProviderInterface::class, function ($app) {
            return new FaceEmbeddingProviderManager([
                'noop' => $app->make(NullFaceEmbeddingProvider::class),
                'compreface' => $app->make(CompreFaceEmbeddingProvider::class),
            ]);
        });
        $this->app->singleton(FaceVectorStoreInterface::class, fn ($app) => $app->make(PgvectorFaceVectorStore::class));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunFaceIndexLaneThroughputCommand::class,
                RunFaceSearchBenchmarkCommand::class,
                RunCompreFaceSmokeCommand::class,
            ]);
        }

        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }
}
