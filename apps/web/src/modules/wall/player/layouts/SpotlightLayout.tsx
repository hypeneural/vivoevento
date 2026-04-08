/**
 * SpotlightLayout — Glow/holofote effect behind the media.
 *
 * Features:
 * - Radial gradient background (deep navy → black)
 * - Orange-to-magenta glow div behind media (blur 80px)
 * - Media with glow box-shadow and rounded corners
 * - Sender caption panel with glass/blur bottom center
 *
 * Inspired by MomentLoop's Spotlight template.
 */

import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';
import { resolvePrimaryMediaFit } from '../engine/layoutStrategy';

export function SpotlightLayout({ media }: { media: WallRuntimeItem }) {
  return (
    <div
      className="relative flex min-h-screen items-center justify-center"
      style={{
        background: 'radial-gradient(ellipse at center, #1a1a2e 0%, #000 70%)',
      }}
    >
      {/* Radial glow behind media */}
      <div
        className="pointer-events-none absolute"
        style={{
          width: '70vw',
          height: '70vh',
          borderRadius: '50%',
          filter: 'blur(80px)',
          opacity: 0.4,
          background: 'radial-gradient(circle, #ff7b00 0%, #ff0055 50%, transparent 70%)',
        }}
      />

      {/* Media card */}
      <div
        className="relative z-10 flex h-[calc(100vh-max(80px,10vh))] w-[calc(100vw-max(80px,10vw))] max-w-[85vw] items-center justify-center overflow-hidden rounded-xl"
        style={{
          boxShadow:
            '0 0 60px rgba(255,123,0,0.3), 0 20px 60px rgba(0,0,0,0.8)',
        }}
      >
        <MediaSurface
          media={media}
          fit={resolvePrimaryMediaFit('spotlight' as never, media)}
        />
      </div>

      {/* Sender caption panel — bottom center, glass */}
      {media.sender_name ? (
        <div
          className="absolute bottom-[max(24px,3vh)] z-20 flex items-center gap-3 rounded-2xl border border-white/10 px-6 py-3"
          style={{
            background: 'rgba(10, 10, 10, 0.6)',
            backdropFilter: 'blur(10px)',
            WebkitBackdropFilter: 'blur(10px)',
          }}
        >
          <span className="text-[clamp(1.1rem,2.5vw,2.4rem)] font-bold text-white/90" style={{ textShadow: '0 2px 12px rgba(0,0,0,0.4)' }}>
            {media.sender_name}
          </span>
          {media.caption ? (
            <span className="max-w-[40vw] truncate text-[clamp(0.85rem,1.4vw,1.4rem)] text-white/60">
              {media.caption}
            </span>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}

export default SpotlightLayout;
