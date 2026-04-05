<?php

namespace App\Modules\MediaProcessing\Http\Requests;

class BulkUpdateEventMediaPinnedRequest extends BulkModerationIdsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_pinned' => ['required', 'boolean'],
        ];
    }
}
