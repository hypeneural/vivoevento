<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;

class DeleteEventFaceSearchCollectionAction
{
    public function __construct(
        private readonly AwsRekognitionFaceSearchBackend $backend,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Event $event): array
    {
        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        $deletedCollectionId = $settings->aws_collection_id;

        $this->backend->deleteEventBackend($event, $settings);

        return [
            'status' => 'deleted',
            'backend_key' => 'aws_rekognition',
            'collection_id' => $deletedCollectionId,
        ];
    }
}
