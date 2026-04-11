import { fireEvent, render, screen } from '@testing-library/react';
import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';
import type { ApiWallOptionsResponse, ApiWallSettings } from '@/lib/api-types';

import { fallbackOptions } from '../../../manager-config';
import { WallAppearanceTab } from './WallAppearanceTab';

function makeSettings(overrides?: Partial<ApiWallSettings>): ApiWallSettings {
  return {
    interval_ms: 8000,
    queue_limit: 50,
    selection_mode: 'balanced',
    event_phase: 'flow',
    selection_policy: fallbackOptions.selection_modes[0].selection_policy,
    theme_config: {},
    layout: 'auto',
    transition_effect: 'fade',
    transition_mode: 'fixed',
    transition_pool: null,
    background_url: null,
    partner_logo_url: null,
    show_qr: true,
    show_branding: true,
    show_neon: true,
    neon_text: 'Compartilhe o melhor momento da noite',
    neon_color: '#ffffff',
    show_sender_credit: false,
    show_side_thumbnails: true,
    accepted_orientation: 'all',
    video_enabled: true,
    public_upload_video_enabled: true,
    private_inbound_video_enabled: true,
    video_playback_mode: 'play_to_end_if_short_else_cap',
    video_max_seconds: 30,
    video_resume_mode: 'resume_if_same_item_else_restart',
    video_audio_policy: 'muted',
    video_multi_layout_policy: 'disallow',
    video_preferred_variant: 'wall_video_720p',
    ad_mode: 'disabled',
    ad_frequency: 5,
    ad_interval_minutes: 3,
    instructions_text: 'Envie sua foto',
    ...overrides,
  };
}

function makeOptionsWithPuzzle(): ApiWallOptionsResponse {
  return {
    ...fallbackOptions,
    layouts: [
      ...fallbackOptions.layouts,
      {
        value: 'puzzle',
        label: 'Quebra Cabeca',
        capabilities: {
          supports_video_playback: false,
          supports_video_poster_only: false,
          supports_multi_video: false,
          max_simultaneous_videos: 0,
          fallback_video_layout: 'cinematic',
          supports_side_thumbnails: false,
          supports_floating_caption: false,
          supports_theme_config: true,
        },
        defaults: {
          theme_config: {
            preset: 'standard',
            anchor_mode: 'event_brand',
            burst_intensity: 'normal',
            hero_enabled: true,
            video_behavior: 'fallback_single_item',
          },
        },
      },
    ],
  };
}

describe('WallAppearanceTab', () => {
  const originalScrollIntoView = HTMLElement.prototype.scrollIntoView;

  beforeAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      value: vi.fn(),
    });
  });

  afterAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      value: originalScrollIntoView,
    });
  });

  it('renderiza os controles de aparencia e propaga alteracoes do rascunho', () => {
    const onDraftChange = vi.fn();

    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={makeSettings()}
          options={fallbackOptions}
          videoPolicySummary="Resumo"
          onDraftChange={onDraftChange}
        />
      </TooltipProvider>,
    );

    expect(screen.getAllByText(/Ajustes da exibicao/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Visual e troca de fotos/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/Rollout publico/i)).toBeInTheDocument();
    expect(screen.getByText(/Rollout privado/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Mensagem quando nao ha fotos/i).length).toBeGreaterThan(0);

    fireEvent.change(screen.getByDisplayValue(/Compartilhe o melhor momento da noite/i), {
      target: { value: 'Nova chamada visual' },
    });

    expect(onDraftChange).toHaveBeenCalledWith('neon_text', 'Nova chamada visual');
  });

  it('expoe transition_mode no editor para layouts single-item', async () => {
    const onDraftChange = vi.fn();

    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={makeSettings({
            layout: 'fullscreen',
            transition_mode: 'fixed',
          })}
          options={fallbackOptions}
          videoPolicySummary="Resumo"
          onDraftChange={onDraftChange}
        />
      </TooltipProvider>,
    );

    const trigger = screen.getByTestId('wall-transition-mode-select');

    expect(trigger).toBeInTheDocument();
    fireEvent.mouseDown(trigger);
    fireEvent.keyDown(trigger, { key: 'ArrowDown' });
    fireEvent.click(await screen.findByRole('option', { name: /aleatoria/i }));

    expect(onDraftChange).toHaveBeenCalledWith('transition_mode', 'random');
    expect(screen.queryByTestId('wall-transition-mode-locked')).not.toBeInTheDocument();
  });

  it('expoe transition_pool custom apenas quando o slideshow single-item esta em modo random', () => {
    const onDraftChange = vi.fn();

    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={makeSettings({
            layout: 'fullscreen',
            transition_mode: 'random',
            transition_pool: ['fade', 'slide'],
          })}
          options={fallbackOptions}
          videoPolicySummary="Resumo"
          onDraftChange={onDraftChange}
        />
      </TooltipProvider>,
    );

    expect(screen.getByTestId('wall-transition-pool')).toBeInTheDocument();
    expect(screen.getByTestId('wall-transition-pool-toggle-slide')).toBeInTheDocument();
    expect(screen.queryByTestId('wall-transition-pool-toggle-none')).not.toBeInTheDocument();

    fireEvent.click(screen.getByTestId('wall-transition-pool-toggle-slide'));

    expect(onDraftChange).toHaveBeenCalledWith('transition_pool', ['fade']);
  });

  it('bloqueia transition_mode random para layouts board no editor', () => {
    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={makeSettings({
            layout: 'grid',
            transition_mode: 'fixed',
          })}
          options={fallbackOptions}
          videoPolicySummary="Resumo"
          onDraftChange={vi.fn()}
        />
      </TooltipProvider>,
    );

    expect(screen.getByTestId('wall-transition-mode-locked')).toBeInTheDocument();
    expect(screen.getByText(/layouts em grade usam troca fixa/i)).toBeInTheDocument();
    expect(screen.queryByTestId('wall-transition-mode-select')).not.toBeInTheDocument();
    expect(screen.queryByTestId('wall-transition-pool')).not.toBeInTheDocument();
  });

  it('mostra os controles minimos do puzzle e bloqueia capabilities incompativeis', () => {
    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={makeSettings({
            layout: 'puzzle',
            show_side_thumbnails: false,
            theme_config: {
              preset: 'standard',
              anchor_mode: 'event_brand',
              burst_intensity: 'normal',
              hero_enabled: true,
              video_behavior: 'fallback_single_item',
            },
          })}
          options={makeOptionsWithPuzzle()}
          videoPolicySummary="Resumo puzzle"
          onDraftChange={vi.fn()}
        />
      </TooltipProvider>,
    );

    expect(screen.getByText(/Configuracao do puzzle/i)).toBeInTheDocument();
    expect(screen.getByText(/Preset do mosaico/i)).toBeInTheDocument();
    expect(screen.getByTestId('wall-puzzle-anchor-select')).toBeInTheDocument();
    expect(screen.getByText(/Hero slot/i)).toBeInTheDocument();
    expect(screen.getByTestId('wall-transition-mode-locked')).toBeInTheDocument();
    expect(screen.getByText(/Puzzle exibe imagens\. Videos entram em layout individual de fallback\./i)).toBeInTheDocument();
    expect(screen.getByTestId('wall-side-thumbnails-switch')).toBeDisabled();
    expect(screen.getByTestId('wall-video-multi-layout-locked')).toBeInTheDocument();
    expect(screen.queryByTestId('wall-video-multi-layout-select')).not.toBeInTheDocument();
    expect(screen.queryByTestId('wall-transition-mode-select')).not.toBeInTheDocument();
    expect(screen.queryByTestId('wall-transition-pool')).not.toBeInTheDocument();
  });
});
