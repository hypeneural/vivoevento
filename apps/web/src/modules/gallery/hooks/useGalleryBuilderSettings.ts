import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';
import { getEventGallerySettings } from '../api';

export function useGalleryBuilderSettings(eventId: string | number | null | undefined) {
  return useQuery({
    queryKey: queryKeys.gallery.settings(eventId ?? 'missing'),
    enabled: !!eventId,
    queryFn: () => getEventGallerySettings(eventId as string | number),
  });
}
