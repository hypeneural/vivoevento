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
            'provider_key' => ['required', 'string', 'in:noop,compreface'],
            'embedding_model_key' => ['required', 'string', 'max:120'],
            'vector_store_key' => ['required', 'string', 'in:pgvector'],
            'search_strategy' => ['required', 'string', 'in:exact,ann'],
            'min_face_size_px' => ['required', 'integer', 'min:16', 'max:1024'],
            'min_quality_score' => ['required', 'numeric', 'min:0', 'max:1'],
            'search_threshold' => ['required', 'numeric', 'min:0', 'max:1'],
            'top_k' => ['required', 'integer', 'min:1', 'max:500'],
            'allow_public_selfie_search' => ['required', 'boolean'],
            'selfie_retention_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'recognition_enabled' => ['sometimes', 'boolean'],
            'search_backend_key' => ['sometimes', 'string', 'in:local_pgvector,aws_rekognition,luxand_managed'],
            'fallback_backend_key' => ['sometimes', 'nullable', 'string', 'in:local_pgvector,aws_rekognition,luxand_managed'],
            'routing_policy' => ['sometimes', 'string', 'in:local_only,aws_primary_local_fallback,aws_primary_local_shadow,local_primary_aws_on_error'],
            'shadow_mode_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'aws_region' => ['sometimes', 'string', 'max:40'],
            'aws_collection_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'aws_collection_arn' => ['sometimes', 'nullable', 'string', 'max:255'],
            'aws_face_model_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'aws_search_mode' => ['sometimes', 'string', 'in:faces,users'],
            'aws_index_quality_filter' => ['sometimes', 'string', 'in:AUTO,LOW,MEDIUM,HIGH,NONE'],
            'aws_search_faces_quality_filter' => ['sometimes', 'string', 'in:AUTO,LOW,MEDIUM,HIGH,NONE'],
            'aws_search_users_quality_filter' => ['sometimes', 'string', 'in:AUTO,LOW,MEDIUM,HIGH,NONE'],
            'aws_search_face_match_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'aws_search_user_match_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'aws_associate_user_match_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'aws_max_faces_per_image' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'aws_index_profile_key' => ['sometimes', 'string', 'max:120'],
            'aws_detection_attributes_json' => ['sometimes', 'array', 'min:1'],
            'aws_detection_attributes_json.*' => ['string', 'max:64'],
            'delete_remote_vectors_on_event_close' => ['sometimes', 'boolean'],
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

            $searchBackend = (string) $this->input('search_backend_key', 'local_pgvector');
            $recognitionEnabled = (bool) $this->input('recognition_enabled', false);
            $awsRegion = (string) $this->input('aws_region', '');

            if ($searchBackend === 'aws_rekognition' && ! $recognitionEnabled) {
                $validator->errors()->add(
                    'recognition_enabled',
                    'O backend AWS Rekognition exige recognition_enabled=true para o evento.',
                );
            }

            if ($searchBackend === 'aws_rekognition' && $awsRegion === '') {
                $validator->errors()->add(
                    'aws_region',
                    'Informe a regiao AWS quando o backend do evento for aws_rekognition.',
                );
            }
        });
    }
}
