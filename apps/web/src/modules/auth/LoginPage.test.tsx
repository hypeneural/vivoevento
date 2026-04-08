import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import LoginPage from './LoginPage';

const useAuthMock = vi.fn();
const toastMock = vi.fn();

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
    requestForgotPasswordOtp: vi.fn(),
    resendForgotPasswordOtp: vi.fn(),
    verifyForgotPasswordOtp: vi.fn(),
    resetPasswordWithOtp: vi.fn(),
  },
}));

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    useAuthMock.mockReturnValue({
      login: vi.fn(),
      loginMock: vi.fn(),
      availableUsers: [],
      isLoading: false,
      refreshSession: vi.fn(),
    });
  });

  it('renders without throwing on the login route', () => {
    render(
      <MemoryRouter initialEntries={['/login?returnTo=%2Fplans']}>
        <LoginPage />
      </MemoryRouter>,
    );

    expect(screen.getByText(/acesse sua conta/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /entrar com whatsapp/i })).toBeInTheDocument();
    expect(screen.queryByText(/\(dev\)/i)).not.toBeInTheDocument();
  });
});
