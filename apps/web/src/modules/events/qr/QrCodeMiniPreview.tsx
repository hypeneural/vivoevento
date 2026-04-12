import { cn } from '@/lib/utils';
import { useQrCodePreview } from '@/modules/qr-code/hooks/useQrCodePreview';
import type { QRCodeStylingOptions } from '@/modules/qr-code/support/qrCodeStylingDriver';

interface QrCodeMiniPreviewProps {
  options: QRCodeStylingOptions;
  size?: number;
  unavailable?: boolean;
  className?: string;
}

export function QrCodeMiniPreview({
  options,
  size = 120,
  unavailable = false,
  className,
}: QrCodeMiniPreviewProps) {
  const enabled = !unavailable && Boolean(options.data);
  const { containerRef } = useQrCodePreview({
    options,
    enabled,
  });

  if (!enabled) {
    return (
      <div
        data-testid="qr-code-mini-preview-unavailable"
        className={cn(
          'flex items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white text-[11px] text-muted-foreground',
          className,
        )}
        style={{ width: size, height: size }}
      >
        QR indisponivel
      </div>
    );
  }

  return (
    <div
      ref={containerRef}
      data-testid="qr-code-mini-preview"
      className={cn(
        'flex items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-sm [&>canvas]:h-full [&>canvas]:w-full [&>canvas]:max-w-full [&>svg]:h-full [&>svg]:w-full [&>svg]:max-w-full',
        className,
      )}
      style={{ width: size, height: size }}
    />
  );
}
