import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { PublicCheckoutStatusViewModel } from '../mappers/checkoutStatusViewModel';
import { PaymentStatusCard } from './PaymentStatusCard';

const writeTextMock = vi.fn();

function createStatus(overrides: Partial<PublicCheckoutStatusViewModel> = {}): PublicCheckoutStatusViewModel {
  return {
    state: 'pending',
    tone: 'info',
    title: 'Pix gerado com sucesso',
    description: 'Use o QR Code ou o codigo copia e cola abaixo.',
    paymentMethod: 'pix',
    statusLabel: 'Aguardando pagamento',
    isTerminal: false,
    qrCode: '000201010212',
    qrCodeUrl: 'https://pagar.me/qr/teste.png',
    pixExpiresAt: '2026-04-09T12:30:00Z',
    pixExpiresLabel: '14 min',
    whatsappPixNotice: {
      delivered: true,
      sent_at: '2026-04-09T12:01:00Z',
      destination: '*****1111',
    },
    onboardingPath: '/events/11',
    ...overrides,
  };
}

describe('PaymentStatusCard', () => {
  beforeEach(() => {
    writeTextMock.mockReset();
    vi.stubGlobal('navigator', {
      ...window.navigator,
      clipboard: {
        writeText: writeTextMock,
      },
    });
  });

  it('shows a buyer-facing Pix status without leaking operational terms', async () => {
    const onRefresh = vi.fn();

    render(
      <MemoryRouter>
        <PaymentStatusCard status={createStatus()} onRefresh={onRefresh} />
      </MemoryRouter>,
    );

    expect(screen.getByText(/acompanhe seu pagamento/i)).toBeInTheDocument();
    expect(screen.getByText(/aguardando pagamento/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copiar codigo pix/i })).toBeInTheDocument();
    expect(screen.queryByText(/gateway status/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/uuid/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /copiar codigo pix/i }));

    await waitFor(() => {
      expect(writeTextMock).toHaveBeenCalledWith('000201010212');
    });

    fireEvent.click(screen.getByRole('button', { name: /atualizar pagamento/i }));

    expect(onRefresh).toHaveBeenCalledTimes(1);
  });

  it('shows follow-up actions when the payment is already confirmed', () => {
    render(
      <MemoryRouter>
        <PaymentStatusCard
          status={createStatus({
            state: 'paid',
            tone: 'success',
            title: 'Pagamento confirmado',
            statusLabel: 'Confirmado',
            isTerminal: true,
          })}
          isAuthenticated
          onRefresh={vi.fn()}
        />
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: /abrir meu evento/i })).toHaveAttribute('href', '/events/11');
    expect(screen.getByRole('link', { name: /ver cobrancas e faturas/i })).toHaveAttribute('href', '/plans');
  });
});
