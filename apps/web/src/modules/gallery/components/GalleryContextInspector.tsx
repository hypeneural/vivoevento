import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  formatGalleryModelMatrix,
  type GalleryBuilderEventSummary,
  type GalleryBuilderSettings,
} from '../gallery-builder';

interface GalleryContextInspectorProps {
  event: GalleryBuilderEventSummary;
  draft: GalleryBuilderSettings;
  autosaveState: 'idle' | 'dirty' | 'saving' | 'saved' | 'error';
}

export function GalleryContextInspector({
  event,
  draft,
  autosaveState,
}: GalleryContextInspectorProps) {
  const enabledBlocks = Object.values(draft.page_schema.blocks).filter((block) => {
    if (!block || typeof block !== 'object') {
      return false;
    }

    return (block as { enabled?: boolean }).enabled !== false;
  }).length;

  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Contexto atual</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-1">
          <p className="text-sm font-medium">{event.title}</p>
          <p className="text-sm text-muted-foreground">{formatGalleryModelMatrix(draft)}</p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Badge variant="secondary">{draft.theme_key}</Badge>
          <Badge variant="outline">{draft.layout_key}</Badge>
          <Badge variant="outline">{draft.media_behavior.video.mode}</Badge>
          <Badge variant="outline">Autosave {autosaveState}</Badge>
        </div>

        <div className="grid gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 text-sm sm:grid-cols-2">
          <div>
            <p className="font-medium">Cobertura de blocos</p>
            <p className="text-muted-foreground">{enabledBlocks}/{draft.page_schema.block_order.length} blocos ativos</p>
          </div>
          <div>
            <p className="font-medium">Guardrails</p>
            <p className="text-muted-foreground">
              Preview obrigatorio antes de publicar
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
