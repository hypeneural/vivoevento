import { useQuery } from '@tanstack/react-query';

import { queryKeys } from '@/lib/query-client';
import { listGalleryPresets } from '../api';

export function useGalleryPresets() {
  return useQuery({
    queryKey: queryKeys.gallery.presets(),
    queryFn: () => listGalleryPresets(),
  });
}
