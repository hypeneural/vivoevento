import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { MemoryRouter } from 'react-router-dom';

import type { ApiEventMediaDetail } from '@/lib/api-types';

import { ModerationReviewPanel } from './ModerationReviewPanel';

const media: ApiEventMediaDetail = {
  id: 101,
  event_id: 42,
  event_title: 'Casamento no Campo',
  event_slug: 'casamento-no-campo',
  event_status: 'active',
  event_moderation_mode: 'ai',
  event_face_search_enabled: false,
  event_allow_public_selfie_search: false,
  media_type: 'image',
  channel: 'upload',
  status: 'pending_moderation',
  processing_status: 'processed',
  moderation_status: 'pending',
  publication_status: 'draft',
  safety_status: 'pass',
  face_index_status: 'skipped',
  vlm_status: 'completed',
  decision_source: 'ai_vlm',
  decision_overridden_at: null,
  decision_overridden_by_user_id: null,
  decision_override_reason: null,
  pipeline_version: 'media_ai_foundation_v1',
  mime_type: 'image/jpeg',
  original_filename: 'entrada.jpg',
  client_filename: 'entrada.jpg',
  duplicate_group_key: null,
  is_duplicate_candidate: false,
  sender_name: 'Maria',
  sender_avatar_url: 'https://example.test/avatar.jpg',
  sender_phone: '554899999999',
  sender_lid: '11111111111111@lid',
  sender_external_id: '11111111111111@lid',
  sender_blocked: true,
  sender_blocking_entry_id: 8,
  sender_block_reason: 'Bloqueado pela equipe',
  sender_block_expires_at: '2026-04-09T18:00:00Z',
  sender_blacklist_enabled: true,
  sender_recommended_identity_type: 'lid',
  sender_recommended_identity_value: '11111111111111@lid',
  sender_recommended_normalized_phone: null,
  sender_media_count: 4,
  caption: 'Legenda atual',
  thumbnail_url: 'https://example.test/thumb.jpg',
  thumbnail_source: 'thumb',
  preview_url: 'https://example.test/preview.jpg',
  preview_source: 'fast_preview',
  original_url: 'https://example.test/original.jpg',
  created_at: '2026-04-02T18:00:00Z',
  updated_at: '2026-04-02T18:00:05Z',
  published_at: null,
  is_featured: false,
  is_pinned: false,
  sort_order: 0,
  width: 1080,
  height: 1350,
  orientation: 'portrait',
  title: null,
  source_label: null,
  perceptual_hash: null,
  size_bytes: 2048,
  duration_seconds: null,
  decision_override: null,
  variants: [],
  processing_runs: [],
  latest_safety_evaluation: {
    id: 1,
    decision: 'pass',
    blocked: false,
    review_required: false,
    category_scores: {
      nudity: 0.01,
      violence: 0.03,
    },
    reason_codes: [],
    completed_at: '2026-04-02T18:00:02Z',
  },
  latest_vlm_evaluation: {
    id: 2,
    decision: 'approve',
    review_required: false,
    reason: 'Imagem compativel com o evento.',
    short_caption: 'Entrada especial na festa.',
    tags: ['festa', 'retrato'],
    mode_applied: 'enrich_only',
    completed_at: '2026-04-02T18:00:03Z',
  },
};

describe('ModerationReviewPanel', () => {
  it('renders structured safety and vlm details when available', () => {
    render(
      <MemoryRouter>
        <ModerationReviewPanel
          media={media}
          canModerate
          isBusy={() => false}
          onAction={vi.fn()}
          onOpenPreview={vi.fn()}
        />
      </MemoryRouter>,
    );

    expect(screen.getByText(/leitura ia/i)).toBeInTheDocument();
    expect(screen.getByText('safety: pass')).toBeInTheDocument();
    expect(screen.getByText('vlm: completed')).toBeInTheDocument();
    expect(screen.getByText(/imagem compativel com o evento\./i)).toBeInTheDocument();
    expect(screen.getByText(/legenda ia: entrada especial na festa\./i)).toBeInTheDocument();
    expect(screen.getByText('festa')).toBeInTheDocument();
    expect(screen.getByText('violence: 0.030')).toBeInTheDocument();
    expect(screen.getByText(/midias deste remetente/i)).toBeInTheDocument();
    expect(screen.getByText('11111111111111@lid')).toBeInTheDocument();
    expect(screen.getByText(/bloqueado ate/i)).toBeInTheDocument();
  });

  it('shows the sender name and normalized channel in the review surface', () => {
    render(
      <MemoryRouter>
        <ModerationReviewPanel
          media={media}
          canModerate
          isBusy={() => false}
          onAction={vi.fn()}
          onOpenPreview={vi.fn()}
        />
      </MemoryRouter>,
    );

    expect(screen.getAllByText('Maria').length).toBeGreaterThan(0);
    expect(screen.getByText('Upload')).toBeInTheDocument();
    expect(screen.getByText('11111111111111@lid')).toBeInTheDocument();
  });

  it('renders a video surface in the review panel when the selected media is a video', () => {
    const videoMedia: ApiEventMediaDetail = {
      ...media,
      media_type: 'video',
      mime_type: 'video/mp4',
      thumbnail_url: 'https://example.test/poster.jpg',
      preview_url: 'https://example.test/preview.mp4',
      original_url: 'https://example.test/original.mp4',
      duration_seconds: 12,
    };

    const { container } = render(
      <MemoryRouter>
        <ModerationReviewPanel
          media={videoMedia}
          canModerate
          isBusy={() => false}
          onAction={vi.fn()}
          onOpenPreview={vi.fn()}
        />
      </MemoryRouter>,
    );

    const previewVideo = container.querySelector('button video');

    expect(previewVideo).not.toBeNull();
    expect(previewVideo?.getAttribute('src')).toBe('https://example.test/preview.mp4');
    expect(previewVideo?.getAttribute('poster')).toBe('https://example.test/poster.jpg');
  });

  it('allows toggling the sender block switch from the review panel', () => {
    const onSenderBlockToggle = vi.fn();

    render(
      <MemoryRouter>
        <ModerationReviewPanel
          media={media}
          canModerate
          isBusy={() => false}
          onAction={vi.fn()}
          onOpenPreview={vi.fn()}
          senderBlockDuration="7d"
          onSenderBlockDurationChange={vi.fn()}
          onSenderBlockToggle={onSenderBlockToggle}
        />
      </MemoryRouter>,
    );

    fireEvent.click(screen.getByRole('switch'));

    expect(onSenderBlockToggle).toHaveBeenCalledWith(false);
  });
});
