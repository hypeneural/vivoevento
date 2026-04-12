<?php

namespace App\Modules\EventPeople\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPeopleGraphResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'people' => array_values($this['people'] ?? []),
            'relations' => array_values($this['relations'] ?? []),
            'groups' => array_values($this['groups'] ?? []),
            'stats' => $this['stats'] ?? [],
            'filters' => $this['filters'] ?? [],
        ];
    }
}
