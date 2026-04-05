<?php

namespace App\Modules\Play\Actions;

use App\Modules\Play\DTOs\GameAssetDTO;
use App\Modules\Play\Models\PlayEventGame;

class SyncGameAssetsAction
{
    /**
     * @param array<int, GameAssetDTO> $assets
     */
    public function execute(PlayEventGame $game, array $assets): PlayEventGame
    {
        $game->assets()->delete();

        foreach ($assets as $asset) {
            $game->assets()->create([
                'media_id' => $asset->mediaId,
                'role' => $asset->role,
                'sort_order' => $asset->sortOrder,
                'metadata_json' => $asset->metadata,
            ]);
        }

        return $game->fresh(['assets.media']);
    }
}
