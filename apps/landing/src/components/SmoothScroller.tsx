import { useEffect, useMemo, useRef, type ReactNode } from "react";
import Lenis from "lenis";
import { SmoothScrollContext } from "@/hooks/useSmoothScroll";

export function SmoothScroller({ children }: { children: ReactNode }) {
  const lenisRef = useRef<Lenis | null>(null);

  useEffect(() => {
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (prefersReducedMotion) {
      return undefined;
    }

    const lenis = new Lenis({
      duration: 1.05,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      orientation: "vertical",
      gestureOrientation: "vertical",
      smoothWheel: true,
      wheelMultiplier: 0.95,
      smoothTouch: false,
      touchMultiplier: 2,
    });

    lenisRef.current = lenis;

    let frameId = 0;

    const raf = (time: number) => {
      lenis.raf(time);
      frameId = requestAnimationFrame(raf);
    };

    frameId = requestAnimationFrame(raf);

    return () => {
      cancelAnimationFrame(frameId);
      lenisRef.current = null;
      lenis.destroy();
    };
  }, []);

  const api = useMemo(
    () => ({
      scrollToId: (id: string) => {
        const target = document.getElementById(id);
        if (!target) return;

        if (lenisRef.current) {
          lenisRef.current.scrollTo(target, {
            offset: -118,
            duration: 1.05,
          });
        } else {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      },
    }),
    []
  );

  return <SmoothScrollContext.Provider value={api}>{children}</SmoothScrollContext.Provider>;
}
