<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventPersonGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:160'],
            'group_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'side' => ['sometimes', 'nullable', Rule::enum(EventPersonSide::class)],
            'importance_rank' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'archived'])],
        ];
    }
}
