<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Jobs\ReconcileAwsCollectionJob;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;

class QueueEventFaceSearchReconciliationAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(Event $event): array
    {
        $settings = $event->faceSearchSettings()->firstOrNew(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
        );

        if (! $this->supportsAwsReconciliation($settings)) {
            return [
                'status' => 'skipped',
                'backend_key' => 'aws_rekognition',
                'job' => 'reconcile_collection',
                'skipped_reason' => 'aws_collection_not_available',
            ];
        }

        ReconcileAwsCollectionJob::dispatch($event->id);

        return [
            'status' => 'queued',
            'backend_key' => 'aws_rekognition',
            'job' => 'reconcile_collection',
        ];
    }

    private function supportsAwsReconciliation(EventFaceSearchSetting $settings): bool
    {
        return $settings->search_backend_key === 'aws_rekognition'
            || $settings->fallback_backend_key === 'aws_rekognition'
            || (is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '');
    }
}
