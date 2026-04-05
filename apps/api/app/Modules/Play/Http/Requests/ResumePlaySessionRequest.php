<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResumePlaySessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'resume_token' => $this->input('resume_token', $this->input('resumeToken')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume_token' => ['required', 'string', 'max:120'],
        ];
    }
}
