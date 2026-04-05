<?php

namespace App\Modules\Play\Services;

use App\Modules\Play\DTOs\GameTypeDTO;
use App\Modules\Play\Enums\PlayGameTypeKey;
use App\Modules\Play\Models\PlayGameType;
use Illuminate\Support\Collection;

class GameCatalogService
{
    public function ensureDefaults(): void
    {
        $this->defaultCatalog()->each(function (GameTypeDTO $item): void {
            PlayGameType::query()->updateOrCreate(
                ['key' => $item->key],
                [
                    'name' => $item->name,
                    'description' => $item->description,
                    'enabled' => true,
                    'supports_ranking' => $item->supportsRanking,
                    'supports_photo_assets' => $item->supportsPhotoAssets,
                    'config_schema_json' => $item->configSchema,
                ],
            );
        });
    }

    /**
     * @return Collection<int, PlayGameType>
     */
    public function catalog(): Collection
    {
        $this->ensureDefaults();

        return PlayGameType::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get();
    }

    public function findByKey(string $key): PlayGameType
    {
        $this->ensureDefaults();

        return PlayGameType::query()
            ->where('key', $key)
            ->where('enabled', true)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, GameTypeDTO>
     */
    private function defaultCatalog(): Collection
    {
        return collect([
            new GameTypeDTO(
                key: PlayGameTypeKey::Memory->value,
                name: PlayGameTypeKey::Memory->label(),
                description: 'Encontre os pares usando fotos do evento.',
                supportsRanking: true,
                supportsPhotoAssets: true,
                configSchema: [
                    'pairsCount' => ['type' => 'integer', 'enum' => [6, 8, 10]],
                    'difficulty' => ['type' => 'string', 'enum' => ['easy', 'normal', 'hard']],
                    'showPreviewSeconds' => ['type' => 'integer', 'min' => 0, 'max' => 10],
                    'allowDuplicateSource' => ['type' => 'boolean'],
                    'flipBackDelayMs' => ['type' => 'integer', 'min' => 200, 'max' => 3000],
                    'scoringVersion' => ['type' => 'string', 'const' => 'memory_v1'],
                ],
            ),
            new GameTypeDTO(
                key: PlayGameTypeKey::Puzzle->value,
                name: PlayGameTypeKey::Puzzle->label(),
                description: 'Monte a foto do evento em formato de puzzle.',
                supportsRanking: true,
                supportsPhotoAssets: true,
                configSchema: [
                    'gridSize' => ['type' => 'string', 'enum' => ['2x2', '3x3']],
                    'showReferenceImage' => ['type' => 'boolean'],
                    'snapEnabled' => ['type' => 'boolean'],
                    'dragTolerance' => ['type' => 'integer', 'min' => 0, 'max' => 64],
                    'scoringVersion' => ['type' => 'string', 'const' => 'puzzle_v1'],
                ],
            ),
        ]);
    }
}
