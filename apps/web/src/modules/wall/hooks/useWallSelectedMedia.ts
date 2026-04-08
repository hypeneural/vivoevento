import { useEffect, useMemo, useState } from 'react';

import type { ApiWallInsightsRecentItem } from '@/lib/api-types';

export function useWallSelectedMedia(items: ApiWallInsightsRecentItem[]) {
  const [selectedMediaId, setSelectedMediaId] = useState<string | null>(null);

  useEffect(() => {
    if (selectedMediaId === null) {
      return;
    }

    const stillExists = items.some((item) => item.id === selectedMediaId);

    if (!stillExists) {
      setSelectedMediaId(null);
    }
  }, [items, selectedMediaId]);

  const selectedMedia = useMemo(
    () => items.find((item) => item.id === selectedMediaId) ?? null,
    [items, selectedMediaId],
  );

  return {
    selectedMediaId,
    selectedMedia,
    selectMedia: setSelectedMediaId,
    clearSelection: () => setSelectedMediaId(null),
  };
}
