import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError } from '@/lib/api';
import type { ApiEventPackage, PublicEventCheckoutResponse } from '@/lib/api-types';

import PublicEventCheckoutPage from './PublicEventCheckoutPage';

const useAuthMock = vi.fn();
const listPackagesMock = vi.fn();
const createCheckoutMock = vi.fn();
const getCheckoutMock = vi.fn();
const createPagarmeCardTokenMock = vi.fn();
const writeTextMock = vi.fn();
const refreshSessionMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('./services/public-event-packages.service', () => ({
  publicEventPackagesService: {
    list: (...args: unknown[]) => listPackagesMock(...args),
  },
}));

vi.mock('./services/public-event-checkout.service', () => ({
  publicEventCheckoutService: {
    create: (...args: unknown[]) => createCheckoutMock(...args),
    get: (...args: unknown[]) => getCheckoutMock(...args),
  },
}));

vi.mock('@/lib/pagarme-tokenization', () => ({
  createPagarmeCardToken: (...args: unknown[]) => createPagarmeCardTokenMock(...args),
  PagarmeTokenizationError: class PagarmeTokenizationError extends Error {},
}));

function makePackage(): ApiEventPackage {
  return {
    id: 1,
    code: 'casamento-essencial',
    name: 'Casamento Essencial',
    description: 'Pacote enxuto para evento unico.',
    target_audience: 'direct_customer',
    is_active: true,
    sort_order: 1,
    default_price: {
      id: 10,
      billing_mode: 'one_time',
      currency: 'BRL',
      amount_cents: 19900,
      is_active: true,
      is_default: true,
    },
    prices: [],
    features: [],
    feature_map: {
      'hub.enabled': true,
      'wall.enabled': true,
      'play.enabled': false,
    },
    modules: {
      hub: true,
      wall: true,
      play: false,
    },
    limits: {
      retention_days: 90,
      max_photos: 400,
    },
  };
}

function makeResponse(overrides: Partial<PublicEventCheckoutResponse> = {}): PublicEventCheckoutResponse {
  return {
    message: 'Checkout iniciado com sucesso.',
    token: 'token_public_checkout',
    user: null,
    organization: null,
    event: null,
    commercial_status: null,
    checkout: {
      id: 11,
      uuid: 'checkout-uuid',
      mode: 'event_package',
      status: 'pending_payment',
      currency: 'BRL',
      total_cents: 19900,
      created_at: '2026-04-05T10:00:00Z',
      updated_at: '2026-04-05T10:00:00Z',
      confirmed_at: null,
      payment: {
        provider: 'pagarme',
        method: 'pix',
        gateway_order_id: 'or_test_123',
        gateway_charge_id: 'ch_test_123',
        gateway_transaction_id: null,
        gateway_status: 'pending',
        status: 'pending_payment',
        checkout_url: null,
        confirm_url: null,
        expires_at: '2026-04-05T10:30:00Z',
        pix: {
          qr_code: '000201010212',
          qr_code_url: 'https://pagar.me/qr/ch_test_123.png',
          expires_at: '2026-04-05T10:30:00Z',
        },
        credit_card: null,
        whatsapp: {
          pix_generated: null,
          payment_paid: null,
          payment_failed: null,
          payment_refunded: null,
        },
      },
      package: {
        id: 1,
        code: 'casamento-essencial',
        name: 'Casamento Essencial',
        description: 'Pacote enxuto para evento unico.',
        target_audience: 'direct_customer',
      },
      items: [],
    },
    purchase: null,
    onboarding: {
      title: 'Evento pronto',
      description: 'Finalize o pagamento para ativar o pacote.',
      next_path: '/events/11',
    },
    ...overrides,
  };
}

function renderPage(initialEntry = '/checkout/evento') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/checkout/evento" element={<PublicEventCheckoutPage pollingIntervalMs={200} />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function fillPixForm() {
  fireEvent.change(screen.getByLabelText(/nome do respons/i), {
    target: { value: 'Camila Rocha' },
  });
  fireEvent.change(screen.getByLabelText(/whatsapp/i), {
    target: { value: '(48) 99977-1111' },
  });
  fireEvent.change(screen.getByLabelText(/email principal/i), {
    target: { value: 'camila@example.com' },
  });
  fireEvent.change(screen.getByLabelText(/nome do evento/i), {
    target: { value: 'Casamento Camila e Bruno' },
  });
  fireEvent.change(screen.getByLabelText(/cidade do evento/i), {
    target: { value: 'Florianopolis' },
  });
}

describe('PublicEventCheckoutPage', () => {
  beforeEach(() => {
    refreshSessionMock.mockReset();
    refreshSessionMock.mockResolvedValue(undefined);
    useAuthMock.mockReturnValue({ isAuthenticated: false, refreshSession: refreshSessionMock });
    listPackagesMock.mockResolvedValue([makePackage()]);
    createCheckoutMock.mockReset();
    getCheckoutMock.mockReset();
    createPagarmeCardTokenMock.mockReset();
    writeTextMock.mockReset();
    vi.stubGlobal('navigator', {
      ...window.navigator,
      clipboard: {
        writeText: writeTextMock,
      },
    });
    window.localStorage.clear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('creates a pix checkout and renders the local qr-code experience', async () => {
    const pendingResponse = makeResponse();

    createCheckoutMock.mockResolvedValue(pendingResponse);
    getCheckoutMock.mockResolvedValue(pendingResponse);

    renderPage();

    await screen.findByRole('button', { name: /casamento essencial/i });

    fillPixForm();
    fireEvent.click(screen.getByRole('button', { name: /gerar pix/i }));

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '48999771111',
      email: 'camila@example.com',
      package_id: 1,
      payment: { method: 'pix' },
      event: {
        title: 'Casamento Camila e Bruno',
        city: 'Florianopolis',
      },
    });

    await waitFor(() => {
      expect(refreshSessionMock).toHaveBeenCalledTimes(1);
    });

    await screen.findByText(/pix gerado com sucesso/i);
    await screen.findByText(/qr code e codigo copia e cola/i);
    await screen.findByText(/expira em/i);

    const qrLink = screen.getByRole('link', { name: /abrir qr em nova aba/i });

    expect(qrLink).toHaveAttribute('href', 'https://pagar.me/qr/ch_test_123.png');
    expect(screen.getByText(/pedido criado/i)).toBeInTheDocument();
    expect(screen.getByText(/aguardando pagamento pix/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /copiar codigo pix/i }));

    await waitFor(() => {
      expect(writeTextMock).toHaveBeenCalledWith('000201010212');
    });

    expect(screen.getByRole('button', { name: /codigo pix copiado/i })).toBeInTheDocument();
  });

  it('surfaces the whatsapp pix delivery evidence when the backend reports it', async () => {
    const pendingResponse = makeResponse({
      checkout: {
        ...makeResponse().checkout,
        payment: {
          ...makeResponse().checkout.payment,
          whatsapp: {
            pix_generated: {
              type: 'pix_generated',
              status: 'queued',
              recipient_phone: '5548999881111',
              dispatched_at: '2026-04-05T10:00:05Z',
              failed_at: null,
              whatsapp_message_id: 91,
              pix_button_message_id: 92,
              pix_button_enabled: true,
              pix_button_value_source: 'gateway_qr_code',
            },
            payment_paid: null,
            payment_failed: null,
            payment_refunded: null,
          },
        },
      },
    });

    createCheckoutMock.mockResolvedValue(pendingResponse);
    getCheckoutMock.mockResolvedValue(pendingResponse);

    renderPage();

    await screen.findByRole('button', { name: /casamento essencial/i });

    fillPixForm();
    fireEvent.click(screen.getByRole('button', { name: /gerar pix/i }));

    expect(await screen.findByText(/tambem enviamos este pix para o seu whatsapp/i)).toBeInTheDocument();
    expect(screen.getByText(/numero usado: 5548999881111/i)).toBeInTheDocument();
    expect(screen.getByText(/incluiu o botao de copiar o pix/i)).toBeInTheDocument();
  });

  it('shows the login continuation branch and stores only a safe resume draft on identity conflict', async () => {
    createCheckoutMock.mockRejectedValue(
      new ApiError(
        422,
        { message: 'Ja existe uma conta com este contato. Faca login para continuar.' },
        {
          whatsapp: ['Ja existe uma conta com este WhatsApp. Faca login para continuar.'],
        },
      ),
    );

    renderPage();

    await screen.findByRole('button', { name: /casamento essencial/i });

    fillPixForm();
    fireEvent.click(screen.getByRole('button', { name: /gerar pix/i }));

    const loginLink = await screen.findByRole('link', { name: /fazer login para continuar/i });

    expect(loginLink).toHaveAttribute('href', '/login?returnTo=%2Fcheckout%2Fevento%3Fresume%3Dauth');

    const draft = JSON.parse(
      window.localStorage.getItem('eventovivo.public-event-checkout.resume-draft') ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      payment_method: 'pix',
      source: 'identity_conflict',
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
    expect(screen.getByText(/faca login para continuar/i)).toBeInTheDocument();
  });

  it('automatically resumes a pix checkout after authentication using the stored safe draft', async () => {
    useAuthMock.mockReturnValue({ isAuthenticated: true });

    const resumedResponse = makeResponse({
      token: null,
      onboarding: {
        title: 'Sessao retomada com sucesso',
        description: 'Seu checkout foi retomado na conta existente.',
        next_path: '/events/11',
      },
    });

    createCheckoutMock.mockResolvedValue(resumedResponse);
    getCheckoutMock.mockResolvedValue(resumedResponse);

    window.localStorage.setItem('eventovivo.public-event-checkout.resume-draft', JSON.stringify({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      organization_name: 'Camila & Bruno',
      package_id: '1',
      event_title: 'Casamento Camila & Bruno',
      event_type: 'wedding',
      event_date: '2026-12-05',
      event_city: 'Florianopolis',
      event_description: 'Evento retomado apos login.',
      payment_method: 'pix',
      payer_document: '',
      payer_phone: '',
      address_street: '',
      address_number: '',
      address_district: '',
      address_complement: '',
      address_zip_code: '',
      address_city: '',
      address_state: '',
      source: 'identity_conflict',
      saved_at: '2026-04-05T12:00:00Z',
    }));

    renderPage('/checkout/evento?resume=auth');

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '48999771111',
      email: 'camila@example.com',
      payment: { method: 'pix' },
      event: {
        title: 'Casamento Camila & Bruno',
        event_type: 'wedding',
      },
    });

    await screen.findByText(/sessao retomada com sucesso/i);
    expect(window.localStorage.getItem('eventovivo.public-event-checkout.resume-draft')).toBeNull();
  });

  it('restores a credit-card draft after authentication without persisting or auto-submitting sensitive card fields', async () => {
    useAuthMock.mockReturnValue({ isAuthenticated: true });

    window.localStorage.setItem('eventovivo.public-event-checkout.resume-draft', JSON.stringify({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      organization_name: 'Camila & Bruno',
      package_id: '1',
      event_title: 'Casamento Camila & Bruno',
      event_type: 'wedding',
      event_date: '2026-12-05',
      event_city: 'Florianopolis',
      event_description: 'Evento retomado apos login.',
      payment_method: 'credit_card',
      payer_document: '123.456.789-09',
      payer_phone: '(48) 99977-1111',
      address_street: 'Rua Exemplo',
      address_number: '123',
      address_district: 'Centro',
      address_complement: 'Sala 2',
      address_zip_code: '88000-000',
      address_city: 'Florianopolis',
      address_state: 'SC',
      source: 'identity_conflict',
      saved_at: '2026-04-05T12:00:00Z',
    }));

    renderPage('/checkout/evento?resume=auth');

    expect(await screen.findAllByText(/retomamos os dados seguros da sua jornada/i)).toHaveLength(2);

    expect(createCheckoutMock).not.toHaveBeenCalled();
    expect(await screen.findByDisplayValue('Camila Rocha')).toBeInTheDocument();
    expect(screen.getByDisplayValue('123.456.789-09')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Rua Exemplo')).toBeInTheDocument();
    expect(screen.getByLabelText(/^numero do cart/i)).toHaveValue('');
    expect(screen.getByLabelText(/cvv/i)).toHaveValue('');
  });

  it('prefills the payer phone from WhatsApp and shows the card checklist', async () => {
    renderPage();

    await screen.findByRole('button', { name: /casamento essencial/i });

    fillPixForm();
    fireEvent.click(screen.getByRole('button', { name: /cart/i }));

    await waitFor(() => {
      expect(screen.getByLabelText(/telefone do pagador/i)).toHaveValue('(48) 99977-1111');
    });

    expect(screen.getByText(/checklist do pagamento/i)).toBeInTheDocument();
    expect(screen.getByText(/cpf valido do pagador/i)).toBeInTheDocument();
    expect(screen.getByText(/quando tudo ficar pronto/i)).toBeInTheDocument();
  });

  it('blocks the card checkout when required payment fields are still invalid', async () => {
    renderPage();

    await screen.findByRole('button', { name: /casamento essencial/i });

    fillPixForm();
    fireEvent.click(screen.getByRole('button', { name: /cart/i }));

    fireEvent.change(screen.getByLabelText(/cpf do pagador/i), {
      target: { value: '123.456.789-00' },
    });
    fireEvent.change(screen.getByLabelText(/^rua$/i), {
      target: { value: 'Rua Exemplo' },
    });
    fireEvent.change(screen.getByLabelText(/^numero$/i), {
      target: { value: '123' },
    });
    fireEvent.change(screen.getByLabelText(/bairro/i), {
      target: { value: 'Centro' },
    });
    fireEvent.change(screen.getByLabelText(/^cep$/i), {
      target: { value: '8800' },
    });
    fireEvent.change(screen.getByLabelText(/^cidade$/i), {
      target: { value: 'Florianopolis' },
    });
    fireEvent.change(screen.getByLabelText(/estado/i), {
      target: { value: 'S' },
    });
    fireEvent.change(screen.getByLabelText(/^numero do cart/i), {
      target: { value: '1234 5678 9012 3456' },
    });
    fireEvent.change(screen.getByLabelText(/nome impresso no cartao/i), {
      target: { value: 'CAMILA' },
    });
    fireEvent.change(screen.getByLabelText(/^mes$/i), {
      target: { value: '01' },
    });
    fireEvent.change(screen.getByLabelText(/^ano$/i), {
      target: { value: '20' },
    });
    fireEvent.change(screen.getByLabelText(/cvv/i), {
      target: { value: '12' },
    });

    fireEvent.click(screen.getByRole('button', { name: /pagar com cart/i }));

    expect(await screen.findByText(/cpf valido/i)).toBeInTheDocument();
    expect(screen.getByText(/uf com 2 letras/i)).toBeInTheDocument();
    expect(screen.getByText(/cartao valido/i)).toBeInTheDocument();
    expect(screen.getAllByText(/nome e sobrenome/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/validade vigente/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/cvv deve ter 3 ou 4/i)).toBeInTheDocument();
    expect(createPagarmeCardTokenMock).not.toHaveBeenCalled();
    expect(createCheckoutMock).not.toHaveBeenCalled();
  });
});
