<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmEventPeopleReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_id' => ['nullable', 'integer'],
            'person' => ['nullable', 'array', 'required_without:person_id'],
            'person.display_name' => ['required_without:person_id', 'string', 'max:120'],
            'person.type' => ['nullable', Rule::enum(EventPersonType::class)],
            'person.side' => ['nullable', Rule::enum(EventPersonSide::class)],
            'person.notes' => ['nullable', 'string'],
            'person.importance_rank' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
