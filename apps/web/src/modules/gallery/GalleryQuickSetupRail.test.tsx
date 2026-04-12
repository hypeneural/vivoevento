import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import {
  createGalleryBuilderOperationalFeedbackFixture,
  createGalleryBuilderSettingsFixture,
  galleryContractCatalog,
} from './gallery-builder';
import { GalleryQuickSetupRail } from './components/GalleryQuickSetupRail';

describe('GalleryQuickSetupRail', () => {
  it('shows the current quick setup summary, budgets and suggested shortcuts', async () => {
    const user = userEvent.setup();
    const onApplyShortcut = vi.fn();

    render(
      <GalleryQuickSetupRail
        draft={createGalleryBuilderSettingsFixture()}
        mobileBudget={galleryContractCatalog.mobileBudget}
        responsiveSizes={galleryContractCatalog.publicResponsiveSizes}
        operationalFeedback={createGalleryBuilderOperationalFeedbackFixture({
          current_preset_origin: {
            origin_type: 'preset',
            key: 'casamento-premium',
            label: 'Casamento premium',
            applied_at: '2026-04-12T12:00:00Z',
            applied_by: {
              id: 9,
              name: 'Operador',
            },
          },
        })}
        onApplyShortcut={onApplyShortcut}
      />,
    );

    expect(screen.getByText('Casamento / Romantico / Historia')).toBeInTheDocument();
    expect(screen.getByText(/LCP <= 2500ms/i)).toBeInTheDocument();
    expect(screen.getByText(/Casamento premium/i)).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /premium light/i }));

    expect(onApplyShortcut).toHaveBeenCalledWith('weddingPremiumLight');
  });
});
