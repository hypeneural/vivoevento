<?php
namespace App\Modules\Analytics\Http\Controllers;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends BaseController
{
    public function eventOverview(int $event): JsonResponse
    {
        // Placeholder — will query analytics_events table
        return $this->success(['event_id' => $event, 'metrics' => []]);
    }

    public function platformOverview(): JsonResponse
    {
        return $this->success(['metrics' => []]);
    }
}
