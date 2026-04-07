import { lazy, Suspense, useEffect, useMemo, useRef, useState, type ReactNode } from "react";

type LazyRivePanelProps = {
  src: string;
  stateMachines?: string | string[];
  artboard?: string;
  className?: string;
  fallback: ReactNode;
  enabled?: boolean;
};

function looksLikeHtmlSnippet(buffer: ArrayBuffer) {
  const preview = new TextDecoder()
    .decode(buffer.slice(0, 32))
    .trim()
    .toLowerCase();

  return preview.startsWith("<!doctype") || preview.startsWith("<html") || preview.startsWith("<");
}

export default function LazyRivePanel({
  src,
  stateMachines,
  artboard,
  className,
  fallback,
  enabled = true,
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
    if (!enabled) {
      setAssetAvailable(false);
      return;
    }

    if (!shouldLoad || assetAvailable !== null) return;

    let isMounted = true;
    const controller = new AbortController();

    fetch(src, {
      method: "GET",
      signal: controller.signal,
      headers: {
        Range: "bytes=0-31",
      },
    })
      .then(async (response) => {
        const contentType = response.headers.get("content-type") || "";
        const buffer = await response.arrayBuffer();
        const validBinary = response.ok && !contentType.includes("text/html") && !looksLikeHtmlSnippet(buffer);

        if (isMounted) {
          setAssetAvailable(validBinary);
        }
      })
      .catch(() => {
        if (isMounted) {
          setAssetAvailable(false);
        }
      });

    return () => {
      isMounted = false;
      controller.abort();
    };
  }, [assetAvailable, enabled, shouldLoad, src]);

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
