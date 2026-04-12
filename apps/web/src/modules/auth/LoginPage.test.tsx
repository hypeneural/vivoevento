import { createElement, type ReactNode } from 'react';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
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

function createDeferred<T>() {
  let resolve!: (value: T | PromiseLike<T>) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });

  return { promise, resolve, reject };
}

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
    user = userEvent.setup({ delay: null }),
  ) {
    render(
      <MemoryRouter initialEntries={[initialEntry]}>
        <LoginPage />
      </MemoryRouter>,
    );

    return { user };
  }

  async function flushMicrotasks() {
    await act(async () => {
      await Promise.resolve();
    });
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
    await user.click(loginInput);
    await user.paste('(11) 99999-4321');

    await user.click(screen.getByLabelText(/^senha$/i));
    await user.paste('SenhaForte123!');

    await waitFor(() => {
      expect(screen.getByLabelText(/^senha$/i)).toHaveValue('SenhaForte123!');
    });
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

    const identityInput = screen.getByLabelText(/whatsapp ou e-mail/i);
    await user.click(identityInput);
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar/i }));

    await waitFor(() => {
      expect(requestForgotPasswordOtpMock).toHaveBeenCalledWith({ login: '11999994321' });
    });

    expect(await screen.findByText(/digite o c/i)).toBeInTheDocument();
    expect(screen.getByText(/\+55 \(11\) \*{5}4321/)).toBeInTheDocument();
    expect(screen.getByText(/novo envio em 00:30/i)).toBeInTheDocument();
  });

  it('shows a destructive toast and keeps the user on the forgot-password request step when the otp request fails', async () => {
    requestForgotPasswordOtpMock.mockRejectedValue(new Error('Nao foi possivel enviar o codigo agora.'));

    const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

    await user.click(screen.getByLabelText(/whatsapp ou e-mail/i));
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar codigo/i }));

    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Erro',
          description: 'Nao foi possivel enviar o codigo agora.',
          variant: 'destructive',
        }),
      );
    });

    expect(screen.getByRole('heading', { name: /esqueci a senha/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /digite o codigo/i })).not.toBeInTheDocument();
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

    const identityInput = screen.getByLabelText(/whatsapp ou e-mail/i);
    await user.click(identityInput);
    await user.paste('marina@example.com');
    await user.click(screen.getByRole('button', { name: /enviar/i }));

    expect(await screen.findByText(/m\*\*\*@example\.com/i)).toBeInTheDocument();

    const codeInput = screen.getByLabelText(/codigo de verificacao/i);
    await user.click(codeInput);
    await user.paste('123456');
    await user.click(screen.getByRole('button', { name: /confirmar c/i }));

    await waitFor(() => {
      expect(verifyForgotPasswordOtpMock).toHaveBeenCalledWith({
        session_token: 'forgot-session-email',
        code: '123456',
      });
    });

    expect(await screen.findByRole('heading', { name: /^nova senha$/i })).toBeInTheDocument();
  });

  it('keeps the user on the otp step and shows a destructive toast when otp verification fails', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-email',
      method: 'email',
      destination_masked: 'm***@example.com',
      resend_in: 0,
    });
    verifyForgotPasswordOtpMock.mockRejectedValue(new Error('Codigo invalido ou expirado.'));

    const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

    await user.click(screen.getByLabelText(/whatsapp ou e-mail/i));
    await user.paste('marina@example.com');
    await user.click(screen.getByRole('button', { name: /enviar codigo/i }));

    expect(await screen.findByText(/m\*\*\*@example\.com/i)).toBeInTheDocument();

    await user.click(screen.getByLabelText(/codigo de verificacao/i));
    await user.paste('123456');
    await user.click(screen.getByRole('button', { name: /confirmar c/i }));

    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Codigo invalido',
          description: 'Codigo invalido ou expirado.',
          variant: 'destructive',
        }),
      );
    });

    expect(screen.getByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /^nova senha$/i })).not.toBeInTheDocument();
  });

  it('keeps the user on the otp step when the otp session is expired', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-expired',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 0,
    });
    verifyForgotPasswordOtpMock.mockRejectedValue(
      new Error('Sessao OTP expirada. Solicite um novo codigo.'),
    );

    const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

    await user.click(screen.getByLabelText(/whatsapp ou e-mail/i));
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar codigo/i }));

    expect(await screen.findByText(/\+55 \(11\) \*{5}4321/)).toBeInTheDocument();

    await user.click(screen.getByLabelText(/codigo de verificacao/i));
    await user.paste('123456');
    await user.click(screen.getByRole('button', { name: /confirmar c/i }));

    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Codigo invalido',
          description: 'Sessao OTP expirada. Solicite um novo codigo.',
          variant: 'destructive',
        }),
      );
    });

    expect(screen.getByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /reenviar c/i })).toBeEnabled();
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

    await user.click(screen.getByLabelText(/seu nome/i));
    await user.paste('Marina');

    await user.click(screen.getByLabelText(/^whatsapp$/i));
    await user.paste('(11) 99999-4321');

    await waitFor(() => {
      expect(screen.getByLabelText(/seu nome/i)).toHaveValue('Marina');
      expect(screen.getByLabelText(/^whatsapp$/i)).toHaveValue('(11) 99999-4321');
    });
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

  it('shows loading and disables actions while the forgot-password otp request is pending', async () => {
    const deferred = createDeferred<{
      session_token: string;
      method: 'whatsapp' | 'email';
      destination_masked: string;
      resend_in: number;
    }>();

    requestForgotPasswordOtpMock.mockReturnValue(deferred.promise);

    const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

    await user.click(screen.getByLabelText(/whatsapp ou e-mail/i));
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar codigo/i }));

    const submitButton = screen.getByRole('button', { name: /enviando/i });
    expect(submitButton).toBeDisabled();

    deferred.resolve({
      session_token: 'forgot-session-whatsapp',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 30,
    });

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();
    });
  });

  it('disables confirm and resend actions while otp verification is pending', async () => {
    requestForgotPasswordOtpMock.mockResolvedValue({
      session_token: 'forgot-session-whatsapp',
      method: 'whatsapp',
      destination_masked: '+55 (11) *****4321',
      resend_in: 0,
    });
    const deferred = createDeferred<{ message: string }>();
    verifyForgotPasswordOtpMock.mockReturnValue(deferred.promise);

    const { user } = renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

    await user.click(screen.getByLabelText(/whatsapp ou e-mail/i));
    await user.paste('(11) 99999-4321');
    await user.click(screen.getByRole('button', { name: /enviar codigo/i }));

    expect(await screen.findByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();

    await user.click(screen.getByLabelText(/codigo de verificacao/i));
    await user.paste('123456');
    await user.click(screen.getByRole('button', { name: /confirmar c/i }));

    expect(screen.getByRole('button', { name: /confirmar c/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /reenviando/i })).toBeDisabled();

    deferred.resolve({ message: 'Codigo validado com sucesso.' });

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /^nova senha$/i })).toBeInTheDocument();
    });
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
      renderPage();

      fireEvent.click(screen.getByRole('button', { name: /entrar com whatsapp/i }));
      fireEvent.click(screen.getByRole('button', { name: /esqueci a senha/i }));
      fireEvent.change(screen.getByLabelText(/whatsapp ou e-mail/i), {
        target: { value: '(11) 99999-4321' },
      });
      fireEvent.click(screen.getByRole('button', { name: /enviar codigo/i }));

      await flushMicrotasks();
      await flushMicrotasks();

      expect(screen.getByRole('heading', { name: /digite o codigo/i })).toBeInTheDocument();
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
      await flushMicrotasks();

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
      renderPage('/login?returnTo=%2Fconvites%2Fequipe%2Ftoken-123&flow=forgot');

      fireEvent.change(screen.getByLabelText(/whatsapp ou e-mail/i), {
        target: { value: '(11) 99999-4321' },
      });
      fireEvent.click(screen.getByRole('button', { name: /enviar c/i }));

      await waitFor(() => {
        expect(requestForgotPasswordOtpMock).toHaveBeenCalledWith({ login: '11999994321' });
      });

      fireEvent.change(screen.getByLabelText(/codigo de verificacao/i), {
        target: { value: '123456' },
      });
      fireEvent.click(screen.getByRole('button', { name: /confirmar c/i }));

      await waitFor(() => {
        expect(verifyForgotPasswordOtpMock).toHaveBeenCalledWith({
          session_token: 'forgot-session-whatsapp',
          code: '123456',
        });
      });

      fireEvent.change(screen.getByLabelText(/^nova senha$/i), {
        target: { value: 'SenhaForte123!' },
      });
      fireEvent.change(screen.getByLabelText(/confirmar nova senha/i), {
        target: { value: 'SenhaForte123!' },
      });

      await waitFor(() => {
        expect(screen.getByLabelText(/^nova senha$/i)).toHaveValue('SenhaForte123!');
        expect(screen.getByLabelText(/confirmar nova senha/i)).toHaveValue('SenhaForte123!');
      });

      const setTimeoutSpy = vi.spyOn(window, 'setTimeout').mockImplementation(((callback: TimerHandler) => {
        if (typeof callback === 'function') {
          void callback();
        }

        return 0 as ReturnType<typeof window.setTimeout>;
      }) as typeof window.setTimeout);

      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /redefinir senha/i }));
        await Promise.resolve();
      });

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
