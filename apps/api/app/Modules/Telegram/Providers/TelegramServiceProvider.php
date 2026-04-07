<?php

namespace App\Modules\Telegram\Providers;

use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\Telegram\Console\RegisterTelegramWebhookCommand;
use App\Modules\Telegram\Listeners\SendTelegramFeedbackOnMediaPublished;
use App\Modules\Telegram\Listeners\SendTelegramFeedbackOnMediaRejected;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TelegramServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix', 'api') . '/' . config('modules.api_version', 'v1'))
                ->middleware(['api'])
                ->group($routeFile);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegisterTelegramWebhookCommand::class,
            ]);
        }

        Event::listen(MediaPublished::class, SendTelegramFeedbackOnMediaPublished::class);
        Event::listen(MediaRejected::class, SendTelegramFeedbackOnMediaRejected::class);
    }
}
