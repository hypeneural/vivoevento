<?php

namespace App\Modules\Events\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EventBrandingController extends BaseController
{
    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
        ]);

        $event->update($validated);

        return $this->success(new \App\Modules\Events\Http\Resources\EventResource($event->fresh()));
    }
}
