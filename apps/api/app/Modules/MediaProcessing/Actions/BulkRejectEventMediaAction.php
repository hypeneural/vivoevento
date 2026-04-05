<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;

class BulkRejectEventMediaAction
{
    public function __construct(
        private readonly RejectEventMediaAction $rejectEventMedia,
    ) {}

    public function execute(Collection $mediaItems, ?User $actor = null, ?string $reason = null): Collection
    {
        return $mediaItems
            ->map(fn ($media) => $this->rejectEventMedia->execute($media, $actor, $reason))
            ->values();
    }
}
