<?php

namespace App\Modules\EventTeam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendEventTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('send_via_whatsapp')) {
            $this->merge([
                'send_via_whatsapp' => true,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'send_via_whatsapp' => ['required', 'boolean'],
        ];
    }
}
