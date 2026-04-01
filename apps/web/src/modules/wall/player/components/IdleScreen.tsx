/**
 * IdleScreen — Shown when wall is live but has no photos yet.
 */

import { Camera, Sparkles, Wifi } from 'lucide-react';
import { cn } from '@/lib/utils';
import { WALL_PANEL, WALL_SAFE_AREA } from '../design/tokens';

interface IdleScreenProps {
  title?: string | null;
  code: string;
  instructions?: string | null;
}

export function IdleScreen({ title, code, instructions }: IdleScreenProps) {
  return (
    <div className={cn('flex min-h-screen items-center justify-center', WALL_SAFE_AREA)}>
      <div className={cn('grid w-full max-w-6xl gap-8 p-8 lg:grid-cols-[1.05fr_0.95fr] lg:p-12', WALL_PANEL)}>
        {/* Left */}
        <div className="space-y-6">
          <div className="inline-flex items-center gap-2 rounded-full border border-orange-400/30 bg-orange-500/10 px-4 py-2 text-sm uppercase tracking-[0.3em] text-orange-100/80">
            <Sparkles className="h-4 w-4" />
            Telão pronto
          </div>

          <div className="space-y-4">
            <p className="text-sm uppercase tracking-[0.35em] text-white/55">Evento Vivo</p>
            <h1 className="max-w-3xl text-[clamp(2.5rem,5vw,5.5rem)] font-semibold leading-[0.95]">
              {title || 'Aguardando as primeiras fotos do evento'}
            </h1>
            <p className="max-w-2xl text-[clamp(1rem,1.4vw,1.35rem)] leading-relaxed text-white/70">
              {instructions || 'Assim que uma foto for aprovada, ela entra automaticamente no slideshow. O telão atualiza em tempo real.'}
            </p>
          </div>
        </div>

        {/* Right — Info card */}
        <div className="flex items-center justify-center">
          <div className="w-full max-w-lg rounded-[28px] border border-white/10 bg-white/95 p-6 text-neutral-950 shadow-2xl">
            <div className="rounded-[24px] border border-neutral-200 bg-[linear-gradient(135deg,_#fff7ed_0%,_#ffffff_48%,_#eff6ff_100%)] p-8">
              <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-center">
                <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-[24px] bg-neutral-950 text-white shadow-lg">
                  <Camera className="h-9 w-9" />
                </div>

                <div className="space-y-2 text-center sm:text-left">
                  <p className="text-xs uppercase tracking-[0.35em] text-neutral-500">Código do telão</p>
                  <p className="font-mono text-[clamp(1.4rem,3vw,2.2rem)] font-semibold tracking-[0.22em] text-neutral-950">
                    {code}
                  </p>
                  <p className="text-sm leading-relaxed text-neutral-600">
                    O player aguarda novas fotos aprovadas para exibição automática.
                  </p>
                </div>
              </div>

              <div className="mt-6 grid gap-3 sm:grid-cols-2">
                <div className="rounded-2xl bg-neutral-950 px-4 py-4 text-white">
                  <p className="text-xs uppercase tracking-[0.3em] text-white/55">Operação</p>
                  <p className="mt-2 text-base font-semibold">Fila e cache ativos</p>
                </div>
                <div className="rounded-2xl border border-neutral-200 bg-white px-4 py-4">
                  <div className="flex items-center gap-2 text-neutral-700">
                    <Wifi className="h-4 w-4" />
                    <p className="text-xs uppercase tracking-[0.3em]">Tempo real</p>
                  </div>
                  <p className="mt-2 text-sm leading-relaxed text-neutral-600">
                    WebSocket ativo. Fotos entram instantaneamente.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default IdleScreen;
