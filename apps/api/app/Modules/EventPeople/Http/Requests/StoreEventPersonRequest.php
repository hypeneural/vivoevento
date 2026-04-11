<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonSide;
use App\Modules\EventPeople\Enums\EventPersonType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:160'],
            'type' => ['nullable', Rule::enum(EventPersonType::class)],
            'side' => ['nullable', Rule::enum(EventPersonSide::class)],
            'importance_rank' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'hidden'])],
        ];
    }
}
