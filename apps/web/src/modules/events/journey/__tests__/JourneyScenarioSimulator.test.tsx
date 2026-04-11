import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { JourneyScenarioSimulator } from '../JourneyScenarioSimulator';
import type { EventJourneyBuiltScenario } from '../types';

function makeScenario(overrides: Partial<EventJourneyBuiltScenario> = {}): EventJourneyBuiltScenario {
  return {
    id: 'photo_whatsapp_private_with_caption',
    label: 'Foto com legenda',
    description: 'Simula uma foto recebida pelo WhatsApp privado com legenda.',
    input: {},
    available: true,
    unavailableReason: null,
    highlightedNodeIds: ['entry_whatsapp_direct', 'output_gallery'],
    highlightedEdgeIds: ['entry_whatsapp_direct:default->output_gallery'],
    humanText: 'Neste cenario, uma foto chega por WhatsApp privado com legenda. A jornada aprova a midia e publica na galeria.',
    outcome: 'approved',
    ...overrides,
  };
}

describe('JourneyScenarioSimulator', () => {
  it('renders the scenario buttons and applies clear through the callback', () => {
    const onScenarioSelect = vi.fn();
    const scenario = makeScenario();

    render(
      <JourneyScenarioSimulator
        scenarios={[scenario]}
        selectedScenario={scenario}
        onScenarioSelect={onScenarioSelect}
      />,
    );

    expect(screen.getByRole('button', { name: 'Foto com legenda' })).toBeInTheDocument();
    expect(screen.getByText('Simulacao ativa')).toBeInTheDocument();
    expect(screen.getByText(/A jornada aprova a midia e publica na galeria/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Limpar simulacao' }));

    expect(onScenarioSelect).toHaveBeenCalledWith(null);
  });

  it('toggles a scenario through the provided callback without touching requests', () => {
    const onScenarioSelect = vi.fn();
    const scenario = makeScenario();

    render(
      <JourneyScenarioSimulator
        scenarios={[scenario]}
        selectedScenario={null}
        onScenarioSelect={onScenarioSelect}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Foto com legenda' }));

    expect(onScenarioSelect).toHaveBeenCalledWith(scenario);
  });

  it('keeps unavailable scenarios disabled', () => {
    render(
      <JourneyScenarioSimulator
        scenarios={[
          makeScenario({
            id: 'video_telegram',
            label: 'Video pelo Telegram',
            available: false,
            unavailableReason: 'Telegram esta desligado na jornada atual.',
          }),
        ]}
        selectedScenario={null}
        onScenarioSelect={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: 'Video pelo Telegram' })).toBeDisabled();
  });
});
