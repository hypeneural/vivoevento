<?php

namespace App\Modules\MediaProcessing\Queries;

use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Support\ModerationFeedStateProjection;
use App\Shared\Concerns\HasPortableLike;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListModerationMediaQuery
{
    use HasPortableLike;

    public function __construct(
        private readonly int $organizationId,
        private readonly ?int $eventId = null,
        private readonly ?string $search = null,
        private readonly ?string $status = null,
        private readonly ?bool $featured = null,
        private readonly ?bool $pinned = null,
        private readonly ?bool $senderBlocked = null,
        private readonly ?string $orientation = null,
        private readonly ?ModerationFeedStateProjection $projection = null,
    ) {}

    public function query(bool $withStatusFilter = true): Builder
    {
        $projection = $this->projection();
        $effectiveStateSql = $projection->effectiveStateExpression();
        $pendingPrioritySql = $projection->pendingPriorityExpression();
        $sortOrderSql = $this->sortOrderExpression();
        $createdAtOrderSql = $this->createdAtOrderExpression();

        $query = EventMedia::query()
            ->select('event_media.*')
            ->addSelect([
                'moderation_feed_sort_order' => DB::raw($sortOrderSql),
                'moderation_feed_created_at_order' => DB::raw($createdAtOrderSql),
                'moderation_feed_effective_state' => DB::raw($effectiveStateSql),
                'moderation_feed_priority' => DB::raw($pendingPrioritySql),
            ])
            ->join('events', 'events.id', '=', 'event_media.event_id')
            ->leftJoin('event_content_moderation_settings', 'event_content_moderation_settings.event_id', '=', 'event_media.event_id')
            ->leftJoin('event_media_intelligence_settings', 'event_media_intelligence_settings.event_id', '=', 'event_media.event_id')
            ->where('events.organization_id', $this->organizationId)
            ->whereNull('events.deleted_at')
            ->with([
                'event:id,title,slug,status,moderation_mode',
                'event.contentModerationSettings:event_id,enabled,mode',
                'event.mediaIntelligenceSettings:event_id,enabled,mode',
                'variants',
                'inboundMessage',
            ])
            ->when($this->eventId, fn (Builder $builder) => $builder->where('event_media.event_id', $this->eventId))
            ->when($this->search !== null && trim($this->search) !== '', fn (Builder $builder) => $this->applySearch($builder))
            ->when($this->featured !== null, fn (Builder $builder) => $builder->where('event_media.is_featured', $this->featured))
            ->when($this->pinned !== null, function (Builder $builder) {
                $operator = $this->pinned ? '>' : '=';
                $builder->where('event_media.sort_order', $operator, 0);
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
                    'portrait' => $builder->whereColumn('event_media.height', '>', 'event_media.width'),
                    'landscape' => $builder->whereColumn('event_media.width', '>', 'event_media.height'),
                    'square' => $builder->whereColumn('event_media.width', '=', 'event_media.height'),
                    default => null,
                };
            });

        if ($withStatusFilter && $this->status) {
            $this->applyStatusFilter($query, $this->status);
        }

        return $query
            ->orderByRaw("{$sortOrderSql} desc")
            ->orderByRaw("{$pendingPrioritySql} desc")
            ->orderByRaw("{$createdAtOrderSql} desc")
            ->orderByDesc('event_media.id');
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
        $baseQuery = $this->query(withStatusFilter: true);
        $effectiveStateSql = $this->projection()->effectiveStateExpression();

        return [
            'total' => (clone $baseQuery)->count('event_media.id'),
            'pending' => (clone $baseQuery)->whereRaw("({$effectiveStateSql}) = 'pending_moderation'")->count('event_media.id'),
            'approved' => (clone $baseQuery)->whereRaw("({$effectiveStateSql}) in ('approved', 'published')")->count('event_media.id'),
            'rejected' => (clone $baseQuery)->whereRaw("({$effectiveStateSql}) = 'rejected'")->count('event_media.id'),
            'featured' => (clone $baseQuery)->where('event_media.is_featured', true)->count('event_media.id'),
            'pinned' => (clone $baseQuery)->where('event_media.sort_order', '>', 0)->count('event_media.id'),
        ];
    }

    private function applyStatusFilter(Builder $builder, string $status): void
    {
        $builder->whereRaw(
            '(' . $this->projection()->effectiveStateExpression() . ') = ?',
            [$status],
        );
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
        $createdAtOrder = isset($payload['created_at_order']) ? (int) $payload['created_at_order'] : null;
        $id = isset($payload['id']) ? (int) $payload['id'] : null;

        if ($createdAtOrder === null || ! $id) {
            throw ValidationException::withMessages([
                'cursor' => ['Cursor de moderacao invalido.'],
            ]);
        }

        $pendingPrioritySql = $this->projection()->pendingPriorityExpression();
        $sortOrderSql = $this->sortOrderExpression();
        $createdAtOrderSql = $this->createdAtOrderExpression();

        $builder->where(function (Builder $nested) use ($sortOrder, $moderationPriority, $createdAtOrder, $id, $pendingPrioritySql, $sortOrderSql, $createdAtOrderSql) {
            $nested->whereRaw("({$sortOrderSql}) < ?", [$sortOrder])
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $pendingPrioritySql, $sortOrderSql) {
                    $query->whereRaw("({$sortOrderSql}) = ?", [$sortOrder])
                        ->whereRaw('(' . $pendingPrioritySql . ') < ?', [$moderationPriority]);
                })
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $createdAtOrder, $pendingPrioritySql, $sortOrderSql, $createdAtOrderSql) {
                    $query->whereRaw("({$sortOrderSql}) = ?", [$sortOrder])
                        ->whereRaw('(' . $pendingPrioritySql . ') = ?', [$moderationPriority])
                        ->whereRaw("({$createdAtOrderSql}) < ?", [$createdAtOrder]);
                })
                ->orWhere(function (Builder $query) use ($sortOrder, $moderationPriority, $createdAtOrder, $id, $pendingPrioritySql, $sortOrderSql, $createdAtOrderSql) {
                    $query->whereRaw("({$sortOrderSql}) = ?", [$sortOrder])
                        ->whereRaw('(' . $pendingPrioritySql . ') = ?', [$moderationPriority])
                        ->whereRaw("({$createdAtOrderSql}) = ?", [$createdAtOrder])
                        ->where('event_media.id', '<', $id);
                });
        });
    }

    private function encodeCursor(EventMedia $media): string
    {
        $sortOrder = $media->getAttribute('moderation_feed_sort_order');
        $createdAtOrder = $media->getAttribute('moderation_feed_created_at_order');
        $effectiveState = $media->getAttribute('moderation_feed_effective_state');

        if (! is_string($effectiveState) || $effectiveState === '') {
            $effectiveState = app(\App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver::class)
                ->resolve($media->loadMissing('event.contentModerationSettings', 'event.mediaIntelligenceSettings'))['effective_media_state'];
        }

        $moderationPriority = (int) ($effectiveState === 'pending_moderation');

        return base64_encode((string) json_encode([
            'sort_order' => (int) ($sortOrder ?? $media->sort_order ?? 0),
            'moderation_priority' => $moderationPriority,
            'created_at_order' => (int) ($createdAtOrder ?? $media->created_at?->getTimestamp() ?? 0),
            'id' => $media->id,
        ]));
    }

    private function projection(): ModerationFeedStateProjection
    {
        return $this->projection ?? new ModerationFeedStateProjection();
    }

    private function applySearch(Builder $builder): void
    {
        $term = trim((string) $this->search);

        if ($term === '') {
            return;
        }

        $matchingEventIds = $this->eventIdsMatchingExactSearchTitle($term);

        if ($matchingEventIds !== []) {
            $builder->whereIn('event_media.event_id', $matchingEventIds);

            return;
        }

        $like = $this->likeOperator();
        $pattern = '%' . $term . '%';

        $builder->where('event_media.moderation_search_document', $like, $pattern);
    }

    private function eventIdsMatchingExactSearchTitle(string $term): array
    {
        return DB::table('events')
            ->where('organization_id', $this->organizationId)
            ->whereNull('deleted_at')
            ->where('title', $this->likeOperator(), $term)
            ->limit(50)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function sortOrderExpression(): string
    {
        return 'coalesce(event_media.sort_order, 0)';
    }

    private function createdAtOrderExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "cast(extract(epoch from event_media.created_at) as bigint)",
            'sqlite' => "cast(strftime('%s', event_media.created_at) as integer)",
            default => 'unix_timestamp(event_media.created_at)',
        };
    }
}
