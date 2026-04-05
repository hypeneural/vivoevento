<?php

use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\FaceSearch\Jobs\IndexMediaFacesJob;
use App\Modules\MediaIntelligence\Jobs\EvaluateMediaPromptJob;
use App\Modules\MediaProcessing\Events\ModerationMediaUpdated;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Jobs\PublishMediaJob;
use App\Modules\MediaProcessing\Jobs\RunModerationJob;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\ThrottlesExceptionsWithRedis;
use Illuminate\Queue\Middleware\WithoutOverlapping;

it('configures unique job keys for the critical media pipeline jobs', function () {
    expect(new GenerateMediaVariantsJob(101))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new GenerateMediaVariantsJob(101))->uniqueId())->toBe('media-variants:101')
        ->and((new GenerateMediaVariantsJob(101))->uniqueFor)->toBe(1800);

    expect(new RunModerationJob(202))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new RunModerationJob(202))->uniqueId())->toBe('media-moderation:202');

    expect(new PublishMediaJob(303))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new PublishMediaJob(303))->uniqueId())->toBe('media-publish:303');

    expect(new AnalyzeContentSafetyJob(404))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new AnalyzeContentSafetyJob(404))->uniqueId())->toBe('media-safety:404');

    expect(new EvaluateMediaPromptJob(505))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new EvaluateMediaPromptJob(505))->uniqueId())->toBe('media-vlm:505');

    expect(new IndexMediaFacesJob(606))
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and((new IndexMediaFacesJob(606))->uniqueId())->toBe('face-index:606');
});

it('configures overlap locks and provider throttling where the pipeline needs protection', function () {
    $variantsMiddleware = (new GenerateMediaVariantsJob(101))->middleware();
    $safetyMiddleware = (new AnalyzeContentSafetyJob(404))->middleware();
    $vlmMiddleware = (new EvaluateMediaPromptJob(505))->middleware();
    $faceIndexMiddleware = (new IndexMediaFacesJob(606))->middleware();

    expect($variantsMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($safetyMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($safetyMiddleware[1])->toBeInstanceOf(ThrottlesExceptionsWithRedis::class)
        ->and($vlmMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($vlmMiddleware[1])->toBeInstanceOf(ThrottlesExceptionsWithRedis::class)
        ->and($faceIndexMiddleware[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($faceIndexMiddleware[1])->toBeInstanceOf(ThrottlesExceptionsWithRedis::class);
});

it('routes moderation broadcasts through the dedicated broadcasts queue', function () {
    $event = new ModerationMediaUpdated(55, ['id' => 9001]);

    expect($event)
        ->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event->broadcastQueue())->toBe('broadcasts')
        ->and($event->tries)->toBe(3)
        ->and($event->timeout)->toBe(20);
});
