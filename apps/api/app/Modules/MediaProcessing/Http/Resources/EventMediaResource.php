<?php

namespace App\Modules\MediaProcessing\Http\Resources;

use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Services\MediaAssetUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assets = app(MediaAssetUrlService::class);

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
            'status' => $this->frontendStatus(),
            'processing_status' => $this->processing_status?->value,
            'moderation_status' => $this->moderation_status?->value,
            'publication_status' => $this->publication_status?->value,
            'safety_status' => $this->safety_status,
            'face_index_status' => $this->face_index_status,
            'vlm_status' => $this->vlm_status,
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
            'caption' => $this->caption,
            'thumbnail_url' => $assets->thumbnail($this->resource),
            'preview_url' => $assets->preview($this->resource),
            'original_url' => $assets->original($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
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
            'whatsapp' => 'whatsapp',
            'telegram' => 'telegram',
            'public_link' => 'link',
            default => 'upload',
        };
    }

    private function frontendStatus(): string
    {
        if ($this->publication_status === PublicationStatus::Published) {
            return 'published';
        }

        if ($this->moderation_status === ModerationStatus::Rejected) {
            return 'rejected';
        }

        if ($this->moderation_status === ModerationStatus::Approved) {
            return 'approved';
        }

        if ($this->moderation_status === ModerationStatus::Pending) {
            return 'pending_moderation';
        }

        return match ($this->processing_status) {
            MediaProcessingStatus::Failed => 'error',
            MediaProcessingStatus::Downloaded, MediaProcessingStatus::Processed => 'processing',
            default => 'received',
        };
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
