import type { ApiWallInsightsResponse } from '@/lib/api-types';

import { Skeleton } from '@/components/ui/skeleton';

import { WallLiveMediaTimelineStrip } from './WallLiveMediaTimelineStrip';
import { WallTopContributorCard } from './WallTopContributorCard';
import { WallTotalMediaCard } from './WallTotalMediaCard';

export function WallTopInsightsRail({
  insights,
  isLoading,
  selectedMediaId,
  onSelectMedia,
}: {
  insights?: ApiWallInsightsResponse | null;
  isLoading?: boolean;
  selectedMediaId?: string | null;
  onSelectMedia?: (mediaId: string) => void;
}) {
  if (isLoading && !insights) {
    return (
      <div className="grid gap-4 xl:grid-cols-[280px_280px_minmax(0,1fr)]">
        <Skeleton className="h-[188px] rounded-3xl" />
        <Skeleton className="h-[188px] rounded-3xl" />
        <Skeleton className="h-[188px] rounded-3xl" />
      </div>
    );
  }

  const payload = insights ?? {
    topContributor: null,
    totals: {
      received: 0,
      approved: 0,
      queued: 0,
      displayed: null,
    },
    recentItems: [],
    sourceMix: [],
    lastCaptureAt: null,
  };

  return (
    <div className="grid gap-4 xl:grid-cols-[280px_280px_minmax(0,1fr)]">
      <WallTopContributorCard contributor={payload.topContributor} />
      <WallTotalMediaCard totals={payload.totals} lastCaptureAt={payload.lastCaptureAt} />
      <WallLiveMediaTimelineStrip
        items={payload.recentItems}
        selectedMediaId={selectedMediaId}
        onSelectItem={(item) => onSelectMedia?.(item.id)}
      />
    </div>
  );
}
