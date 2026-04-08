/**
 * QRFlipCard — 3D flip card component.
 *
 * Front: Partner logo (opacity 0.7)
 * Back: QR code + instruction text on white background
 *
 * Inspired by MomentLoop's flip.css.
 */

import './qr-flip.css';

interface QRFlipCardProps {
  isFlipped: boolean;
  logoUrl?: string | null;
  qrUrl?: string | null;
  qrText?: string;
}

export function QRFlipCard({
  isFlipped,
  logoUrl,
  qrUrl,
  qrText = 'Envie sua foto!',
}: QRFlipCardProps) {
  if (!logoUrl && !qrUrl) return null;

  return (
    <div className={`qr-flip-card ${isFlipped ? 'qr-flip-card--flipped' : ''}`}>
      <div className="qr-flip-card__inner">
        {/* Front — Logo */}
        <div className="qr-flip-card__front">
          {logoUrl ? (
            <img
              src={logoUrl}
              alt="Logo"
              className="qr-flip-card__logo"
            />
          ) : (
            <div className="qr-flip-card__placeholder" />
          )}
        </div>

        {/* Back — QR Code */}
        <div className="qr-flip-card__back">
          {qrUrl ? (
            <>
              <img
                src={qrUrl}
                alt="QR Code"
                className="qr-flip-card__qr"
              />
              <span className="qr-flip-card__text">{qrText}</span>
            </>
          ) : null}
        </div>
      </div>
    </div>
  );
}

export default QRFlipCard;
