import { lazy, Suspense, useCallback, useRef, useState } from 'react';
import { Loader2, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';
import { QrCodeMiniPreview } from '@/modules/events/qr/QrCodeMiniPreview';
import type { QRCodeStylingOptions } from '@/modules/qr-code/support/qrCodeStylingDriver';

import { loadEventPublicLinkQrEditorModule } from './loader';
import { warmEventPublicLinkQrEditor } from './preload';

const LazyEventPublicLinkQrEditor = lazy(loadEventPublicLinkQrEditorModule);

interface EventPublicLinkQrTriggerProps {
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
  previewOptions?: QRCodeStylingOptions | null;
  hasSavedStyle?: boolean;
}

function EventPublicLinkQrEditorFallback() {
  return (
    <div
      data-testid="event-public-link-qr-editor-fallback"
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
      <div className="flex items-center gap-2 rounded-2xl bg-background px-4 py-3 text-sm shadow-lg">
        <Loader2 className="h-4 w-4 animate-spin" />
        Carregando editor de QR...
      </div>
    </div>
  );
}

export function EventPublicLinkQrTrigger({
  eventId,
  link,
  effectiveBranding,
  previewOptions,
  hasSavedStyle = false,
}: EventPublicLinkQrTriggerProps) {
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement | null>(null);

  const warm = useCallback(() => {
    void warmEventPublicLinkQrEditor({
      eventId,
      link,
      effectiveBranding,
    });
  }, [effectiveBranding, eventId, link]);

  const handleOpenChange = useCallback((nextOpen: boolean) => {
    setOpen(nextOpen);

    if (!nextOpen) {
      requestAnimationFrame(() => {
        triggerRef.current?.focus();
      });
    }
  }, []);

  if (!link.qr_value) {
    return (
      <div className="text-center text-xs text-muted-foreground">
        QR indisponivel enquanto o link nao estiver ativo.
      </div>
    );
  }

  return (
    <>
      <Button
        ref={triggerRef}
        type="button"
        variant="ghost"
        className="group flex h-auto min-w-[150px] flex-col items-center justify-center gap-3 rounded-3xl border border-slate-200 bg-white px-4 py-5 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:shadow-md"
        aria-label={`Editar QR code de ${link.label}`}
        onMouseEnter={warm}
        onFocus={warm}
        onClick={() => {
          warm();
          setOpen(true);
        }}
      >
        {previewOptions ? (
          <QrCodeMiniPreview
            options={previewOptions}
            size={112}
            className="border-0 bg-transparent p-0"
          />
        ) : (
          <QRCodeSVG value={link.qr_value} size={112} bgColor="#ffffff" fgColor="#0f172a" />
        )}
        <div className="space-y-1 text-center">
          <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
            <QrCode className="h-3.5 w-3.5" />
            {hasSavedStyle ? 'QR personalizado' : 'QR pronto para uso'}
          </div>
          <p className="text-[11px] text-muted-foreground">
            Clique para editar, trocar a logo e baixar
          </p>
          {hasSavedStyle ? (
            <Badge variant="secondary" className="text-[10px]">
              Estilo salvo
            </Badge>
          ) : (
            <Badge variant="outline" className="text-[10px]">
              Basico
            </Badge>
          )}
        </div>
      </Button>

      <Suspense fallback={open ? <EventPublicLinkQrEditorFallback /> : null}>
        {open ? (
          <LazyEventPublicLinkQrEditor
            open={open}
            onOpenChange={handleOpenChange}
            eventId={eventId}
            link={link}
            effectiveBranding={effectiveBranding}
          />
        ) : null}
      </Suspense>
    </>
  );
}
