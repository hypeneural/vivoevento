import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterAll, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';

import { eventOperationsHealthySnapshotFixture } from './__fixtures__/operations-room.fixture';
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

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/events/42/control-room']}>
      <Routes>
        <Route path="/events/:id/control-room" element={<EventOperationsRoomPage />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('EventOperationsRoomPage', () => {
  beforeAll(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(
      getContextMock as HTMLCanvasElement['getContext'],
    );
  });

  beforeEach(() => {
    useEventOperationsBootMock.mockReturnValue({
      data: makeRoom(),
      isLoading: false,
      isError: false,
    });
  });

  afterAll(() => {
    vi.restoreAllMocks();
  });

  it('renders the fullscreen control room shell without admin chrome', () => {
    renderPage();

    expect(screen.getByRole('heading', { name: 'Control room do evento' })).toBeInTheDocument();
    expect(screen.getByText('Evento atual: 42')).toBeInTheDocument();
    expect(screen.getByText('Contrato live congelado')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Entrar em modo sala' })).toBeInTheDocument();
    expect(screen.getByText('Casamento Ana e Bruno')).toBeInTheDocument();
    expect(screen.getByRole('log', { name: 'Timeline da operacao' })).toBeInTheDocument();
    expect(screen.queryByText(/dashboard/i)).not.toBeInTheDocument();
  });
});
