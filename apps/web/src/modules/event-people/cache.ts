import type { QueryClient, QueryKey } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';

import type { EventPerson, EventPersonRelation, PaginatedApiResponse } from './types';

interface EventPeopleCacheSnapshot {
  peopleLists: Array<[QueryKey, PaginatedApiResponse<EventPerson> | undefined]>;
  personDetailKey: QueryKey | null;
  personDetail: EventPerson | undefined;
}

function clonePerson(person: EventPerson): EventPerson {
  return {
    ...person,
    avatar: person.avatar ? { ...person.avatar } : person.avatar,
    primary_photo: person.primary_photo ? { ...person.primary_photo } : person.primary_photo,
    stats: person.stats ? person.stats.map((stat) => ({ ...stat })) : person.stats,
    reference_photos: person.reference_photos
      ? person.reference_photos.map((photo) => ({
        ...photo,
        face: photo.face ? { ...photo.face } : photo.face,
        upload_media: photo.upload_media ? { ...photo.upload_media } : photo.upload_media,
      }))
      : person.reference_photos,
    representative_faces: person.representative_faces
      ? person.representative_faces.map((face) => ({
        ...face,
        face: face.face ? { ...face.face } : face.face,
      }))
      : person.representative_faces,
    relations: person.relations
      ? person.relations.map((relation) => ({
        ...relation,
        person_a: relation.person_a ? { ...relation.person_a } : relation.person_a,
        person_b: relation.person_b ? { ...relation.person_b } : relation.person_b,
        other_person: relation.other_person ? { ...relation.other_person } : relation.other_person,
      }))
      : person.relations,
  };
}

export function snapshotEventPeopleCache(
  queryClient: QueryClient,
  eventId: number | string,
  selectedPersonId?: number | null,
): EventPeopleCacheSnapshot {
  const peopleLists = queryClient.getQueriesData<PaginatedApiResponse<EventPerson> | undefined>({
    queryKey: queryKeys.eventPeople.peopleLists(eventId),
  });
  const personDetailKey = selectedPersonId === null || selectedPersonId === undefined
    ? null
    : queryKeys.eventPeople.personDetail(eventId, selectedPersonId);

  return {
    peopleLists,
    personDetailKey,
    personDetail: personDetailKey ? queryClient.getQueryData<EventPerson>(personDetailKey) : undefined,
  };
}

export function restoreEventPeopleCache(queryClient: QueryClient, snapshot: EventPeopleCacheSnapshot): void {
  snapshot.peopleLists.forEach(([key, value]) => {
    queryClient.setQueryData(key, value);
  });

  if (snapshot.personDetailKey) {
    queryClient.setQueryData(snapshot.personDetailKey, snapshot.personDetail);
  }
}

export function upsertPersonInEventPeopleLists(
  queryClient: QueryClient,
  eventId: number | string,
  person: EventPerson,
): void {
  queryClient.setQueriesData<PaginatedApiResponse<EventPerson> | undefined>(
    { queryKey: queryKeys.eventPeople.peopleLists(eventId) },
    (current) => {
      if (!current) return current;

      const cloned = clonePerson(person);
      const existingIndex = current.data.findIndex((row) => row.id === person.id);
      const data = [...current.data];

      if (existingIndex >= 0) {
        data[existingIndex] = {
          ...data[existingIndex],
          ...cloned,
        };
      } else {
        data.unshift(cloned);
      }

      return {
        ...current,
        data,
      };
    },
  );
}

export function replaceEventPeopleDetail(
  queryClient: QueryClient,
  eventId: number | string,
  person: EventPerson,
): void {
  queryClient.setQueryData(queryKeys.eventPeople.personDetail(eventId, person.id), clonePerson(person));
}

export function patchEventPeopleDetail(
  queryClient: QueryClient,
  eventId: number | string,
  personId: number,
  patch: (person: EventPerson) => EventPerson,
): void {
  queryClient.setQueryData<EventPerson | undefined>(
    queryKeys.eventPeople.personDetail(eventId, personId),
    (current) => (current ? patch(clonePerson(current)) : current),
  );
}

export function appendOptimisticRelation(
  queryClient: QueryClient,
  eventId: number | string,
  personId: number,
  relation: EventPersonRelation,
): void {
  patchEventPeopleDetail(queryClient, eventId, personId, (person) => ({
    ...person,
    relations: [...(person.relations ?? []), relation],
  }));
}

export function removeOptimisticRelation(
  queryClient: QueryClient,
  eventId: number | string,
  personId: number,
  relationId: number,
): void {
  patchEventPeopleDetail(queryClient, eventId, personId, (person) => ({
    ...person,
    relations: (person.relations ?? []).filter((relation) => relation.id !== relationId),
  }));
}
