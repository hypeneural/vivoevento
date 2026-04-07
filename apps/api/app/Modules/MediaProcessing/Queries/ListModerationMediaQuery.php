<?php

namespace App\Modules\MediaProcessing\Queries;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Shared\Concerns\HasPortableLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ListModerationMediaQuery
{
    use HasPortableLike;

    private const PENDING_PRIORITY_SQL = 'case when moderation_status = ? then 1 else 0 end';

    public function __construct(
        private readonly int $organizationId,
        private readonly ?int $eventId = null,
        private readonly ?string $search = null,
        private readonly ?string $status = null,
        private readonly ?bool $featured = null,
        private readonly ?bool $pinned = null,
        private readonly ?bool $senderBlocked = null,
        private readonly ?string $orientation = null,
    ) {}

    public function query(bool $withStatusFilter = true): Builder
    {
        $query = EventMedia::query()
            ->select('event_media.*')
            ->whereHas('event', fn (Builder $builder) => $builder->where('organization_id', $this->organizationId))
            ->with([
                'event:id,title,slug,status',
                'variants',
                'inboundMessage',
            ])
            ->when($this->eventId, fn (Builder $builder) => $builder->where('event_id', $this->eventId))
            ->when($this->search !== null && trim($this->search) !== '', function (Builder $builder) {
                $term = '%' . trim((string) $this->search) . '%';
                $like = $this->likeOperator();

                $builder->where(function (Builder $nested) use ($term, $like) {
                    $nested
                        ->where('caption', $like, $term)
                        ->orWhere('title', $like, $term)
                        ->orWhere('source_label', $like, $term)
                        ->orWhere('original_filename', $like, $term)
                        ->orWhereHas('event', fn (Builder $query) => $query->where('title', $like, $term))
                        ->orWhereHas('inboundMessage', function (Builder $query) use ($like, $term) {
                            $query->where('sender_name', $like, $term)
                                ->orWhere('sender_phone', $like, $term)
                                ->orWhere('sender_lid', $like, $term)
                                ->orWhere('sender_external_id', $like, $term);
                        });
                });
            })
            ->when($this->featured !== null, fn (Builder $builder) => $builder->where('is_featured', $this->featured))
            ->when($this->pinned !== null, function (Builder $builder) {
                $operator = $this->pinned ? '>' : '=';
                $builder->where('sort_order', $operator, 0);
            })
            ->when($this->senderBlocked !== null, function (Builder $builder) {
                $method = $this->senderBlocked ? 'whereHas' : 'whereDoesntHave';

                $builder->{$method}('inboundMessage', function (Builder $inboundQuery) {
                    $inboundQuery->whereExists(function ($blacklistQuery) {
                        $blacklistQuery->selectRaw('1')
                            ->from('event_media_sender_blacklists as blacklist')
                            ->whereColumn('blacklist.event_id', 'event_media.event_id')
                            ->where('blacklist.is_active', true)
                            ->where(function ($activeQuery) {
                                $activeQuery->whereNull('blacklist.expires_at')
                                    ->orWhere('blacklist.expires_at', '>', now());
                            })
                            ->where(function ($matchQuery) {
                                $matchQuery
                                    ->where(function ($lidQuery) {
                                        $lidQuery
                                            ->where('blacklist.identity_type', 'lid')
                                            ->whereColumn('blacklist.identity_value', 'inbound_messages.sender_lid');
                                    })
                                    ->orWhere(function ($externalIdQuery) {
                                        $externalIdQuery
                                            ->where('blacklist.identity_type', 'external_id')
                                            ->whereColumn('blacklist.identity_value', 'inbound_messages.sender_external_id');
                                    })
                                    ->orWhere(function ($phoneQuery) {
                                        $phoneQuery
                                            ->where('blacklist.identity_type', 'phone')
                                            ->where(function ($candidateQuery) {
                                                $candidateQuery
                                                    ->whereColumn('blacklist.identity_value', 'inbound_messages.sender_phone')
                                                    ->orWhereColumn('blacklist.normalized_phone', 'inbound_messages.sender_phone');
                                            });
                                    });
                            });
                    });
                });
            })
            ->when($this->orientation, function (Builder $builder) {
                match ($this->orientation) {
                    'portrait' => $builder->whereColumn('height', '>', 'width'),
                    'landscape' => $builder->whereColumn('width', '>', 'height'),
                    'square' => $builder->whereColumn('width', '=', 'height'),
                    default => null,
                };
            });

        if ($withStatusFilter && $this->status) {
            $this->applyStatusFilter($query, $this->status);
        }

        return $query
            ->orderByDesc('sort_order')
            ->orderByRaw(self::PENDING_PRIORITY_SQL . ' desc', [ModerationStatus::Pending->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function fetchCursorPage(int $perPage, ?string $cursor = null): array
    {
        $query = $this->query();
        $this->applyCursor($query, $cursor);

        /** @var Collection<int, EventMedia> $items */
        $items = $query
            ->limit($perPage + 1)
            ->get();

        $hasMore = $items->count() > $perPage;
        $pageItems = $hasMore ? $items->take($perPage)->values() : $items->values();
        $lastItem = $pageItems->last();

        return [
            'items' => $pageItems,
            'has_more' => $hasMore,
            'next_cursor' => $hasMore && $lastItem instanceof EventMedia
                ? $this->encodeCursor($lastItem)
                : null,
        ];
    }

    public function stats(): array
    {
        $baseQuery = $this->query(withStatusFilter: false);

        return [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('moderation_status', ModerationStatus::Pending)->count(),
            'approved' => (clone $baseQuery)->where('moderation_status', ModerationStatus::Approved)->count(),
            'rejected' => (clone $baseQuery)->where('moderation_status', ModerationStatus::Rejected)->count(),
            'featured' => (clone $baseQuery)->where('is_featured', true)->count(),
            'pinned' => (clone $baseQuery)->where('sort_order', '>', 0)->count(),
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

    private function applyCursor(Builder $builder, ?string $cursor): void
    {
        if (! $cursor) {
            return;
        }

        $decoded = base64_decode($cursor, true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'cursor' => ['Cursor de moderacao invalido.'],
            ]);
        }

        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $moderationPriority = (int) ($payload['moderation_priority'] ?? 0);
        $createdAt = isset($payload['created_at']) ? (string) $payload['created_at'] : null;
        $id = isset($payload['id']) ? (int) $payload['id'] : null;

        if (! $createdAt || ! $id) {
            throw ValidationException::withMessages([
                'cursor' => ['Cursor de moderacao invalido.'],
            ]);
        }

        $builder->where(function (Builder $nested) use ($sortOrder, $moderationPriority, $createdAt, $id) {
            $nested->where('sort_order', '<', $sortOrder)
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $createdAt, $id) {
                    $query->where('sort_order', $sortOrder)
                        ->whereRaw(self::PENDING_PRIORITY_SQL . ' < ?', [
                            ModerationStatus::Pending->value,
                            $moderationPriority,
                        ]);
                })
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $createdAt, $id) {
                    $query->where('sort_order', $sortOrder)
                        ->whereRaw(self::PENDING_PRIORITY_SQL . ' = ?', [
                            ModerationStatus::Pending->value,
                            $moderationPriority,
                        ])
                        ->where('created_at', '<', $createdAt);
                })
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $createdAt, $id) {
                    $query->where('sort_order', $sortOrder)
                        ->whereRaw(self::PENDING_PRIORITY_SQL . ' = ?', [
                            ModerationStatus::Pending->value,
                            $moderationPriority,
                        ])
                        ->where('created_at', $createdAt)
                        ->where('id', '<', $id);
                });
        });
    }

    private function encodeCursor(EventMedia $media): string
    {
        return base64_encode((string) json_encode([
            'sort_order' => (int) ($media->sort_order ?? 0),
            'moderation_priority' => $media->moderation_status === ModerationStatus::Pending ? 1 : 0,
            'created_at' => $media->created_at?->format('Y-m-d H:i:s.u'),
            'id' => $media->id,
        ]));
    }
}
