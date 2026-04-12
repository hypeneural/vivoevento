import { render, screen, within } from '@testing-library/react';
import { beforeAll, afterAll, describe, expect, it, vi } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import { FULL_CONTROL_ROOM_GESTURES, REDUCED_CONTROL_ROOM_GESTURES } from '../hooks/useReducedControlRoomMotion';
import type { EventOperationsV0Room } from '../types';
import { OperationsRoomCanvas } from './OperationsRoomCanvas';

const getContextMock = vi.fn(() => ({
  clearRect: vi.fn(),
  fillRect: vi.fn(),
  strokeRect: vi.fn(),
  beginPath: vi.fn(),
  moveTo: vi.fn(),
  lineTo: vi.fn(),
  stroke: vi.fn(),
  fillText: vi.fn(),
  save: vi.fn(),
  restore: vi.fn(),
  imageSmoothingEnabled: false,
}));

function makeRoom(): EventOperationsV0Room {
  return {
    ...eventOperationsHealthySnapshotFixture,
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Fluxo atual com recepcao, safety, moderacao e wall.',
      active_entry_channels: ['WhatsApp privado', 'Telegram', 'Link de envio'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: null,
    },
  };
}

describe('OperationsRoomCanvas', () => {
  beforeAll(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(
      getContextMock as HTMLCanvasElement['getContext'],
    );
  });

  afterAll(() => {
    vi.restoreAllMocks();
  });

  it('renders the canvas shell with fixed scene layers and visual roles', () => {
    render(
      <OperationsRoomCanvas
        room={makeRoom()}
        motionMode="full"
        stationGestures={FULL_CONTROL_ROOM_GESTURES}
      />,
    );

    expect(screen.getByTestId('operations-room-canvas')).toBeInTheDocument();

    const layers = screen.getByRole('list', { name: 'Camadas da cena' });
    expect(within(layers).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'background',
      'stations',
      'agents',
      'effects',
    ]);

    const roles = screen.getByRole('list', { name: 'Papeis visuais' });
    expect(roles).toHaveTextContent('Coordinator');
    expect(roles).toHaveTextContent('Dispatcher');
    expect(roles).toHaveTextContent('Runner');
    expect(roles).toHaveTextContent('Reviewer');
    expect(roles).toHaveTextContent('Operator');
  });

  it('shows the reduced-motion operational gestures without hiding the reading cues', () => {
    render(
      <OperationsRoomCanvas
        room={makeRoom()}
        motionMode="reduced"
        stationGestures={REDUCED_CONTROL_ROOM_GESTURES}
      />,
    );

    expect(screen.getByText('Leitura em 5s')).toBeInTheDocument();
    expect(screen.getByText('Leitura em 15s')).toBeInTheDocument();
    expect(screen.getByText('Recepcao')).toBeInTheDocument();

    const gesturesSection = screen.getByText('Gestos por estacao').closest('div');

    expect(gesturesSection).not.toBeNull();
    expect(within(gesturesSection as HTMLElement).getAllByText(/contagem e pulso discreto/i).length).toBeGreaterThan(0);
    expect(within(gesturesSection as HTMLElement).getAllByText(/selo claro de current\/next\/health/i).length).toBeGreaterThan(0);
  });
});
