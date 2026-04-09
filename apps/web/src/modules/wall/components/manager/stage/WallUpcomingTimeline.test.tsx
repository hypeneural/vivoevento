import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import type { ApiWallSimulationResponse } from '@/lib/api-types';

import { WallUpcomingTimeline } from './WallUpcomingTimeline';

const summary: ApiWallSimulationResponse['summary'] = {
  selection_mode: 'balanced',
  selection_mode_label: 'Equilibrado',
  event_phase: 'flow',
  event_phase_label: 'Fluxo',
  queue_items: 42,
  active_senders: 9,
  estimated_first_appearance_seconds: 55,
  monopolization_risk: 'low',
  freshness_intensity: 'medium',
  fairness_level: 'high',
};

describe('WallUpcomingTimeline', () => {
  it('mostra proximas fotos com thumbnail, origem e estado da fila', () => {
    render(
      <WallUpcomingTimeline
        selectionSummary="A fila alterna remetentes quando ha alternativa pronta."
        simulationSummary={summary}
        simulationPreview={[
          {
            position: 1,
            eta_seconds: 0,
            item_id: 'media-1',
            preview_url: 'https://cdn.example.com/upcoming-1.jpg',
            sender_name: 'Ana',
            sender_key: 'sender-ana',
            source_type: 'upload',
            caption: 'Entrada principal',
            layout_hint: 'cinematic',
            duplicate_cluster_key: null,
            is_featured: false,
            is_replay: false,
            created_at: '2026-04-08T10:00:00Z',
          },
          {
            position: 2,
            eta_seconds: 8,
            item_id: 'media-2',
            preview_url: null,
            sender_name: 'Pedro',
            sender_key: 'sender-pedro',
            source_type: 'whatsapp',
            duplicate_cluster_key: null,
            is_featured: false,
            is_replay: true,
            created_at: '2026-04-08T10:00:10Z',
          },
        ]}
        simulationExplanation={['A fila usou o rascunho atual e a ordem real do evento.']}
        isLoading={false}
        isError={false}
        isRefreshing={false}
        isDraftPending={false}
      />,
    );

    expect(screen.getByText(/Ordem mais provavel das proximas 2 exibicoes/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Timeline horizontal das proximas exibicoes/i)).toBeInTheDocument();
    expect(screen.getByRole('img', { name: /Miniatura da proxima foto de Ana/i })).toBeInTheDocument();
    expect(screen.getByText(/^Upload$/i)).toBeInTheDocument();
    expect(screen.getByText(/^WhatsApp$/i)).toBeInTheDocument();
    expect(screen.getByText(/Entrada principal/i)).toBeInTheDocument();
    expect(screen.getByText(/Layout Cinematografico/i)).toBeInTheDocument();
    expect(screen.getByText(/Reprise/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Fila real/i).length).toBeGreaterThan(0);
  });
});
