import MediaSurface, { type MediaSurfaceVideoControlProps } from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { WALL_BADGE } from '../design/tokens';
import { resolvePrimaryMediaFit } from '../engine/layoutStrategy';

export function PolaroidLayout({
  media,
  videoControl,
}: {
  media: WallRuntimeItem;
  videoControl?: MediaSurfaceVideoControlProps | null;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center px-[max(16px,2vw)] py-[max(16px,2vh)]">
      <div className="relative w-full max-w-[min(72vw,1080px)] rotate-[-1deg] rounded-[18px] bg-[#faf7f2] p-5 text-neutral-950 shadow-[0_35px_120px_rgba(0,0,0,0.45)] md:p-7">
        {media.is_featured ? (
          <div className={`absolute left-6 top-6 z-20 ${WALL_BADGE}`}>Destaque</div>
        ) : null}

        <div className="overflow-hidden rounded-[14px] bg-neutral-200 shadow-inner">
          <MediaSurface
            media={media}
            fit={resolvePrimaryMediaFit('polaroid', media)}
            className="aspect-[4/3] bg-[#f2ede6]"
            videoControl={videoControl}
          />
        </div>

        <div className="mt-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            {media.caption ? (
              <p className="max-w-3xl text-[clamp(1.4rem,2.8vw,2.8rem)] font-semibold leading-tight">
                {media.caption}
              </p>
            ) : null}
            {media.sender_name ? (
              <p className="mt-2 text-[clamp(0.75rem,1vw,0.95rem)] uppercase tracking-[0.28em] text-neutral-500">
                {media.sender_name}
              </p>
            ) : (
              <p className="mt-1 text-[clamp(0.88rem,1vw,1.05rem)] uppercase tracking-[0.28em] text-neutral-500">
                Evento Vivo
              </p>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

export default PolaroidLayout;
