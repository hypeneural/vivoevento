<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class AccessPresetController extends BaseController
{
    public function index(EventAccessPresetRegistry $registry): JsonResponse
    {
        $sanitize = static fn (array $preset): array => [
            'key' => $preset['key'],
            'scope' => $preset['scope'],
            'persisted_role' => $preset['persisted_role'],
            'label' => $preset['label'],
            'description' => $preset['description'],
            'capabilities' => $preset['capabilities'],
        ];

        return $this->success([
            'event' => collect($registry->eventPresets())
                ->values()
                ->map($sanitize)
                ->all(),
            'organization' => collect($registry->organizationPresets())
                ->values()
                ->map($sanitize)
                ->all(),
        ]);
    }
}
