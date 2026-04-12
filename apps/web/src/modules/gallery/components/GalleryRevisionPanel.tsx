import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type {
  GalleryBuilderOperationalFeedback,
  GalleryBuilderRevision,
  GalleryBuilderSettings,
} from '../gallery-builder';

interface GalleryRevisionPanelProps {
  revisions: GalleryBuilderRevision[];
  settings: GalleryBuilderSettings;
  operationalFeedback: GalleryBuilderOperationalFeedback;
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
  operationalFeedback,
  previewUrl,
  previewExpiresAt,
  onRestore,
  onGeneratePreviewLink,
  isRestoringId,
  isGeneratingPreview,
}: GalleryRevisionPanelProps) {
  const lastRestore = operationalFeedback.last_restore;
  const lastAi = operationalFeedback.last_ai_application;

  return (
    <Card className="rounded-[28px] border-border/60" role="region" aria-label="Historico de revisoes e preview compartilhavel">
      <CardHeader>
        <CardTitle>Revisoes e preview</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="secondary">Draft v{settings.draft_version}</Badge>
          <Badge variant="outline">Publicado v{settings.published_version}</Badge>
        </div>

        <div className="grid gap-3 rounded-2xl border border-border/60 bg-muted/40 p-4 text-sm md:grid-cols-2">
          <div>
            <p className="font-medium">Ultimo restore</p>
            <p className="text-muted-foreground">
              {lastRestore
                ? `v${lastRestore.version_number}${lastRestore.source_version_number ? ` ← v${lastRestore.source_version_number}` : ''}`
                : 'Nenhum restore executado ainda.'}
            </p>
          </div>
          <div>
            <p className="font-medium">Ultima IA aplicada</p>
            <p className="text-muted-foreground">
              {lastAi
                ? `${lastAi.variation_id ?? 'variacao'} · ${lastAi.apply_scope ?? 'all'}`
                : 'Nenhuma aplicacao de IA registrada ainda.'}
            </p>
          </div>
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
              aria-label="Gerar preview compartilhavel da revisao draft"
              onClick={onGeneratePreviewLink}
              disabled={isGeneratingPreview}
            >
              {isGeneratingPreview ? 'Gerando...' : 'Gerar preview compartilhavel'}
            </Button>
          </div>

          {previewUrl ? (
            <div className="space-y-2">
              <Input readOnly value={previewUrl} aria-label="Link do preview compartilhavel" />
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
                aria-label={`Restaurar revisao ${revision.version_number}`}
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
