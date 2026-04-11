import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { FormProvider, useForm, useWatch } from 'react-hook-form';
import { describe, expect, it, vi } from 'vitest';

import { QrCodeLogoPanel } from '@/modules/qr-code/components/QrCodeLogoPanel';
import { buildQrConfigDefaults } from '@/modules/qr-code/support/qrDefaults';
import type { EventPublicLinkQrConfig } from '@/modules/qr-code/support/qrTypes';

function LogoPanelHarness({
  onUploadCustomLogo,
}: {
  onUploadCustomLogo?: (file: File, previousPath?: string | null) => Promise<{ path: string; url: string }>;
}) {
  const form = useForm<EventPublicLinkQrConfig>({
    defaultValues: buildQrConfigDefaults({ linkKey: 'gallery' }),
  });

  const mode = useWatch({ control: form.control, name: 'logo.mode' });
  const assetUrl = useWatch({ control: form.control, name: 'logo.asset_url' });

  return (
    <FormProvider {...form}>
      <div>
        <QrCodeLogoPanel
          effectiveBranding={{
            logo_path: null,
            logo_url: 'https://cdn.example.com/event-logo.png',
            cover_image_path: null,
            cover_image_url: null,
            primary_color: '#112233',
            secondary_color: '#445566',
            source: 'event',
            inherits_from_organization: false,
          }}
          explanation={{
            usagePreset: 'preset',
            skinPreset: 'preset',
            primaryColor: 'preset',
            backgroundColor: 'preset',
            logo: 'event',
            exportDefaults: 'preset',
          }}
          onResetSection={vi.fn()}
          onUploadCustomLogo={onUploadCustomLogo}
        />
        <output data-testid="logo-mode">{mode}</output>
        <output data-testid="logo-asset-url">{assetUrl ?? 'empty'}</output>
      </div>
    </FormProvider>
  );
}

describe('QrCodeLogoPanel', () => {
  it('uploads a custom logo and writes the semantic fields back to the form', async () => {
    const onUploadCustomLogo = vi.fn().mockResolvedValue({
      path: 'events/branding/9/logo/qr-logo.webp',
      url: 'https://cdn.example.com/events/branding/9/logo/qr-logo.webp',
    });

    const { container } = render(
      <LogoPanelHarness onUploadCustomLogo={onUploadCustomLogo} />,
    );

    const input = container.querySelector('input[type="file"]');
    expect(input).not.toBeNull();

    const file = new File(['logo'], 'logo.png', { type: 'image/png' });

    fireEvent.change(input!, {
      target: {
        files: [file],
      },
    });

    await waitFor(() => {
      expect(onUploadCustomLogo).toHaveBeenCalledWith(file, null);
    });

    await waitFor(() => {
      expect(screen.getByTestId('logo-mode')).toHaveTextContent('custom');
      expect(screen.getByTestId('logo-asset-url')).toHaveTextContent('https://cdn.example.com/events/branding/9/logo/qr-logo.webp');
    });
  });
});
