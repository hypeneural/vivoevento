/**
 * FeaturedBadge — "★ Destaque" badge for featured media.
 *
 * Renders in the top-left corner of any layout when media.is_featured is true.
 * Uses WALL_BADGE design token with a subtle shimmer animation.
 * Respects reduced-motion.
 */

import { WALL_BADGE } from '../design/tokens';

interface FeaturedBadgeProps {
  isFeatured: boolean;
  reducedMotion?: boolean;
}

export function FeaturedBadge({ isFeatured, reducedMotion = false }: FeaturedBadgeProps) {
  if (!isFeatured) return null;

  return (
    <div className="absolute left-[max(16px,2vw)] top-[max(16px,2vh)] z-20">
      <span
        className={`${WALL_BADGE} inline-flex items-center gap-1.5`}
        style={
          reducedMotion
            ? undefined
            : {
                animation: 'featured-shimmer 3s ease-in-out infinite',
              }
        }
      >
        ★ Destaque
      </span>

      {!reducedMotion ? (
        <style>{`
          @keyframes featured-shimmer {
            0%, 100% { box-shadow: 0 0 12px rgba(249,115,22,0.25); }
            50% { box-shadow: 0 0 24px rgba(249,115,22,0.5), 0 0 8px rgba(249,115,22,0.3); }
          }
        `}</style>
      ) : null}
    </div>
  );
}

export default FeaturedBadge;
