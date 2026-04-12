import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { GalleryModeSwitch } from './components/GalleryModeSwitch';

describe('GalleryModeSwitch', () => {
  it('switches between quick and professional modes while keeping version context visible', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    render(
      <GalleryModeSwitch
        value="quick"
        onChange={onChange}
        draftVersion={7}
        publishedVersion={5}
        autosaveState="saved"
      />,
    );

    expect(screen.getByRole('button', { name: /modo rapido/i })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText('Draft v7')).toBeInTheDocument();
    expect(screen.getByText('Publicado v5')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /modo profissional/i }));

    expect(onChange).toHaveBeenCalledWith('professional');
  });
});
