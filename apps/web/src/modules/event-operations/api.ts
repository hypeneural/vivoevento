import { api } from '@/lib/api';

import type {
  EventOperationsTimelineFilters,
  EventOperationsTimelinePage,
  EventOperationsV0Room,
} from './types';

export async function getEventOperationsBootRoom(eventId: string | number): Promise<EventOperationsV0Room> {
  return api.get<EventOperationsV0Room>(`/events/${String(eventId)}/operations/room`);
}

export async function getEventOperationsBootTimeline(
  eventId: string | number,
  filters: EventOperationsTimelineFilters = {},
): Promise<EventOperationsTimelinePage> {
  return api.get<EventOperationsTimelinePage>(`/events/${String(eventId)}/operations/timeline`, {
    params: filters,
  });
}
