<?php

namespace App\Modules\FaceSearch\Services;

interface FaceIndexLaneExecutorInterface
{
    /**
     * @param array<int, int> $eventMediaIds
     * @return array<string, mixed>
     */
    public function execute(array $eventMediaIds, string $queueName = 'face-index'): array;
}
