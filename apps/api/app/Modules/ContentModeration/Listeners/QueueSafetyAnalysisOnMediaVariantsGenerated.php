<?php

namespace App\Modules\ContentModeration\Listeners;

use App\Modules\ContentModeration\Jobs\AnalyzeContentSafetyJob;
use App\Modules\MediaProcessing\Events\MediaVariantsGenerated;

class QueueSafetyAnalysisOnMediaVariantsGenerated
{
    public function handle(MediaVariantsGenerated $event): void
    {
        AnalyzeContentSafetyJob::dispatch($event->eventMediaId);
    }
}
