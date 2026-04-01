/**
 * PlayerShell — Outermost container for the wall player.
 *
 * Full-viewport, dark background with optional blurred BG image
 * and ambient radial gradients.
 */

import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { WALL_OVERLAY_GRADIENT } from '../design/tokens';

interface PlayerShellProps {
  backgroundUrl?: string | null;
  children: ReactNode;
  className?: string;
}

export function PlayerShell({ backgroundUrl, children, className }: PlayerShellProps) {
  return (
    <div className={cn('relative min-h-screen overflow-hidden bg-neutral-950 text-white', className)}>
      {backgroundUrl ? (
        <>
          <div
            className="absolute inset-0 bg-cover bg-center opacity-40"
            style={{ backgroundImage: `url(${backgroundUrl})` }}
          />
          <div className="absolute inset-0 bg-neutral-950/70 backdrop-blur-sm" />
        </>
      ) : (
        <>
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.28),_transparent_32%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.22),_transparent_28%),linear-gradient(180deg,_#09090b_0%,_#111827_100%)]" />
          <div className="absolute inset-0 bg-[linear-gradient(135deg,_rgba(255,255,255,0.03)_0%,_transparent_30%,_transparent_70%,_rgba(255,255,255,0.03)_100%)]" />
        </>
      )}

      <div className={cn('absolute inset-0 bg-black/20', WALL_OVERLAY_GRADIENT)} />
      <div className="relative min-h-screen">{children}</div>
    </div>
  );
}

export default PlayerShell;
