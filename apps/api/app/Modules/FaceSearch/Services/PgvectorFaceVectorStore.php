<?php

namespace App\Modules\FaceSearch\Services;

use App\Modules\FaceSearch\DTOs\FaceEmbeddingData;
use App\Modules\FaceSearch\DTOs\FaceSearchMatchData;
use App\Modules\FaceSearch\Models\EventMediaFace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PgvectorFaceVectorStore implements FaceVectorStoreInterface
{
    public function upsert(EventMediaFace $face, FaceEmbeddingData $embedding): EventMediaFace
    {
        $vector = $this->serializeVector($embedding->vector);

        $face->forceFill([
            'embedding' => $vector,
            'vector_store_key' => 'pgvector',
            'embedding_version' => $embedding->embeddingVersion,
            'embedding_model_key' => $embedding->modelKey ?? $face->embedding_model_key,
        ])->save();

        return $face->refresh();
    }

    public function delete(EventMediaFace $face): void
    {
        if (! $face->exists) {
            return;
        }

        $face->forceFill([
            'embedding' => null,
            'vector_ref' => null,
        ])->save();
    }

    public function search(
        int $eventId,
        array $queryEmbedding,
        int $topK,
        ?float $threshold = null,
        bool $searchableOnly = true,
        ?string $searchStrategy = null,
    ): array {
        $driver = DB::connection()->getDriverName();
        $topK = max(1, $topK);
        $searchStrategy = $this->normalizeSearchStrategy($searchStrategy);

        if ($driver === 'pgsql') {
            $vector = $this->serializeVector($queryEmbedding);

            return $searchStrategy === 'exact'
                ? $this->searchPgsqlExact($eventId, $vector, $topK, $threshold, $searchableOnly)
                : $this->searchPgsqlAnn($eventId, $vector, $topK, $threshold, $searchableOnly);
        }

        return EventMediaFace::query()
            ->where('event_id', $eventId)
            ->whereNotNull('embedding')
            ->when($searchableOnly, fn ($builder) => $builder->where('searchable', true))
            ->get(['id', 'event_media_id', 'quality_score', 'quality_tier', 'face_area_ratio', 'embedding'])
            ->map(function (EventMediaFace $face) use ($queryEmbedding) {
                $distance = $this->cosineDistance($queryEmbedding, $this->parseVector((string) $face->embedding));

                return new FaceSearchMatchData(
                    faceId: $face->id,
                    eventMediaId: $face->event_media_id,
                    distance: $distance,
                    qualityScore: $face->quality_score !== null ? (float) $face->quality_score : null,
                    faceAreaRatio: $face->face_area_ratio !== null ? (float) $face->face_area_ratio : null,
                    qualityTier: $face->quality_tier,
                );
            })
            ->when($threshold !== null, fn (Collection $matches) => $matches->filter(fn (FaceSearchMatchData $match) => $match->distance <= $threshold))
            ->sortBy(fn (FaceSearchMatchData $match) => $match->distance)
            ->take($topK)
            ->values()
            ->all();
    }

    private function searchPgsqlExact(
        int $eventId,
        string $vector,
        int $topK,
        ?float $threshold,
        bool $searchableOnly,
    ): array {
        $cteFilters = [
            'event_id = ?',
            'embedding IS NOT NULL',
        ];
        $bindings = [$eventId];

        if ($searchableOnly) {
            $cteFilters[] = 'searchable = true';
        }

        $sql = sprintf(
            'WITH filtered_faces AS MATERIALIZED (
                SELECT id, event_media_id, quality_score, quality_tier, face_area_ratio, embedding
                FROM event_media_faces
                WHERE %s
            )
            SELECT id, event_media_id, quality_score, quality_tier, face_area_ratio, embedding <=> ?::vector AS distance
            FROM filtered_faces',
            implode(' AND ', $cteFilters),
        );
        $bindings[] = $vector;

        if ($threshold !== null) {
            $sql .= ' WHERE embedding <=> ?::vector <= ?';
            $bindings[] = $vector;
            $bindings[] = $threshold;
        }

        $sql .= ' ORDER BY embedding <=> ?::vector ASC LIMIT ?';
        $bindings[] = $vector;
        $bindings[] = $topK;

        return $this->mapPgsqlRows(DB::select($sql, $bindings));
    }

    private function searchPgsqlAnn(
        int $eventId,
        string $vector,
        int $topK,
        ?float $threshold,
        bool $searchableOnly,
    ): array {
        return DB::transaction(function () use ($eventId, $vector, $topK, $threshold, $searchableOnly) {
            $this->applyPgvectorAnnSettings();

            $query = EventMediaFace::query()
                ->select(['id', 'event_media_id', 'quality_score', 'quality_tier', 'face_area_ratio'])
                ->selectRaw('embedding <=> ?::vector as distance', [$vector])
                ->where('event_id', $eventId)
                ->whereNotNull('embedding')
                ->when($searchableOnly, fn ($builder) => $builder->where('searchable', true))
                ->when($threshold !== null, fn ($builder) => $builder->whereRaw('embedding <=> ?::vector <= ?', [$vector, $threshold]))
                ->orderByRaw('embedding <=> ?::vector asc', [$vector])
                ->limit($topK);

            return $query->get()
                ->map(fn (EventMediaFace $face) => new FaceSearchMatchData(
                    faceId: $face->id,
                    eventMediaId: $face->event_media_id,
                    distance: (float) ($face->distance ?? 1.0),
                    qualityScore: $face->quality_score !== null ? (float) $face->quality_score : null,
                    faceAreaRatio: $face->face_area_ratio !== null ? (float) $face->face_area_ratio : null,
                    qualityTier: $face->quality_tier,
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, FaceSearchMatchData>
     */
    private function mapPgsqlRows(array $rows): array
    {
        return collect($rows)
            ->map(fn (object $row) => new FaceSearchMatchData(
                faceId: (int) $row->id,
                eventMediaId: (int) $row->event_media_id,
                distance: (float) ($row->distance ?? 1.0),
                qualityScore: $row->quality_score !== null ? (float) $row->quality_score : null,
                faceAreaRatio: $row->face_area_ratio !== null ? (float) $row->face_area_ratio : null,
                qualityTier: $row->quality_tier ?? null,
            ))
            ->values()
            ->all();
    }

    private function normalizeSearchStrategy(?string $searchStrategy): string
    {
        if (in_array($searchStrategy, ['exact', 'ann'], true)) {
            return $searchStrategy;
        }

        $default = (string) config('face_search.default_search_strategy', 'exact');

        return in_array($default, ['exact', 'ann'], true) ? $default : 'exact';
    }

    private function applyPgvectorAnnSettings(): void
    {
        $efSearch = max(1, (int) config('face_search.ann.hnsw_ef_search', 100));
        DB::statement("SET LOCAL hnsw.ef_search = {$efSearch}");

        $iterativeScan = (string) config('face_search.ann.hnsw_iterative_scan', 'strict_order');

        if (in_array($iterativeScan, ['off', 'strict_order', 'relaxed_order'], true)) {
            DB::statement("SET LOCAL hnsw.iterative_scan = {$iterativeScan}");
        }
    }

    /**
     * @param array<int, float> $vector
     */
    public function serializeVector(array $vector): string
    {
        return '[' . collect($vector)
            ->map(fn ($value) => rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.'))
            ->map(fn ($value) => $value === '' ? '0' : $value)
            ->implode(',') . ']';
    }

    /**
     * @return array<int, float>
     */
    private function parseVector(string $serialized): array
    {
        $trimmed = trim($serialized, "[] \t\n\r\0\x0B");

        if ($trimmed === '') {
            return [];
        }

        return array_map(
            static fn ($value) => (float) trim($value),
            explode(',', $trimmed),
        );
    }

    /**
     * @param array<int, float> $left
     * @param array<int, float> $right
     */
    private function cosineDistance(array $left, array $right): float
    {
        $count = min(count($left), count($right));

        if ($count === 0) {
            return 1.0;
        }

        $dot = 0.0;
        $normLeft = 0.0;
        $normRight = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $dot += $left[$index] * $right[$index];
            $normLeft += $left[$index] ** 2;
            $normRight += $right[$index] ** 2;
        }

        if ($normLeft <= 0.0 || $normRight <= 0.0) {
            return 1.0;
        }

        $similarity = $dot / (sqrt($normLeft) * sqrt($normRight));

        return 1.0 - max(-1.0, min(1.0, $similarity));
    }
}
