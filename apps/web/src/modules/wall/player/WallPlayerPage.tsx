/**
 * WallPlayerPage — Public fullscreen page for the wall player.
 *
 * Route: /wall/player/:code
 *
 * This page:
 * 1. Extracts the wall_code from the URL
 * 2. Requests fullscreen mode on load
 * 3. Hides the cursor after inactivity
 * 4. Renders WallPlayerRoot
 */

import { useParams } from 'react-router-dom';
import { useEffect, useRef, useState, useCallback } from 'react';
import WallPlayerRoot from './components/WallPlayerRoot';

const CURSOR_HIDE_DELAY = 3000;

export function WallPlayerPage() {
  const { code } = useParams<{ code: string }>();
  const containerRef = useRef<HTMLDivElement>(null);
  const [cursorHidden, setCursorHidden] = useState(false);
  const hideTimerRef = useRef<number | null>(null);

  // Request fullscreen on first click
  const requestFullscreen = useCallback(() => {
    const el = containerRef.current;
    if (!el || document.fullscreenElement) return;

    el.requestFullscreen?.().catch(() => {
      // Fullscreen not available — ignore (e.g. iframe)
    });
  }, []);

  // Auto-hide cursor
  useEffect(() => {
    const handleMouseMove = () => {
      setCursorHidden(false);
      if (hideTimerRef.current) window.clearTimeout(hideTimerRef.current);
      hideTimerRef.current = window.setTimeout(() => setCursorHidden(true), CURSOR_HIDE_DELAY);
    };

    window.addEventListener('mousemove', handleMouseMove);
    hideTimerRef.current = window.setTimeout(() => setCursorHidden(true), CURSOR_HIDE_DELAY);

    return () => {
      window.removeEventListener('mousemove', handleMouseMove);
      if (hideTimerRef.current) window.clearTimeout(hideTimerRef.current);
    };
  }, []);

  // Prevent screen sleep (Wake Lock API)
  useEffect(() => {
    let wakeLock: WakeLockSentinel | null = null;

    const acquireWakeLock = async () => {
      try {
        if ('wakeLock' in navigator) {
          wakeLock = await navigator.wakeLock.request('screen');
        }
      } catch {
        // Wake Lock not available — screen may sleep
      }
    };

    void acquireWakeLock();

    // Re-acquire on visibility change (tab switch)
    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void acquireWakeLock();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      wakeLock?.release().catch(() => {});
    };
  }, []);

  if (!code) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-neutral-950 text-white">
        <p className="text-lg text-white/70">Código do telão não informado.</p>
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      onClick={requestFullscreen}
      className={`min-h-screen bg-neutral-950 ${cursorHidden ? 'cursor-none' : ''}`}
    >
      <WallPlayerRoot code={code} />
    </div>
  );
}

export default WallPlayerPage;
