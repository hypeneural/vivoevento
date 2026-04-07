<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class ListPartnerActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('viewPartner', $partner);
    }

    public function rules(): array
    {
        return [
            'activity_event' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:180'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
