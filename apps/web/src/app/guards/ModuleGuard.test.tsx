import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ModuleGuard } from './ModuleGuard';

const useAuthMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

describe('ModuleGuard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the children when module and permission access are granted', () => {
    useAuthMock.mockReturnValue({
      canAccessModule: () => true,
      can: () => true,
    });

    render(
      <MemoryRouter>
        <ModuleGuard moduleKey="clients" requiredPermissions={['clients.view']}>
          <div>Conteudo Protegido</div>
        </ModuleGuard>
      </MemoryRouter>,
    );

    expect(screen.getByText('Conteudo Protegido')).toBeInTheDocument();
  });

  it('renders an access denied state when the user lacks the required permission', () => {
    useAuthMock.mockReturnValue({
      canAccessModule: () => true,
      can: () => false,
    });

    render(
      <MemoryRouter>
        <ModuleGuard moduleKey="audit" requiredPermissions={['audit.view']}>
          <div>Conteudo Protegido</div>
        </ModuleGuard>
      </MemoryRouter>,
    );

    expect(screen.getByText(/acesso indisponivel/i)).toBeInTheDocument();
    expect(screen.queryByText('Conteudo Protegido')).not.toBeInTheDocument();
  });

  it('renders the feature lock when the module is not enabled for the session', () => {
    useAuthMock.mockReturnValue({
      canAccessModule: () => false,
      can: () => true,
    });

    render(
      <MemoryRouter>
        <ModuleGuard moduleKey="analytics" requiredPermissions={['analytics.view']}>
          <div>Conteudo Protegido</div>
        </ModuleGuard>
      </MemoryRouter>,
    );

    expect(screen.getByRole('heading', { name: /relatorios/i })).toBeInTheDocument();
    expect(screen.queryByText('Conteudo Protegido')).not.toBeInTheDocument();
  });
});
