import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { GalleryRenderer } from './components/GalleryRenderer';
import { galleryExperienceFixtures } from './gallery-builder';
import type { ApiEventMediaItem } from '@/lib/api-types';

const item: ApiEventMediaItem = {
  id: 1,
  event_id: 10,
  media_type: 'image',
  channel: 'upload',
  status: 'published',
  processing_status: null,
  moderation_status: 'approved',
  publication_status: 'published',
  sender_name: 'Convidado',
  caption: 'Foto acessivel',
  thumbnail_url: 'https://cdn.eventovivo.test/thumb.webp',
  preview_url: 'https://cdn.eventovivo.test/preview.webp',
  original_url: null,
  created_at: null,
  published_at: null,
  is_featured: false,
  width: 1200,
  height: 800,
  orientation: 'landscape',
};

describe('public gallery accessibility', () => {
  it('renders clickable media with accessible names and reduced-motion-aware root metadata', () => {
    render(<GalleryRenderer media={[item]} experience={galleryExperienceFixtures.weddingPremiumLight} />);

    expect(screen.getByRole('button', { name: /abrir foto acessivel/i })).toBeInTheDocument();
    expect(screen.getByTestId('gallery-renderer')).toHaveAttribute('data-respect-reduced-motion', 'true');
  });
});
