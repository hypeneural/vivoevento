import { Gauge, Layers3, ShieldAlert, Sparkles } from 'lucide-react';

import { Progress } from '@/components/ui/progress';
import type { MemoryRuntimeProgress } from '@/modules/play/types';

type PublicGameMemoryStatusProps = {
  progress: MemoryRuntimeProgress;
};

export function PublicGameMemoryStatus({ progress }: PublicGameMemoryStatusProps) {
  const completionPercent = Math.max(0, Math.min(100, Math.round(progress.completionRatio * 100)));
  const accuracyPercent = Math.max(0, Math.min(100, Math.round(progress.accuracy * 100)));

  return (
    <div className="rounded-3xl border border-sky-500/20 bg-sky-500/8 p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-white">Progresso do memory</p>
          <p className="text-xs text-white/65">
            {progress.matchedPairs}/{progress.totalPairs} pares encontrados
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/75">
            Preview {progress.scorePreview}
          </span>
          <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/75">
            Acerto {accuracyPercent}%
          </span>
        </div>
      </div>

      <div className="mt-3 space-y-2">
        <div className="flex items-center justify-between text-xs text-white/65">
          <span>Pares montados</span>
          <span>{completionPercent}%</span>
        </div>
        <Progress value={completionPercent} className="h-2 bg-white/10 [&>*]:bg-sky-400" />
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-4">
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <Layers3 className="h-3.5 w-3.5" />
            Pares
          </div>
          <p className="mt-2 text-lg font-semibold text-white">{progress.matchedPairs}/{progress.totalPairs}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <Gauge className="h-3.5 w-3.5" />
            Moves
          </div>
          <p className="mt-2 text-lg font-semibold text-white">{progress.moves}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <ShieldAlert className="h-3.5 w-3.5" />
            Erros
          </div>
          <p className="mt-2 text-lg font-semibold text-white">{progress.mistakes}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <Sparkles className="h-3.5 w-3.5" />
            Acerto
          </div>
          <p className="mt-2 text-lg font-semibold text-white">{accuracyPercent}%</p>
        </div>
      </div>
    </div>
  );
}
