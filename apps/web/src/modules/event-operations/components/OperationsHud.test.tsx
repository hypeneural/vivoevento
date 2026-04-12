import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import { OperationsHud } from './OperationsHud';

function makeRoom(overrides: Partial<EventOperationsV0Room> = {}): EventOperationsV0Room {
  return {
    ...eventOperationsHealthySnapshotFixture,
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Fluxo atual com recepcao, safety, moderacao e wall.',
      active_entry_channels: ['WhatsApp privado', 'Telegram'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: null,
    },
    ...overrides,
  };
}

describe('OperationsHud', () => {
  it('keeps the HUD short with event name, global health, clock, connectivity, wall health and human queue', () => {
    render(<OperationsHud room={makeRoom()} />);

    expect(screen.getByText('Casamento Ana e Bruno')).toBeInTheDocument();
    expect(screen.getByText('Status global')).toBeInTheDocument();
    expect(screen.getByText('Operacao saudavel')).toBeInTheDocument();
    expect(screen.getByText('Horario da sala')).toBeInTheDocument();
    expect(screen.getByText('Conexao')).toBeInTheDocument();
    expect(screen.getByText('Wall health')).toBeInTheDocument();
    expect(screen.getByText('Fila humana')).toBeInTheDocument();
    expect(screen.queryByText('Falhas de processamento')).not.toBeInTheDocument();
  });
});
