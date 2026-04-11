import { createElement, type ReactNode } from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import LoginPage from './LoginPage';

const useAuthMock = vi.fn();
const navigateMock = vi.fn();
const toastMock = vi.fn();
const requestRegisterOtpMock = vi.fn();
const resendRegisterOtpMock = vi.fn();
const verifyRegisterOtpMock = vi.fn();
const requestForgotPasswordOtpMock = vi.fn();
const resendForgotPasswordOtpMock = vi.fn();
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

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');

  return {
    ...actual,
    useNavigate: () => navigateMock,
  };
});

vi.mock('@/modules/auth/services/auth.service', () => ({
  authService: {
    requestRegisterOtp: (...args: unknown[]) => requestRegisterOtpMock(...args),
    resendRegisterOtp: (...args: unknown[]) => resendRegisterOtpMock(...args),
    verifyRegisterOtp: (...args: unknown[]) => verifyRegisterOtpMock(...args),
    requestForgotPasswordOtp: (...args: unknown[]) => requestForgotPasswordOtpMock(...args),
    resendForgotPasswordOtp: (...args: unknown[]) => resendForgotPasswordOtpMock(...args),
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

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      login: vi.fn().mockResolvedValue(undefined),
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: vi.fn(),
    });
  });

  function renderPage(
    initialEntry = '/login?returnTo=%2Fplans',
    user = userEvent.setup(),
  ) {
    render(
      <MemoryRouter initialEntries={[initialEntry]}>
        <LoginPage />
      </MemoryRouter>,
    );

    return { user };
  }

  it('renders without throwing on the login route', () => {
    renderPage();

    expect(screen.getByText(/acesse sua conta/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /entrar com whatsapp/i })).toBeInTheDocument();
    expect(screen.queryByText(/\(dev\)/i)).not.toBeInTheDocument();
  });

  it('opens the forgot-password step directly when the login route is called from an invitation recovery CTA', () => {
    renderPage('/login?returnTo=%2Fconvites%2Feventos%2Ftoken-123&flow=forgot');

    expect(screen.getByRole('heading', { name: /esqueci a senha/i })).toBeInTheDocument();
    expect(screen.getByText(/você está entrando para continuar um convite de evento/i)).toBeInTheDocument();
    expect(screen.getByText(/você voltará para este convite/i)).toBeInTheDocument();
  });

  it('submits login and preserves the returnTo path after authentication', async () => {
    const loginMock = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue({
      login: loginMock,
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: vi.fn(),
    });

    const { user } = renderPage();

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));

    const loginInput = await screen.findByLabelText(/whatsapp ou e-mail/i);
    await user.type(loginInput, '(11) 99999-4321');
    await user.type(screen.getByLabelText(/^senha$/i), 'SenhaForte123!');
    await user.click(screen.getByRole('button', { name: /^entrar$/i }));

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith({
        login: '11999994321',
        password: 'SenhaForte123!',
      });
    });

    expect(navigateMock).toHaveBeenCalledWith('/plans', { replace: true });
  });

  it('advances to the forgot-password code step after requesting a whatsapp otp', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-whatsapp',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 30,
    });

    const { user } = renderPage();

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
    await user.click(await screen.findByRole('button', { name: /esqueci a senha/i }));
    expect(await screen.findByRole('heading', { name: /esqueci a senha/i })).toBeInTheDocument();

    await user.type(screen.getByLabelText(/whatsapp ou e-mail/i), '(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar/i }));

    await waitFor(() => {
      expect(requestForgotPasswordOtpMock).toHaveBeenCalledWith({ login: '11999994321' });
    });

    expect(await screen.findByText(/digite o c/i)).toBeInTheDocument();
    expect(screen.getByText(/\+55 \(11\) \*{5}4321/)).toBeInTheDocument();
    expect(screen.getByText(/novo envio em 00:30/i)).toBeInTheDocument();
  });

  it('advances to the new-password step after validating an email otp', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-email',
      method: 'email',
      destination_masked: 'm***@example.com',
      resend_in: 0,
    });
    verifyForgotPasswordOtpMock.mockResolvedValue({
      message: 'Codigo validado com sucesso.',
    });

    const { user } = renderPage();

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
    await user.click(await screen.findByRole('button', { name: /esqueci a senha/i }));
    expect(await screen.findByRole('heading', { name: /esqueci a senha/i })).toBeInTheDocument();

    await user.type(screen.getByLabelText(/whatsapp ou e-mail/i), 'marina@example.com');
    await user.click(screen.getByRole('button', { name: /enviar/i }));

    expect(await screen.findByText(/m\*\*\*@example\.com/i)).toBeInTheDocument();

    await user.type(screen.getByLabelText(/codigo de verificacao/i), '123456');
    await user.click(screen.getByRole('button', { name: /confirmar c/i }));

    await waitFor(() => {
      expect(verifyForgotPasswordOtpMock).toHaveBeenCalledWith({
        session_token: 'forgot-session-email',
        code: '123456',
      });
    });

    expect(await screen.findByRole('heading', { name: /^nova senha$/i })).toBeInTheDocument();
  });

  it('navigates between login and register using the shared footer actions', async () => {
    const { user } = renderPage();

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
    await user.click(await screen.findByRole('button', { name: /criar conta/i }));

    expect(await screen.findByRole('heading', { name: /criar conta/i })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /^entrar$/i }));

    expect(await screen.findByRole('heading', { name: /^entrar$/i })).toBeInTheDocument();
  });

  it('advances to the register otp step after requesting a whatsapp code', async () => {
    requestRegisterOtpMock.mockResolvedValue({
      session_token: 'register-session',
      phone_masked: '+55 (11) *****4321',
      resend_in: 30,
    });

    const { user } = renderPage();

    await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
    await user.click(await screen.findByRole('button', { name: /criar conta/i }));

    await user.type(screen.getByLabelText(/seu nome/i), 'Marina');
    await user.type(screen.getByLabelText(/^whatsapp$/i), '(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /continuar/i }));

    await waitFor(() => {
      expect(requestRegisterOtpMock).toHaveBeenCalledWith({
        name: 'Marina',
        phone: '11999994321',
      });
    });

    expect(await screen.findByRole('heading', { name: /valide seu whatsapp/i })).toBeInTheDocument();
    expect(screen.getByText(/\+55 \(11\) \*{5}4321/)).toBeInTheDocument();
    expect(screen.getByText(/nao recebeu\?/i)).toBeInTheDocument();
    expect(screen.getByText(/00:30/i)).toBeInTheDocument();
  });

  it('counts down the forgot-password resend timer deterministically before enabling a new request', async () => {
    vi.useFakeTimers();

    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-whatsapp',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 2,
    });

    try {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      renderPage('/login?returnTo=%2Fplans', user);

      await user.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
      await user.click(await screen.findByRole('button', { name: /esqueci a senha/i }));
      await user.type(screen.getByLabelText(/whatsapp ou e-mail/i), '(11) 99999-4321');
      await user.click(screen.getByRole('button', { name: /enviar/i }));

      expect(requestForgotPasswordOtpMock).toHaveBeenCalledWith({ login: '11999994321' });
      expect(screen.getByText(/novo envio em 00:02/i)).toBeInTheDocument();

      const resendButton = screen.getByRole('button', { name: /reenviar c/i });
      expect(resendButton).toBeDisabled();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(1000);
      });

      expect(screen.getByText(/novo envio em 00:01/i)).toBeInTheDocument();

      await act(async () => {
        await vi.advanceTimersByTimeAsync(1000);
      });
      await act(async () => {
        await Promise.resolve();
      });

      expect(screen.queryByText(/novo envio em 00:01/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/novo envio em 00:00/i)).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: /reenviar c/i })).not.toBeDisabled();
    } finally {
      vi.useRealTimers();
    }
  });

  it('resets the password and returns to the same invitation after success', async () => {
    const refreshSessionMock = vi.fn().mockResolvedValue(undefined);

    useAuthMock.mockReturnValue({
      login: vi.fn().mockResolvedValue(undefined),
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: refreshSessionMock,
    });

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

    try {
      const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

      await user.type(screen.getByLabelText(/whatsapp ou e-mail/i), '(11) 99999-4321');
      await user.click(screen.getByRole('button', { name: /enviar c/i }));

      await waitFor(() => {
        expect(requestForgotPasswordOtpMock).toHaveBeenCalledWith({ login: '11999994321' });
      });

      await user.type(screen.getByLabelText(/codigo de verificacao/i), '123456');
      await user.click(screen.getByRole('button', { name: /confirmar c/i }));

      await waitFor(() => {
        expect(verifyForgotPasswordOtpMock).toHaveBeenCalledWith({
          session_token: 'forgot-session-whatsapp',
          code: '123456',
        });
      });

      await user.type(screen.getByLabelText(/^nova senha$/i), 'SenhaForte123!');
      await user.type(screen.getByLabelText(/confirmar nova senha/i), 'SenhaForte123!');

      const setTimeoutSpy = vi.spyOn(window, 'setTimeout').mockImplementation(((callback: TimerHandler) => {
        if (typeof callback === 'function') {
          void callback();
        }

        return 0 as ReturnType<typeof window.setTimeout>;
      }) as typeof window.setTimeout);

      await user.click(screen.getByRole('button', { name: /redefinir senha/i }));

      expect(resetPasswordWithOtpMock).toHaveBeenCalledWith({
        session_token: 'forgot-session-whatsapp',
        password: 'SenhaForte123!',
        password_confirmation: 'SenhaForte123!',
        device_name: 'web-panel',
      });

      await act(async () => {
        await Promise.resolve();
      });

      expect(screen.getByRole('heading', { name: /senha redefinida/i })).toBeInTheDocument();
      await act(async () => {
        await Promise.resolve();
      });

      expect(refreshSessionMock).toHaveBeenCalled();
      expect(navigateMock).toHaveBeenCalledWith('/convites/equipe/token-123', { replace: true });
      setTimeoutSpy.mockRestore();
    } finally {
      vi.restoreAllMocks();
    }
  });
});
