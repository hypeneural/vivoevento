import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  formatGalleryModelMatrix,
  type GalleryAiApplyScope,
  type GalleryAiProposalRun,
  type GalleryAiTargetLayer,
  type GalleryAiVariation,
} from '../gallery-builder';

interface GalleryAiVariationsPanelProps {
  promptText: string;
  targetLayer: GalleryAiTargetLayer;
  run: GalleryAiProposalRun | null;
  variations: GalleryAiVariation[];
  isGenerating: boolean;
  isApplyingVariationId?: string | null;
  previewRequired: boolean;
  onPromptTextChange: (value: string) => void;
  onTargetLayerChange: (value: GalleryAiTargetLayer) => void;
  onGenerate: () => void;
  onApplyVariation: (variation: GalleryAiVariation, scope: GalleryAiApplyScope) => void;
}

const TARGET_LAYER_OPTIONS: Array<{ value: GalleryAiTargetLayer; label: string }> = [
  { value: 'mixed', label: 'Misto' },
  { value: 'theme_tokens', label: 'Tema' },
  { value: 'page_schema', label: 'Pagina' },
  { value: 'media_behavior', label: 'Midia' },
];

export function GalleryAiVariationsPanel({
  promptText,
  targetLayer,
  run,
  variations,
  isGenerating,
  isApplyingVariationId,
  previewRequired,
  onPromptTextChange,
  onTargetLayerChange,
  onGenerate,
  onApplyVariation,
}: GalleryAiVariationsPanelProps) {
  return (
    <Card className="rounded-[28px] border-border/60" role="region" aria-label="Assistente de IA da galeria">
      <CardHeader>
        <CardTitle>Assistente de IA</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="gallery-ai-prompt">Pedido da IA</Label>
          <Textarea
            id="gallery-ai-prompt"
            value={promptText}
            onChange={(event) => onPromptTextChange(event.target.value)}
            placeholder="Ex.: quero uma galeria romantica em tons rose com hero mais editorial"
            rows={4}
          />
        </div>

        <div className="space-y-2">
          <p className="text-sm font-medium">Camada alvo</p>
          <div className="flex flex-wrap gap-2">
            {TARGET_LAYER_OPTIONS.map((option) => (
              <Button
                key={option.value}
                type="button"
                variant={targetLayer === option.value ? 'default' : 'outline'}
                className="rounded-full"
                aria-pressed={targetLayer === option.value}
                aria-label={`Aplicar IA na camada ${option.label}`}
                onClick={() => onTargetLayerChange(option.value)}
              >
                {option.label}
              </Button>
            ))}
          </div>
        </div>

        <div className="rounded-2xl border border-border/60 bg-muted/40 p-4 text-sm text-muted-foreground">
          A IA devolve somente JSON guardrailed com `3` variacoes seguras. Nada de HTML, CSS ou JSX livre.
        </div>

        <Button
          type="button"
          onClick={onGenerate}
          disabled={isGenerating || promptText.trim().length < 8}
        >
          {isGenerating ? 'Gerando 3 variacoes...' : 'Gerar 3 variacoes seguras'}
        </Button>

        {run ? (
          <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <Badge variant="outline">{run.provider_key}</Badge>
            <Badge variant="outline">{run.model_key}</Badge>
            <Badge variant="secondary">schema v{run.response_schema_version}</Badge>
          </div>
        ) : null}

        {previewRequired ? (
          <div className="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            Gere um preview compartilhavel antes de publicar uma alteracao aplicada pela IA.
          </div>
        ) : null}

        <div className="space-y-3">
          {variations.map((variation) => (
            <div
              key={variation.id}
              className="space-y-3 rounded-2xl border border-border/60 bg-background/80 p-4"
            >
              <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <p className="font-medium">{variation.label}</p>
                  <Badge variant="outline">{variation.scope}</Badge>
                </div>
                <p className="text-sm text-muted-foreground">{variation.summary}</p>
                <p className="text-xs text-muted-foreground">
                  {formatGalleryModelMatrix(variation.model_matrix)}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                {variation.available_layers.map((layer) => (
                  <Badge key={layer} variant="secondary">
                    {layer}
                  </Badge>
                ))}
              </div>

              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  aria-label={`Aplicar tudo na variacao ${variation.label}`}
                  onClick={() => onApplyVariation(variation, 'all')}
                  disabled={isApplyingVariationId === variation.id}
                >
                  {isApplyingVariationId === variation.id ? 'Aplicando...' : 'Aplicar tudo'}
                </Button>

                {variation.available_layers.includes('theme_tokens') ? (
                  <Button
                    type="button"
                    variant="outline"
                    aria-label={`Aplicar so paleta na variacao ${variation.label}`}
                    onClick={() => onApplyVariation(variation, 'theme_tokens')}
                    disabled={isApplyingVariationId === variation.id}
                  >
                    So paleta
                  </Button>
                ) : null}

                {variation.available_layers.includes('page_schema') ? (
                  <Button
                    type="button"
                    variant="outline"
                    aria-label={`Aplicar so hero e blocos na variacao ${variation.label}`}
                    onClick={() => onApplyVariation(variation, 'page_schema')}
                    disabled={isApplyingVariationId === variation.id}
                  >
                    So hero e blocos
                  </Button>
                ) : null}

                {variation.available_layers.includes('media_behavior') ? (
                  <Button
                    type="button"
                    variant="outline"
                    aria-label={`Aplicar so media behavior na variacao ${variation.label}`}
                    onClick={() => onApplyVariation(variation, 'media_behavior')}
                    disabled={isApplyingVariationId === variation.id}
                  >
                    So media behavior
                  </Button>
                ) : null}
              </div>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}
