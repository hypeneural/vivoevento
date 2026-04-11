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
            'inherit_branding' => ['nullable', 'boolean'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'moderation_mode' => ['nullable', 'string', 'in:none,manual,ai'],

            'branding' => ['nullable', 'array'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
            'branding.secondary_color' => ['nullable', 'string', 'max:20'],
            'branding.cover_image_path' => ['nullable', 'string', 'max:255'],
            'branding.logo_path' => ['nullable', 'string', 'max:255'],
            'branding.inherit_branding' => ['nullable', 'boolean'],

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
            'face_search.search_strategy' => ['nullable', 'string', 'in:exact,ann'],
            'face_search.allow_public_selfie_search' => ['nullable', 'boolean'],
            'face_search.selfie_retention_hours' => ['nullable', 'integer', 'min:1', 'max:720'],

            'intake_defaults' => ['nullable', 'array'],
            'intake_defaults.whatsapp_instance_id' => ['nullable', 'integer', 'exists:whatsapp_instances,id'],
            'intake_defaults.whatsapp_instance_mode' => ['nullable', 'string', 'in:shared,dedicated'],

            'intake_channels' => ['nullable', 'array'],
            'intake_channels.whatsapp_groups' => ['nullable', 'array'],
            'intake_channels.whatsapp_groups.enabled' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_groups.groups' => ['nullable', 'array'],
            'intake_channels.whatsapp_groups.groups.*.group_external_id' => ['required_with:intake_channels.whatsapp_groups.groups', 'string', 'max:180'],
            'intake_channels.whatsapp_groups.groups.*.group_name' => ['nullable', 'string', 'max:180'],
            'intake_channels.whatsapp_groups.groups.*.is_active' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_groups.groups.*.auto_feedback_enabled' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_direct' => ['nullable', 'array'],
            'intake_channels.whatsapp_direct.enabled' => ['nullable', 'boolean'],
            'intake_channels.whatsapp_direct.media_inbox_code' => ['nullable', 'string', 'max:80'],
            'intake_channels.whatsapp_direct.session_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],
            'intake_channels.public_upload' => ['nullable', 'array'],
            'intake_channels.public_upload.enabled' => ['nullable', 'boolean'],
            'intake_channels.telegram' => ['nullable', 'array'],
            'intake_channels.telegram.enabled' => ['nullable', 'boolean'],
            'intake_channels.telegram.bot_username' => ['nullable', 'string', 'max:80'],
            'intake_channels.telegram.media_inbox_code' => ['nullable', 'string', 'max:80'],
            'intake_channels.telegram.session_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:4320'],

            'intake_blacklist' => ['nullable', 'array'],
            'intake_blacklist.entries' => ['nullable', 'array'],
            'intake_blacklist.entries.*.id' => ['nullable', 'integer'],
            'intake_blacklist.entries.*.identity_type' => ['required_with:intake_blacklist.entries', 'string', 'in:phone,lid,external_id'],
            'intake_blacklist.entries.*.identity_value' => ['required_with:intake_blacklist.entries', 'string', 'max:180'],
            'intake_blacklist.entries.*.normalized_phone' => ['nullable', 'string', 'max:40'],
            'intake_blacklist.entries.*.reason' => ['nullable', 'string', 'max:255'],
            'intake_blacklist.entries.*.expires_at' => ['nullable', 'date'],
            'intake_blacklist.entries.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
