import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

import { useAuth } from '@/app/providers/AuthProvider';
import { useIsMobile } from '@/hooks/use-mobile';

import { preloadCommonAdminRoutes } from './route-preload';

const WARMUP_SESSION_KEY = 'eventovivo.adminWarmup.done';

type NavigatorConnection = {
  saveData?: boolean;
  effectiveType?: string;
};

type IdleWindow = Window & {
  requestIdleCallback?: (callback: IdleRequestCallback, options?: IdleRequestOptions) => number;
  cancelIdleCallback?: (handle: number) => void;
};

export function AdminWarmup() {
  const { isAuthenticated } = useAuth();
  const location = useLocation();
  const isMobile = useIsMobile();

  useEffect(() => {
    if (!isAuthenticated || isMobile || location.pathname !== '/') {
      return;
    }

    if (window.sessionStorage.getItem(WARMUP_SESSION_KEY) === '1') {
      return;
    }

    const connection = (navigator as Navigator & { connection?: NavigatorConnection }).connection;
    const slowConnection = connection?.saveData || ['slow-2g', '2g', '3g'].includes(connection?.effectiveType ?? '');
    const lowMemory = typeof (navigator as Navigator & { deviceMemory?: number }).deviceMemory === 'number'
      && ((navigator as Navigator & { deviceMemory?: number }).deviceMemory ?? 0) <= 4;

    if (slowConnection || lowMemory) {
      return;
    }

    const idleWindow = window as IdleWindow;
    let cancelled = false;
    let timeoutId: number | null = null;

    const runWarmup = () => {
      if (cancelled) {
        return;
      }

      window.sessionStorage.setItem(WARMUP_SESSION_KEY, '1');
      void preloadCommonAdminRoutes();
    };

    if (typeof idleWindow.requestIdleCallback === 'function') {
      const idleId = idleWindow.requestIdleCallback(runWarmup, { timeout: 1500 });

      return () => {
        cancelled = true;
        if (typeof idleWindow.cancelIdleCallback === 'function') {
          idleWindow.cancelIdleCallback(idleId);
        }
      };
    }

    timeoutId = window.setTimeout(runWarmup, 1200);

    return () => {
      cancelled = true;
      if (timeoutId !== null) {
        window.clearTimeout(timeoutId);
      }
    };
  }, [isAuthenticated, isMobile, location.pathname]);

  return null;
}
