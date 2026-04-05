<?php

namespace App\Modules\FaceSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertEventFaceSearchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider_key' => ['required', 'string', 'in:noop'],
            'embedding_model_key' => ['required', 'string', 'max:120'],
            'vector_store_key' => ['required', 'string', 'in:pgvector'],
            'min_face_size_px' => ['required', 'integer', 'min:32', 'max:1024'],
            'min_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'search_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'top_k' => ['required', 'integer', 'min:1', 'max:500'],
            'allow_public_selfie_search' => ['required', 'boolean'],
            'selfie_retention_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $publicSearch = (bool) $this->input('allow_public_selfie_search', false);
            $enabled = (bool) $this->input('enabled', false);

            if ($publicSearch && ! $enabled) {
                $validator->errors()->add(
                    'allow_public_selfie_search',
                    'A busca publica por selfie exige FaceSearch habilitado para o evento.',
                );
            }
        });
    }
}
