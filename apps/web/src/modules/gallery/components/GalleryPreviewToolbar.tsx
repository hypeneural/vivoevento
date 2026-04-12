import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
  GalleryBuilderOperationalFeedback,
  GalleryRenderMode,
  GalleryBuilderSettings,
  GalleryBuilderViewport,
} from '../gallery-builder';

interface GalleryPreviewToolbarProps {
  viewport: GalleryBuilderViewport;
  onViewportChange: (viewport: GalleryBuilderViewport) => void;
  settings: GalleryBuilderSettings;
  autosaveState: 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
  onSaveNow: () => void;
  onPublish: () => void;
  isSaving: boolean;
  isPublishing: boolean;
  renderMode: GalleryRenderMode;
  operationalFeedback: GalleryBuilderOperationalFeedback;
  publishBlockedReason?: string | null;
}

export function GalleryPreviewToolbar({
  viewport,
  onViewportChange,
  settings,
  autosaveState,
  onSaveNow,
  onPublish,
  isSaving,
  isPublishing,
  renderMode,
  operationalFeedback,
  publishBlockedReason,
}: GalleryPreviewToolbarProps) {
  const lastPublish = operationalFeedback.last_publish;

  return (
    <section
      className="space-y-4 rounded-[28px] border border-border/60 bg-background/90 p-4"
      role="region"
      aria-label="Comandos do preview da galeria"
    >
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            size="sm"
            variant={viewport === 'mobile' ? 'default' : 'outline'}
            aria-pressed={viewport === 'mobile'}
            aria-label="Visualizar preview mobile"
            onClick={() => onViewportChange('mobile')}
          >
            Mobile
          </Button>
          <Button
            type="button"
            size="sm"
            variant={viewport === 'desktop' ? 'default' : 'outline'}
            aria-pressed={viewport === 'desktop'}
            aria-label="Visualizar preview desktop"
            onClick={() => onViewportChange('desktop')}
          >
            Desktop
          </Button>
          <Badge variant="secondary">Draft v{settings.draft_version}</Badge>
          <Badge variant="outline">Publicado v{settings.published_version}</Badge>
          <Badge variant="outline">Autosave {autosaveState}</Badge>
          <Badge variant="outline">{renderMode === 'optimized' ? 'Render otimizado' : 'Render padrao'}</Badge>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" onClick={onSaveNow} disabled={isSaving}>
            {isSaving ? 'Salvando...' : 'Salvar agora'}
          </Button>
          <Button
            type="button"
            onClick={onPublish}
            disabled={isPublishing || !!publishBlockedReason}
            title={publishBlockedReason ?? undefined}
          >
            {isPublishing ? 'Publicando...' : 'Publicar'}
          </Button>
        </div>
      </div>

      <div className="grid gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 text-sm md:grid-cols-2">
        <div>
          <p className="font-medium">Origem atual</p>
          <p className="text-muted-foreground">
            {settings.current_preset_origin?.label
              ? `${settings.current_preset_origin.label}${settings.current_preset_origin.origin_type ? ` · ${settings.current_preset_origin.origin_type}` : ''}`
              : 'Sem origem persistida ainda.'}
          </p>
        </div>
        <div>
          <p className="font-medium">Ultimo publish</p>
          <p className="text-muted-foreground">
            {lastPublish
              ? `v${lastPublish.version_number} · ${lastPublish.occurred_at ? new Date(lastPublish.occurred_at).toLocaleString('pt-BR') : 'sem horario'}`
              : 'Nenhum publish registrado ainda.'}
          </p>
        </div>
      </div>

      {publishBlockedReason ? (
        <p className="text-xs text-amber-700">{publishBlockedReason}</p>
      ) : null}
    </section>
  );
}
