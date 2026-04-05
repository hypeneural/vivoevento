<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCurrentUserAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Selecione uma imagem.',
            'avatar.image' => 'O arquivo precisa ser uma imagem.',
            'avatar.max' => 'A imagem nao pode ter mais de 5MB.',
            'avatar.mimes' => 'Formato aceito: JPG, PNG ou WebP.',
        ];
    }
}
