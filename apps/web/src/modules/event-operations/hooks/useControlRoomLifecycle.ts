import { useCallback, useEffect, useMemo, useRef, useState, type RefObject } from 'react';

export type ControlRoomLifecycleMode = 'active' | 'hidden' | 'degraded';
export type ControlRoomWakeLockStatus =
  | 'unsupported'
  | 'idle'
  | 'requesting'
  | 'active'
  | 'released'
  | 'denied';

interface UseControlRoomLifecycleOptions {
  targetRef?: RefObject<HTMLElement | null>;
  wakeLockEnabled?: boolean;
}

type WakeLockCapableNavigator = Navigator & {
  wakeLock?: {
    request: (type: 'screen') => Promise<WakeLockSentinel>;
  };
};

function supportsWakeLock(): boolean {
  return Boolean((navigator as WakeLockCapableNavigator).wakeLock?.request);
}

function getIsVisible(): boolean {
  return document.visibilityState !== 'hidden';
}

export function useControlRoomLifecycle({
  targetRef,
  wakeLockEnabled = true,
}: UseControlRoomLifecycleOptions = {}) {
  const wakeLockRef = useRef<WakeLockSentinel | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(() => Boolean(document.fullscreenElement));
  const [isVisible, setIsVisible] = useState(getIsVisible);
  const [fullscreenError, setFullscreenError] = useState<string | null>(null);
  const [wakeLockStatus, setWakeLockStatus] = useState<ControlRoomWakeLockStatus>(() =>
    wakeLockEnabled && supportsWakeLock() ? 'idle' : 'unsupported',
  );

  const releaseWakeLock = useCallback(async () => {
    const wakeLock = wakeLockRef.current;
    wakeLockRef.current = null;

    if (!wakeLock) {
      return;
    }

    try {
      await wakeLock.release();
    } catch {
      // The browser may release the sentinel before cleanup. The UI only needs a safe status.
    } finally {
      setWakeLockStatus('released');
    }
  }, []);

  const acquireWakeLock = useCallback(async () => {
    if (!wakeLockEnabled) {
      setWakeLockStatus('unsupported');
      return;
    }

    const wakeLock = (navigator as WakeLockCapableNavigator).wakeLock;

    if (!wakeLock?.request) {
      setWakeLockStatus('unsupported');
      return;
    }

    if (!getIsVisible()) {
      setWakeLockStatus('released');
      return;
    }

    setWakeLockStatus('requesting');

    try {
      wakeLockRef.current = await wakeLock.request('screen');
      setWakeLockStatus('active');
    } catch {
      setWakeLockStatus('denied');
    }
  }, [wakeLockEnabled]);

  const requestFullscreen = useCallback(async () => {
    setFullscreenError(null);

    const target = targetRef?.current ?? document.documentElement;

    if (!document.fullscreenEnabled || typeof target.requestFullscreen !== 'function') {
      setFullscreenError('Fullscreen nao esta disponivel neste navegador.');
      return;
    }

    try {
      await target.requestFullscreen();
      setIsFullscreen(Boolean(document.fullscreenElement));
    } catch {
      setFullscreenError('O navegador recusou o fullscreen.');
    }
  }, [targetRef]);

  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(Boolean(document.fullscreenElement));
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);

    return () => {
      document.removeEventListener('fullscreenchange', handleFullscreenChange);
    };
  }, []);

  useEffect(() => {
    void acquireWakeLock();

    const handleVisibilityChange = () => {
      const visible = getIsVisible();
      setIsVisible(visible);

      if (visible) {
        void acquireWakeLock();
      } else {
        void releaseWakeLock();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      void releaseWakeLock();
    };
  }, [acquireWakeLock, releaseWakeLock]);

  const lifecycleMode = useMemo<ControlRoomLifecycleMode>(() => {
    if (!isVisible) {
      return 'hidden';
    }

    if (fullscreenError || wakeLockStatus === 'denied') {
      return 'degraded';
    }

    return 'active';
  }, [fullscreenError, isVisible, wakeLockStatus]);

  return {
    isFullscreen,
    isVisible,
    lifecycleMode,
    fullscreenError,
    wakeLockStatus,
    requestFullscreen,
  };
}
