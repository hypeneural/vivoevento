<?php

namespace App\Modules\EventPeople\Http\Requests;

use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Enums\EventPersonReviewQueueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventPeopleReviewQueueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(EventPersonReviewQueueStatus::class)],
            'type' => ['nullable', Rule::enum(EventPersonReviewQueueType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
