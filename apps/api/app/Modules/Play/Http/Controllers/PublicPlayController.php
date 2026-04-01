<?php
namespace App\Modules\Play\Http\Controllers;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class PublicPlayController extends BaseController
{
    public function manifest(string $event): JsonResponse
    {
        $eventModel = \App\Modules\Events\Models\Event::where('slug', $event)->firstOrFail();
        $settings = \App\Modules\Play\Models\EventPlaySetting::where('event_id', $eventModel->id)->first();
        return $this->success(['event_id' => $eventModel->id, 'settings' => $settings]);
    }
}
