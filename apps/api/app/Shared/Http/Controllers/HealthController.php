<?php

namespace App\Shared\Http\Controllers;

use App\Shared\Http\BaseController;
use App\Shared\Support\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class HealthController extends BaseController
{
    public function __construct(
        private readonly HealthCheckService $healthChecks,
    ) {}

    public function live(): JsonResponse
    {
        return $this->success($this->healthChecks->live());
    }

    public function ready(): JsonResponse
    {
        $payload = $this->healthChecks->ready();
        $status = $payload['status'] === 'ready' ? 200 : 503;

        return response()->json([
            'success' => $status < 400,
            'message' => $status < 400 ? 'OK' : 'Service unavailable',
            'data' => $payload,
            'meta' => [
                'request_id' => (string) Context::get('request_id', 'req_' . Str::random(12)),
            ],
        ], $status);
    }
}
