import { type ChangeEvent, useRef, useState } from 'react';
import { Camera, CheckCircle2, ImageIcon, Loader2, ScanFace, Search, Upload, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { ApiFaceSearchMatch, ApiFaceSearchRequestSummary } from '@/lib/api-types';
import { cn } from '@/lib/utils';

interface FaceSearchSearchPanelProps {
  title: string;
  description: string;
  submitLabel: string;
  isPending: boolean;
  requireConsent?: boolean;
  consentLabel?: string;
  includePendingEnabled?: boolean;
  includePending?: boolean;
  onIncludePendingChange?: (value: boolean) => void;
  disabled?: boolean;
  disabledMessage?: string | null;
  requestMeta?: ApiFaceSearchRequestSummary | null;
  results?: ApiFaceSearchMatch[];
  errorMessage?: string | null;
  onSubmit: (payload: { file: File; includePending: boolean; consentAccepted: boolean }) => void;
}

function formatDistance(distance: number) {
  return distance.toFixed(3);
}

export function FaceSearchSearchPanel({
  title,
  description,
  submitLabel,
  isPending,
  requireConsent = false,
  consentLabel = 'Autorizo o uso da selfie apenas para localizar minhas fotos neste evento.',
  includePendingEnabled = false,
  includePending = true,
  onIncludePendingChange,
  disabled = false,
  disabledMessage,
  requestMeta,
  results = [],
  errorMessage,
  onSubmit,
}: FaceSearchSearchPanelProps) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [consentAccepted, setConsentAccepted] = useState(false);

  const submitDisabled = disabled || isPending || !file || (requireConsent && !consentAccepted);

  function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
    const nextFile = event.target.files?.[0] ?? null;
    setFile(nextFile);
  }

  function clearFile() {
    setFile(null);

    if (inputRef.current) {
      inputRef.current.value = '';
    }
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <div className="flex items-center gap-2">
          <ScanFace className="h-4 w-4 text-primary" />
          <p className="text-sm font-semibold">{title}</p>
        </div>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        data-testid="face-search-file-input"
        onChange={handleFileChange}
      />

      <Card className="border-dashed border-border/80 bg-muted/20 shadow-none">
        <CardContent className="space-y-4 p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-1">
              <p className="text-sm font-medium">Selfie para consulta</p>
              <p className="text-xs text-muted-foreground">
                Prefira uma selfie nitida, com uma unica pessoa e rosto centralizado.
              </p>
            </div>

            <Button
              type="button"
              variant="outline"
              className="rounded-full"
              disabled={disabled || isPending}
              onClick={() => inputRef.current?.click()}
            >
              <Upload className="h-4 w-4" />
              Escolher selfie
            </Button>
          </div>

          {file ? (
            <div className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-background px-3 py-2">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium">{file.name}</p>
                <p className="text-xs text-muted-foreground">{Math.max(1, Math.round(file.size / 1024))} KB</p>
              </div>
              <Button type="button" variant="ghost" size="icon" onClick={clearFile} disabled={isPending}>
                <X className="h-4 w-4" />
              </Button>
            </div>
          ) : (
            <div className="rounded-2xl border border-dashed border-border bg-background/70 px-4 py-8 text-center">
              <Camera className="mx-auto h-6 w-6 text-muted-foreground" />
              <p className="mt-2 text-sm font-medium">Nenhuma selfie selecionada</p>
              <p className="mt-1 text-xs text-muted-foreground">
                O arquivo fica so no fluxo desta busca e nao entra na galeria do evento.
              </p>
            </div>
          )}

          {includePendingEnabled ? (
            <div className="flex items-center justify-between rounded-2xl border border-border bg-background px-3 py-3">
              <div className="space-y-1">
                <Label htmlFor="include-pending" className="text-sm font-medium">
                  Incluir pendentes
                </Label>
                <p className="text-xs text-muted-foreground">
                  Mostra tambem fotos ainda nao publicadas para uso interno.
                </p>
              </div>
              <Switch
                id="include-pending"
                checked={includePending}
                onCheckedChange={onIncludePendingChange}
                disabled={disabled || isPending}
              />
            </div>
          ) : null}

          {requireConsent ? (
            <div className="flex items-start gap-3 rounded-2xl border border-border bg-background px-3 py-3">
              <Checkbox
                id="face-search-consent"
                checked={consentAccepted}
                onCheckedChange={(checked) => setConsentAccepted(Boolean(checked))}
                disabled={disabled || isPending}
              />
              <div className="space-y-1">
                <Label htmlFor="face-search-consent" className="text-sm font-medium leading-5">
                  {consentLabel}
                </Label>
                <p className="text-xs text-muted-foreground">
                  Sem consentimento explicito a busca publica nao pode ser executada.
                </p>
              </div>
            </div>
          ) : null}

          {disabledMessage ? (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900">
              {disabledMessage}
            </div>
          ) : null}

          {errorMessage ? (
            <div className="rounded-2xl border border-destructive/30 bg-destructive/5 px-3 py-3 text-sm text-destructive">
              {errorMessage}
            </div>
          ) : null}

          <Button
            type="button"
            className="w-full rounded-2xl"
            disabled={submitDisabled}
            onClick={() => file && onSubmit({ file, includePending, consentAccepted })}
          >
            {isPending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Processando busca
              </>
            ) : (
              <>
                <Search className="h-4 w-4" />
                {submitLabel}
              </>
            )}
          </Button>
        </CardContent>
      </Card>

      {requestMeta ? (
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant="outline">Faces detectadas: {requestMeta.faces_detected}</Badge>
          <Badge variant="outline">Top K: {requestMeta.top_k}</Badge>
          {requestMeta.best_distance !== null ? (
            <Badge variant="outline">Melhor distancia: {formatDistance(requestMeta.best_distance)}</Badge>
          ) : null}
          {requestMeta.expires_at ? (
            <Badge variant="secondary">
              Expira em {new Date(requestMeta.expires_at).toLocaleString('pt-BR')}
            </Badge>
          ) : null}
        </div>
      ) : null}

      {requestMeta ? (
        results.length > 0 ? (
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {results.map((result) => (
              <Card key={result.event_media_id} className="overflow-hidden">
                <CardContent className="p-0">
                  <div className="aspect-[4/3] overflow-hidden bg-muted">
                    {result.media.thumbnail_url ? (
                      <img
                        src={result.media.thumbnail_url}
                        alt={result.media.caption || result.media.sender_name}
                        className="h-full w-full object-cover"
                      />
                    ) : (
                      <div className="flex h-full items-center justify-center text-muted-foreground">
                        <ImageIcon className="h-5 w-5" />
                      </div>
                    )}
                  </div>
                  <div className="space-y-2 p-4">
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-sm font-medium">
                        {result.media.caption || result.media.sender_name || `Foto #${result.event_media_id}`}
                      </p>
                      <Badge variant="secondary">#{result.rank}</Badge>
                    </div>
                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                      <span className="rounded-full bg-muted px-2 py-1">
                        Distancia {formatDistance(result.best_distance)}
                      </span>
                      {result.best_quality_score !== null ? (
                        <span className="rounded-full bg-muted px-2 py-1">
                          Qualidade {result.best_quality_score.toFixed(2)}
                        </span>
                      ) : null}
                    </div>
                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                      <span>{result.media.event_title || 'Evento Vivo'}</span>
                      <span>{result.media.publication_status || result.media.moderation_status || 'desconhecido'}</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        ) : (
          <div className="rounded-2xl border border-dashed border-border bg-muted/20 px-4 py-8 text-center">
            <CheckCircle2 className={cn('mx-auto h-6 w-6', errorMessage ? 'text-destructive' : 'text-muted-foreground')} />
            <p className="mt-2 text-sm font-medium">Nenhuma foto encontrada para esta selfie</p>
            <p className="mt-1 text-xs text-muted-foreground">
              Tente outra imagem com mais nitidez ou enquadramento melhor.
            </p>
          </div>
        )
      ) : null}
    </div>
  );
}
