<?php

namespace App\Modules\MediaProcessing\Queries;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Concerns\HasPortableLike;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ListCatalogMediaQuery
{
    use HasPortableLike;

    public function __construct(
        private readonly int $organizationId,
        private readonly ?int $eventId = null,
        private readonly ?string $search = null,
        private readonly ?string $status = null,
        private readonly ?string $channel = null,
        private readonly ?string $mediaType = null,
        private readonly ?bool $featured = null,
        private readonly ?bool $pinned = null,
        private readonly ?bool $duplicates = null,
        private readonly ?bool $hasCaption = null,
        private readonly ?bool $faceSearchEnabled = null,
        private readonly ?string $faceIndexStatus = null,
        private readonly ?string $safetyStatus = null,
        private readonly ?string $vlmStatus = null,
        private readonly ?string $decisionSource = null,
        private readonly ?string $publicationStatus = null,
        private readonly ?string $orientation = null,
        private readonly ?string $createdFrom = null,
        private readonly ?string $createdTo = null,
        private readonly string $sortBy = 'created_at',
        private readonly string $sortDirection = 'desc',
    ) {}

    public function query(bool $withStatusFilter = true): Builder
    {
        $query = EventMedia::query()
            ->select('event_media.*')
            ->whereHas('event', fn (Builder $builder) => $builder->where('organization_id', $this->organizationId))
            ->with([
                'event:id,title,slug,status,moderation_mode',
                'event.faceSearchSettings:id,event_id,enabled,allow_public_selfie_search',
                'variants',
                'inboundMessage',
            ])
            ->when($this->eventId, fn (Builder $builder) => $builder->where('event_id', $this->eventId))
            ->when($this->mediaType, fn (Builder $builder) => $builder->where('media_type', $this->mediaType))
            ->when($this->channel, fn (Builder $builder) => $this->applyChannelFilter($builder, $this->channel))
            ->when($this->featured !== null, fn (Builder $builder) => $builder->where('is_featured', $this->featured))
            ->when($this->pinned !== null, function (Builder $builder) {
                $operator = $this->pinned ? '>' : '=';
                $builder->where('sort_order', $operator, 0);
            })
            ->when($this->duplicates !== null, function (Builder $builder) {
                if ($this->duplicates) {
                    $builder->whereNotNull('duplicate_group_key');
                    return;
                }

                $builder->whereNull('duplicate_group_key');
            })
            ->when($this->hasCaption !== null, function (Builder $builder) {
                if ($this->hasCaption) {
                    $builder->whereNotNull('caption')->where('caption', '!=', '');
                    return;
                }

                $builder->where(function (Builder $nested) {
                    $nested->whereNull('caption')->orWhere('caption', '');
                });
            })
            ->when($this->faceSearchEnabled !== null, fn (Builder $builder) => $this->applyFaceSearchFilter($builder, $this->faceSearchEnabled))
            ->when($this->faceIndexStatus, fn (Builder $builder) => $builder->where('face_index_status', $this->faceIndexStatus))
            ->when($this->safetyStatus, fn (Builder $builder) => $builder->where('safety_status', $this->safetyStatus))
            ->when($this->vlmStatus, fn (Builder $builder) => $builder->where('vlm_status', $this->vlmStatus))
            ->when($this->decisionSource, fn (Builder $builder) => $builder->where('decision_source', $this->decisionSource))
            ->when($this->publicationStatus, fn (Builder $builder) => $builder->where('publication_status', $this->publicationStatus))
            ->when($this->orientation, function (Builder $builder) {
                match ($this->orientation) {
                    'portrait' => $builder->whereColumn('height', '>', 'width'),
                    'landscape' => $builder->whereColumn('width', '>', 'height'),
                    'square' => $builder->whereColumn('width', '=', 'height'),
                    default => null,
                };
            })
            ->when($this->createdFrom, fn (Builder $builder) => $builder->where('event_media.created_at', '>=', $this->parseDateBoundary($this->createdFrom, false)))
            ->when($this->createdTo, fn (Builder $builder) => $builder->where('event_media.created_at', '<=', $this->parseDateBoundary($this->createdTo, true)))
            ->when($this->search !== null && trim($this->search) !== '', function (Builder $builder) {
                $term = '%' . trim((string) $this->search) . '%';
                $like = $this->likeOperator();

                $builder->where(function (Builder $nested) use ($term, $like) {
                    $nested
                        ->where('caption', $like, $term)
                        ->orWhere('title', $like, $term)
                        ->orWhere('source_label', $like, $term)
                        ->orWhere('original_filename', $like, $term)
                        ->orWhere('client_filename', $like, $term)
                        ->orWhereHas('event', fn (Builder $query) => $query->where('title', $like, $term))
                        ->orWhereHas('inboundMessage', function (Builder $query) use ($like, $term) {
                            $query->where('sender_name', $like, $term)
                                ->orWhere('sender_phone', $like, $term)
                                ->orWhere('sender_lid', $like, $term)
                                ->orWhere('sender_external_id', $like, $term);
                        });
                });
            });

        if ($withStatusFilter && $this->status) {
            $this->applyStatusFilter($query, $this->status);
        }

        return $this->applySorting($query);
    }

    public function stats(): array
    {
        $baseQuery = $this->query(withStatusFilter: false);

        return [
            'total' => (clone $baseQuery)->count(),
            'images' => (clone $baseQuery)->where('media_type', 'image')->count(),
            'videos' => (clone $baseQuery)->where('media_type', 'video')->count(),
            'pending' => (clone $baseQuery)->where('moderation_status', ModerationStatus::Pending)->count(),
            'published' => (clone $baseQuery)->where('publication_status', PublicationStatus::Published)->count(),
            'featured' => (clone $baseQuery)->where('is_featured', true)->count(),
            'pinned' => (clone $baseQuery)->where('sort_order', '>', 0)->count(),
            'duplicates' => (clone $baseQuery)->whereNotNull('duplicate_group_key')->count(),
            'face_indexed' => (clone $baseQuery)->where('face_index_status', 'indexed')->count(),
        ];
    }

    private function applyStatusFilter(Builder $builder, string $status): void
    {
        match ($status) {
            'published' => $builder->where('publication_status', PublicationStatus::Published),
            'approved' => $builder->where('moderation_status', ModerationStatus::Approved),
            'rejected' => $builder->where('moderation_status', ModerationStatus::Rejected),
            'pending_moderation' => $builder->where('moderation_status', ModerationStatus::Pending),
            'processing' => $builder->whereIn('processing_status', [MediaProcessingStatus::Downloaded, MediaProcessingStatus::Processed]),
            'error' => $builder->where('processing_status', MediaProcessingStatus::Failed),
            default => $builder->where('processing_status', MediaProcessingStatus::Received),
        };
    }

    private function applyChannelFilter(Builder $builder, string $channel): void
    {
        match ($channel) {
            'upload' => $builder->whereIn('source_type', ['public_upload', 'upload', 'channel']),
            'link' => $builder->where('source_type', 'public_link'),
            'whatsapp' => $builder->whereIn('source_type', ['whatsapp', 'whatsapp_group', 'whatsapp_direct']),
            'telegram' => $builder->where('source_type', 'telegram'),
            'qrcode' => $builder->where('source_type', 'qrcode'),
            default => null,
        };
    }

    private function applyFaceSearchFilter(Builder $builder, bool $enabled): void
    {
        if ($enabled) {
            $builder->whereHas('event.faceSearchSettings', fn (Builder $query) => $query->where('enabled', true));
            return;
        }

        $builder->where(function (Builder $nested) {
            $nested
                ->whereDoesntHave('event.faceSearchSettings')
                ->orWhereHas('event.faceSearchSettings', fn (Builder $query) => $query->where('enabled', false));
        });
    }

    private function applySorting(Builder $builder): Builder
    {
        $direction = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';
        $sortBy = in_array($this->sortBy, ['created_at', 'published_at', 'sort_order'], true)
            ? $this->sortBy
            : 'created_at';

        if ($sortBy === 'sort_order') {
            return $builder
                ->orderBy('sort_order', $direction)
                ->orderByRaw('published_at is null asc')
                ->orderBy('published_at', 'desc')
                ->orderByDesc('event_media.id');
        }

        if ($sortBy === 'published_at') {
            return $builder
                ->orderByRaw('published_at is null asc')
                ->orderBy('published_at', $direction)
                ->orderByDesc('event_media.id');
        }

        return $builder
            ->orderBy('created_at', $direction)
            ->orderByDesc('event_media.id');
    }

    private function parseDateBoundary(string $value, bool $isUpperBound): Carbon
    {
        $parsed = Carbon::parse($value);
        $hasExplicitTime = preg_match('/[T\s]\d{2}:\d{2}/', $value) === 1;

        if ($hasExplicitTime) {
            return $parsed;
        }

        return $isUpperBound
            ? $parsed->endOfDay()
            : $parsed->startOfDay();
    }
}
