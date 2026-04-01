/**
 * ExpiredScreen — Shown when the wall is stopped, expired, or unavailable.
 */

import { Power } from 'lucide-react';

interface ExpiredScreenProps {
  title?: string;
  message?: string;
}

export function ExpiredScreen({ title, message }: ExpiredScreenProps) {
  return (
    <div className="flex min-h-screen items-center justify-center px-[max(16px,2vw)] py-[max(16px,2vh)]">
      <div className="w-full max-w-3xl rounded-[32px] border border-white/10 bg-black/35 p-10 text-center shadow-[0_30px_120px_rgba(0,0,0,0.45)] backdrop-blur-xl">
        <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-white/10 text-white">
          <Power className="h-10 w-10" />
        </div>
        <p className="mt-6 text-sm uppercase tracking-[0.35em] text-white/55">Sessão encerrada</p>
        <h1 className="mt-4 text-[clamp(2rem,4vw,4rem)] font-semibold leading-tight">
          {title || 'Este telão não está mais disponível'}
        </h1>
        <p className="mx-auto mt-4 max-w-2xl text-[clamp(1rem,1.4vw,1.2rem)] leading-relaxed text-white/70">
          {message || 'A cobertura foi encerrada, arquivada ou está temporariamente indisponível.'}
        </p>
      </div>
    </div>
  );
}

export default ExpiredScreen;
