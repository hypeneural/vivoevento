/**
 * useQRFlip — Timer logic for the QR flip card.
 *
 * Modes:
 * - 'disabled': no flip
 * - 'minutes': flip every N minutes
 * - 'photos': flip after N new photos arrive
 *
 * Does NOT flip if the central QR is already visible.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

export type QRFlipMode = 'disabled' | 'minutes' | 'photos';

interface UseQRFlipOptions {
  mode: QRFlipMode;
  every: number;  // N minutes or N photos
  durationSec: number;  // how long to show QR when flipped (default 60)
  qrCentralVisible?: boolean;  // if true, suppress flip
}

export interface QRFlipState {
  isFlipped: boolean;
  trigger: () => void;  // manually trigger for 'photos' mode
}

export function useQRFlip({
  mode,
  every,
  durationSec = 60,
  qrCentralVisible = false,
}: UseQRFlipOptions): QRFlipState {
  const [isFlipped, setIsFlipped] = useState(false);
  const flipTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const minuteTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const photoCountRef = useRef(0);

  // Schedule auto-unflip after duration
  const doFlip = useCallback(() => {
    if (qrCentralVisible || mode === 'disabled') return;

    setIsFlipped(true);

    if (flipTimerRef.current) clearTimeout(flipTimerRef.current);
    flipTimerRef.current = setTimeout(() => {
      setIsFlipped(false);
    }, durationSec * 1000);
  }, [qrCentralVisible, mode, durationSec]);

  // Minutes mode: interval timer
  useEffect(() => {
    if (mode !== 'minutes' || every <= 0) return;

    minuteTimerRef.current = setInterval(() => {
      doFlip();
    }, every * 60 * 1000);

    return () => {
      if (minuteTimerRef.current) clearInterval(minuteTimerRef.current);
    };
  }, [mode, every, doFlip]);

  // Photos mode: count trigger calls
  const trigger = useCallback(() => {
    if (mode !== 'photos') return;

    photoCountRef.current += 1;
    if (photoCountRef.current >= every) {
      photoCountRef.current = 0;
      doFlip();
    }
  }, [mode, every, doFlip]);

  // Cleanup
  useEffect(() => {
    return () => {
      if (flipTimerRef.current) clearTimeout(flipTimerRef.current);
      if (minuteTimerRef.current) clearInterval(minuteTimerRef.current);
    };
  }, []);

  // Suppress flip when central QR is visible
  useEffect(() => {
    if (qrCentralVisible && isFlipped) {
      setIsFlipped(false);
    }
  }, [qrCentralVisible, isFlipped]);

  return { isFlipped, trigger };
}
