import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { WALL_CARD, WALL_READING_GRADIENT } from '../design/tokens';
import { resolvePrimaryMediaFit } from '../engine/layoutStrategy';

export function FullscreenLayout({ media }: { media: WallRuntimeItem }) {
  return (
    <div className="relative flex min-h-screen items-center justify-center px-[max(16px,2vw)] py-[max(16px,2vh)]">
      {/* Background blur echo */}
      <div className="absolute inset-0 overflow-hidden opacity-30 blur-3xl">
        <MediaSurface media={media} fit="cover" imageClassName="scale-110" />
      </div>

      <div className={`relative flex h-[calc(100vh-max(32px,4vh))] w-full items-center justify-center bg-black/45 ${WALL_CARD}`}>
        <div className={`pointer-events-none absolute inset-x-0 bottom-0 h-1/3 ${WALL_READING_GRADIENT}`} />
        <MediaSurface media={media} fit={resolvePrimaryMediaFit('fullscreen', media)} className="p-6" />
      </div>
    </div>
  );
}

export default FullscreenLayout;
