<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.create');
    }

    public function rules(): array
    {
        return [
            // Core
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:180'],
            'event_type' => ['required', Rule::enum(EventType::class)],
            'slug' => ['nullable', 'string', 'max:200', 'unique:events,slug'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],

            // Branding (inline)
            'branding' => ['nullable', 'array'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.secondary_color' => ['nullable', 'string', 'max:20'],
            'branding.cover_image_path' => ['nullable', 'string', 'max:255'],
            'branding.cover_media_id' => ['nullable', 'integer'],
            'branding.logo_path' => ['nullable', 'string', 'max:255'],

            // Modules (inline)
            'modules' => ['nullable', 'array'],
            'modules.live' => ['nullable', 'boolean'],
            'modules.wall' => ['nullable', 'boolean'],
            'modules.play' => ['nullable', 'boolean'],
            'modules.hub' => ['nullable', 'boolean'],

            // Privacy & moderation (inline)
            'privacy' => ['nullable', 'array'],
            'privacy.visibility' => ['nullable', 'string', 'in:public,private,unlisted'],
            'privacy.moderation_mode' => ['nullable', 'string', 'in:none,manual,ai'],
            'privacy.retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            'face_search' => ['nullable', 'array'],
            'face_search.enabled' => ['nullable', 'boolean'],
            'face_search.allow_public_selfie_search' => ['nullable', 'boolean'],
            'face_search.selfie_retention_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }
}
