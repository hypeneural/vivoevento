<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonSide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPersonGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:160'],
            'group_type' => ['nullable', 'string', 'max:60'],
            'side' => ['nullable', Rule::enum(EventPersonSide::class)],
            'importance_rank' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['nullable', Rule::in(['active', 'archived'])],
        ];
    }
}
