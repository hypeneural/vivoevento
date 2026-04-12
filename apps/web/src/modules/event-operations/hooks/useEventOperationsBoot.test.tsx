import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { queryKeys } from '@/lib/query-client';

import { eventOperationsHealthySnapshotFixture } from '../__fixtures__/operations-room.fixture';
import type { EventOperationsV0Room } from '../types';
import { eventOperationsBootQueryOptions, useEventOperationsBoot } from './useEventOperationsBoot';

const getEventOperationsBootRoomMock = vi.fn();

vi.mock('../api', () => ({
  getEventOperationsBootRoom: (...args: unknown[]) => getEventOperationsBootRoomMock(...args),
}));

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return function Wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

function makeBootRoom(): EventOperationsV0Room {
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

describe('useEventOperationsBoot', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('combines the existing endpoints into the initial read-only room view model', async () => {
    getEventOperationsBootRoomMock.mockResolvedValue(makeBootRoom());

    const { result } = renderHook(() => useEventOperationsBoot('42'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.data?.event.title).toBe('Casamento Ana e Bruno');
    });

    expect(result.current.data?.v0.mode).toBe('read_only');
    expect(result.current.data?.wall.confidence).toBe('high');
    expect(result.current.data?.timeline[0]?.title).toBe('Midia publicada');
    expect(getEventOperationsBootRoomMock).toHaveBeenCalledWith('42');
  });

  it('freezes the boot query as high-stale read-only polling instead of a live engine', () => {
    const options = eventOperationsBootQueryOptions('42');

    expect(options.queryKey).toEqual(queryKeys.operations.room('42'));
    expect(options.staleTime).toBe(60 * 1000);
    expect(options.refetchOnWindowFocus).toBe(false);
    expect(options.refetchOnReconnect).toBe(false);
    expect(options.refetchOnMount).toBe(false);
    expect(options.refetchInterval).toBe(15 * 1000);
    expect(options.notifyOnChangeProps).toEqual(['data', 'error', 'fetchStatus', 'status']);
  });
});
