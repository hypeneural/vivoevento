import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { createGalleryBuilderSettingsFixture } from './gallery-builder';
import { GalleryQuickStartWizard } from './components/GalleryQuickStartWizard';

describe('GalleryQuickStartWizard', () => {
  it('lets the operator choose a matrix and apply it as the quick start base', async () => {
    const user = userEvent.setup();
    const onApplySelection = vi.fn();

    render(
      <GalleryQuickStartWizard
        draft={createGalleryBuilderSettingsFixture()}
        onApplySelection={onApplySelection}
      />,
    );

    await user.click(screen.getByRole('button', { name: '15 anos' }));
    await user.click(screen.getByRole('button', { name: 'Moderno' }));
    await user.click(screen.getByRole('button', { name: 'Ao vivo' }));
    await user.click(screen.getByRole('button', { name: /aplicar base do evento/i }));

    expect(onApplySelection).toHaveBeenCalledWith({
      event_type_family: 'quince',
      style_skin: 'modern',
      behavior_profile: 'live',
    });
  });
});
