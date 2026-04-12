<?php

namespace App\Modules\Gallery\Support;

use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Models\GalleryBuilderPromptRun;

class GalleryBuilderOperationalFeedbackResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(EventGallerySetting $settings): array
    {
        $settings->loadMissing(['currentPublishedRevision.creator']);

        return [
            'current_preset_origin' => $this->normalizePresetOrigin($settings->current_preset_origin_json),
            'last_ai_application' => $this->resolveLastAiApplication($settings->event_id),
            'last_publish' => $this->resolveLastPublish($settings),
            'last_restore' => $this->resolveLastRestore($settings->event_id),
        ];
    }

    /**
     * @param  mixed  $origin
     * @return array<string, mixed>|null
     */
    private function normalizePresetOrigin(mixed $origin): ?array
    {
        if (! is_array($origin)) {
            return null;
        }

        $appliedBy = is_array($origin['applied_by'] ?? null) ? $origin['applied_by'] : null;

        return [
            'origin_type' => isset($origin['origin_type']) ? (string) $origin['origin_type'] : null,
            'key' => isset($origin['key']) ? (string) $origin['key'] : null,
            'label' => isset($origin['label']) ? (string) $origin['label'] : null,
            'applied_at' => isset($origin['applied_at']) ? (string) $origin['applied_at'] : null,
            'applied_by' => $appliedBy
                ? [
                    'id' => isset($appliedBy['id']) ? (int) $appliedBy['id'] : null,
                    'name' => isset($appliedBy['name']) ? (string) $appliedBy['name'] : null,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLastAiApplication(int $eventId): ?array
    {
        $run = GalleryBuilderPromptRun::query()
            ->with('user:id,name')
            ->where('event_id', $eventId)
            ->whereNotNull('selected_variation_id')
            ->latest('updated_at')
            ->first();

        if (! $run instanceof GalleryBuilderPromptRun) {
            return null;
        }

        $responsePayload = is_array($run->response_payload_json) ? $run->response_payload_json : [];
        $selectedVariation = is_array($responsePayload['selected_variation'] ?? null)
            ? $responsePayload['selected_variation']
            : [];

        return [
            'run_id' => $run->id,
            'variation_id' => $run->selected_variation_id,
            'apply_scope' => isset($selectedVariation['apply_scope']) ? (string) $selectedVariation['apply_scope'] : null,
            'prompt_text' => $run->prompt_text,
            'target_layer' => $run->target_layer,
            'occurred_at' => isset($selectedVariation['applied_at'])
                ? (string) $selectedVariation['applied_at']
                : $run->updated_at?->toIso8601String(),
            'actor' => $run->user
                ? [
                    'id' => $run->user->id,
                    'name' => $run->user->name,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLastPublish(EventGallerySetting $settings): ?array
    {
        $revision = $settings->currentPublishedRevision instanceof EventGalleryRevision
            ? $settings->currentPublishedRevision
            : EventGalleryRevision::query()
                ->with('creator:id,name')
                ->where('event_id', $settings->event_id)
                ->where('kind', 'published')
                ->latest('id')
                ->first();

        return $this->mapRevision($revision);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveLastRestore(int $eventId): ?array
    {
        $revision = EventGalleryRevision::query()
            ->with('creator:id,name')
            ->where('event_id', $eventId)
            ->where('kind', 'restored')
            ->latest('id')
            ->first();

        return $this->mapRevision($revision, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRevision(?EventGalleryRevision $revision, bool $includeRestoreMeta = false): ?array
    {
        if (! $revision instanceof EventGalleryRevision) {
            return null;
        }

        $changeSummary = is_array($revision->change_summary_json) ? $revision->change_summary_json : [];

        return [
            'revision_id' => $revision->id,
            'version_number' => $revision->version_number,
            'occurred_at' => $revision->created_at?->toIso8601String(),
            'actor' => $revision->creator
                ? [
                    'id' => $revision->creator->id,
                    'name' => $revision->creator->name,
                ]
                : null,
            'change_reason' => isset($changeSummary['reason']) ? (string) $changeSummary['reason'] : null,
            'source_revision_id' => $includeRestoreMeta ? ($changeSummary['restored_from_revision_id'] ?? null) : null,
            'source_version_number' => $includeRestoreMeta ? ($changeSummary['restored_from_version_number'] ?? null) : null,
        ];
    }
}
