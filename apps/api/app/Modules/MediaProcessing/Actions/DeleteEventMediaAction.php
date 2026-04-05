<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaDeleted;
use App\Modules\MediaProcessing\Models\EventMedia;

class DeleteEventMediaAction
{
    public function execute(EventMedia $eventMedia): void
    {
        $eventMediaId = $eventMedia->id;

        $eventMedia->faces()->update([
            'searchable' => false,
        ]);

        $eventMedia->forceFill([
            'publication_status' => PublicationStatus::Deleted,
        ])->save();

        $eventMedia->delete();

        event(new MediaDeleted($eventMediaId));
    }
}
