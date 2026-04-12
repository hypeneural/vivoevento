<?php

namespace App\Modules\Gallery\Actions;

use App\Modules\Gallery\Models\EventGalleryRevision;
use App\Modules\Gallery\Models\EventGallerySetting;
use App\Modules\Gallery\Support\GalleryRevisionManager;
use App\Modules\Users\Models\User;

class AutosaveEventGalleryDraftAction
{
    public function __construct(
        private readonly GalleryRevisionManager $revisions,
    ) {}

    /**
     * @return array{settings: EventGallerySetting, revision: EventGalleryRevision}
     */
    public function execute(EventGallerySetting $settings, ?User $user = null): array
    {
        $revision = $this->revisions->createRevision($settings, 'autosave', $user);

        $settings->fill([
            'current_draft_revision_id' => $revision->id,
            'draft_version' => $revision->version_number,
            'last_autosaved_at' => now(),
            'preview_share_token' => null,
            'preview_share_expires_at' => null,
            'preview_revision_id' => null,
            'updated_by' => $user?->id ?? $settings->updated_by,
        ]);
        $settings->save();

        return [
            'settings' => $settings->fresh(['currentDraftRevision', 'currentPublishedRevision', 'previewRevision']),
            'revision' => $revision->fresh(),
        ];
    }
}
