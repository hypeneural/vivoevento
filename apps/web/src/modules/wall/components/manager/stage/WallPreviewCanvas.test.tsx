import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { ApiWallSettings } from '@/lib/api-types';

import { WallPreviewCanvas } from './WallPreviewCanvas';

const wallSettings: ApiWallSettings = {
  interval_ms: 8000,
  queue_limit: 50,
  selection_mode: 'balanced',
  event_phase: 'flow',
  selection_policy: {
    max_eligible_items_per_sender: 4,
    max_replays_per_item: 2,
    low_volume_max_items: 6,
    medium_volume_max_items: 12,
    replay_interval_low_minutes: 8,
    replay_interval_medium_minutes: 12,
    replay_interval_high_minutes: 20,
    sender_cooldown_seconds: 60,
    sender_window_limit: 3,
    sender_window_minutes: 10,
    avoid_same_sender_if_alternative_exists: true,
    avoid_same_duplicate_cluster_if_alternative_exists: true,
  },
  layout: 'fullscreen',
  transition_effect: 'fade',
  transition_mode: 'fixed',
  background_url: 'https://cdn.example.com/background.jpg',
  partner_logo_url: 'https://cdn.example.com/logo.png',
  show_qr: true,
  show_branding: true,
  show_neon: true,
  neon_text: 'Compartilhe seu momento',
  neon_color: '#ffcc00',
  show_sender_credit: true,
  show_side_thumbnails: true,
  accepted_orientation: 'all',
  ad_mode: 'disabled',
  ad_frequency: 5,
  ad_interval_minutes: 3,
  instructions_text: 'Envie sua foto',
};

describe('WallPreviewCanvas', () => {
  it('reaproveita o renderer visual do player com overlays reais de branding, QR e credito', () => {
    render(
      <WallPreviewCanvas
        settings={{
          ...wallSettings,
          transition_mode: 'random',
        }}
        primaryItem={{
          itemId: 'media-1',
          previewUrl: 'https://cdn.example.com/current.jpg',
          senderName: 'Carla',
          sourceType: 'telegram',
          isFeatured: true,
          caption: 'Noite principal',
        }}
        upcomingItems={[
          {
            itemId: 'media-2',
            previewUrl: 'https://cdn.example.com/upcoming-1.jpg',
            senderName: 'Ana',
          },
          {
            itemId: 'media-3',
            previewUrl: 'https://cdn.example.com/upcoming-2.jpg',
            senderName: 'Bruno',
          },
        ]}
      />,
    );

    expect(screen.getByLabelText(/Canvas da previa do rascunho/i)).toBeInTheDocument();
    expect(screen.getByText(/FOTO POR:/i)).toBeInTheDocument();
    expect(screen.getByText(/Envie sua foto/i)).toBeInTheDocument();
    expect(screen.getByText(/Compartilhe seu momento/i)).toBeInTheDocument();
    expect(screen.getByAltText(/Logo do parceiro/i)).toBeInTheDocument();
    expect(screen.getByAltText(/Ana/i)).toBeInTheDocument();
  });

  it('mantem a previa do puzzle previsivel com o mesmo renderer e sem miniaturas laterais', () => {
    render(
      <WallPreviewCanvas
        settings={{
          ...wallSettings,
          layout: 'puzzle',
          transition_mode: 'random',
          show_side_thumbnails: true,
          theme_config: {
            preset: 'compact',
          },
        }}
        primaryItem={{
          itemId: 'media-1',
          previewUrl: 'https://cdn.example.com/current.jpg',
          senderName: 'Carla',
          sourceType: 'telegram',
          isFeatured: true,
          caption: 'Noite principal',
        }}
        upcomingItems={[
          {
            itemId: 'media-2',
            previewUrl: 'https://cdn.example.com/upcoming-1.jpg',
            senderName: 'Ana',
          },
          {
            itemId: 'media-3',
            previewUrl: 'https://cdn.example.com/upcoming-2.jpg',
            senderName: 'Bruno',
          },
          {
            itemId: 'media-4',
            previewUrl: 'https://cdn.example.com/upcoming-3.jpg',
            senderName: 'Dani',
          },
          {
            itemId: 'media-5',
            previewUrl: 'https://cdn.example.com/upcoming-4.jpg',
            senderName: 'Eva',
          },
          {
            itemId: 'media-6',
            previewUrl: 'https://cdn.example.com/upcoming-5.jpg',
            senderName: 'Fabio',
          },
        ]}
      />,
    );

    expect(screen.getAllByTestId(/puzzle-piece-/)).toHaveLength(6);
    expect(screen.queryByTestId('wall-side-thumbnails-left')).not.toBeInTheDocument();
    expect(screen.queryByTestId('wall-side-thumbnails-right')).not.toBeInTheDocument();
  });
});
