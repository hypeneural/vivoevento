/**
 * GalleryLayout — Art gallery museum frame effect.
 *
 * Features:
 * - Dark background with subtle wall texture
 * - White/cream mat (passepartout) with triple shadow
 * - Inner frame with #d4d0c8 border
 * - Serif typography (Georgia italic) — museum label style
 * - Unique light palette layout (all other layouts are dark)
 *
 * Inspired by MomentLoop's Gallery template.
 */

import MediaSurface from '../components/MediaSurface';
import type { WallRuntimeItem } from '../types';

export function GalleryLayout({ media }: { media: WallRuntimeItem }) {
  return (
    <div
      className="relative flex min-h-screen items-center justify-center"
      style={{
        background: '#1a1a1a',
        // Subtle wall texture
        backgroundImage:
          'repeating-linear-gradient(90deg, transparent, transparent 50px, rgba(255,255,255,0.015) 50px, rgba(255,255,255,0.015) 51px)',
      }}
    >
      {/* Mat (passepartout) — cream/white frame */}
      <div
        className="relative flex items-center justify-center"
        style={{
          background: '#f5f5f0',
          padding: 'clamp(24px, 4vw, 60px)',
          boxShadow:
            '0 8px 40px rgba(0,0,0,0.35), 0 2px 8px rgba(0,0,0,0.2), inset 0 1px 0 rgba(255,255,255,0.3)',
          maxWidth: '85vw',
          maxHeight: '85vh',
        }}
      >
        {/* Inner frame */}
        <div
          className="relative overflow-hidden"
          style={{
            border: '2px solid #d4d0c8',
            boxShadow: 'inset 0 2px 8px rgba(0,0,0,0.12)',
            background: '#e8e4dc',
            width: 'clamp(300px, 65vw, 1200px)',
            height: 'clamp(200px, 55vh, 800px)',
          }}
        >
          <MediaSurface media={media} fit="contain" />
        </div>

        {/* Museum label — bottom of mat */}
        <div
          className="absolute bottom-0 left-0 right-0 flex items-center justify-between"
          style={{
            padding: 'clamp(8px, 1.5vw, 20px) clamp(24px, 4vw, 60px)',
          }}
        >
          <div className="flex flex-col gap-0.5">
            {media.caption ? (
              <span
                className="max-w-[50vw] truncate"
                style={{
                  fontFamily: 'Georgia, "Times New Roman", serif',
                  fontStyle: 'italic',
                  fontSize: 'clamp(0.85rem, 1.4vw, 1.2rem)',
                  color: '#4a4a4a',
                  lineHeight: 1.3,
                }}
              >
                {media.caption}
              </span>
            ) : null}
            {media.sender_name ? (
              <span
                style={{
                  fontSize: 'clamp(0.6rem, 0.9vw, 0.8rem)',
                  textTransform: 'uppercase',
                  letterSpacing: '0.15em',
                  color: '#888',
                  fontFamily: 'Georgia, "Times New Roman", serif',
                }}
              >
                {media.sender_name}
              </span>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}

export default GalleryLayout;
