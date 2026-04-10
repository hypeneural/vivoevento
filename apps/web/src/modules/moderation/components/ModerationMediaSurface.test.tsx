import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import type { ApiEventMediaItem } from '@/lib/api-types';

import { ModerationMediaSurface } from './ModerationMediaSurface';

const { trackTelemetryMock } = vi.hoisted(() => ({
  trackTelemetryMock: vi.fn().mockResolvedValue(null),
}));

vi.mock('../services/moderation.service', () => ({
  moderationService: {
    trackTelemetry: trackTelemetryMock,
  },
}));

function makeMedia(overrides: Partial<ApiEventMediaItem> = {}): ApiEventMediaItem {
  return {
    id: 1,
    event_id: 10,
    event_title: 'Evento',
    event_slug: 'evento',
    event_status: 'active',
    event_moderation_mode: 'ai',
    media_type: 'image',
    channel: 'upload',
    status: 'pending_moderation',
    processing_status: 'processed',
    moderation_status: 'pending',
    publication_status: 'draft',
    sender_name: 'Convidado',
    caption: 'Legenda',
    thumbnail_url: 'https://example.test/thumb.jpg',
    thumbnail_source: 'thumb',
    preview_url: 'https://example.test/preview.jpg',
    preview_source: 'fast_preview',
    moderation_thumbnail_url: 'https://example.test/moderation-thumb.jpg',
    moderation_thumbnail_source: 'moderation_thumb',
    moderation_preview_url: 'https://example.test/moderation-preview.jpg',
    moderation_preview_source: 'moderation_preview',
    original_url: 'https://example.test/original.jpg',
    created_at: '2026-04-09T12:00:00Z',
    updated_at: '2026-04-09T12:00:10Z',
    published_at: null,
    is_featured: false,
    is_pinned: false,
    sort_order: 0,
    orientation: 'portrait',
    ...overrides,
  };
}

describe('ModerationMediaSurface', () => {
  afterEach(() => {
    trackTelemetryMock.mockClear();
  });

  it('keeps a loading placeholder until the image finishes loading', () => {
    render(
      <ModerationMediaSurface
        media={makeMedia()}
        variant="thumbnail"
        className="h-40 w-40"
      />,
    );

    expect(screen.getByTestId('media-surface-loading')).toBeInTheDocument();

    fireEvent.load(screen.getByRole('img'));

    expect(screen.queryByTestId('media-surface-loading')).not.toBeInTheDocument();
  });

  it('renders a controlled fallback instead of broken browser chrome when the asset fails', () => {
    render(
      <ModerationMediaSurface
        media={makeMedia()}
        variant="thumbnail"
        className="h-40 w-40"
      />,
    );

    fireEvent.error(screen.getByRole('img'));

    expect(screen.getByTestId('media-surface-fallback')).toBeInTheDocument();
    expect(screen.getByText(/preview indisponivel/i)).toBeInTheDocument();
    expect(trackTelemetryMock).toHaveBeenCalledWith(expect.objectContaining({
      event: 'media_surface_error',
      media_id: 1,
      surface_variant: 'thumbnail',
    }));
  });

  it('uses the video preview as the playback source and the thumbnail as poster when available', () => {
    const media = makeMedia({
      media_type: 'video',
      mime_type: 'video/mp4',
      thumbnail_url: 'https://example.test/poster.jpg',
      thumbnail_source: 'wall_video_poster',
      preview_url: 'https://example.test/preview.mp4',
      preview_source: 'wall_video_720p',
      moderation_thumbnail_url: 'https://example.test/moderation-poster.jpg',
      moderation_thumbnail_source: 'wall_video_poster',
      moderation_preview_url: 'https://example.test/moderation-preview.mp4',
      moderation_preview_source: 'wall_video_720p',
      original_url: 'https://example.test/original.mp4',
    });

    const { container } = render(
      <ModerationMediaSurface
        media={media}
        variant="preview"
        className="h-40 w-40"
      />,
    );

    const previewVideo = container.querySelector('video');

    expect(previewVideo).not.toBeNull();
    expect(previewVideo?.getAttribute('src')).toBe('https://example.test/moderation-preview.mp4');
    expect(previewVideo?.getAttribute('poster')).toBe('https://example.test/moderation-poster.jpg');
  });

  it('shows the controlled fallback when the moderation profile is present but no dedicated assets exist', () => {
    render(
      <ModerationMediaSurface
        media={makeMedia({
          thumbnail_url: 'https://example.test/original-thumb.jpg',
          thumbnail_source: 'original',
          preview_url: 'https://example.test/original-preview.jpg',
          preview_source: 'original',
          moderation_thumbnail_url: null,
          moderation_thumbnail_source: null,
          moderation_preview_url: null,
          moderation_preview_source: null,
        })}
        variant="preview"
        className="h-40 w-40"
      />,
    );

    expect(screen.getByTestId('media-surface-fallback')).toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(trackTelemetryMock).toHaveBeenCalledWith(expect.objectContaining({
      event: 'media_surface_unavailable',
      media_id: 1,
      surface_variant: 'preview',
    }));
  });

  it('tracks when the moderation surface falls back to an original-backed asset', () => {
    render(
      <ModerationMediaSurface
        media={makeMedia({
          moderation_thumbnail_url: undefined,
          moderation_thumbnail_source: undefined,
          moderation_preview_url: undefined,
          moderation_preview_source: undefined,
          thumbnail_url: 'https://example.test/original-thumb.jpg',
          thumbnail_source: 'original',
          preview_url: null,
          preview_source: null,
        })}
        variant="thumbnail"
        className="h-40 w-40"
      />,
    );

    expect(trackTelemetryMock).toHaveBeenCalledWith(expect.objectContaining({
      event: 'media_surface_original_fallback',
      media_id: 1,
      asset_source: 'original',
      surface_variant: 'thumbnail',
    }));
  });
});
