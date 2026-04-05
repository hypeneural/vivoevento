<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.update');
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->id ?? $this->route('event');

        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'title' => ['sometimes', 'string', 'max:180'],
            'event_type' => ['sometimes', Rule::enum(EventType::class)],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('events', 'slug')->ignore($eventId)],
            'visibility' => ['nullable', 'string', 'in:public,private,unlisted'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'cover_image_path' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'moderation_mode' => ['nullable', 'string', 'in:none,manual,ai'],

            'branding' => ['nullable', 'array'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.secondary_color' => ['nullable', 'string', 'max:20'],
            'branding.cover_image_path' => ['nullable', 'string', 'max:255'],
            'branding.logo_path' => ['nullable', 'string', 'max:255'],

            'modules' => ['nullable', 'array'],
            'modules.live' => ['nullable', 'boolean'],
            'modules.wall' => ['nullable', 'boolean'],
            'modules.play' => ['nullable', 'boolean'],
            'modules.hub' => ['nullable', 'boolean'],

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
