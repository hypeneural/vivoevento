<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoSource;
use App\Modules\EventPeople\Enums\EventPersonReferencePhotoStatus;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonReferencePhoto;
use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Models\EventFaceSearchSetting;
use App\Modules\FaceSearch\Services\SelfiePreflightService;
use App\Modules\MediaProcessing\Enums\MediaProcessingStatus;
use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadEventPersonReferencePhotoAction
{
    public function __construct(
        private readonly SelfiePreflightService $selfiePreflight,
    ) {}

    public function execute(
        Event $event,
        EventPerson $person,
        User $user,
        UploadedFile $file,
        EventPersonReferencePhotoPurpose $purpose = EventPersonReferencePhotoPurpose::Matching,
    ): EventPersonReferencePhoto {
        if ((int) $person->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'person_id' => 'A pessoa informada nao pertence ao evento.',
            ]);
        }

        $binary = (string) file_get_contents($file->getRealPath());

        if ($binary === '') {
            throw ValidationException::withMessages([
                'file' => ['Nao foi possivel ler a imagem enviada.'],
            ]);
        }

        $settings = $this->validationSettings($event);

        try {
            $preflight = $this->selfiePreflight->validateForSearch(
                event: $event,
                settings: $settings,
                binary: $binary,
                publicSearch: false,
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'file' => collect($exception->errors()['selfie'] ?? ['A foto de referencia precisa mostrar uma pessoa dominante.'])->values()->all(),
            ]);
        }

        if ($preflight['assessment']->isRejected()) {
            throw ValidationException::withMessages([
                'file' => ['A foto precisa estar mais nitida e aproximada para virar referencia.'],
            ]);
        }

        $path = $file->store("events/{$event->id}/people/reference-uploads", 'public');

        try {
            [$width, $height] = $this->imageDimensions($file);

            $referencePhoto = DB::transaction(function () use ($event, $person, $user, $file, $path, $width, $height, $purpose, $preflight): EventPersonReferencePhoto {
                $media = EventMedia::query()->create([
                    'event_id' => $event->id,
                    'uploaded_by_user_id' => $user->id,
                    'media_type' => 'image',
                    'source_type' => 'upload',
                    'source_label' => $person->display_name,
                    'title' => 'Referencia manual de ' . $person->display_name,
                    'caption' => 'Upload manual de referencia do EventPeople',
                    'original_filename' => $file->getClientOriginalName(),
                    'original_disk' => 'public',
                    'original_path' => $path,
                    'client_filename' => $file->getClientOriginalName(),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'width' => $width,
                    'height' => $height,
                    'processing_status' => MediaProcessingStatus::Processed->value,
                    'moderation_status' => ModerationStatus::Approved->value,
                    'publication_status' => PublicationStatus::Draft->value,
                    'safety_status' => 'skipped',
                    'face_index_status' => 'skipped',
                    'vlm_status' => 'skipped',
                    'pipeline_version' => 'event_people_reference_upload_v1',
                ]);

                return EventPersonReferencePhoto::query()->create([
                    'event_id' => $event->id,
                    'event_person_id' => $person->id,
                    'source' => EventPersonReferencePhotoSource::ManualUpload->value,
                    'event_media_id' => null,
                    'event_media_face_id' => null,
                    'reference_upload_media_id' => $media->id,
                    'purpose' => $purpose->value,
                    'status' => EventPersonReferencePhotoStatus::Active->value,
                    'quality_score' => $preflight['face']->qualityScore,
                    'is_primary_avatar' => false,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return $referencePhoto->fresh(['uploadMedia']);
    }

    private function validationSettings(Event $event): EventFaceSearchSetting
    {
        $event->loadMissing('faceSearchSettings');

        if ($event->faceSearchSettings) {
            return new EventFaceSearchSetting(array_merge(
                ['event_id' => $event->id],
                $event->faceSearchSettings->only(EventFaceSearchSetting::configurableAttributeKeys()),
                ['enabled' => true],
            ));
        }

        return new EventFaceSearchSetting(array_merge(
            ['event_id' => $event->id],
            EventFaceSearchSetting::defaultAttributes(),
            ['enabled' => true],
        ));
    }

    /**
     * @return array{0:int|null, 1:int|null}
     */
    private function imageDimensions(UploadedFile $file): array
    {
        $dimensions = @getimagesize($file->getRealPath());

        if (! is_array($dimensions)) {
            return [null, null];
        }

        return [
            is_numeric($dimensions[0] ?? null) ? (int) $dimensions[0] : null,
            is_numeric($dimensions[1] ?? null) ? (int) $dimensions[1] : null,
        ];
    }
}
