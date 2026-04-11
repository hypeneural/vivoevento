<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\DTOs\GameLaunchReadinessDTO;
use App\Modules\Play\Models\PlayEventGame;
use App\Modules\Play\Support\RuntimeAssetProfile;

class GameLaunchReadinessService
{
    public function __construct(
        private readonly GameAssetResolverService $assets,
    ) {}

    public function forGame(PlayEventGame $game, ?RuntimeAssetProfile $profile = null): GameLaunchReadinessDTO
    {
        $published = (bool) $game->is_active;
        $reason = $published ? $this->assets->unavailableReason($game, $profile) : 'play.not_published';
        $launchable = $published && $reason === null;

        return new GameLaunchReadinessDTO(
            published: $published,
            launchable: $launchable,
            bootable: $launchable,
            reason: $reason,
        );
    }
}
