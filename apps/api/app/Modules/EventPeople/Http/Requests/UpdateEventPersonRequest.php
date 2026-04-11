<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:160'],
            'type' => ['sometimes', 'nullable', Rule::enum(EventPersonType::class)],
            'side' => ['sometimes', 'nullable', Rule::enum(EventPersonSide::class)],
            'importance_rank' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'hidden'])],
        ];
    }
}
