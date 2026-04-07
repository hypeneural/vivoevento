<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class SuspendPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('suspendPartner', $partner);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
