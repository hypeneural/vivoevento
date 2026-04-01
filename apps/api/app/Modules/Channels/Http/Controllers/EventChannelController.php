<?php

namespace App\Modules\Channels\Http\Controllers;

use App\Modules\Channels\Models\EventChannel;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventChannelController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $channels = EventChannel::where('event_id', $request->query('event_id'))
            ->latest()->get();

        return $this->success($channels);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
            'channel_type' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'external_id' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:120'],
            'config_json' => ['nullable', 'array'],
        ]);

        $channel = EventChannel::create(array_merge($validated, ['status' => 'active']));

        return $this->created($channel);
    }

    public function show(EventChannel $eventChannel): JsonResponse
    {
        return $this->success($eventChannel);
    }

    public function destroy(EventChannel $eventChannel): JsonResponse
    {
        $eventChannel->delete();

        return $this->noContent();
    }
}
