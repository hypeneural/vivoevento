<?php

namespace App\Modules\EventPeople\Services;

use App\Modules\EventPeople\Enums\EventCoverageState;

class EventCoverageScoringService
{
    /**
     * @return array<string, mixed>
     */
    public function scorePerson(
        int $mediaCount,
        int $publishedMediaCount,
        bool $hasPrimaryPhoto,
        int $requiredMediaCount,
        int $requiredPublishedMediaCount,
        int $resolvedEntityCount = 1,
    ): array {
        $mediaRatio = $requiredMediaCount > 0 ? min($mediaCount / $requiredMediaCount, 1) : 1;
        $publishedRatio = $requiredPublishedMediaCount > 0 ? min($publishedMediaCount / $requiredPublishedMediaCount, 1) : 1;
        $primaryRatio = $hasPrimaryPhoto ? 1 : 0;
        $score = round(($mediaRatio * 40) + ($publishedRatio * 40) + ($primaryRatio * 20), 2);

        return [
            'coverage_state' => $this->stateFor($score, $mediaCount, $publishedMediaCount),
            'score' => $score,
            'resolved_entity_count' => $resolvedEntityCount,
            'media_count' => $mediaCount,
            'published_media_count' => $publishedMediaCount,
            'joint_media_count' => 0,
            'people_with_primary_photo_count' => $hasPrimaryPhoto ? 1 : 0,
            'reason_codes' => array_values(array_filter([
                $resolvedEntityCount <= 0 ? 'target_unresolved' : null,
                $mediaCount <= 0 ? 'sem_fotos_confirmadas' : null,
                $publishedMediaCount < $requiredPublishedMediaCount ? 'publicacao_insuficiente' : null,
                ! $hasPrimaryPhoto ? 'sem_foto_principal' : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scorePair(
        int $jointMediaCount,
        bool $hasPrimaryRelation,
        int $requiredMediaCount,
        int $resolvedEntityCount = 2,
    ): array {
        $jointRatio = $requiredMediaCount > 0 ? min($jointMediaCount / $requiredMediaCount, 1) : 1;
        $relationRatio = $hasPrimaryRelation ? 1 : 0;
        $score = round(($jointRatio * 70) + ($relationRatio * 30), 2);

        return [
            'coverage_state' => $this->stateFor($score, $jointMediaCount, 0),
            'score' => $score,
            'resolved_entity_count' => $resolvedEntityCount,
            'media_count' => $jointMediaCount,
            'published_media_count' => 0,
            'joint_media_count' => $jointMediaCount,
            'people_with_primary_photo_count' => 0,
            'reason_codes' => array_values(array_filter([
                $resolvedEntityCount < 2 ? 'pair_unresolved' : null,
                $jointMediaCount <= 0 ? 'sem_fotos_juntos' : null,
                ! $hasPrimaryRelation ? 'relacao_principal_ausente' : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scoreGroup(
        int $memberCount,
        int $peopleWithPrimaryPhotoCount,
        int $mediaCount,
        int $publishedMediaCount,
        int $requiredMediaCount,
        int $requiredPublishedMediaCount,
    ): array {
        $mediaRatio = $requiredMediaCount > 0 ? min($mediaCount / $requiredMediaCount, 1) : 1;
        $publishedRatio = $requiredPublishedMediaCount > 0 ? min($publishedMediaCount / $requiredPublishedMediaCount, 1) : 1;
        $primaryRatio = $memberCount > 0 ? min($peopleWithPrimaryPhotoCount / $memberCount, 1) : 0;
        $score = round(($mediaRatio * 30) + ($publishedRatio * 25) + ($primaryRatio * 45), 2);

        return [
            'coverage_state' => $this->stateFor($score, $mediaCount, $publishedMediaCount),
            'score' => $score,
            'resolved_entity_count' => $memberCount,
            'media_count' => $mediaCount,
            'published_media_count' => $publishedMediaCount,
            'joint_media_count' => 0,
            'people_with_primary_photo_count' => $peopleWithPrimaryPhotoCount,
            'reason_codes' => array_values(array_filter([
                $memberCount <= 0 ? 'grupo_sem_membros' : null,
                $mediaCount <= 0 ? 'grupo_sem_fotos' : null,
                $publishedMediaCount < $requiredPublishedMediaCount ? 'grupo_publicacao_insuficiente' : null,
                $memberCount > 0 && $peopleWithPrimaryPhotoCount < $memberCount ? 'membros_sem_foto_principal' : null,
            ])),
        ];
    }

    private function stateFor(float $score, int $mediaCount, int $publishedMediaCount): string
    {
        if ($mediaCount <= 0 && $publishedMediaCount <= 0) {
            return EventCoverageState::Missing->value;
        }

        if ($score < 50) {
            return EventCoverageState::Weak->value;
        }

        if ($score < 80) {
            return EventCoverageState::Ok->value;
        }

        return EventCoverageState::Strong->value;
    }
}
