<?php

namespace App\Modules\Organizations\Http\Requests;

use App\Modules\Organizations\Support\CurrentOrganizationAccess;
use Illuminate\Foundation\Http\FormRequest;

class UploadCurrentOrganizationLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CurrentOrganizationAccess::canManageBranding($this->user());
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'Selecione uma imagem para o logo.',
            'logo.image' => 'O arquivo precisa ser uma imagem.',
            'logo.max' => 'O logo nao pode ter mais de 5MB.',
            'logo.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ];
    }
}
