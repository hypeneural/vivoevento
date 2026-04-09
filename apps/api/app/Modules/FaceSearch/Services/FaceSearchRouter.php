<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\DTOs\DetectedFaceData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\MediaProcessing\Models\EventMedia;
use InvalidArgumentException;

class FaceSearchRouter
{
    /**
     * @param iterable<int, FaceSearchBackendInterface> $backends
     */
    public function __construct(
        iterable $backends,
    ) {
        $this->backends = collect($backends)
            ->keyBy(fn (FaceSearchBackendInterface $backend) => $backend->key())
            ->all();
    }

    /**
     * @var array<string, FaceSearchBackendInterface>
     */
    private array $backends;

    public function backendForSettings(EventFaceSearchSetting $settings): FaceSearchBackendInterface
    {
        $backendKey = $settings->search_backend_key ?: 'local_pgvector';

        if ($backendKey === 'aws_rekognition' && ! $settings->recognition_enabled) {
            $backendKey = 'local_pgvector';
        }

        if (isset($this->backends[$backendKey])) {
            return $this->backends[$backendKey];
        }

        if (isset($this->backends['local_pgvector'])) {
            return $this->backends['local_pgvector'];
        }

        throw new InvalidArgumentException(sprintf('Nenhum backend de FaceSearch foi registrado para a chave [%s].', $backendKey));
    }

    /**
     * @return array{
     *   matches:array<int, FaceSearchMatchData>,
     *   provider_payload_json?:array<string, mixed>
     * }
     */
    public function searchBySelfie(
        Event $event,
        EventFaceSearchSetting $settings,
        EventMedia $probeMedia,
        string $binary,
        DetectedFaceData $face,
        int $topK,
    ): array {
        return $this->backendForSettings($settings)->searchBySelfie(
            event: $event,
            settings: $settings,
            probeMedia: $probeMedia,
            binary: $binary,
            face: $face,
            topK: $topK,
        );
    }

    /**
     * @return array{
     *   status:string,
     *   source_ref:string|null,
     *   faces_detected:int,
     *   faces_indexed:int,
     *   skipped_reason:string|null,
     *   quality_summary?:array<string,int>,
     *   dominant_rejection_reason?:string|null
     * }
     */
    public function indexMedia(EventMedia $media, EventFaceSearchSetting $settings): array
    {
        return $this->backendForSettings($settings)->indexMedia($media, $settings);
    }
}
