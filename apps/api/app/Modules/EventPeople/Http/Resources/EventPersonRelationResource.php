<?php

namespace App\Modules\EventPeople\Http\Resources;

use App\Modules\EventPeople\Models\EventPerson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPersonRelationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $focusPersonId = (int) ($request->route('person')?->id ?? $request->query('person_id') ?? 0);
        $otherPerson = $this->resolveOtherPerson($focusPersonId);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'person_pair_key' => $this->person_pair_key,
            'relation_type' => $this->relation_type?->value ?? $this->relation_type,
            'directionality' => $this->directionality?->value ?? $this->directionality,
            'source' => $this->source?->value ?? $this->source,
            'confidence' => $this->confidence,
            'strength' => $this->strength,
            'is_primary' => (bool) $this->is_primary,
            'notes' => $this->notes,
            'person_a' => $this->whenLoaded('personA', fn () => $this->personSummary($this->personA)),
            'person_b' => $this->whenLoaded('personB', fn () => $this->personSummary($this->personB)),
            'other_person' => $otherPerson ? $this->personSummary($otherPerson) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveOtherPerson(int $focusPersonId): ?EventPerson
    {
        if ($focusPersonId <= 0) {
            return null;
        }

        if ((int) $this->person_a_id === $focusPersonId) {
            return $this->personB;
        }

        if ((int) $this->person_b_id === $focusPersonId) {
            return $this->personA;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personSummary(?EventPerson $person): ?array
    {
        if (! $person) {
            return null;
        }

        return [
            'id' => $person->id,
            'display_name' => $person->display_name,
            'type' => $person->type?->value ?? $person->type,
            'side' => $person->side?->value ?? $person->side,
            'status' => $person->status?->value ?? $person->status,
        ];
    }
}
