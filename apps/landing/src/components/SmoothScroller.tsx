import { useEffect, useMemo, useRef, type ReactNode } from "react";
import Lenis from "lenis";
import { SmoothScrollContext } from "@/hooks/useSmoothScroll";

const HEADER_OFFSET = -96;
const MAX_SCROLL_RETRIES = 8;
const RETRY_DELAY_MS = 80;

export function SmoothScroller({ children }: { children: ReactNode }) {
  const lenisRef = useRef<Lenis | null>(null);
  const retryTimeoutRef = useRef<number | null>(null);

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

  useEffect(() => {
    return () => {
      if (retryTimeoutRef.current !== null) {
        window.clearTimeout(retryTimeoutRef.current);
      }
    };
  }, []);

  const api = useMemo(
    () => ({
      scrollToId: (id: string) => {
        const attemptScroll = (attempt = 0) => {
          const target = document.getElementById(id);
          if (!target) {
            if (attempt >= MAX_SCROLL_RETRIES) {
              return;
            }

            retryTimeoutRef.current = window.setTimeout(() => {
              attemptScroll(attempt + 1);
            }, RETRY_DELAY_MS);
            return;
          }

          if (retryTimeoutRef.current !== null) {
            window.clearTimeout(retryTimeoutRef.current);
            retryTimeoutRef.current = null;
          }

          if (lenisRef.current) {
            lenisRef.current.scrollTo(target, {
              offset: HEADER_OFFSET,
              duration: 1.05,
            });
            return;
          }

          const targetTop = target.getBoundingClientRect().top + window.scrollY + HEADER_OFFSET;
          window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: "smooth",
          });
        };

        attemptScroll();
      },
    }),
    []
  );

  return <SmoothScrollContext.Provider value={api}>{children}</SmoothScrollContext.Provider>;
}
