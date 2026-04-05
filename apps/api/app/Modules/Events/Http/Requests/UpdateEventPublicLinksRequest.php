<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventPublicLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.update');
    }

    public function rules(): array
    {
        return [
            'slug' => ['sometimes', 'string', 'max:200'],
            'upload_slug' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
