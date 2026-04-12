<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Support\GalleryRevisionManager;
use App\Modules\Users\Models\User;

class RestoreEventGalleryRevisionAction
{
    public function __construct(
        private readonly GalleryRevisionManager $revisions,
    ) {}

    /**
     * @return array{settings: EventGallerySetting, revision: EventGalleryRevision}
     */
    public function execute(
        EventGallerySetting $settings,
        EventGalleryRevision $sourceRevision,
        ?User $user = null,
    ): array {
        $settings = $this->revisions->applyRevisionToSettings($settings, $sourceRevision, $user);
        $restored = $this->revisions->createRevision($settings, 'restored', $user, [
            'restored_from_revision_id' => $sourceRevision->id,
            'restored_from_version_number' => $sourceRevision->version_number,
        ]);

        $settings->fill([
            'current_draft_revision_id' => $restored->id,
            'draft_version' => $restored->version_number,
            'last_autosaved_at' => now(),
            'preview_share_token' => null,
            'preview_share_expires_at' => null,
            'preview_revision_id' => null,
            'updated_by' => $user?->id ?? $settings->updated_by,
        ]);
        $settings->save();

        return [
            'settings' => $settings->fresh(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']),
            'revision' => $restored->fresh(),
        ];
    }
}
