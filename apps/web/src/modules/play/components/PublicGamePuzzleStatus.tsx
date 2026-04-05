import { Sparkles, Target, TrendingUp } from 'lucide-react';

import { Progress } from '@/components/ui/progress';
import type { PuzzleRuntimeProgress } from '@/modules/play/types';

type PublicGamePuzzleStatusProps = {
  progress: PuzzleRuntimeProgress;
};

export function PublicGamePuzzleStatus({ progress }: PublicGamePuzzleStatusProps) {
  const completionPercent = Math.max(0, Math.min(100, Math.round(progress.completionRatio * 100)));

  return (
    <div className="rounded-3xl border border-emerald-500/20 bg-emerald-500/8 p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-white">Progresso do puzzle</p>
          <p className="text-xs text-white/65">
            {progress.placed}/{progress.total} pecas encaixadas
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/75">
            Preview {progress.scorePreview}
          </span>
          <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/75">
            Combo x{progress.combo}
          </span>
        </div>
      </div>

      <div className="mt-3 space-y-2">
        <div className="flex items-center justify-between text-xs text-white/65">
          <span>Montagem</span>
          <span>{completionPercent}%</span>
        </div>
        <Progress value={completionPercent} className="h-2 bg-white/10 [&>*]:bg-emerald-400" />
      </div>

      <div className="mt-4 grid gap-3 sm:grid-cols-3">
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <Target className="h-3.5 w-3.5" />
            Pecas
          </div>
          <p className="mt-2 text-lg font-semibold text-white">{progress.placed}/{progress.total}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <Sparkles className="h-3.5 w-3.5" />
            Combo
          </div>
          <p className="mt-2 text-lg font-semibold text-white">x{progress.combo}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-white/5 p-3">
          <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-white/45">
            <TrendingUp className="h-3.5 w-3.5" />
            Melhor combo
          </div>
          <p className="mt-2 text-lg font-semibold text-white">x{progress.maxCombo}</p>
        </div>
      </div>
    </div>
  );
}
