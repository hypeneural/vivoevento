<?php

namespace App\Modules\FaceSearch\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use App\Modules\FaceSearch\Services\FaceSearchRouter;

class RunEventFaceSearchHealthCheckAction
{
    public function __construct(
        private readonly FaceSearchRouter $router,
        private readonly AwsRekognitionFaceSearchBackend $awsBackend,
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

        $backend = $this->shouldUseAwsOperations($settings)
            ? $this->awsBackend
            : $this->router->backendForSettings($settings);

        return [
            ...$backend->healthCheck($event, $settings),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function shouldUseAwsOperations(EventFaceSearchSetting $settings): bool
    {
        return $settings->search_backend_key === 'aws_rekognition'
            || $settings->fallback_backend_key === 'aws_rekognition'
            || (is_string($settings->aws_collection_id) && trim($settings->aws_collection_id) !== '');
    }
}
