<?php

namespace App\Modules\EventOperations\Providers;

use App\Modules\EventOperations\Listeners\ProjectFeedbackToOperations;
use App\Modules\EventOperations\Listeners\ProjectGalleryToOperations;
use App\Modules\EventOperations\Listeners\ProjectInboundToOperations;
use App\Modules\EventOperations\Listeners\ProjectMediaRunsToOperations;
use App\Modules\EventOperations\Listeners\ProjectModerationToOperations;
use App\Modules\EventOperations\Listeners\ProjectWallToOperations;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\Wall\Events\WallDiagnosticsUpdated;
use App\Modules\Wall\Events\WallMediaPublished;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EventOperationsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }

        InboundMessage::created(function (InboundMessage $message): void {
            app(ProjectInboundToOperations::class)->handle($message);
        });

        EventMedia::created(function (EventMedia $media): void {
            app(ProjectMediaRunsToOperations::class)->handleDownloadedMedia($media);
        });

        WhatsAppMessageFeedback::created(function (WhatsAppMessageFeedback $feedback): void {
            app(ProjectFeedbackToOperations::class)->handleWhatsAppFeedback($feedback);
        });

        WhatsAppMessageFeedback::updated(function (WhatsAppMessageFeedback $feedback): void {
            app(ProjectFeedbackToOperations::class)->handleWhatsAppFeedback($feedback);
        });

        TelegramMessageFeedback::created(function (TelegramMessageFeedback $feedback): void {
            app(ProjectFeedbackToOperations::class)->handleTelegramFeedback($feedback);
        });

        TelegramMessageFeedback::updated(function (TelegramMessageFeedback $feedback): void {
            app(ProjectFeedbackToOperations::class)->handleTelegramFeedback($feedback);
        });

        Event::listen(MediaVariantsGenerated::class, [ProjectMediaRunsToOperations::class, 'handleVariantsGenerated']);
        Event::listen(MediaPublished::class, [ProjectModerationToOperations::class, 'handleApproved']);
        Event::listen(MediaRejected::class, [ProjectModerationToOperations::class, 'handleRejected']);
        Event::listen(MediaPublished::class, [ProjectGalleryToOperations::class, 'handle']);
        Event::listen(WallMediaPublished::class, [ProjectWallToOperations::class, 'handleMediaPublished']);
        Event::listen(WallDiagnosticsUpdated::class, [ProjectWallToOperations::class, 'handleDiagnosticsUpdated']);
    }
}
