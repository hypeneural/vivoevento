<?php

namespace App\Modules\EventPeople\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListEventPersonReferencePhotoCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:48'],
        ];
    }
}
