import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';
import type { ApiWallSettings } from '@/lib/api-types';

import { WallHeroStage } from './WallHeroStage';

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
  layout: 'auto',
  transition_effect: 'fade',
  background_url: null,
  partner_logo_url: null,
  show_qr: true,
  show_branding: true,
  show_neon: false,
  neon_text: null,
  neon_color: '#ffffff',
  show_sender_credit: true,
  show_side_thumbnails: true,
  accepted_orientation: 'all',
  ad_mode: 'disabled',
  ad_frequency: 5,
  ad_interval_minutes: 3,
  instructions_text: 'Envie sua foto',
};

describe('WallHeroStage', () => {
  it('destaca origem final e video com duracao no palco atual e na proxima exibicao', () => {
    render(
      <TooltipProvider>
        <WallHeroStage
          activeTab="live"
          onTabChange={vi.fn()}
          isLive
          isPaused={false}
          status="live"
          selectedMedia={null}
          liveSnapshot={{
            wallStatus: 'live',
            wallStatusLabel: 'Ao vivo',
            layout: 'auto',
            transitionEffect: 'fade',
            currentPlayer: {
              playerInstanceId: 'player-alpha',
              healthStatus: 'healthy',
              runtimeStatus: 'playing',
              connectionStatus: 'connected',
              lastSeenAt: '2026-04-09T04:20:00Z',
            },
            currentItem: {
              id: 'media-1',
              previewUrl: 'https://cdn.example.com/video-atual.jpg',
              senderName: 'Juliana',
              senderKey: 'upload:juliana',
              source: 'upload',
              caption: 'Video principal',
              layoutHint: 'split',
              isFeatured: false,
              isVideo: true,
              durationSeconds: 18,
              videoPolicyLabel: 'Video com duracao diferenciada',
              videoAdmission: {
                state: 'eligible',
                reasons: [],
                has_minimum_metadata: true,
                supported_format: true,
                preferred_variant_available: true,
                preferred_variant_key: 'wall_video_720p',
                poster_available: true,
                poster_variant_key: 'wall_video_poster',
                asset_source: 'wall_variant',
                duration_limit_seconds: 30,
              },
              createdAt: '2026-04-09T04:19:40Z',
            },
            nextItem: {
              id: 'media-2',
              previewUrl: 'https://cdn.example.com/video-proximo.jpg',
              senderName: 'Bruno',
              senderKey: 'whatsapp:5511999999999',
              source: 'whatsapp',
              caption: 'Proximo video',
              layoutHint: 'cinematic',
              isFeatured: false,
              isVideo: true,
              durationSeconds: 34,
              videoPolicyLabel: 'Video longo com politica especial',
              videoAdmission: {
                state: 'eligible_with_fallback',
                reasons: ['variant_missing'],
                has_minimum_metadata: true,
                supported_format: true,
                preferred_variant_available: false,
                preferred_variant_key: null,
                poster_available: true,
                poster_variant_key: 'wall_video_poster',
                asset_source: 'original',
                duration_limit_seconds: 30,
              },
              createdAt: '2026-04-09T04:19:20Z',
            },
            advancedAt: '2026-04-09T04:19:52Z',
            updatedAt: '2026-04-09T04:20:00Z',
          }}
          eventTitle="Evento teste"
          eventSchedule="09/04/2026, 20:00"
          wallCode="ABCD1234"
          copied={false}
          onCopyWallCode={vi.fn()}
          hasUnsavedChanges={false}
          onOpenSelectedMediaDetails={vi.fn()}
          wallSettings={wallSettings}
          selectionSummary="A fila alterna remetentes quando ha alternativa."
          simulationSummary={null}
          simulationPreview={[]}
          simulationExplanation={[]}
          isSimulationLoading={false}
          isSimulationError={false}
          isSimulationRefreshing={false}
          isSimulationDraftPending={false}
        />
      </TooltipProvider>,
    );

    expect(screen.getAllByText(/^Upload$/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/WhatsApp - Video 34s - Video longo com politica especial/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Video 18s/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Video 34s/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Video com duracao diferenciada/i)).toBeInTheDocument();
    expect(screen.getByText(/Video longo com politica especial/i)).toBeInTheDocument();
    expect(screen.getByText(/WhatsApp - Video 34s - Video longo com politica especial - Elegivel com fallback/i)).toBeInTheDocument();
  });

  it('mantem contexto operacional quando o player ainda nao confirmou item atual e proximo', () => {
    render(
      <TooltipProvider>
        <WallHeroStage
          activeTab="live"
          onTabChange={vi.fn()}
          isLive
          isPaused={false}
          status="live"
          selectedMedia={null}
          liveSnapshot={{
            wallStatus: 'live',
            wallStatusLabel: 'Ao vivo',
            layout: 'auto',
            transitionEffect: 'fade',
            currentPlayer: {
              playerInstanceId: 'player-alpha',
              healthStatus: 'warning',
              runtimeStatus: 'playing',
              connectionStatus: 'connecting',
              lastSeenAt: '2026-04-09T04:20:00Z',
            },
            currentItem: null,
            nextItem: null,
            advancedAt: null,
            updatedAt: '2026-04-09T04:20:00Z',
          }}
          eventTitle="Evento teste"
          eventSchedule="09/04/2026, 20:00"
          wallCode="ABCD1234"
          copied={false}
          onCopyWallCode={vi.fn()}
          hasUnsavedChanges={false}
          onOpenSelectedMediaDetails={vi.fn()}
          wallSettings={wallSettings}
          selectionSummary="A fila alterna remetentes quando ha alternativa."
          simulationSummary={null}
          simulationPreview={[]}
          simulationExplanation={[]}
          isSimulationLoading={false}
          isSimulationError={false}
          isSimulationRefreshing={false}
          isSimulationDraftPending={false}
        />
      </TooltipProvider>,
    );

    expect(screen.getByText(/Telao ativo aguardando a primeira confirmacao do player/i)).toBeInTheDocument();
    expect(screen.getByText(/Sincronizando a midia atual do telao\./i)).toBeInTheDocument();
    expect(screen.getByText(/A proxima exibicao aparece assim que a fila confirmar a ordem\./i)).toBeInTheDocument();
  });
});
