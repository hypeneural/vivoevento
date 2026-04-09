import { useEffect, useState } from 'react';

interface WallAdvanceClockProps {
  advancedAt?: string | null;
  intervalMs: number;
  isLive: boolean;
  isPaused: boolean;
}

export function WallAdvanceClock({
  advancedAt,
  intervalMs,
  isLive,
  isPaused,
}: WallAdvanceClockProps) {
  const advancedAtMs = advancedAt ? new Date(advancedAt).getTime() : Number.NaN;
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    if (!Number.isFinite(advancedAtMs) || isPaused || !isLive) {
      return undefined;
    }

    const timer = window.setInterval(() => {
      setNow(Date.now());
    }, 1000);

    return () => window.clearInterval(timer);
  }, [advancedAtMs, isLive, isPaused]);

  if (!Number.isFinite(advancedAtMs)) {
    return null;
  }

  const remainingMs = Math.max(0, intervalMs - (now - advancedAtMs));
  const remainingSeconds = Math.ceil(remainingMs / 1000);
  const label = isPaused
    ? 'Troca congelada na pausa'
    : isLive
      ? remainingSeconds > 0
        ? `Troca prevista em ${remainingSeconds}s`
        : 'Trocando agora'
      : 'Troca aguardando inicio';

  const className = isPaused
    ? 'border-amber-400/25 bg-amber-500/15 text-amber-100'
    : isLive
      ? 'border-sky-400/25 bg-sky-500/15 text-sky-100'
      : 'border-white/15 bg-white/10 text-white/80';

  return (
    <span className={`inline-flex rounded-full border px-2 py-1 font-medium ${className}`}>
      {label}
    </span>
  );
}
