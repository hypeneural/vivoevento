import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { GalleryBuilderMode } from '../gallery-builder';

interface GalleryModeSwitchProps {
  value: GalleryBuilderMode;
  onChange: (mode: GalleryBuilderMode) => void;
  draftVersion: number;
  publishedVersion: number;
  autosaveState: 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
}

const AUTOSAVE_LABELS = {
  idle: 'Pronto',
  dirty: 'Alteracoes pendentes',
  saving: 'Autosave em andamento',
  saved: 'Autosave ok',
  error: 'Falha no autosave',
} as const;

export function GalleryModeSwitch({
  value,
  onChange,
  draftVersion,
  publishedVersion,
  autosaveState,
}: GalleryModeSwitchProps) {
  return (
    <div className="flex flex-col gap-3 rounded-[28px] border border-border/60 bg-background/80 p-4">
      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant={value === 'quick' ? 'default' : 'outline'}
          aria-pressed={value === 'quick'}
          onClick={() => onChange('quick')}
        >
          Modo rapido
        </Button>
        <Button
          type="button"
          variant={value === 'professional' ? 'default' : 'outline'}
          aria-pressed={value === 'professional'}
          onClick={() => onChange('professional')}
        >
          Modo profissional
        </Button>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Badge variant="secondary">Draft v{draftVersion}</Badge>
        <Badge variant="outline">Publicado v{publishedVersion}</Badge>
        <Badge
          variant="outline"
          className={cn(
            autosaveState === 'saved' && 'border-emerald-300 text-emerald-700',
            autosaveState === 'saving' && 'border-sky-300 text-sky-700',
            autosaveState === 'dirty' && 'border-amber-300 text-amber-700',
            autosaveState === 'error' && 'border-rose-300 text-rose-700',
          )}
        >
          {AUTOSAVE_LABELS[autosaveState]}
        </Badge>
      </div>
    </div>
  );
}
