<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Enums\OrganizationType;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Organization::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
