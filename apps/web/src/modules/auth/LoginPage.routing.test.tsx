import { createElement, type ReactNode } from 'react';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import LoginPage from './LoginPage';

const useAuthMock = vi.fn();
const toastMock = vi.fn();
const requestForgotPasswordOtpMock = vi.fn();
const verifyForgotPasswordOtpMock = vi.fn();
const resetPasswordWithOtpMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

vi.mock('@/modules/auth/services/auth.service', () => ({
  authService: {
    requestRegisterOtp: vi.fn(),
    resendRegisterOtp: vi.fn(),
    verifyRegisterOtp: vi.fn(),
    requestForgotPasswordOtp: (...args: unknown[]) => requestForgotPasswordOtpMock(...args),
    resendForgotPasswordOtp: vi.fn(),
    verifyForgotPasswordOtp: (...args: unknown[]) => verifyForgotPasswordOtpMock(...args),
    resetPasswordWithOtp: (...args: unknown[]) => resetPasswordWithOtpMock(...args),
  },
}));

vi.mock('framer-motion', async () => {
  const actual = await vi.importActual<typeof import('framer-motion')>('framer-motion');
  const motionProxy = new Proxy(
    {},
    {
      get: (_target, tag) => {
        return ({ children, ...props }: { children: ReactNode } & Record<string, unknown>) =>
          createElement(typeof tag === 'string' ? tag : 'div', props, children);
      },
    },
  );

  return {
    ...actual,
    AnimatePresence: ({ children }: { children: ReactNode }) => <>{children}</>,
    motion: motionProxy as typeof actual.motion,
  };
});

function renderWithRouter(initialEntry: string) {
  const router = createMemoryRouter(
    [
      {
        path: '/login',
        element: <LoginPage />,
      },
      {
        path: '/plans',
        element: <div>plans-destination</div>,
      },
      {
        path: '/convites/equipe/:token',
        element: <div>team-invitation-destination</div>,
      },
    ],
    {
      initialEntries: [initialEntry],
    },
  );

  render(<RouterProvider router={router} />);

  return { router };
}

describe('LoginPage routing', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      login: vi.fn().mockResolvedValue(undefined),
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: vi.fn().mockResolvedValue(undefined),
    });
  });

  it('navigates to the resolved returnTo route after login using the real router tree', async () => {
    const loginMock = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue({
      login: loginMock,
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: vi.fn().mockResolvedValue(undefined),
    });

    const user = userEvent.setup({ delay: null });

    renderWithRouter('/login?returnTo=%2Fplans');

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
    await user.click(await screen.findByLabelText(/whatsapp ou e-mail/i));
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByLabelText(/^senha$/i));
    await user.paste('SenhaForte123!');
    await user.click(screen.getByRole('button', { name: /^entrar$/i }));

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        login: '11999994321',
        password: 'SenhaForte123!',
      });
    });

    expect(await screen.findByText('plans-destination')).toBeInTheDocument();
  });

  it('returns to the invitation route after password reset using the real router tree', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-whatsapp',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 30,
    });
    verifyForgotPasswordOtpMock.mockResolvedValue({
      message: 'Codigo validado com sucesso.',
    });
    resetPasswordWithOtpMock.mockResolvedValue({
      user: { id: 10 },
      active_context: null,
      workspaces: { organizations: [], event_accesses: [] },
    });

    const refreshSessionMock = vi.fn().mockResolvedValue(undefined);
    useAuthMock.mockReturnValue({
      login: vi.fn().mockResolvedValue(undefined),
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: refreshSessionMock,
    });

    try {
      renderWithRouter('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

      fireEvent.change(screen.getByLabelText(/whatsapp ou e-mail/i), {
        target: { value: '(11) 99999-4321' },
      });
      fireEvent.click(screen.getByRole('button', { name: /enviar codigo/i }));

      expect(await screen.findByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();

      fireEvent.change(screen.getByLabelText(/codigo de verificacao/i), {
        target: { value: '123456' },
      });
      fireEvent.click(screen.getByRole('button', { name: /confirmar c/i }));

      expect(await screen.findByRole('heading', { name: /^nova senha$/i })).toBeInTheDocument();

      fireEvent.change(screen.getByLabelText(/^nova senha$/i), {
        target: { value: 'SenhaForte123!' },
      });
      fireEvent.change(screen.getByLabelText(/confirmar nova senha/i), {
        target: { value: 'SenhaForte123!' },
      });

      const nativeSetTimeout = window.setTimeout.bind(window);
      const setTimeoutSpy = vi
        .spyOn(window, 'setTimeout')
        .mockImplementation(((handler: TimerHandler, timeout?: number, ...args: unknown[]) => {
          if (timeout === 1200) {
            if (typeof handler === 'function') {
              void handler(...args);
            }

            return 0 as ReturnType<typeof window.setTimeout>;
          }

          return nativeSetTimeout(handler, timeout, ...(args as []));
        }) as typeof window.setTimeout);

      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /redefinir senha/i }));
        await Promise.resolve();
      });

      await waitFor(() => {
        expect(resetPasswordWithOtpMock).toHaveBeenCalledWith({
          session_token: 'forgot-session-whatsapp',
          password: 'SenhaForte123!',
          password_confirmation: 'SenhaForte123!',
          device_name: 'web-panel',
        });
      });

      await waitFor(() => {
        expect(refreshSessionMock).toHaveBeenCalled();
      });

      expect(screen.getByText('team-invitation-destination')).toBeInTheDocument();
      setTimeoutSpy.mockRestore();
    } finally {
      vi.restoreAllMocks();
    }
  });
});
