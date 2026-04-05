<?php

namespace App\Modules\Hub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListHubPresetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hub.view') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
