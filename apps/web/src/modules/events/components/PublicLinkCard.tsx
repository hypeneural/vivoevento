import { Copy, ExternalLink } from 'lucide-react';
import { useMemo } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { EventPublicLinkQrTrigger } from '@/modules/events/qr/EventPublicLinkQrTrigger';
import type { EventPublicLinkQrEditorState } from '@/modules/events/qr/api';
import { buildQrCodeStylingOptions } from '@/modules/qr-code/support/qrOptionsBuilder';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

interface PublicLinkCardProps {
  eventId: string | number;
  effectiveBranding?: ApiEventEffectiveBranding | null;
  link: ApiEventPublicLink;
  qrState?: EventPublicLinkQrEditorState | null;
  onCopy: (value: string, label: string) => void;
}

const IDENTIFIER_LABELS: Record<ApiEventPublicLink['identifier_type'], string> = {
  slug: 'Endereco publico',
  upload_slug: 'Endereco de envio',
  wall_code: 'Codigo do telao',
};

const LINK_HELPERS: Record<ApiEventPublicLink['key'], { title: string; description: string }> = {
  gallery: {
    title: 'Compartilhe a galeria em segundos',
    description: 'Ideal para convidado abrir a galeria com a camera do celular, sem precisar digitar o endereco.',
  },
  upload: {
    title: 'Facilite o envio de fotos',
    description: 'Leva direto para a tela de envio. Bom para convite, recepcao e materiais de mesa.',
  },
  wall: {
    title: 'Leve o publico para o telao',
    description: 'Ajuda a divulgar o acesso ao ambiente do telao de forma rapida e visual.',
  },
  hub: {
    title: 'Concentre os links do evento',
    description: 'Abre a pagina com os atalhos principais do evento em um unico lugar.',
  },
  play: {
    title: 'Abra os jogos do evento',
    description: 'Bom para convidado entrar no jogo certo sem navegar por varias telas.',
  },
  find_me: {
    title: 'Direcione para a busca por fotos',
    description: 'Leva o convidado para a trilha de encontrar as proprias fotos com menos atrito.',
  },
};

function buildCardPreviewConfig(config: EventPublicLinkQrConfig): EventPublicLinkQrConfig {
  return {
    ...config,
    render: {
      ...config.render,
      preview_size: 112,
    },
  };
}

export function PublicLinkCard({ eventId, effectiveBranding, link, qrState, onCopy }: PublicLinkCardProps) {
  const canOpen = !!link.url;
  const canCopy = !!link.url;
  const helper = LINK_HELPERS[link.key];
  const previewOptions = useMemo(() => {
    if (!qrState?.config || !link.qr_value) {
      return null;
    }

    return buildQrCodeStylingOptions({
      config: buildCardPreviewConfig(qrState.config),
      data: link.qr_value,
    });
  }, [link.qr_value, qrState?.config]);

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardContent className="grid gap-4 p-4 md:grid-cols-[minmax(0,1fr)_156px]">
        <div className="space-y-3">
          <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-sm font-semibold">{link.label}</p>
                <Badge variant={link.enabled ? 'outline' : 'secondary'}>
                  {link.enabled ? 'Ativo' : 'Inativo'}
                </Badge>
                <Badge variant="secondary">
                  {qrState?.hasSavedConfig ? 'Estilo salvo' : 'QR basico'}
                </Badge>
              </div>
              <p className="text-sm text-slate-700">{helper.title}</p>
              <p className="text-xs text-muted-foreground">
                {IDENTIFIER_LABELS[link.identifier_type]}: <span className="font-mono">{link.identifier || '-'}</span>
              </p>
            </div>
          </div>

          <div className="rounded-2xl border border-emerald-100 bg-gradient-to-r from-emerald-50 via-white to-sky-50 px-3 py-2.5">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-emerald-700">Como este QR ajuda</p>
            <p className="mt-1.5 text-sm text-slate-700">{helper.description}</p>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
            <p className="break-all text-xs text-muted-foreground">{link.url || 'Link nao disponivel para este evento.'}</p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={!canCopy || !link.url}
              onClick={() => link.url && onCopy(link.url, link.label)}
            >
              <Copy className="mr-1.5 h-3.5 w-3.5" />
              Copiar
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={!canOpen || !link.url}
              onClick={() => link.url && window.open(link.url, '_blank', 'noopener,noreferrer')}
            >
              <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
              Abrir
            </Button>
          </div>
        </div>

        <div className="flex min-w-[144px] flex-col items-center justify-center gap-2 rounded-[24px] border border-slate-200 bg-gradient-to-b from-white to-slate-50 p-2.5">
          <div className="space-y-1 text-center">
            <p className="text-[11px] font-medium uppercase tracking-[0.16em] text-slate-500">Escaneie ou edite</p>
            <p className="text-xs text-slate-700">Clique no QR para ajustar visual, logo e download.</p>
          </div>
          <EventPublicLinkQrTrigger
            eventId={eventId}
            link={link}
            effectiveBranding={effectiveBranding}
            previewOptions={previewOptions}
            hasSavedStyle={Boolean(qrState?.hasSavedConfig)}
          />
        </div>
      </CardContent>
    </Card>
  );
}
