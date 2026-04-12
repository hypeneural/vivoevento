import { lazy, Suspense, useCallback, useRef, useState } from 'react';
import { Loader2, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { Badge } from '@/components/ui/badge';
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
      className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 backdrop-blur-sm"
      role="status"
      aria-live="polite"
    >
      <div className="w-full max-w-sm rounded-[28px] border border-white/10 bg-slate-950/95 p-5 text-white shadow-2xl">
        <div className="flex items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10">
            <Loader2 className="h-5 w-5 animate-spin" />
          </div>
          <div>
            <p className="text-sm font-semibold">Abrindo editor de QR</p>
            <p className="text-xs text-white/70">Preparando visual, modelos e arquivo para baixar.</p>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2">
          <div className="h-2 rounded-full bg-emerald-400/80 animate-pulse" />
          <div className="h-2 rounded-full bg-sky-400/60 animate-pulse [animation-delay:120ms]" />
          <div className="h-2 rounded-full bg-white/30 animate-pulse [animation-delay:240ms]" />
        </div>
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
      <button
        ref={triggerRef}
        type="button"
        className="group flex h-auto min-w-[132px] flex-col items-center justify-center gap-2 rounded-[22px] border border-slate-200 bg-white px-3 py-3 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50"
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
            size={100}
            className="border-0 bg-transparent p-0"
          />
        ) : (
          <QRCodeSVG value={link.qr_value} size={100} bgColor="#ffffff" fgColor="#0f172a" />
        )}
        <div className="space-y-1 text-center">
          <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
            <QrCode className="h-3.5 w-3.5" />
            {hasSavedStyle ? 'QR personalizado' : 'QR pronto para uso'}
          </div>
          <p className="text-[10px] leading-4 text-muted-foreground">
            Editar, trocar a logo e baixar
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
      </button>

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
