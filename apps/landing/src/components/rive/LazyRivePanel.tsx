import { lazy, Suspense, useEffect, useMemo, useRef, useState, type ReactNode } from "react";

type LazyRivePanelProps = {
  src: string;
  stateMachines?: string | string[];
  artboard?: string;
  className?: string;
  fallback: ReactNode;
};

export default function LazyRivePanel({
  src,
  stateMachines,
  artboard,
  className,
  fallback,
}: LazyRivePanelProps) {
  const hostRef = useRef<HTMLDivElement | null>(null);
  const [shouldLoad, setShouldLoad] = useState(false);
  const [assetAvailable, setAssetAvailable] = useState<boolean | null>(null);

  useEffect(() => {
    const node = hostRef.current;
    if (!node) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          setShouldLoad(true);
          observer.disconnect();
        }
      },
      { threshold: 0.2 }
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  useEffect(() => {
    if (!shouldLoad || assetAvailable !== null) return;

    let isMounted = true;

    fetch(src, { method: "HEAD" })
      .then((response) => {
        if (isMounted) {
          setAssetAvailable(response.ok);
        }
      })
      .catch(() => {
        if (isMounted) {
          setAssetAvailable(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [assetAvailable, shouldLoad, src]);

  const Player = useMemo(() => lazy(() => import("./RivePlayer")), []);

  return (
    <div ref={hostRef} className={className}>
      {assetAvailable ? (
        <Suspense fallback={fallback}>
          <Player src={src} stateMachines={stateMachines} artboard={artboard} className={className} />
        </Suspense>
      ) : (
        fallback
      )}
    </div>
  );
}
