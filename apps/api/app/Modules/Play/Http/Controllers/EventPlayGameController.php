<?php

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Events\Models\Event;
use App\Modules\Play\Actions\CreateEventGameAction;
use App\Modules\Play\Actions\SyncGameAssetsAction;
use App\Modules\Play\Actions\UpdateEventGameAction;
use App\Modules\Play\DTOs\CreateEventGameDTO;
use App\Modules\Play\DTOs\GameAssetDTO;
use App\Modules\Play\Http\Requests\StoreEventGameRequest;
use App\Modules\Play\Http\Requests\SyncGameAssetsRequest;
use App\Modules\Play\Http\Requests\UpdateEventGameRequest;
use App\Modules\Play\Http\Resources\PlayGameAssetResource;
use App\Modules\Play\Http\Resources\PlayEventGameResource;
use App\Modules\Play\Models\EventPlaySetting;
use App\Modules\Play\Models\PlayEventGame;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class EventPlayGameController extends BaseController
{
    public function store(
        StoreEventGameRequest $request,
        Event $event,
        CreateEventGameAction $action,
    ): JsonResponse {
        $this->authorize('managePlay', $event);

        $validated = $request->validated();

        $game = $action->execute(new CreateEventGameDTO(
            eventId: $event->id,
            gameTypeKey: $validated['game_type_key'],
            title: $validated['title'],
            slug: $validated['slug'] ?? null,
            rankingEnabled: (bool) ($validated['ranking_enabled'] ?? true),
            isActive: (bool) ($validated['is_active'] ?? true),
            sortOrder: (int) ($validated['sort_order'] ?? 0),
            settings: $validated['settings'] ?? [],
        ));

        EventPlaySetting::query()->firstOrCreate(['event_id' => $event->id], ['is_enabled' => true]);

        return $this->success(new PlayEventGameResource($game), 201);
    }

    public function update(
        UpdateEventGameRequest $request,
        Event $event,
        PlayEventGame $playGame,
        UpdateEventGameAction $action,
    ): JsonResponse {
        $this->authorize('managePlay', $event);
        $this->ensureGameBelongsToEvent($event, $playGame);

        $game = $action->execute($playGame, $request->validated());

        return $this->success(new PlayEventGameResource($game));
    }

    public function destroy(Event $event, PlayEventGame $playGame): JsonResponse
    {
        $this->authorize('managePlay', $event);
        $this->ensureGameBelongsToEvent($event, $playGame);

        $playGame->delete();

        return $this->noContent();
    }

    public function assets(Event $event, PlayEventGame $playGame): JsonResponse
    {
        $this->authorize('viewPlay', $event);
        $this->ensureGameBelongsToEvent($event, $playGame);

        $playGame->load('assets.media.variants');

        return $this->success(
            PlayGameAssetResource::collection($playGame->assets),
        );
    }

    public function syncAssets(
        SyncGameAssetsRequest $request,
        Event $event,
        PlayEventGame $playGame,
        SyncGameAssetsAction $action,
    ): JsonResponse {
        $this->authorize('managePlay', $event);
        $this->ensureGameBelongsToEvent($event, $playGame);

        $assets = array_map(
            fn (array $item) => new GameAssetDTO(
                mediaId: (int) $item['media_id'],
                role: (string) $item['role'],
                sortOrder: (int) ($item['sort_order'] ?? 0),
                metadata: $item['metadata'] ?? [],
            ),
            $request->validated('assets'),
        );

        $game = $action->execute($playGame, $assets);

        return $this->success(new PlayEventGameResource($game->load('gameType')));
    }

    private function ensureGameBelongsToEvent(Event $event, PlayEventGame $game): void
    {
        abort_unless($game->event_id === $event->id, 404);
    }
}
