import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { GalleryPhotoLightbox, type GalleryLightboxPhoto } from './components/GalleryPhotoLightbox';

vi.mock('photoswipe/lightbox', () => ({
  default: class PhotoSwipeLightboxMock {
    init = vi.fn();
    destroy = vi.fn();
    loadAndOpen = vi.fn();
    on = vi.fn();
  },
}));

const photos: GalleryLightboxPhoto[] = [
  {
    src: 'https://cdn.eventovivo.test/gallery.webp',
    msrc: 'https://cdn.eventovivo.test/thumb.webp',
    width: 1200,
    height: 800,
    alt: 'Foto aberta',
    srcset: 'https://cdn.eventovivo.test/gallery.webp 1200w',
    sizes: '(max-width: 640px) 50vw, 25vw',
  },
];

describe('GalleryPhotoLightbox', () => {
  it('renders an accessible fallback while PhotoSwipe is responsible for the photo lightbox', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(<GalleryPhotoLightbox photos={photos} activeIndex={0} onClose={onClose} />);

    expect(screen.getByRole('dialog', { name: /foto aberta/i })).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'Foto aberta' })).toHaveAttribute(
      'src',
      'https://cdn.eventovivo.test/gallery.webp',
    );

    await user.click(screen.getByRole('button', { name: /fechar foto/i }));
    expect(onClose).toHaveBeenCalled();
  });
});
