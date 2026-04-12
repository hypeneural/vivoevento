import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { createGalleryBuilderSettingsFixture } from './gallery-builder';
import { GalleryPreviewFrame } from './components/GalleryPreviewFrame';

vi.mock('./components/GalleryRenderer', () => ({
  GalleryRenderer: () => <div data-testid="gallery-renderer">Renderer real reutilizado</div>,
}));

describe('GalleryPreviewFrame', () => {
  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: vi.fn().mockImplementation((query: string) => ({
        matches: query === '(prefers-reduced-motion: reduce)',
        media: query,
        onchange: null,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        addListener: vi.fn(),
        removeListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    });
  });

  it('renders the central preview with hero, reduced-motion state and public renderer reuse', () => {
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
        renderMode="optimized"
      />,
    );

    expect(screen.getByTestId('gallery-preview-frame')).toHaveAttribute('data-viewport', 'mobile');
    expect(screen.getByTestId('gallery-preview-frame')).toHaveAttribute('data-render-mode', 'optimized');
    expect(screen.getByTestId('gallery-preview-frame')).toHaveAttribute('data-reduced-motion', 'true');
    expect(screen.getByTestId('gallery-preview-frame')).toHaveAttribute('role', 'region');
    expect(screen.getByText('Casamento Ana e Leo')).toBeInTheDocument();
    expect(screen.getByText(/Encontrar minhas fotos/i)).toBeInTheDocument();
    expect(screen.getByText(/Modo otimizado/i)).toBeInTheDocument();
    expect(screen.getByTestId('gallery-renderer')).toBeInTheDocument();
  });
});
