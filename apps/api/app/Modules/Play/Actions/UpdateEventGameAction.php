<?php

namespace App\Modules\Play\Actions;

use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Services\GameSettingsValidationService;
use App\Shared\Support\Helpers;

class UpdateEventGameAction
{
    public function __construct(
        private readonly GameSettingsValidationService $settings,
    ) {}

    public function execute(PlayEventGame $game, array $data): PlayEventGame
    {
        if (array_key_exists('slug', $data) && $data['slug']) {
            $data['slug'] = Helpers::generateUniqueSlug(
                $data['slug'],
                PlayEventGame::class,
                'slug',
                $game->id,
            );
        }

        if (array_key_exists('settings', $data)) {
            $game->loadMissing('gameType');
            $gameTypeKey = $game->gameType?->key?->value ?? $game->gameType?->key;
            $data['settings_json'] = $this->settings->validateForType($gameTypeKey, $data['settings']);
            unset($data['settings']);
        }

        $game->update($data);

        return $game->fresh(['gameType', 'assets.media']);
    }
}
