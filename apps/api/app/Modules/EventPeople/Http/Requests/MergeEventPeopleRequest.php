<?php

namespace App\Modules\EventPeople\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeEventPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_person_id' => ['required', 'integer'],
            'target_person_id' => ['required', 'integer', 'different:source_person_id'],
        ];
    }
}
