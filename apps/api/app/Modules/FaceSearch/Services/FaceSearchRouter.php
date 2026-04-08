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

        if (isset($this->backends[$backendKey])) {
            return $this->backends[$backendKey];
        }

        if (isset($this->backends['local_pgvector'])) {
            return $this->backends['local_pgvector'];
        }

        throw new InvalidArgumentException(sprintf('Nenhum backend de FaceSearch foi registrado para a chave [%s].', $backendKey));
    }

    /**
     * @return array<int, FaceSearchMatchData>
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
}
