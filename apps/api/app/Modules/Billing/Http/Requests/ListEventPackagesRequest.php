<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\EventPackageAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventPackagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_audience' => ['nullable', Rule::enum(EventPackageAudience::class)],
        ];
    }

    public function targetAudience(): ?EventPackageAudience
    {
        $value = $this->validated('target_audience');

        if ($value instanceof EventPackageAudience) {
            return $value;
        }

        return $value ? EventPackageAudience::tryFrom((string) $value) : null;
    }
}
