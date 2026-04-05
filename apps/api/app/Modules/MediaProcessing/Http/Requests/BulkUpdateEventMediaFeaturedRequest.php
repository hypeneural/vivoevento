<?php

namespace App\Modules\MediaProcessing\Http\Requests;

class BulkUpdateEventMediaFeaturedRequest extends BulkModerationIdsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_featured' => ['required', 'boolean'],
        ];
    }
}
