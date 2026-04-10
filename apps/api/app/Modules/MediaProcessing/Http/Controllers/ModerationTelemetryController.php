<?php

namespace App\Modules\MediaProcessing\Http\Controllers;

use App\Modules\MediaProcessing\Http\Requests\StoreModerationTelemetryRequest;
use App\Modules\MediaProcessing\Services\ModerationObservabilityService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class ModerationTelemetryController extends BaseController
{
    public function store(
        StoreModerationTelemetryRequest $request,
        ModerationObservabilityService $observability,
    ): JsonResponse {
        $organizationId = $request->user()?->currentOrganization()?->id;

        abort_unless($organizationId, 422, 'Nenhuma organizacao ativa encontrada.');

        $observability->recordClientTelemetry(
            actor: $request->user(),
            organizationId: $organizationId,
            payload: $request->validated(),
        );

        return $this->success(null);
    }
}
