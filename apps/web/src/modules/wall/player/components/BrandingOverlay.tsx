/**
 * BrandingOverlay — Neon text, partner logo, sender credit, live badge.
 * Positioned using TV-safe area tokens.
 */

import {
  WALL_LOGO_DOCK,
  WALL_NEON_PANEL,
  WALL_SAFE_BOTTOM,
  WALL_SAFE_LEFT,
  WALL_SAFE_RIGHT,
  WALL_SAFE_TOP,
} from '../design/tokens';

interface BrandingOverlayProps {
  showNeon: boolean;
  neonText?: string | null;
  neonColor?: string | null;
  partnerLogoUrl?: string | null;
  showSenderCredit?: boolean;
  senderCredit?: string | null;
  syncLabel?: string;
}

export function BrandingOverlay({
  showNeon,
  neonText,
  neonColor,
  partnerLogoUrl,
  showSenderCredit,
  senderCredit,
  syncLabel,
}: BrandingOverlayProps) {
  return (
    <>
      {/* Neon indicator (top-left) */}
      {showNeon && neonText ? (
        <div className={`pointer-events-none absolute z-30 ${WALL_SAFE_LEFT} ${WALL_SAFE_TOP}`}>
          <div className={WALL_NEON_PANEL}>
            <div className="flex items-center gap-2">
              <span
                className="h-2 w-2 animate-pulse rounded-full"
                style={{ backgroundColor: neonColor || '#f97316' }}
              />
              <p className="text-sm uppercase tracking-[0.35em] text-orange-200/80">Ao vivo</p>
            </div>
            <p className="mt-1 text-[clamp(1rem,1.5vw,1.5rem)] font-semibold text-white">
              {neonText}
            </p>
          </div>
        </div>
      ) : null}

      {/* Live / sync label (top-right) */}
      {syncLabel ? (
        <div className={`pointer-events-none absolute z-30 rounded-full border border-white/10 bg-black/40 px-3 py-1 text-xs uppercase tracking-[0.3em] text-white/65 backdrop-blur-md ${WALL_SAFE_RIGHT} ${WALL_SAFE_TOP}`}>
          {syncLabel}
        </div>
      ) : null}

      {/* Partner logo (bottom-left) */}
      {partnerLogoUrl ? (
        <div className={`pointer-events-none absolute z-30 flex h-14 w-28 items-center justify-center ${WALL_LOGO_DOCK} ${WALL_SAFE_BOTTOM} ${WALL_SAFE_LEFT}`}>
          <img src={partnerLogoUrl} alt="Logo do parceiro" className="max-h-full max-w-full object-contain" />
        </div>
      ) : null}

      {/* Sender credit (bottom-left, above partner logo) */}
      {showSenderCredit && senderCredit ? (
        <div
          className={`pointer-events-none absolute z-30 rounded-2xl border border-white/10 bg-black/45 px-4 py-2 text-left backdrop-blur-md shadow-[0_16px_50px_rgba(0,0,0,0.22)] ${WALL_SAFE_LEFT} ${partnerLogoUrl ? 'bottom-[calc(max(16px,2vh)+72px)]' : WALL_SAFE_BOTTOM}`}
        >
          <p className="text-[10px] uppercase tracking-[0.3em] text-white/55">FOTO POR:</p>
          <p className="mt-1 text-sm font-medium text-white/88">{senderCredit}</p>
        </div>
      ) : null}

      {/* Evento Vivo branding (bottom-right) */}
      <div className={`pointer-events-none absolute z-30 ${WALL_LOGO_DOCK} ${WALL_SAFE_BOTTOM} ${WALL_SAFE_RIGHT}`}>
        <p className="text-xs font-bold uppercase tracking-[0.15em] text-white/60">
          Evento<span className="text-orange-400">Vivo</span>
        </p>
      </div>
    </>
  );
}

export default BrandingOverlay;
