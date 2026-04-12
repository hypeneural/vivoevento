import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';
import { listEventGalleryRevisions } from '../api';

export function useGalleryRevisions(eventId: string | number | null | undefined) {
  return useQuery({
    queryKey: queryKeys.gallery.revisions(eventId ?? 'missing'),
    enabled: !!eventId,
    queryFn: () => listEventGalleryRevisions(eventId as string | number),
  });
}
