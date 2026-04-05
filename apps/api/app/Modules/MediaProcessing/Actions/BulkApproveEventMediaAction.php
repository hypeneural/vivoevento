<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;

class BulkApproveEventMediaAction
{
    public function __construct(
        private readonly ApproveEventMediaAction $approveEventMedia,
    ) {}

    public function execute(Collection $mediaItems, ?User $actor = null, ?string $reason = null): Collection
    {
        return $mediaItems
            ->map(fn ($media) => $this->approveEventMedia->execute($media, $actor, $reason))
            ->values();
    }
}
