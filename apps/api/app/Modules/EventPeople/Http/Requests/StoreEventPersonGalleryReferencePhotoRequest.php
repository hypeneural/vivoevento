<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonReferencePhotoPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPersonGalleryReferencePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_media_face_id' => ['required', 'integer', 'exists:event_media_faces,id'],
            'purpose' => ['nullable', Rule::enum(EventPersonReferencePhotoPurpose::class)],
        ];
    }
}
