import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import { fallbackOptions } from '../../../manager-config';
import { WallAppearanceTab } from './WallAppearanceTab';

describe('WallAppearanceTab', () => {
  it('renderiza os controles de aparencia e propaga alteracoes do rascunho', () => {
    const onDraftChange = vi.fn();

    render(
      <TooltipProvider>
        <WallAppearanceTab
          wallSettings={{
            interval_ms: 8000,
            queue_limit: 50,
            selection_mode: 'balanced',
            event_phase: 'flow',
            selection_policy: fallbackOptions.selection_modes[0].selection_policy,
            layout: 'auto',
            transition_effect: 'fade',
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
          }}
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
});
