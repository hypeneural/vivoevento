import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { ApiEventMediaItem } from '@/lib/api-types';

import { GallerySenderActions } from './GallerySenderActions';

const media: ApiEventMediaItem = {
  id: 10,
  event_id: 42,
  event_title: 'Evento Vivo',
  event_slug: 'evento-vivo',
  event_status: 'active',
  event_moderation_mode: 'manual',
  event_face_search_enabled: false,
  event_allow_public_selfie_search: false,
  media_type: 'image',
  channel: 'whatsapp',
  status: 'published',
  processing_status: 'processed',
  moderation_status: 'approved',
  publication_status: 'published',
  sender_name: 'Ana Martins',
  sender_avatar_url: 'https://cdn.eventovivo.test/ana.jpg',
  sender_phone: '554899999999',
  sender_lid: '11111111111111@lid',
  sender_external_id: '11111111111111@lid',
  sender_blocked: false,
  sender_blocking_entry_id: null,
  sender_block_reason: null,
  sender_block_expires_at: null,
  sender_blacklist_enabled: true,
  sender_recommended_identity_type: 'lid',
  sender_recommended_identity_value: '11111111111111@lid',
  sender_recommended_normalized_phone: null,
  sender_media_count: 2,
  caption: 'Legenda',
  thumbnail_url: 'https://cdn.eventovivo.test/thumb.jpg',
  preview_url: null,
  original_url: null,
  created_at: '2026-04-06T18:00:00.000Z',
  published_at: '2026-04-06T18:05:00.000Z',
  is_featured: false,
  is_pinned: false,
  sort_order: 0,
};

describe('GallerySenderActions', () => {
  it('shows sender status and triggers quick block', () => {
    const onToggle = vi.fn();

    render(
      <GallerySenderActions
        media={media}
        canManage
        duration="7d"
        onDurationChange={vi.fn()}
        onToggle={onToggle}
      />,
    );

    expect(screen.getByText(/remetente ativo/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /bloquear/i }));

    expect(onToggle).toHaveBeenCalledWith(true);
  });
});
