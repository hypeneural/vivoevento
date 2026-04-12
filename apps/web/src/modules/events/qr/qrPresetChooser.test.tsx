import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { ApiEventPublicLink } from '@/lib/api-types';
import { QrCodeContentPanel } from '@/modules/qr-code/components/QrCodeContentPanel';
import { buildQrConfigDefaults } from '@/modules/qr-code/support/qrDefaults';

vi.mock('@/modules/events/qr/QrCodeMiniPreview', () => ({
  QrCodeMiniPreview: () => <div data-testid="qr-code-mini-preview" />,
}));

const link: ApiEventPublicLink = {
  key: 'upload',
  label: 'Upload',
  enabled: true,
  identifier_type: 'upload_slug',
  identifier: 'envio',
  url: 'https://example.com/upload/envio',
  api_url: null,
  qr_value: 'https://example.com/upload/envio',
};

describe('qrPresetChooser', () => {
  it('renders usage presets with microcopy and copy-style affordance', () => {
    render(
      <QrCodeContentPanel
        link={link}
        config={buildQrConfigDefaults({ linkKey: 'upload' })}
        explanation={{
          usagePreset: 'preset',
          skinPreset: 'preset',
          primaryColor: 'preset',
          backgroundColor: 'preset',
          logo: 'preset',
          exportDefaults: 'preset',
        }}
        onUsagePresetChange={() => undefined}
        availableStyles={[]}
        onCopyStyle={() => undefined}
        onResetSection={() => undefined}
      />,
    );

    expect(screen.getByText('Telao')).toBeInTheDocument();
    expect(screen.getByText(/Melhor para exibicao/i)).toBeInTheDocument();
    expect(screen.getByText(/Copiar visual/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Usar este visual/i })).toBeInTheDocument();
    expect(screen.getAllByTestId('qr-code-mini-preview').length).toBeGreaterThan(0);
  });
});
