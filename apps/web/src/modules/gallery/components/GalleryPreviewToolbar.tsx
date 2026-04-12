import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
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
  publishBlockedReason,
}: GalleryPreviewToolbarProps) {
  return (
    <div className="space-y-4 rounded-[28px] border border-border/60 bg-background/90 p-4">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          <Button
            type="button"
            size="sm"
            variant={viewport === 'mobile' ? 'default' : 'outline'}
            onClick={() => onViewportChange('mobile')}
          >
            Mobile
          </Button>
          <Button
            type="button"
            size="sm"
            variant={viewport === 'desktop' ? 'default' : 'outline'}
            onClick={() => onViewportChange('desktop')}
          >
            Desktop
          </Button>
          <Badge variant="secondary">Draft v{settings.draft_version}</Badge>
          <Badge variant="outline">Publicado v{settings.published_version}</Badge>
          <Badge variant="outline">Autosave {autosaveState}</Badge>
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

      {publishBlockedReason ? (
        <p className="text-xs text-amber-700">{publishBlockedReason}</p>
      ) : null}
    </div>
  );
}
