import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import type { ApiWallInsightsResponse } from '@/lib/api-types';

import { useWallTopInsights } from './useWallTopInsights';

const getEventWallInsightsMock = vi.fn();

vi.mock('../api', () => ({
  getEventWallInsights: (...args: unknown[]) => getEventWallInsightsMock(...args),
}));

function makeInsightsResponse(overrides: Partial<ApiWallInsightsResponse> = {}): ApiWallInsightsResponse {
  return {
    topContributor: {
      senderKey: 'whatsapp:5511999990001',
      displayName: 'Ana',
      maskedContact: '5511...01',
      source: 'whatsapp',
      mediaCount: 4,
      lastSentAt: '2026-04-08T10:10:00Z',
      avatarUrl: null,
    },
    totals: {
      received: 12,
      approved: 10,
      queued: 7,
      displayed: null,
    },
    recentItems: [
      {
        id: 'media-10',
        previewUrl: 'https://cdn.example.com/thumbs/media-10.jpg',
        senderName: 'Ana',
        senderKey: 'whatsapp:5511999990001',
        source: 'whatsapp',
        createdAt: '2026-04-08T10:09:00Z',
        approvedAt: '2026-04-08T10:09:30Z',
        displayedAt: null,
        status: 'queued',
        isFeatured: false,
        isReplay: false,
      },
    ],
    sourceMix: [
      {
        source: 'whatsapp',
        count: 12,
      },
    ],
    lastCaptureAt: '2026-04-08T10:09:00Z',
    ...overrides,
  };
}

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

function createDeferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((res) => {
    resolve = res;
  });

  return { promise, resolve };
}

describe('useWallTopInsights', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('carrega os insights agregados do wall pelo eventId', async () => {
    getEventWallInsightsMock.mockResolvedValue(makeInsightsResponse());

    const { result } = renderHook(() => useWallTopInsights('31'), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.data?.topContributor?.displayName).toBe('Ana');
    });

    expect(getEventWallInsightsMock).toHaveBeenCalledWith('31');
    expect(result.current.data?.totals.received).toBe(12);
  });

  it('nao dispara a query quando o eventId ainda nao existe', async () => {
    const { result } = renderHook(() => useWallTopInsights(''), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.fetchStatus).toBe('idle');
    });

    expect(getEventWallInsightsMock).not.toHaveBeenCalled();
  });

  it('mantem os dados anteriores visiveis enquanto a proxima chave ainda esta carregando', async () => {
    const previousInsights = makeInsightsResponse({
      topContributor: {
        senderKey: 'whatsapp:5511999990001',
        displayName: 'Ana',
        maskedContact: '5511...01',
        source: 'whatsapp',
        mediaCount: 4,
        lastSentAt: '2026-04-08T10:10:00Z',
        avatarUrl: null,
      },
    });
    const nextInsights = makeInsightsResponse({
      topContributor: {
        senderKey: 'upload:galeria',
        displayName: 'Bruno',
        maskedContact: null,
        source: 'upload',
        mediaCount: 2,
        lastSentAt: '2026-04-08T10:12:00Z',
        avatarUrl: null,
      },
    });
    const deferred = createDeferred<ApiWallInsightsResponse>();

    getEventWallInsightsMock
      .mockResolvedValueOnce(previousInsights)
      .mockImplementationOnce(() => deferred.promise);

    const { result, rerender } = renderHook(
      ({ eventId }) => useWallTopInsights(eventId),
      {
        initialProps: { eventId: '31' },
        wrapper: createWrapper(),
      },
    );

    await waitFor(() => {
      expect(result.current.data?.topContributor?.displayName).toBe('Ana');
    });

    rerender({ eventId: '32' });

    await waitFor(() => {
      expect(getEventWallInsightsMock).toHaveBeenNthCalledWith(2, '32');
    });

    expect(result.current.data?.topContributor?.displayName).toBe('Ana');

    deferred.resolve(nextInsights);

    await waitFor(() => {
      expect(result.current.data?.topContributor?.displayName).toBe('Bruno');
    });
  });
});
