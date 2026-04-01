<?php

namespace App\Modules\WhatsApp\Providers;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
use App\Modules\WhatsApp\Clients\Providers\ZApi\ZApiWhatsAppProvider;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\WhatsApp\Listeners\RouteInboundToMediaPipeline;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config
        $this->mergeConfigFrom(__DIR__ . '/../../config/whatsapp.php', 'whatsapp');

        // Singletons
        $this->app->singleton(WhatsAppProviderResolver::class);
        $this->app->singleton(WhatsAppMessagingService::class);

        // Bind Z-API provider
        $this->app->bind('whatsapp.provider.zapi', ZApiWhatsAppProvider::class);

        // Default provider binding
        $this->app->bind(WhatsAppProviderInterface::class, function ($app) {
            return $app->make(WhatsAppProviderResolver::class)->forDefaultProvider();
        });
    }

    public function boot(): void
    {
        // Routes
        $routeFile = __DIR__ . '/../routes/api.php';
        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix', 'api') . '/' . config('modules.api_version', 'v1'))
                ->middleware(['api'])
                ->group($routeFile);
        }

        // Event → Listener bindings
        Event::listen(WhatsAppMessageReceived::class, RouteInboundToMediaPipeline::class);
    }
}
