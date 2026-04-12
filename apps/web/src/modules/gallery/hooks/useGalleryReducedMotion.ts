import { useEffect, useMemo, useState } from 'react';

export function useGalleryReducedMotion(respectUserPreference = true) {
  const [prefersReducedMotion, setPrefersReducedMotion] = useState(() =>
    typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
      : false,
  );

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') {
      return undefined;
    }

    const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    const update = () => setPrefersReducedMotion(mediaQuery.matches);

    update();
    mediaQuery.addEventListener?.('change', update);

    return () => {
      mediaQuery.removeEventListener?.('change', update);
    };
  }, []);

  const shouldReduceMotion = useMemo(
    () => respectUserPreference && prefersReducedMotion,
    [prefersReducedMotion, respectUserPreference],
  );

  return {
    prefersReducedMotion,
    shouldReduceMotion,
  };
}
