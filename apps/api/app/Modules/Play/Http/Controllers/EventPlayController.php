<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Play\Http\Requests\UpdateEventPlaySettingsRequest;
use App\Modules\Play\Http\Resources\EventPlayManagerResource;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Services\GameCatalogService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventPlayController extends BaseController
{
    public function manager(Event $event, GameCatalogService $catalog): JsonResponse
    {
        $this->authorize('viewPlay', $event);

        $event->load([
            'playSettings',
            'playGames.gameType',
            'playGames.assets.media.variants',
        ]);
        $event->loadCount(['playGames', 'playGames as active_play_games_count' => fn ($query) => $query->where('is_active', true)]);

        return $this->success(new EventPlayManagerResource($event, $catalog->catalog()));
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('viewPlay', $event);

        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event->id]);

        return $this->success($settings);
    }

    public function update(UpdateEventPlaySettingsRequest $request, Event $event): JsonResponse
    {
        $this->authorize('managePlay', $event);

        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event->id]);
        $settings->update($request->validated());

        return $this->success($settings->fresh());
    }

    public function generateMemory(Event $event): JsonResponse
    {
        $this->authorize('managePlay', $event);

        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event->id]);

        return $this->success([
            'message' => 'Estrutura de geracao de assets do memory pronta para receber job dedicado.',
            'card_count' => $settings->memory_card_count,
        ]);
    }

    public function generatePuzzle(Event $event): JsonResponse
    {
        $this->authorize('managePlay', $event);

        $settings = EventPlaySetting::firstOrCreate(['event_id' => $event->id]);

        return $this->success([
            'message' => 'Estrutura de geracao de assets do puzzle pronta para receber job dedicado.',
            'piece_count' => $settings->puzzle_piece_count,
        ]);
    }
}
