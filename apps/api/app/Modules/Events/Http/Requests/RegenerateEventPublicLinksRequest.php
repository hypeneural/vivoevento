<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegenerateEventPublicLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.update');
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in(['slug', 'upload_slug', 'wall_code'])],
        ];
    }
}
