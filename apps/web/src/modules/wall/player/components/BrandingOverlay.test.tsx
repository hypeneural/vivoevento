import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { BrandingOverlay } from './BrandingOverlay';

describe('BrandingOverlay', () => {
  it('renders the standard upload qr card when enabled and a public upload url exists', () => {
    const { container } = render(
      <BrandingOverlay
        showBranding={true}
        showQr={true}
        qrUrl="https://eventovivo.com.br/u/evento"
        showNeon={false}
        neonText={null}
        neonColor={null}
        partnerLogoUrl={null}
        showSenderCredit={false}
        senderCredit={null}
      />,
    );

    expect(screen.getByText('Envie sua foto')).toBeInTheDocument();
    expect(screen.getByText('Aponte a camera para entrar no upload do evento.')).toBeInTheDocument();
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('does not render the qr card when the upload url is missing', () => {
    render(
      <BrandingOverlay
        showBranding={true}
        showQr={true}
        qrUrl={null}
        showNeon={false}
        neonText={null}
        neonColor={null}
        partnerLogoUrl={null}
        showSenderCredit={false}
        senderCredit={null}
      />,
    );

    expect(screen.queryByText('Envie sua foto')).not.toBeInTheDocument();
  });
});
