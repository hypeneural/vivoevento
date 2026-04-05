<?php

namespace App\Modules\FaceSearch\Providers;

use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceDetectionProviderManager;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderManager;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\FaceSearch\Services\NullFaceDetectionProvider;
use App\Modules\FaceSearch\Services\NullFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\PgvectorFaceVectorStore;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FaceSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NullFaceDetectionProvider::class);
        $this->app->singleton(NullFaceEmbeddingProvider::class);
        $this->app->singleton(PgvectorFaceVectorStore::class);
        $this->app->singleton(FaceDetectionProviderInterface::class, function ($app) {
            return new FaceDetectionProviderManager([
                'noop' => $app->make(NullFaceDetectionProvider::class),
            ]);
        });
        $this->app->singleton(FaceEmbeddingProviderInterface::class, function ($app) {
            return new FaceEmbeddingProviderManager([
                'noop' => $app->make(NullFaceEmbeddingProvider::class),
            ]);
        });
        $this->app->singleton(FaceVectorStoreInterface::class, fn ($app) => $app->make(PgvectorFaceVectorStore::class));
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
