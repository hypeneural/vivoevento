import MediaSurface, { type MediaSurfaceVideoControlProps } from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { WALL_TEXT_PRIMARY, WALL_TEXT_SECONDARY, WALL_ACCENT_BAR, WALL_CARD } from '../design/tokens';
import { resolvePrimaryMediaFit } from '../engine/layoutStrategy';

export function SplitLayout({
  media,
  videoControl,
}: {
  media: WallRuntimeItem;
  videoControl?: MediaSurfaceVideoControlProps | null;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center px-[max(16px,2vw)] py-[max(16px,2vh)]">
      <div className={`grid w-full max-w-7xl gap-0 overflow-hidden lg:grid-cols-[1.15fr_0.85fr] ${WALL_CARD}`}>
        <div className="relative aspect-[3/4] lg:aspect-auto">
          <MediaSurface
            media={media}
            fit={resolvePrimaryMediaFit('split', media)}
            videoControl={videoControl}
          />
        </div>

        <div className="flex flex-col justify-center gap-6 p-8 lg:p-12">
          <div className={WALL_ACCENT_BAR} />
          {media.caption ? (
            <p className={WALL_TEXT_PRIMARY}>{media.caption}</p>
          ) : null}
          {media.sender_name ? (
            <p className={WALL_TEXT_SECONDARY}>Foto por {media.sender_name}</p>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default SplitLayout;
