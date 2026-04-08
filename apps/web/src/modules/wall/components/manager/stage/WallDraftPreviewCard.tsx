import type { ApiWallMediaSource, ApiWallSettings } from '@/lib/api-types';

import { getWallSourceMeta } from '../../../wall-source-meta';

type PreviewMedia = {
  previewUrl: string | null;
  senderName: string | null;
  sourceType: ApiWallMediaSource | null;
};

type UpcomingPreviewItem = {
  itemId: string;
  previewUrl: string | null;
  senderName: string;
};

interface WallDraftPreviewCardProps {
  settings: ApiWallSettings;
  previewMedia: PreviewMedia | null;
  upcomingItems: UpcomingPreviewItem[];
}

export function WallDraftPreviewCard({
  settings,
  previewMedia,
  upcomingItems,
}: WallDraftPreviewCardProps) {
  const previewSourceMeta = previewMedia?.sourceType ? getWallSourceMeta(previewMedia.sourceType) : null;
  const visibleUpcomingItems = settings.show_side_thumbnails ? upcomingItems.slice(0, 3) : [];

  return (
    <div className="rounded-2xl border border-border/60 bg-background/70 p-4">
      <div className="space-y-1">
        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Previa do rascunho</p>
        <p className="text-sm text-muted-foreground">
          Uma composicao fiel ao layout atual, sem abrir outro player e sem depender de `iframe`.
        </p>
      </div>

      <div
        className="relative mt-4 overflow-hidden rounded-[28px] border border-border/60 bg-neutral-950"
        style={{
          backgroundImage: settings.background_url ? `url(${settings.background_url})` : undefined,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
        }}
      >
        <div className="aspect-video bg-gradient-to-br from-neutral-950/90 via-neutral-950/75 to-neutral-900/90">
          {previewMedia?.previewUrl ? (
            <img
              src={previewMedia.previewUrl}
              alt={`Previa principal da midia de ${previewMedia.senderName || 'Convidado'}`}
              className={`h-full w-full ${settings.layout === 'gallery' || settings.layout === 'mosaic' ? 'object-cover' : 'object-contain'}`}
            />
          ) : (
            <div className="flex h-full items-center justify-center px-6 text-center text-sm text-white/70">
              Selecione uma midia recente ou aguarde a fila prevista para ver a previa do layout.
            </div>
          )}

          <div className="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/25 to-transparent" />

          {settings.show_branding && settings.partner_logo_url ? (
            <img
              src={settings.partner_logo_url}
              alt="Marca do parceiro"
              className="absolute left-4 top-4 h-10 w-10 rounded-xl border border-white/20 bg-white/90 object-contain p-1.5 shadow-sm"
            />
          ) : null}

          {settings.show_qr ? (
            <div className="absolute right-4 top-4 space-y-1 text-right">
              <span className="inline-flex rounded-full border border-white/20 bg-white/10 px-2 py-1 text-[10px] font-medium uppercase tracking-[0.16em] text-white/80">
                QR visivel
              </span>
              <div className="ml-auto flex h-12 w-12 items-center justify-center rounded-xl border border-white/20 bg-white/90 text-[10px] font-semibold text-neutral-900">
                QR
              </div>
            </div>
          ) : null}

          {visibleUpcomingItems.length > 0 ? (
            <div className="absolute bottom-4 right-4 flex gap-2 rounded-2xl border border-white/15 bg-neutral-950/75 p-2 backdrop-blur">
              {visibleUpcomingItems.map((item) => (
                <div key={item.itemId} className="overflow-hidden rounded-xl border border-white/10 bg-white/5">
                  {item.previewUrl ? (
                    <img
                      src={item.previewUrl}
                      alt={`Proxima midia de ${item.senderName}`}
                      className="h-12 w-12 object-cover"
                    />
                  ) : (
                    <div className="flex h-12 w-12 items-center justify-center text-[10px] text-white/60">
                      Sem capa
                    </div>
                  )}
                </div>
              ))}
            </div>
          ) : null}

          <div className="absolute inset-x-0 bottom-0 space-y-3 p-4 text-white">
            {settings.show_neon && settings.neon_text ? (
              <p
                className="text-sm font-semibold drop-shadow-[0_0_12px_rgba(255,255,255,0.35)]"
                style={{ color: settings.neon_color ?? '#ffffff' }}
              >
                {settings.neon_text}
              </p>
            ) : null}

            <div className="flex flex-wrap gap-2 text-[11px]">
              <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/85">
                Layout {formatLooseLabel(settings.layout, 'Auto')}
              </span>
              <span className="inline-flex rounded-full border border-white/15 bg-white/10 px-2 py-1 font-medium text-white/85">
                Troca {formatLooseLabel(settings.transition_effect, 'Fade')}
              </span>
              {previewSourceMeta ? (
                <span className={`inline-flex items-center gap-1 rounded-full border px-2 py-1 font-medium ${previewSourceMeta.chipClassName}`}>
                  <previewSourceMeta.Icon className="h-3.5 w-3.5" />
                  {previewSourceMeta.label}
                </span>
              ) : null}
            </div>

            {settings.show_sender_credit ? (
              <div className="rounded-2xl border border-white/15 bg-white/10 px-3 py-2 text-sm font-medium text-white/90">
                Credito do remetente ativo: {previewMedia?.senderName || 'Convidado'}
              </div>
            ) : null}
          </div>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {settings.show_side_thumbnails ? 'Miniaturas laterais ativas' : 'Miniaturas laterais ocultas'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {settings.show_sender_credit ? 'Credito do remetente ativo' : 'Credito do remetente oculto'}
        </span>
        <span className="rounded-full border border-border/60 bg-background px-3 py-1">
          {settings.show_qr ? 'QR visivel' : 'QR oculto'}
        </span>
      </div>
    </div>
  );
}

function formatLooseLabel(value?: string | null, fallback = 'Sem dado') {
  if (!value) {
    return fallback;
  }

  const normalized = value.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();

  if (!normalized) {
    return fallback;
  }

  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}
