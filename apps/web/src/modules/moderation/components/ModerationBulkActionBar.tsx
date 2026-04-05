import { Check, FilterX, Pin, Star, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

import type { ModerationMediaAction } from './ModerationMediaCard';

interface ModerationBulkActionBarProps {
  selectedCount: number;
  canModerate: boolean;
  isBusy: boolean;
  favoriteLabel: string;
  pinLabel: string;
  onAction: (action: ModerationMediaAction) => void;
  onClear: () => void;
}

export function ModerationBulkActionBar({
  selectedCount,
  canModerate,
  isBusy,
  favoriteLabel,
  pinLabel,
  onAction,
  onClear,
}: ModerationBulkActionBarProps) {
  if (selectedCount === 0) {
    return null;
  }

  return (
    <div className="sticky bottom-4 z-40">
      <div className="mx-auto flex max-w-5xl flex-col gap-3 rounded-[26px] border border-border/60 bg-background/95 p-4 shadow-2xl shadow-black/10 backdrop-blur lg:flex-row lg:items-center lg:justify-between">
        <div>
          <p className="text-sm font-semibold">{selectedCount} midias selecionadas</p>
          <p className="text-sm text-muted-foreground">
            A fila continua no lugar enquanto as acoes em lote rodam no fundo. Use Esc para limpar a selecao.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            className={cn('rounded-full', !canModerate && 'pointer-events-none opacity-50')}
            disabled={!canModerate || isBusy}
            onClick={() => onAction('approve')}
          >
            <Check className="h-4 w-4" />
            Aprovar
          </Button>
          <Button
            type="button"
            variant="destructive"
            className="rounded-full"
            disabled={!canModerate || isBusy}
            onClick={() => onAction('reject')}
          >
            <X className="h-4 w-4" />
            Reprovar
          </Button>
          <Button
            type="button"
            variant="outline"
            className="rounded-full"
            disabled={!canModerate || isBusy}
            onClick={() => onAction('favorite')}
          >
            <Star className="h-4 w-4" />
            {favoriteLabel}
          </Button>
          <Button
            type="button"
            variant="outline"
            className="rounded-full"
            disabled={!canModerate || isBusy}
            onClick={() => onAction('pin')}
          >
            <Pin className="h-4 w-4" />
            {pinLabel}
          </Button>
          <Button type="button" variant="ghost" className="rounded-full" disabled={isBusy} onClick={onClear}>
            <FilterX className="h-4 w-4" />
            Limpar
          </Button>
        </div>
      </div>
    </div>
  );
}
