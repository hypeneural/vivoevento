<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonRelationDirectionality;
use App\Modules\EventPeople\Enums\EventPersonRelationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventPersonRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_a_id' => ['sometimes', 'required', 'integer', 'different:person_b_id'],
            'person_b_id' => ['sometimes', 'required', 'integer'],
            'relation_type' => ['sometimes', 'required', Rule::enum(EventPersonRelationType::class)],
            'directionality' => ['sometimes', 'nullable', Rule::enum(EventPersonRelationDirectionality::class)],
            'confidence' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'strength' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }
}
