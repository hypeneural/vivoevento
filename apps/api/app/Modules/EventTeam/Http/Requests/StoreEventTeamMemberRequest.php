<?php

namespace App\Modules\EventTeam\Http\Requests;

use App\Modules\EventTeam\Support\EventAccessPresetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registry = app(EventAccessPresetRegistry::class);

        return [
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['nullable', 'string', Rule::in(['manager', 'operator', 'moderator', 'viewer'])],
            'preset_key' => ['nullable', 'string', Rule::in($registry->eventPresetKeys())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('role') && ! $this->filled('preset_key')) {
                $validator->errors()->add('preset_key', 'Informe um perfil de acesso.');
            }
        });
    }
}
