<?php

namespace App\Modules\MediaProcessing\Events;

use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class AbstractMediaPipelineEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $eventMediaId,
    ) {}

    public static function fromMedia(EventMedia $media): static
    {
        return new static($media->id);
    }

    public function resolveMedia(bool $withTrashed = false): ?EventMedia
    {
        $query = $withTrashed
            ? EventMedia::withTrashed()
            : EventMedia::query();

        return $query->find($this->eventMediaId);
    }
}
