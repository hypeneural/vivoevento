import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { IdentityAssistInline } from './IdentityAssistInline';

describe('IdentityAssistInline', () => {
  it('renders a checking state while the pre-check is running', () => {
    render(<IdentityAssistInline isChecking />);

    expect(screen.getByText(/verificando seus dados/i)).toBeInTheDocument();
  });

  it('renders the login suggestion with a continuation link', () => {
    render(
      <IdentityAssistInline
        state={{
          identity_status: 'login_suggested',
          title: 'Ja encontramos seu cadastro',
          description: 'Entrar agora costuma ser mais rapido para continuar sua compra.',
          action_label: 'Entrar para continuar',
          login_url: '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth',
          cooldown_seconds: null,
        }}
      />,
    );

    expect(screen.getByText(/ja encontramos seu cadastro/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /entrar para continuar/i })).toHaveAttribute(
      'href',
      '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth',
    );
  });

  it('renders the authenticated mismatch state without login CTA', () => {
    render(
      <IdentityAssistInline
        state={{
          identity_status: 'authenticated_mismatch',
          title: 'Use os dados da conta atual',
          description: 'Para continuar com seguranca, ajuste os dados para combinar com a conta autenticada.',
          action_label: null,
          login_url: null,
          cooldown_seconds: null,
        }}
      />,
    );

    expect(screen.getByText(/use os dados da conta atual/i)).toBeInTheDocument();
    expect(screen.queryByRole('link')).toBeNull();
  });
});
