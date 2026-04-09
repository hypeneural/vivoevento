/**
 * CinematicLayout — Edge-to-edge immersive layout.
 *
 * Blurred background copy of the image + sharp inner card.
 * Inspired by the VIPSocial cinematic layout with double-card effect.
 */

import MediaSurface, { type MediaSurfaceVideoControlProps } from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { WALL_CARD, WALL_READING_GRADIENT } from '../design/tokens';
import { resolvePrimaryMediaFit } from '../engine/layoutStrategy';

export function CinematicLayout({
  media,
  videoControl,
}: {
  media: WallRuntimeItem;
  videoControl?: MediaSurfaceVideoControlProps | null;
}) {
  return (
    <div className="relative flex min-h-screen items-center justify-center px-[max(16px,2vw)] py-[max(16px,2vh)]">
      {/* Blurred background echo */}
      <div className="absolute inset-0 overflow-hidden">
        <MediaSurface
          media={media}
          fit="cover"
          imageClassName="scale-110 opacity-60 blur-2xl"
          renderVideoPosterOnly={media.type === 'video'}
        />
        <div className="absolute inset-0 bg-[linear-gradient(180deg,_rgba(9,9,11,0.25)_0%,_rgba(9,9,11,0.75)_100%)]" />
      </div>

      {/* Outer card (frosted frame) */}
      <div className="relative flex h-[calc(100vh-max(48px,6vh))] w-full max-w-[80vw] items-center justify-center rounded-[40px] border border-white/15 bg-white/5 p-6 shadow-[0_35px_140px_rgba(0,0,0,0.45)] backdrop-blur-md">
        {/* Inner card (sharp image) */}
        <div className={`relative h-full w-full overflow-hidden bg-black/40 ${WALL_CARD}`}>
          <div className={`pointer-events-none absolute inset-x-0 bottom-0 z-10 h-1/3 ${WALL_READING_GRADIENT}`} />
          <MediaSurface
            media={media}
            fit={resolvePrimaryMediaFit('cinematic', media)}
            videoControl={videoControl}
          />
        </div>
      </div>
    </div>
  );
}

export default CinematicLayout;
