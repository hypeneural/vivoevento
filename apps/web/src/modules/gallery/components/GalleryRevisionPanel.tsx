import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type {
  GalleryBuilderRevision,
  GalleryBuilderSettings,
} from '../gallery-builder';

interface GalleryRevisionPanelProps {
  revisions: GalleryBuilderRevision[];
  settings: GalleryBuilderSettings;
  previewUrl?: string | null;
  previewExpiresAt?: string | null;
  onRestore: (revisionId: number) => void;
  onGeneratePreviewLink: () => void;
  isRestoringId?: number | null;
  isGeneratingPreview: boolean;
}

export function GalleryRevisionPanel({
  revisions,
  settings,
  previewUrl,
  previewExpiresAt,
  onRestore,
  onGeneratePreviewLink,
  isRestoringId,
  isGeneratingPreview,
}: GalleryRevisionPanelProps) {
  return (
    <Card className="rounded-[28px] border-border/60">
      <CardHeader>
        <CardTitle>Revisoes e preview</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">Draft v{settings.draft_version}</Badge>
          <Badge variant="outline">Publicado v{settings.published_version}</Badge>
        </div>

        <div className="space-y-2 rounded-2xl border border-border/60 bg-muted/40 p-4">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium">Preview compartilhavel</p>
              <p className="text-xs text-muted-foreground">
                O link sempre nasce sobre uma revisao draft real.
              </p>
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={onGeneratePreviewLink}
              disabled={isGeneratingPreview}
            >
              {isGeneratingPreview ? 'Gerando...' : 'Gerar preview compartilhavel'}
            </Button>
          </div>

          {previewUrl ? (
            <div className="space-y-2">
              <Input readOnly value={previewUrl} />
              {previewExpiresAt ? (
                <p className="text-xs text-muted-foreground">Expira em {new Date(previewExpiresAt).toLocaleString('pt-BR')}</p>
              ) : null}
            </div>
          ) : null}
        </div>

        <div className="space-y-3">
          {revisions.map((revision) => (
            <div
              key={revision.id}
              className="space-y-3 rounded-2xl border border-border/60 bg-background/80 p-4"
            >
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">Versao {revision.version_number}</p>
                <Badge variant="outline">{revision.kind}</Badge>
              </div>
              <p className="text-sm text-muted-foreground">
                {revision.change_summary?.reason || 'Sem resumo detalhado.'}
              </p>
              <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>{revision.creator?.name || 'Sistema'}</span>
                <span>{revision.created_at ? new Date(revision.created_at).toLocaleString('pt-BR') : 'Sem data'}</span>
              </div>
              <Button
                type="button"
                variant="outline"
                onClick={() => onRestore(revision.id)}
                disabled={isRestoringId === revision.id}
              >
                {isRestoringId === revision.id ? 'Restaurando...' : `Restaurar v${revision.version_number}`}
              </Button>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
