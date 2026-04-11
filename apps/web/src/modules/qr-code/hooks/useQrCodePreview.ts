import { useEffect, useRef } from 'react';

import {
  createQrCodeStylingDriver,
  type QRCodeStylingOptions,
  type QrCodeStylingDriver,
} from '../support/qrCodeStylingDriver';

interface UseQrCodePreviewParams {
  options: QRCodeStylingOptions;
  enabled?: boolean;
}

export function useQrCodePreview({
  options,
  enabled = true,
}: UseQrCodePreviewParams) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const driverRef = useRef<QrCodeStylingDriver | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const container = containerRef.current;

    if (!container) {
      return;
    }

    if (!driverRef.current) {
      container.innerHTML = '';
      const driver = createQrCodeStylingDriver(options);
      driver.append(container);
      driverRef.current = driver;
      return;
    }

    driverRef.current.update(options);
  }, [enabled, options]);

  useEffect(() => () => {
    if (containerRef.current) {
      containerRef.current.innerHTML = '';
    }

    driverRef.current = null;
  }, []);

  return {
    containerRef,
  };
}
