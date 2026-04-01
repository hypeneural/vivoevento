<?php
namespace App\Modules\Hub\Http\Controllers;
use App\Modules\Hub\Models\EventHubSetting;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventHubController extends BaseController
{
    public function show(int $event): JsonResponse
    {
        $settings = EventHubSetting::firstOrCreate(['event_id' => $event]);
        return $this->success($settings);
    }

    public function update(Request $request, int $event): JsonResponse
    {
        $settings = EventHubSetting::firstOrCreate(['event_id' => $event]);
        $settings->update($request->all());
        return $this->success($settings->fresh());
    }
}
