import { cn } from '@/lib/utils';

import { useQrCodePreview } from '../hooks/useQrCodePreview';
import type { QRCodeStylingOptions } from '../support/qrCodeStylingDriver';

interface QrCodePreviewPaneProps {
  options: QRCodeStylingOptions;
  unavailable?: boolean;
  unavailableLabel?: string;
  className?: string;
}

export function QrCodePreviewPane({
  options,
  unavailable = false,
  unavailableLabel = 'QR indisponivel',
  className,
}: QrCodePreviewPaneProps) {
  const enabled = !unavailable && Boolean(options.data);
  const { containerRef } = useQrCodePreview({
    options,
    enabled,
  });

  if (!enabled) {
    return (
      <div
        data-testid="qr-code-preview-pane-unavailable"
        className={cn(
          'flex h-[220px] w-full items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white text-sm text-muted-foreground',
          className,
        )}
      >
        {unavailableLabel}
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      data-testid="qr-code-preview-pane"
      className={cn(
        'flex min-h-[220px] w-full items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm [&>canvas]:h-full [&>canvas]:w-full [&>canvas]:max-w-full [&>svg]:h-full [&>svg]:w-full [&>svg]:max-w-full',
        className,
      )}
    />
  );
}
