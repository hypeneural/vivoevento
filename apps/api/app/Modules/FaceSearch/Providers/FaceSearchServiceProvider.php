<?php

namespace App\Modules\FaceSearch\Providers;

use App\Modules\FaceSearch\Console\RunFaceSearchBenchmarkCommand;
use App\Modules\FaceSearch\Console\RunCompreFaceSmokeCommand;
use App\Modules\FaceSearch\Console\RunCaltechWebFacesLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunCalfwLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunCofwLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunCfpFpLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunDetectionDatasetProbeCommand;
use App\Modules\FaceSearch\Console\RunFaceIndexLaneThroughputCommand;
use App\Modules\FaceSearch\Console\RunManifestFaceSizeThresholdSweepCommand;
use App\Modules\FaceSearch\Console\RunFaceSizeThresholdSweepCommand;
use App\Modules\FaceSearch\Console\RunLfwLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunOrganizeLocalGalleryByFaceCommand;
use App\Modules\FaceSearch\Console\RunSearchThresholdSweepCommand;
use App\Modules\FaceSearch\Console\RunSmokeMinFaceSizeAnalysisCommand;
use App\Modules\FaceSearch\Console\RunWiderFaceLocalLoaderCommand;
use App\Modules\FaceSearch\Console\RunXqlfwLocalLoaderCommand;
use App\Modules\FaceSearch\Services\ArtisanQueueFaceIndexLaneExecutor;
use App\Modules\FaceSearch\Services\AwsImagePreprocessor;
use App\Modules\FaceSearch\Services\AwsRekognitionClientFactory;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\CaltechWebFacesLocalLoaderService;
use App\Modules\FaceSearch\Services\CalfwLocalLoaderService;
use App\Modules\FaceSearch\Services\CofwLocalLoaderService;
use App\Modules\FaceSearch\Services\CfpFpLocalLoaderService;
use App\Modules\FaceSearch\Services\DetectionDatasetProbeService;
use App\Modules\FaceSearch\Services\FaceDetectionProviderInterface;
use App\Modules\FaceSearch\Services\FaceDetectionProviderManager;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface;
use App\Modules\FaceSearch\Services\FaceEmbeddingProviderManager;
use App\Modules\FaceSearch\Services\FaceSearchMediaSourceLoader;
use App\Modules\FaceSearch\Services\FaceSearchRouter;
use App\Modules\FaceSearch\Services\FaceIndexLaneExecutorInterface;
use App\Modules\FaceSearch\Services\FaceIndexLaneThroughputService;
use App\Modules\FaceSearch\Services\FaceSizeThresholdSweepService;
use App\Modules\FaceSearch\Services\FaceVectorStoreInterface;
use App\Modules\FaceSearch\Services\FaceSearchBenchmarkService;
use App\Modules\FaceSearch\Services\FaceSearchThresholdSweepService;
use App\Modules\FaceSearch\Services\LfwLocalLoaderService;
use App\Modules\FaceSearch\Services\ManifestFaceSizeThresholdSweepService;
use App\Modules\FaceSearch\Services\LocalPgvectorFaceSearchBackend;
use App\Modules\FaceSearch\Services\OrganizeLocalGalleryByFaceService;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\FaceSearch\Services\SmokeMinFaceSizeAnalysisService;
use App\Modules\FaceSearch\Services\CompreFaceClient;
use App\Modules\FaceSearch\Services\CompreFaceDetectionProvider;
use App\Modules\FaceSearch\Services\CompreFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\CompreFaceSmokeService;
use App\Modules\FaceSearch\Services\NullFaceDetectionProvider;
use App\Modules\FaceSearch\Services\NullFaceEmbeddingProvider;
use App\Modules\FaceSearch\Services\PgvectorFaceVectorStore;
use App\Modules\FaceSearch\Services\WiderFaceLocalLoaderService;
use App\Modules\FaceSearch\Services\XqlfwLocalLoaderService;
use Aws\Sdk;
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
        $this->app->singleton(AwsImagePreprocessor::class);
        $this->app->singleton(AwsRekognitionClientFactory::class, function ($app) {
            return new AwsRekognitionClientFactory(
                $app->bound(Sdk::class) ? $app->make(Sdk::class) : null,
            );
        });
        $this->app->singleton(AwsRekognitionFaceSearchBackend::class);
        $this->app->singleton(CaltechWebFacesLocalLoaderService::class);
        $this->app->singleton(CalfwLocalLoaderService::class);
        $this->app->singleton(CofwLocalLoaderService::class);
        $this->app->singleton(CfpFpLocalLoaderService::class);
        $this->app->singleton(DetectionDatasetProbeService::class);
        $this->app->singleton(FaceSearchBenchmarkService::class);
        $this->app->singleton(FaceSearchMediaSourceLoader::class);
        $this->app->singleton(FaceSearchThresholdSweepService::class);
        $this->app->singleton(FaceIndexLaneThroughputService::class);
        $this->app->singleton(FaceSizeThresholdSweepService::class);
        $this->app->singleton(LfwLocalLoaderService::class);
        $this->app->singleton(LocalPgvectorFaceSearchBackend::class);
        $this->app->singleton(ManifestFaceSizeThresholdSweepService::class);
        $this->app->singleton(NullFaceDetectionProvider::class);
        $this->app->singleton(NullFaceEmbeddingProvider::class);
        $this->app->singleton(OrganizeLocalGalleryByFaceService::class);
        $this->app->singleton(PgvectorFaceVectorStore::class);
        $this->app->singleton(SelfiePreflightService::class);
        $this->app->singleton(SmokeMinFaceSizeAnalysisService::class);
        $this->app->singleton(WiderFaceLocalLoaderService::class);
        $this->app->singleton(XqlfwLocalLoaderService::class);
        $this->app->singleton(FaceSearchRouter::class, function ($app) {
            return new FaceSearchRouter([
                $app->make(LocalPgvectorFaceSearchBackend::class),
                $app->make(AwsRekognitionFaceSearchBackend::class),
            ]);
        });
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
                RunCaltechWebFacesLocalLoaderCommand::class,
                RunCalfwLocalLoaderCommand::class,
                RunCofwLocalLoaderCommand::class,
                RunCfpFpLocalLoaderCommand::class,
                RunDetectionDatasetProbeCommand::class,
                RunFaceSizeThresholdSweepCommand::class,
                RunLfwLocalLoaderCommand::class,
                RunManifestFaceSizeThresholdSweepCommand::class,
                RunOrganizeLocalGalleryByFaceCommand::class,
                RunSearchThresholdSweepCommand::class,
                RunSmokeMinFaceSizeAnalysisCommand::class,
                RunWiderFaceLocalLoaderCommand::class,
                RunXqlfwLocalLoaderCommand::class,
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
