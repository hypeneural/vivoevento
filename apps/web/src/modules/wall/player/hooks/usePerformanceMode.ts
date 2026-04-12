/**
 * usePerformanceMode — Detects low-end hardware and reduced-motion preference.
 *
 * On low-end devices (≤4GB RAM or ≤4 cores), disables heavy effects like
 * backdrop-blur, 3D transforms, and complex shadows. Also respects
 * prefers-reduced-motion media query.
 */

import { useEffect, useMemo, useState } from 'react';
import {
  resolveWallPerformanceTier,
  resolveWallRuntimeBudget,
} from '../runtime-capabilities';

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

  const performanceTier = useMemo(() => {
    const deviceMemory = 'deviceMemory' in navigator
      ? Number((navigator as Navigator & { deviceMemory?: number }).deviceMemory ?? 0)
      : 0;
    const hardwareConcurrency = 'hardwareConcurrency' in navigator
      ? Number(navigator.hardwareConcurrency ?? 0)
      : 0;

    return resolveWallPerformanceTier({
      prefersReducedMotion,
      deviceMemoryGb: deviceMemory > 0 ? deviceMemory : null,
      hardwareConcurrency: hardwareConcurrency > 0 ? hardwareConcurrency : null,
    });
  }, [prefersReducedMotion]);

  const reducedEffects = useMemo(
    () => performanceTier === 'performance',
    [performanceTier],
  );
  const runtimeBudget = useMemo(
    () => resolveWallRuntimeBudget(performanceTier),
    [performanceTier],
  );

  return {
    reducedEffects,
    prefersReducedMotion,
    performanceTier,
    runtimeBudget,
    modeLabel: reducedEffects ? 'Performance' : 'Premium',
  };
}

export default usePerformanceMode;
