import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { GalleryVideoModal } from './components/GalleryVideoModal';
import type { ApiEventMediaItem } from '@/lib/api-types';

const video: ApiEventMediaItem = {
  id: 8,
  event_id: 10,
  media_type: 'video',
  mime_type: 'video/mp4',
  channel: 'upload',
  status: 'published',
  processing_status: null,
  moderation_status: 'approved',
  publication_status: 'published',
  sender_name: 'Convidado',
  caption: 'Video do evento',
  thumbnail_url: 'https://cdn.eventovivo.test/poster.webp',
  preview_url: 'https://cdn.eventovivo.test/video.mp4',
  original_url: 'https://cdn.eventovivo.test/original.mp4',
  created_at: null,
  published_at: null,
  is_featured: false,
  width: 1280,
  height: 720,
  orientation: 'landscape',
};

describe('GalleryVideoModal', () => {
  it('renders a dedicated video player with poster and controls', () => {
    render(<GalleryVideoModal media={video} open onOpenChange={vi.fn()} />);

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Video do evento')).toBeInTheDocument();
    expect(screen.getByLabelText('Player do video')).toHaveAttribute('controls');
    expect(screen.getByLabelText('Player do video')).toHaveAttribute('poster', video.thumbnail_url);
    expect(screen.getByLabelText('Player do video')).toHaveAttribute('src', video.preview_url);
  });
});
