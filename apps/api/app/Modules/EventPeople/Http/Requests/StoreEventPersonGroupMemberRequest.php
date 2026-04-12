<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Models\EventPersonGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPersonGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventId = (int) $this->route('event')->id;
        $group = $this->route('group');

        return [
            'event_person_id' => [
                'required',
                'integer',
                Rule::exists('event_people', 'id')->where('event_id', $eventId),
                Rule::unique('event_person_group_memberships', 'event_person_id')->where(
                    fn ($query) => $query
                        ->where('event_id', $eventId)
                        ->where('event_person_group_id', $group instanceof EventPersonGroup ? $group->id : $group),
                ),
            ],
            'role_label' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', Rule::in(['manual', 'preset_seed'])],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ];
    }
}
