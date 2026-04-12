import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { createGalleryBuilderSettingsFixture } from './gallery-builder';
import { GalleryContextInspector } from './components/GalleryContextInspector';

describe('GalleryContextInspector', () => {
  it('surfaces the current matrix, guardrails and active block count', () => {
    render(
      <GalleryContextInspector
        event={{
          id: 42,
          title: 'Casamento Ana e Leo',
          slug: 'casamento-ana-leo',
        }}
        draft={createGalleryBuilderSettingsFixture()}
        autosaveState="saved"
      />,
    );

    expect(screen.getByText('Casamento Ana e Leo')).toBeInTheDocument();
    expect(screen.getByText('Casamento / Romantico / Historia')).toBeInTheDocument();
    expect(screen.getByText(/Preview obrigatorio antes de publicar/i)).toBeInTheDocument();
    expect(screen.getByText(/Blocos ativos/i)).toBeInTheDocument();
  });
});
