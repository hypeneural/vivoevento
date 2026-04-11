import { lazy, Suspense, useCallback, useRef, useState } from 'react';
import { Loader2, QrCode } from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

import { Button } from '@/components/ui/button';
import type { ApiEventEffectiveBranding, ApiEventPublicLink } from '@/lib/api-types';

import { loadEventPublicLinkQrEditorModule } from './loader';
import { warmEventPublicLinkQrEditor } from './preload';

const LazyEventPublicLinkQrEditor = lazy(loadEventPublicLinkQrEditorModule);

interface EventPublicLinkQrTriggerProps {
  eventId: string | number;
  link: ApiEventPublicLink;
  effectiveBranding?: ApiEventEffectiveBranding | null;
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
        className="flex h-auto min-w-[150px] flex-col items-center justify-center gap-3 rounded-3xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 hover:bg-slate-100"
        aria-label={`Editar QR code de ${link.label}`}
        onMouseEnter={warm}
        onFocus={warm}
        onClick={() => {
          warm();
          setOpen(true);
        }}
      >
        <QRCodeSVG value={link.qr_value} size={112} bgColor="#ffffff" fgColor="#0f172a" />
        <div className="space-y-1 text-center">
          <div className="flex items-center justify-center gap-1 text-xs text-muted-foreground">
            <QrCode className="h-3.5 w-3.5" />
            QR pronto para uso
          </div>
          <p className="text-[11px] text-muted-foreground">
            Clique para editar e baixar
          </p>
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
