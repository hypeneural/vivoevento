<?php

namespace App\Modules\EventOperations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOperationsRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        return (array) $this->resource;
    }
}
