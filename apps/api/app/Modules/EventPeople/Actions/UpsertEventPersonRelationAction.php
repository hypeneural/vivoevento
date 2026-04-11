<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonRelationDirectionality;
use App\Modules\EventPeople\Enums\EventPersonRelationSource;
use App\Modules\EventPeople\Enums\EventPersonRelationType;
use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Models\EventPersonRelation;
use App\Modules\EventPeople\Support\PersonPairKey;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertEventPersonRelationAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Event $event,
        User $user,
        array $payload,
        ?EventPersonRelation $relation = null,
    ): EventPersonRelation {
        return DB::transaction(function () use ($event, $user, $payload, $relation): EventPersonRelation {
            $personA = $this->resolvePerson($event, (int) $payload['person_a_id']);
            $personB = $this->resolvePerson($event, (int) $payload['person_b_id']);

            if ((int) $personA->id === (int) $personB->id) {
                throw ValidationException::withMessages([
                    'person_b_id' => 'A relacao precisa ligar duas pessoas diferentes.',
                ]);
            }

            $relationType = EventPersonRelationType::tryFrom((string) ($payload['relation_type'] ?? $relation?->relation_type?->value ?? $relation?->relation_type));

            if (! $relationType instanceof EventPersonRelationType) {
                throw ValidationException::withMessages([
                    'relation_type' => 'Informe um tipo de relacao valido.',
                ]);
            }

            $directionality = EventPersonRelationDirectionality::tryFrom((string) ($payload['directionality'] ?? $relation?->directionality?->value ?? EventPersonRelationDirectionality::Undirected->value))
                ?? EventPersonRelationDirectionality::Undirected;

            $record = $relation ?? new EventPersonRelation();

            $record->fill([
                'event_id' => $event->id,
                'person_a_id' => $personA->id,
                'person_b_id' => $personB->id,
                'person_pair_key' => PersonPairKey::make($personA->id, $personB->id),
                'relation_type' => $relationType->value,
                'directionality' => $directionality->value,
                'source' => EventPersonRelationSource::Manual->value,
                'confidence' => array_key_exists('confidence', $payload) ? $payload['confidence'] : $record->confidence,
                'strength' => array_key_exists('strength', $payload) ? $payload['strength'] : $record->strength,
                'is_primary' => (bool) ($payload['is_primary'] ?? $record->is_primary ?? false),
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $record->notes,
                'updated_by' => $user->id,
            ]);

            if (! $record->exists) {
                $record->created_by = $user->id;
            }

            $record->save();

            return $record->fresh(['personA', 'personB']);
        });
    }

    private function resolvePerson(Event $event, int $personId): EventPerson
    {
        $person = EventPerson::query()
            ->where('event_id', $event->id)
            ->find($personId);

        if (! $person) {
            throw ValidationException::withMessages([
                'person' => 'A relacao so pode usar pessoas do mesmo evento.',
            ]);
        }

        return $person;
    }
}
