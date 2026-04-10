<?php

namespace App\Modules\MediaProcessing\Http\Resources;

use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use App\Modules\MediaProcessing\Services\MediaEffectiveStateResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);
        $thumbnailAsset = $this->normalizeAsset($assets->thumbnailAsset($this->resource));
        $previewAsset = $this->normalizeAsset($assets->previewAsset($this->resource));
        $moderationThumbnailAsset = $this->normalizeAsset($assets->moderationThumbnailAsset($this->resource));
        $moderationPreviewAsset = $this->normalizeAsset($assets->moderationPreviewAsset($this->resource));
        $state = app(MediaEffectiveStateResolver::class)->resolve($this->resource);
        $effectiveState = is_string($this->resource->getAttribute('moderation_feed_effective_state'))
            ? $this->resource->getAttribute('moderation_feed_effective_state')
            : $state['effective_media_state'];

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_title' => $this->event?->title,
            'event_slug' => $this->event?->slug,
            'event_status' => $this->event?->status?->value,
            'event_moderation_mode' => $this->event?->moderation_mode?->value,
            'event_face_search_enabled' => $this->when(
                $this->event?->relationLoaded('faceSearchSettings') ?? false,
                fn () => (bool) ($this->event?->faceSearchSettings?->enabled ?? false),
            ),
            'event_allow_public_selfie_search' => $this->when(
                $this->event?->relationLoaded('faceSearchSettings') ?? false,
                fn () => (bool) ($this->event?->faceSearchSettings?->allow_public_selfie_search ?? false),
            ),
            'media_type' => $this->media_type,
            'channel' => $this->channel(),
            'status' => $effectiveState,
            'effective_media_state' => $effectiveState,
            'processing_status' => $this->processing_status?->value,
            'moderation_status' => $this->moderation_status?->value,
            'publication_status' => $this->publication_status?->value,
            'safety_status' => $this->safety_status,
            'face_index_status' => $this->face_index_status,
            'vlm_status' => $this->vlm_status,
            'safety_decision' => $state['safety_decision'],
            'safety_is_blocking' => $state['safety_is_blocking'],
            'context_decision' => $state['context_decision'],
            'context_is_blocking' => $state['context_is_blocking'],
            'operator_decision' => $state['operator_decision'],
            'publication_decision' => $state['publication_decision'],
            'decision_source' => $this->decision_source?->value,
            'decision_overridden_at' => $this->decision_overridden_at?->toIso8601String(),
            'decision_overridden_by_user_id' => $this->decision_overridden_by_user_id,
            'decision_override_reason' => $this->decision_override_reason,
            'pipeline_version' => $this->pipeline_version,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->displayFilename(),
            'client_filename' => $this->client_filename,
            'duplicate_group_key' => $this->duplicate_group_key,
            'is_duplicate_candidate' => $this->duplicate_group_key !== null,
            'sender_name' => $this->senderName(),
            'sender_avatar_url' => $this->sender_avatar_url,
            'sender_phone' => $this->sender_phone,
            'sender_lid' => $this->sender_lid,
            'sender_external_id' => $this->sender_external_id,
            'sender_blocked' => (bool) ($this->sender_blocked ?? false),
            'sender_blocking_entry_id' => $this->sender_blocking_entry_id,
            'sender_block_reason' => $this->sender_block_reason,
            'sender_block_expires_at' => $this->sender_block_expires_at,
            'sender_blacklist_enabled' => (bool) ($this->sender_blacklist_enabled ?? false),
            'sender_recommended_identity_type' => $this->sender_recommended_identity_type,
            'sender_recommended_identity_value' => $this->sender_recommended_identity_value,
            'sender_recommended_normalized_phone' => $this->sender_recommended_normalized_phone,
            'sender_media_count' => $this->sender_media_count,
            'caption' => $this->caption,
            'thumbnail_url' => $thumbnailAsset['url'],
            'thumbnail_source' => $thumbnailAsset['source'],
            'preview_url' => $previewAsset['url'],
            'preview_source' => $previewAsset['source'],
            'moderation_thumbnail_url' => $moderationThumbnailAsset['url'],
            'moderation_thumbnail_source' => $moderationThumbnailAsset['source'],
            'moderation_preview_url' => $moderationPreviewAsset['url'],
            'moderation_preview_source' => $moderationPreviewAsset['source'],
            'original_url' => $assets->original($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_featured' => (bool) $this->is_featured,
            'is_pinned' => (int) ($this->sort_order ?? 0) > 0,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation(),
        ];
    }

    private function senderName(): string
    {
        if ($this->relationLoaded('inboundMessage') && $this->inboundMessage?->sender_name) {
            return $this->inboundMessage->sender_name;
        }

        return $this->source_label ?: 'Convidado';
    }

    private function channel(): string
    {
        return match ($this->source_type) {
            'public_upload' => 'upload',
            'whatsapp', 'whatsapp_group', 'whatsapp_direct' => 'whatsapp',
            'telegram' => 'telegram',
            'public_link' => 'link',
            default => 'upload',
        };
    }

    /**
     * @param  mixed  $asset
     * @return array{url: ?string, source: ?string}
     */
    private function normalizeAsset(mixed $asset): array
    {
        if (! is_array($asset)) {
            return ['url' => null, 'source' => null];
        }

        $url = array_key_exists('url', $asset) && is_string($asset['url'])
            ? $asset['url']
            : null;
        $source = array_key_exists('source', $asset) && is_string($asset['source'])
            ? $asset['source']
            : null;

        return [
            'url' => $url,
            'source' => $source,
        ];
    }

    private function orientation(): string
    {
        $width = (int) ($this->width ?? 0);
        $height = (int) ($this->height ?? 0);

        if ($width > 0 && $height > 0) {
            if ($height > $width) {
                return 'portrait';
            }

            if ($width > $height) {
                return 'landscape';
            }
        }

        return 'square';
    }
}
