<?php

namespace App\Modules\Play\Actions;

use App\Modules\Play\DTOs\CreateEventGameDTO;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Services\GameCatalogService;
use App\Modules\Play\Services\GameSettingsValidationService;
use App\Shared\Support\Helpers;

class CreateEventGameAction
{
    public function __construct(
        private readonly GameCatalogService $catalog,
        private readonly GameSettingsValidationService $settings,
    ) {}

    public function execute(CreateEventGameDTO $dto): PlayEventGame
    {
        $type = $this->catalog->findByKey($dto->gameTypeKey);

        $slug = $dto->slug
            ? Helpers::generateUniqueSlug($dto->slug, PlayEventGame::class, 'slug')
            : Helpers::generateUniqueSlug($dto->title, PlayEventGame::class, 'slug');

        $normalizedSettings = $this->settings->validateForType($type->key->value ?? $type->key, $dto->settings);

        $game = PlayEventGame::query()->create([
            'event_id' => $dto->eventId,
            'game_type_id' => $type->id,
            'title' => $dto->title,
            'slug' => $slug,
            'is_active' => $dto->isActive,
            'sort_order' => $dto->sortOrder,
            'ranking_enabled' => $dto->rankingEnabled,
            'settings_json' => $normalizedSettings,
        ]);

        return $game->fresh(['gameType', 'assets.media']);
    }
}
