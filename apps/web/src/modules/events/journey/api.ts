import type { QueryClient } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';

import type { EventJourneyProjection, EventJourneyUpdatePayload } from './types';

function legacyEventDetailQueryKey(eventId: string) {
  return ['event-detail', eventId] as const;
}

function legacyContentModerationSettingsQueryKey(eventId: number | string) {
  return ['event-content-moderation-settings', eventId] as const;
}

function legacyMediaIntelligenceSettingsQueryKey(eventId: number | string) {
  return ['event-media-intelligence-settings', eventId] as const;
}

export function getEventJourneyBuilder(eventId: number | string) {
  return api.get<EventJourneyProjection>(`/events/${eventId}/journey-builder`);
}

export function updateEventJourneyBuilder(
  eventId: number | string,
  payload: EventJourneyUpdatePayload,
) {
  return api.patch<EventJourneyProjection>(`/events/${eventId}/journey-builder`, {
    body: payload,
  });
}

export function eventJourneyBuilderQueryOptions(eventId: number | string) {
  const normalizedEventId = String(eventId);

  return {
    queryKey: queryKeys.events.journeyBuilder(normalizedEventId),
    queryFn: () => getEventJourneyBuilder(normalizedEventId),
  } as const;
}

export async function invalidateEventJourneyBuilderQueries(
  queryClient: QueryClient,
  eventId: number | string,
) {
  const normalizedEventId = String(eventId);

  await Promise.all([
    queryClient.invalidateQueries({ queryKey: queryKeys.events.all() }),
    queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(normalizedEventId) }),
    queryClient.invalidateQueries({ queryKey: legacyEventDetailQueryKey(normalizedEventId) }),
    queryClient.invalidateQueries({ queryKey: queryKeys.events.journeyBuilder(normalizedEventId) }),
    queryClient.invalidateQueries({ queryKey: queryKeys.events.telegramOperationalStatus(normalizedEventId) }),
    queryClient.invalidateQueries({ queryKey: legacyContentModerationSettingsQueryKey(eventId) }),
    queryClient.invalidateQueries({ queryKey: legacyMediaIntelligenceSettingsQueryKey(eventId) }),
  ]);
}

export function eventJourneyBuilderMutationOptions(
  queryClient: QueryClient,
  eventId: number | string,
) {
  return {
    mutationFn: (payload: EventJourneyUpdatePayload) => updateEventJourneyBuilder(eventId, payload),
    onSuccess: async () => {
      await invalidateEventJourneyBuilderQueries(queryClient, eventId);
    },
  } as const;
}
