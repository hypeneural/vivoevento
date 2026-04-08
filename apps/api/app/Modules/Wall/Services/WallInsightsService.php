<?php

namespace App\Modules\Wall\Services;

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Modules\Wall\Queries\BuildWallInsightsQuery;
use App\Modules\Wall\Support\WallSourceNormalizer;
use App\Shared\Support\PhoneNumber;
use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WallInsightsService
{
    public function __construct(
        private readonly BuildWallInsightsQuery $query,
        private readonly MediaAssetUrlService $mediaAssets,
    ) {}

    public function buildInsightsPayload(Event $event, int $recentLimit = 10): array
    {
        return [
            'topContributor' => $this->topContributor($this->query->contributorMedia($event)),
            'totals' => $this->query->totals($event),
            'recentItems' => $this->recentItems($this->query->recentItems($event, $recentLimit)),
            'sourceMix' => $this->sourceMix($this->query->sourceMix($event)),
            'lastCaptureAt' => $this->query->lastCaptureAt($event),
        ];
    }

    private function topContributor(Collection $mediaItems): ?array
    {
        if ($mediaItems->isEmpty()) {
            return null;
        }

        $aggregates = [];

        foreach ($mediaItems as $mediaItem) {
            $senderKey = $this->senderKey($mediaItem);

            if (! isset($aggregates[$senderKey])) {
                $aggregates[$senderKey] = [
                    'senderKey' => $senderKey,
                    'displayName' => $this->displayName($mediaItem),
                    'maskedContact' => $this->maskedContact($mediaItem),
                    'source' => WallSourceNormalizer::normalize($mediaItem->source_type),
                    'mediaCount' => 0,
                    'lastSentAt' => null,
                    'avatarUrl' => $mediaItem->inboundMessage?->sender_avatar_url,
                ];
            }

            $aggregates[$senderKey]['mediaCount']++;

            $createdAt = $mediaItem->created_at?->toIso8601String();
            if (
                $createdAt !== null
                && (
                    $aggregates[$senderKey]['lastSentAt'] === null
                    || $createdAt > $aggregates[$senderKey]['lastSentAt']
                )
            ) {
                $aggregates[$senderKey]['lastSentAt'] = $createdAt;
                $aggregates[$senderKey]['source'] = WallSourceNormalizer::normalize($mediaItem->source_type);
                $aggregates[$senderKey]['displayName'] = $this->displayName($mediaItem);
                $aggregates[$senderKey]['maskedContact'] = $this->maskedContact($mediaItem);
                $aggregates[$senderKey]['avatarUrl'] = $mediaItem->inboundMessage?->sender_avatar_url;
            }
        }

        return collect($aggregates)
            ->sortByDesc('lastSentAt')
            ->sortByDesc('mediaCount')
            ->values()
            ->first();
    }

    private function recentItems(Collection $mediaItems): array
    {
        return $mediaItems
            ->map(fn (EventMedia $mediaItem) => [
                'id' => (string) $mediaItem->id,
                'previewUrl' => $this->mediaAssets->thumbnail($mediaItem),
                'senderName' => $this->displayName($mediaItem),
                'senderKey' => $this->senderKey($mediaItem),
                'source' => WallSourceNormalizer::normalize($mediaItem->source_type),
                'createdAt' => $mediaItem->created_at?->toIso8601String(),
                'approvedAt' => $this->enumValue($mediaItem->moderation_status) === 'approved'
                    ? $mediaItem->updated_at?->toIso8601String()
                    : null,
                'displayedAt' => null,
                'status' => $this->recentStatus($mediaItem),
                'isFeatured' => (bool) $mediaItem->is_featured,
                'isReplay' => false,
            ])
            ->values()
            ->all();
    }

    private function sourceMix(Collection $rows): array
    {
        return $rows
            ->groupBy(fn ($row) => WallSourceNormalizer::normalize($row->source_type))
            ->map(fn (Collection $group, string $source) => [
                'source' => $source,
                'count' => (int) $group->sum(fn ($row) => (int) $row->aggregate),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function senderKey(EventMedia $mediaItem): string
    {
        $phone = PhoneNumber::normalizeBrazilianWhatsAppOrNull($mediaItem->inboundMessage?->sender_phone);

        if ($phone) {
            return 'whatsapp:'.$phone;
        }

        if ($mediaItem->uploaded_by_user_id) {
            return 'user:'.$mediaItem->uploaded_by_user_id;
        }

        if ($mediaItem->source_type && $mediaItem->source_label) {
            return 'source:'.Str::slug($mediaItem->source_type.'-'.$mediaItem->source_label);
        }

        $displayName = $this->displayName($mediaItem);

        if ($displayName !== null) {
            return 'guest:'.Str::slug($displayName);
        }

        return 'media:'.$mediaItem->id;
    }

    private function displayName(EventMedia $mediaItem): string
    {
        return trim((string) (
            $mediaItem->inboundMessage?->sender_name
            ?: $mediaItem->source_label
            ?: 'Convidado'
        ));
    }

    private function maskedContact(EventMedia $mediaItem): ?string
    {
        $phone = PhoneNumber::normalizeBrazilianWhatsAppOrNull($mediaItem->inboundMessage?->sender_phone);

        if ($phone === null || strlen($phone) < 6) {
            return null;
        }

        return substr($phone, 0, 4).'...'.substr($phone, -2);
    }

    private function recentStatus(EventMedia $mediaItem): string
    {
        $publicationStatus = $this->enumValue($mediaItem->publication_status);
        $moderationStatus = $this->enumValue($mediaItem->moderation_status);
        $processingStatus = $this->enumValue($mediaItem->processing_status);

        if ($publicationStatus === 'published') {
            return 'queued';
        }

        if ($moderationStatus === 'approved') {
            return 'approved';
        }

        if (
            $moderationStatus === 'rejected'
            || $processingStatus === 'failed'
        ) {
            return 'error';
        }

        return 'received';
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
