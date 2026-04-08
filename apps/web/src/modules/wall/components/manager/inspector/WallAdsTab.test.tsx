import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { WallAdsTab } from './WallAdsTab';

describe('WallAdsTab', () => {
  it('renderiza os criativos ativos e dispara acoes do tab de anuncios', () => {
    const onDraftChange = vi.fn();
    const onAdFileChange = vi.fn();
    const onAdDurationChange = vi.fn();
    const onUploadAd = vi.fn();
    const onResetAdUploadForm = vi.fn();
    const onDeleteAd = vi.fn();
    const onMoveAd = vi.fn();

    render(
      <WallAdsTab
        wallSettings={{
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
          layout: 'auto',
          transition_effect: 'fade',
          background_url: null,
          partner_logo_url: null,
          show_qr: true,
          show_branding: true,
          show_neon: false,
          neon_text: null,
          neon_color: '#ffffff',
          show_sender_credit: false,
          show_side_thumbnails: true,
          accepted_orientation: 'all',
          ad_mode: 'by_photos',
          ad_frequency: 5,
          ad_interval_minutes: 3,
          instructions_text: 'Envie sua foto',
        }}
        wallAds={[
          {
            id: 1,
            url: 'https://cdn.example.com/ad-1.jpg',
            media_type: 'image',
            duration_seconds: 10,
            position: 0,
          },
        ]}
        adsLoading={false}
        uploadPending={false}
        deletePending={false}
        reorderPending={false}
        selectedAdFile={null}
        selectedAdDuration="10"
        selectedAdIsVideo={false}
        adFileInputRef={{ current: null }}
        onDraftChange={onDraftChange}
        onAdFileChange={onAdFileChange}
        onAdDurationChange={onAdDurationChange}
        onUploadAd={onUploadAd}
        onResetAdUploadForm={onResetAdUploadForm}
        onDeleteAd={onDeleteAd}
        onMoveAd={onMoveAd}
      />,
    );

    expect(screen.getByText(/Patrocinadores no telao/i)).toBeInTheDocument();
    expect(screen.getByText(/Patrocinador 1/i)).toBeInTheDocument();

    fireEvent.change(screen.getByDisplayValue('5'), {
      target: { value: '7' },
    });
    expect(onDraftChange).toHaveBeenCalledWith('ad_frequency', 7);

    fireEvent.click(screen.getByRole('button', { name: /Remover anuncio 1/i }));
    expect(onDeleteAd).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
  });
});
