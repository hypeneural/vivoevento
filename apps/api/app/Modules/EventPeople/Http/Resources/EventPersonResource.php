<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $relations = collect();
        $stat = $this->relationLoaded('mediaStats') ? $this->mediaStats->first() : null;
        $primaryReferencePhoto = $this->relationLoaded('primaryReferencePhoto') ? $this->primaryReferencePhoto : null;

        if ($this->relationLoaded('outgoingRelations')) {
            $relations = $relations->merge($this->outgoingRelations);
        }

        if ($this->relationLoaded('incomingRelations')) {
            $relations = $relations->merge($this->incomingRelations);
        }

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'display_name' => $this->display_name,
            'slug' => $this->slug,
            'type' => $this->type?->value ?? $this->type,
            'side' => $this->side?->value ?? $this->side,
            'avatar_media_id' => $this->avatar_media_id,
            'avatar_face_id' => $this->avatar_face_id,
            'avatar' => [
                'media_id' => $this->avatar_media_id,
                'face_id' => $this->avatar_face_id,
            ],
            'importance_rank' => $this->importance_rank,
            'notes' => $this->notes,
            'status' => $this->status?->value ?? $this->status,
            'primary_photo' => ($primaryReferencePhoto || $stat)
                ? [
                    'reference_photo_id' => $primaryReferencePhoto?->id,
                    'selection_mode' => $primaryReferencePhoto ? 'manual' : 'derived',
                    'source' => $primaryReferencePhoto?->source?->value ?? $primaryReferencePhoto?->source,
                    'media_id' => $primaryReferencePhoto?->event_media_id
                        ?? $primaryReferencePhoto?->reference_upload_media_id
                        ?? $stat?->best_media_id
                        ?? $stat?->latest_media_id,
                    'event_media_id' => $primaryReferencePhoto?->event_media_id,
                    'event_media_face_id' => $primaryReferencePhoto?->event_media_face_id,
                    'reference_upload_media_id' => $primaryReferencePhoto?->reference_upload_media_id,
                    'best_media_id' => $stat?->best_media_id,
                    'latest_media_id' => $stat?->latest_media_id,
                ]
                : null,
            'stats' => $this->whenLoaded('mediaStats', fn () => EventPersonMediaStatResource::collection($this->mediaStats)),
            'reference_photos' => $this->whenLoaded('referencePhotos', fn () => EventPersonReferencePhotoResource::collection($this->referencePhotos)),
            'representative_faces' => $this->whenLoaded('representativeFaces', fn () => EventPersonRepresentativeFaceResource::collection($this->representativeFaces)),
            'relations' => $relations->isNotEmpty()
                ? EventPersonRelationResource::collection($relations->unique('id')->values())
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
