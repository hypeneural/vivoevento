<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonRelationDirectionality;
use App\Modules\EventPeople\Enums\EventPersonRelationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPersonRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'person_a_id' => ['required', 'integer', 'different:person_b_id'],
            'person_b_id' => ['required', 'integer'],
            'relation_type' => ['required', Rule::enum(EventPersonRelationType::class)],
            'directionality' => ['nullable', Rule::enum(EventPersonRelationDirectionality::class)],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'strength' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
