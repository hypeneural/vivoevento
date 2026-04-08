import type { RefObject } from 'react';
import { ArrowDown, ArrowUp, Loader2, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { ApiWallAdItem, ApiWallSettings } from '@/lib/api-types';

import { WallManagerSection } from '../../WallManagerSection';

type UpdateDraft = <K extends keyof ApiWallSettings>(key: K, value: ApiWallSettings[K]) => void;

interface WallAdsTabProps {
  wallSettings: ApiWallSettings;
  wallAds: ApiWallAdItem[];
  adsLoading: boolean;
  uploadPending: boolean;
  deletePending: boolean;
  reorderPending: boolean;
  selectedAdFile: File | null;
  selectedAdDuration: string;
  selectedAdIsVideo: boolean;
  adFileInputRef: RefObject<HTMLInputElement | null>;
  onDraftChange: UpdateDraft;
  onAdFileChange: (file: File | null) => void;
  onAdDurationChange: (value: string) => void;
  onUploadAd: () => void;
  onResetAdUploadForm: () => void;
  onDeleteAd: (ad: ApiWallAdItem) => void;
  onMoveAd: (adId: number, direction: -1 | 1) => void;
}

function clampIntegerInput(value: string | number | undefined, fallback: number, min: number, max: number) {
  const parsed = typeof value === 'number' ? value : Number(value);

  if (!Number.isFinite(parsed)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.trunc(parsed)));
}

function formatFileSize(sizeBytes: number): string {
  if (sizeBytes < 1024) {
    return `${sizeBytes} B`;
  }

  if (sizeBytes < 1024 * 1024) {
    return `${Math.round(sizeBytes / 102.4) / 10} KB`;
  }

  return `${Math.round(sizeBytes / 104857.6) / 10} MB`;
}

export function WallAdsTab({
  wallSettings,
  wallAds,
  adsLoading,
  uploadPending,
  deletePending,
  reorderPending,
  selectedAdFile,
  selectedAdDuration,
  selectedAdIsVideo,
  adFileInputRef,
  onDraftChange,
  onAdFileChange,
  onAdDurationChange,
  onUploadAd,
  onResetAdUploadForm,
  onDeleteAd,
  onMoveAd,
}: WallAdsTabProps) {
  const adMode = wallSettings.ad_mode ?? 'disabled';
  const adFrequency = wallSettings.ad_frequency ?? 5;
  const adIntervalMinutes = wallSettings.ad_interval_minutes ?? 3;

  return (
    <WallManagerSection
      title={(
        <span className="flex items-center gap-2">
          Patrocinadores no telao
        </span>
      )}
      description="Configure quando os anuncios entram no slideshow e gerencie os criativos ativos do evento."
    >
      <div className="space-y-5">
        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
            <div className="space-y-2">
              <p className="text-sm font-medium">Modo de exibicao dos anuncios</p>
              <Select
                value={adMode}
                onValueChange={(value) => onDraftChange('ad_mode', value as ApiWallSettings['ad_mode'])}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione o modo de anuncios" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="disabled">Desativado</SelectItem>
                  <SelectItem value="by_photos">A cada X fotos</SelectItem>
                  <SelectItem value="by_minutes">A cada X minutos</SelectItem>
                </SelectContent>
              </Select>
              <p className="text-[11px] text-muted-foreground">
                Videos de patrocinador sao reproduzidos sem som para respeitar autoplay nos navegadores.
              </p>
            </div>

            {adMode === 'by_photos' ? (
              <div className="space-y-2">
                <p className="text-sm font-medium">Frequencia por fotos</p>
                <Input
                  type="number"
                  min={1}
                  max={100}
                  value={String(adFrequency)}
                  onChange={(event) => onDraftChange('ad_frequency', clampIntegerInput(event.target.value, 5, 1, 100))}
                />
                <p className="text-[11px] text-muted-foreground">
                  O anuncio entra depois de cada bloco de fotos exibidas pelo slideshow.
                </p>
              </div>
            ) : null}

            {adMode === 'by_minutes' ? (
              <div className="space-y-2">
                <p className="text-sm font-medium">Intervalo por minutos</p>
                <Input
                  type="number"
                  min={1}
                  max={60}
                  value={String(adIntervalMinutes)}
                  onChange={(event) => onDraftChange('ad_interval_minutes', clampIntegerInput(event.target.value, 3, 1, 60))}
                />
                <p className="text-[11px] text-muted-foreground">
                  Use esse modo quando quiser ciclos mais previsiveis para patrocinadores em eventos longos.
                </p>
              </div>
            ) : null}
          </div>

          <div className="space-y-4 rounded-2xl border border-border/60 bg-background/60 p-4">
            <div className="space-y-2">
              <p className="text-sm font-medium">Adicionar novo criativo</p>
              <Input
                ref={adFileInputRef}
                aria-label="Arquivo do patrocinador"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,image/jpeg,image/png,image/webp,image/gif,video/mp4"
                onChange={(event) => onAdFileChange(event.target.files?.[0] ?? null)}
              />
            </div>

            <div className="space-y-2">
              <p className="text-sm font-medium">Duracao da imagem em segundos</p>
              <Input
                aria-label="Duracao do anuncio"
                type="number"
                min={3}
                max={120}
                disabled={selectedAdIsVideo || !selectedAdFile}
                value={selectedAdDuration}
                onChange={(event) => onAdDurationChange(event.target.value)}
              />
              <p className="text-[11px] text-muted-foreground">
                Para video, a duracao vem do proprio arquivo e o player avanca ao terminar.
              </p>
            </div>

            <div className="rounded-xl border border-dashed border-border/70 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
              {selectedAdFile
                ? `Selecionado: ${selectedAdFile.name} - ${formatFileSize(selectedAdFile.size)}`
                : 'Formatos aceitos: JPG, PNG, WebP, GIF e MP4. Tamanho maximo: 20 MB.'}
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                onClick={onUploadAd}
                disabled={!selectedAdFile || uploadPending}
              >
                {uploadPending ? (
                  <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                ) : null}
                Enviar anuncio
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={onResetAdUploadForm}
                disabled={!selectedAdFile || uploadPending}
              >
                Limpar selecao
              </Button>
            </div>
          </div>
        </div>

        <div className="space-y-3">
          <div className="flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-medium">Criativos ativos</p>
              <p className="text-[11px] text-muted-foreground">
                A ordem abaixo define a sequencia usada pelo player em round-robin.
              </p>
            </div>
            <span className="rounded-full border border-border/70 bg-background px-3 py-1 text-xs text-muted-foreground">
              {wallAds.length} item(ns)
            </span>
          </div>

          {adsLoading ? (
            <div className="rounded-2xl border border-border/60 bg-background/60 px-4 py-6 text-sm text-muted-foreground">
              Carregando anuncios do telao...
            </div>
          ) : wallAds.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-border/60 bg-background/60 px-4 py-8 text-sm text-muted-foreground">
              Nenhum anuncio cadastrado ainda. Envie o primeiro criativo para liberar a monetizacao do telao.
            </div>
          ) : (
            <div className="space-y-3">
              {wallAds.map((ad, index) => (
                <div key={ad.id} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                  <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <div className="h-24 w-full overflow-hidden rounded-xl border border-border/60 bg-muted/30 lg:w-40">
                      {ad.media_type === 'image' && ad.url ? (
                        <img
                          src={ad.url}
                          alt={`Criativo ${index + 1}`}
                          className="h-full w-full object-cover"
                        />
                      ) : (
                        <div className="flex h-full w-full items-center justify-center text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                          Video MP4
                        </div>
                      )}
                    </div>

                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-semibold">Patrocinador {index + 1}</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {ad.media_type === 'video' ? 'Video' : 'Imagem'}
                        {' - '}
                        {ad.media_type === 'video' ? 'termina no fim do video' : `${ad.duration_seconds}s na tela`}
                      </p>
                      {ad.url ? (
                        <a
                          href={ad.url}
                          target="_blank"
                          rel="noreferrer"
                          className="mt-2 inline-flex text-xs font-medium text-primary underline-offset-4 hover:underline"
                        >
                          Abrir criativo
                        </a>
                      ) : null}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={`Subir anuncio ${index + 1}`}
                        disabled={index === 0 || reorderPending}
                        onClick={() => onMoveAd(ad.id, -1)}
                      >
                        <ArrowUp className="h-4 w-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={`Descer anuncio ${index + 1}`}
                        disabled={index === wallAds.length - 1 || reorderPending}
                        onClick={() => onMoveAd(ad.id, 1)}
                      >
                        <ArrowDown className="h-4 w-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="destructive"
                        size="icon"
                        aria-label={`Remover anuncio ${index + 1}`}
                        disabled={deletePending}
                        onClick={() => onDeleteAd(ad)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </WallManagerSection>
  );
}
