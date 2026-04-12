import { render, screen, within } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  eventOperationsHealthySnapshotFixture,
  eventOperationsHumanReviewBottleneckSnapshotFixture,
} from './__fixtures__/operations-room.fixture';
import EventOperationsRoomPage from './EventOperationsRoomPage';
import type { EventOperationsV0Room } from './types';

const useEventOperationsBootMock = vi.fn();
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

vi.mock('./hooks/useEventOperationsBoot', () => ({
  useEventOperationsBoot: (...args: unknown[]) => useEventOperationsBootMock(...args),
}));

vi.mock('./hooks/useEventOperationsRealtime', () => ({
  useEventOperationsRealtime: () => ({
    connectionState: 'connected',
    statusMessage: null,
    lastResyncCompletedAt: null,
  }),
}));

vi.mock('./hooks/useEventOperationsFallback', () => ({
  useEventOperationsFallback: () => ({
    isPollingFallbackActive: false,
    roomIntervalMs: false,
    timelineIntervalMs: false,
  }),
}));

function makeRoom(snapshot = eventOperationsHealthySnapshotFixture): EventOperationsV0Room {
  return {
    ...snapshot,
    v0: {
      mode: 'read_only',
      journey_summary_text: 'Fluxo atual com recepcao, safety, moderacao e wall.',
      active_entry_channels: ['WhatsApp privado', 'Telegram', 'Link de envio'],
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
      dominant_station_reason: snapshot.health.dominant_station_key === 'human_review'
        ? 'Fila de revisao humana acima do normal.'
        : null,
    },
  };
}

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/events/42/control-room']}>
      <Routes>
        <Route path="/events/:id/control-room" element={<EventOperationsRoomPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('event-operations visual reading', () => {
  beforeAll(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(
      getContextMock as HTMLCanvasElement['getContext'],
    );
  });

  beforeEach(() => {
    useEventOperationsBootMock.mockReset();
  });

  afterAll(() => {
    vi.restoreAllMocks();
  });

  it('surfaces the macro reading in 5s for a healthy room', () => {
    useEventOperationsBootMock.mockReturnValue({
      data: makeRoom(),
      isLoading: false,
      isError: false,
    });

    renderPage();

    const macroCard = screen.getByText('Leitura em 5s').closest('div');

    expect(macroCard).not.toBeNull();
    expect(within(macroCard as HTMLElement).getByText('Operacao saudavel')).toBeInTheDocument();
    expect(within(macroCard as HTMLElement).getByText('Estado calmo ativo')).toBeInTheDocument();
  });

  it('surfaces the dominant bottleneck in 15s for an attention room', () => {
    useEventOperationsBootMock.mockReturnValue({
      data: makeRoom(eventOperationsHumanReviewBottleneckSnapshotFixture),
      isLoading: false,
      isError: false,
    });

    renderPage();

    const mesoCard = screen.getByText('Leitura em 15s').closest('div');

    expect(mesoCard).not.toBeNull();
    expect(within(mesoCard as HTMLElement).getByText('Moderacao humana')).toBeInTheDocument();
    expect(within(mesoCard as HTMLElement).getByText('Fila de revisao humana acima do normal.')).toBeInTheDocument();
  });
});
