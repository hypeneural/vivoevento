/**
 * usePerformanceMode — Detects low-end hardware and reduced-motion preference.
 *
 * On low-end devices (≤4GB RAM or ≤4 cores), disables heavy effects like
 * backdrop-blur, 3D transforms, and complex shadows. Also respects
 * prefers-reduced-motion media query.
 */

import { useEffect, useMemo, useState } from 'react';

function shouldReduceForHardware(): boolean {
  if (typeof navigator === 'undefined') return false;

  const deviceMemory = 'deviceMemory' in navigator
    ? Number((navigator as Navigator & { deviceMemory?: number }).deviceMemory ?? 0)
    : 0;

  const hardwareConcurrency = 'hardwareConcurrency' in navigator
    ? Number(navigator.hardwareConcurrency ?? 0)
    : 0;

  return (deviceMemory > 0 && deviceMemory <= 4)
    || (hardwareConcurrency > 0 && hardwareConcurrency <= 4);
}

export function usePerformanceMode() {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = () => setPrefersReducedMotion(mediaQuery.matches);

    update();
    mediaQuery.addEventListener?.('change', update);

    return () => {
      mediaQuery.removeEventListener?.('change', update);
    };
  }, []);

  const reducedEffects = useMemo(
    () => prefersReducedMotion || shouldReduceForHardware(),
    [prefersReducedMotion],
  );

  return {
    reducedEffects,
    modeLabel: reducedEffects ? 'Performance' : 'Premium',
  };
}

export default usePerformanceMode;
