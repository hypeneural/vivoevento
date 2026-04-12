<?php

namespace App\Modules\EventPeople\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventPersonGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
            'group_type' => ['nullable', 'string', 'max:60'],
            'person_id' => ['nullable', 'integer'],
        ];
    }
}
