import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { createGalleryBuilderSettingsFixture } from './gallery-builder';
import { GalleryPreviewFrame } from './components/GalleryPreviewFrame';

vi.mock('./components/GalleryRenderer', () => ({
  GalleryRenderer: () => <div data-testid="gallery-renderer">Renderer real reutilizado</div>,
}));

describe('GalleryPreviewFrame', () => {
  it('renders the central preview with hero and public renderer reuse', () => {
    render(
      <GalleryPreviewFrame
        event={{
          id: 42,
          title: 'Casamento Ana e Leo',
          slug: 'casamento-ana-leo',
        }}
        draft={createGalleryBuilderSettingsFixture()}
        media={[]}
        viewport="mobile"
      />,
    );

    expect(screen.getByTestId('gallery-preview-frame')).toHaveAttribute('data-viewport', 'mobile');
    expect(screen.getByText('Casamento Ana e Leo')).toBeInTheDocument();
    expect(screen.getByText(/Encontrar minhas fotos/i)).toBeInTheDocument();
    expect(screen.getByTestId('gallery-renderer')).toBeInTheDocument();
  });
});
