import { act, renderHook } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useCheckoutIdentityPrecheck } from './useCheckoutIdentityPrecheck';

const checkMock = vi.fn();

vi.mock('../services/public-checkout-identity.service', () => ({
  publicCheckoutIdentityService: {
    check: (...args: unknown[]) => checkMock(...args),
  },
}));

function createWrapper() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return function Wrapper({ children }: { children: React.ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        {children}
      </QueryClientProvider>
    );
  };
}

describe('useCheckoutIdentityPrecheck', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    checkMock.mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('does not trigger while WhatsApp is still invalid', () => {
    renderHook(() => useCheckoutIdentityPrecheck({
      whatsapp: '123',
      email: '',
      debounceMs: 700,
    }), {
      wrapper: createWrapper(),
    });

    act(() => {
      vi.advanceTimersByTime(1000);
    });

    expect(checkMock).not.toHaveBeenCalled();
  });

  it('debounces the request and forwards AbortSignal to the service', async () => {
    checkMock.mockResolvedValue({
      identity_status: 'new_account',
      title: 'Tudo certo para continuar',
      description: 'Voce pode seguir normalmente para o pagamento.',
      action_label: null,
      login_url: null,
      cooldown_seconds: null,
    });

    const { result } = renderHook(() => useCheckoutIdentityPrecheck({
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      debounceMs: 700,
    }), {
      wrapper: createWrapper(),
    });

    expect(result.current.isChecking).toBe(false);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(699);
    });

    expect(checkMock).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(1);
    });

    await act(async () => {
      await Promise.resolve();
    });

    expect(checkMock).toHaveBeenCalledTimes(1);
    expect(checkMock.mock.calls[0]?.[0]).toMatchObject({
      whatsapp: '48999771111',
      email: 'camila@example.com',
    });
    expect(checkMock.mock.calls[0]?.[1]).toBeInstanceOf(AbortSignal);
  });

  it('cancels the previous request when the contact changes after debounce', async () => {
    const signals: AbortSignal[] = [];

    checkMock.mockImplementation((_payload: unknown, signal?: AbortSignal) => {
      if (signal) {
        signals.push(signal);
      }

      return new Promise(() => {});
    });

    const { rerender } = renderHook(
      ({ whatsapp }) => useCheckoutIdentityPrecheck({
        whatsapp,
        email: 'camila@example.com',
        debounceMs: 700,
      }),
      {
        initialProps: {
          whatsapp: '(48) 99977-1111',
        },
        wrapper: createWrapper(),
      },
    );

    await act(async () => {
      await vi.advanceTimersByTimeAsync(700);
    });

    await act(async () => {
      await Promise.resolve();
    });

    expect(checkMock).toHaveBeenCalledTimes(1);
    rerender({
      whatsapp: '(48) 99988-2222',
    });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(700);
    });

    await act(async () => {
      await Promise.resolve();
    });

    expect(checkMock).toHaveBeenCalledTimes(2);
    expect(signals[0]?.aborted).toBe(true);
  });

  it('keeps working when the request fails instead of blocking the flow', async () => {
    checkMock.mockRejectedValue(new Error('network'));

    const { result } = renderHook(() => useCheckoutIdentityPrecheck({
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      debounceMs: 700,
    }), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(700);
    });

    await act(async () => {
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(result.current.isReady).toBe(true);
    expect(result.current.identityAssist).toBeNull();
  });
});
