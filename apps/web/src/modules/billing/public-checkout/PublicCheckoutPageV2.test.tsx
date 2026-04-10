import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError } from '@/lib/api';
import type {
  ApiEventPackage,
  PublicCheckoutIdentityCheckResponse,
  PublicEventCheckoutResponse,
} from '@/lib/api-types';

import { PublicCheckoutPageV2 } from './PublicCheckoutPageV2';
import { PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY } from './support/checkoutFormUtils';

const useAuthMock = vi.fn();
const listPackagesMock = vi.fn();
const createCheckoutMock = vi.fn();
const getCheckoutMock = vi.fn();
const useCheckoutIdentityPrecheckMock = vi.fn();
const createPagarmeCardTokenMock = vi.fn();
const refreshSessionMock = vi.fn();
const writeTextMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('../services/public-event-packages.service', () => ({
  publicEventPackagesService: {
    list: (...args: unknown[]) => listPackagesMock(...args),
  },
}));

vi.mock('../services/public-event-checkout.service', () => ({
  publicEventCheckoutService: {
    create: (...args: unknown[]) => createCheckoutMock(...args),
    get: (...args: unknown[]) => getCheckoutMock(...args),
  },
}));

vi.mock('./hooks/useCheckoutIdentityPrecheck', () => ({
  useCheckoutIdentityPrecheck: (...args: unknown[]) => useCheckoutIdentityPrecheckMock(...args),
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
    checkout_marketing: {
      slug: 'casamento-essencial',
      subtitle: 'O pacote mais equilibrado para eventos sociais com compra rapida.',
      ideal_for: 'Casamentos e aniversarios com telao ao vivo.',
      benefits: [
        'Telao ao vivo para os convidados',
        'Pagina do evento pronta para compartilhar',
        'Pix e cartao com confirmacao automatica',
      ],
      badge: 'Mais escolhido',
      recommended: true,
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

function createIdentityState(
  overrides: Partial<PublicCheckoutIdentityCheckResponse> = {},
): PublicCheckoutIdentityCheckResponse {
  return {
    identity_status: 'login_suggested',
    title: 'Ja encontramos seu cadastro',
    description: 'Entrar agora costuma ser mais rapido para continuar.',
    action_label: 'Entrar para continuar',
    login_url: '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth',
    cooldown_seconds: null,
    ...overrides,
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

function renderPage(initialEntry = '/checkout/evento?v2=1') {
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
          <Route path="/checkout/evento" element={<PublicCheckoutPageV2 />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function LoginProbe() {
  const location = useLocation();

  return <div>{`login-screen:${location.search}`}</div>;
}

function renderPageWithLoginRoute(initialEntry = '/checkout/evento?v2=1') {
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
          <Route path="/checkout/evento" element={<PublicCheckoutPageV2 />} />
          <Route path="/login" element={<LoginProbe />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

function fillDetailsStep() {
  fireEvent.change(screen.getByLabelText(/seu nome/i), {
    target: { value: 'Camila Rocha' },
  });
  fireEvent.change(screen.getByLabelText(/whatsapp/i), {
    target: { value: '(48) 99977-1111' },
  });
  fireEvent.change(screen.getByLabelText(/nome do evento/i), {
    target: { value: 'Casamento Camila e Bruno' },
  });
}

describe('PublicCheckoutPageV2', () => {
  beforeEach(() => {
    refreshSessionMock.mockReset();
    refreshSessionMock.mockResolvedValue(undefined);
    useAuthMock.mockReturnValue({ isAuthenticated: false, refreshSession: refreshSessionMock });
    listPackagesMock.mockResolvedValue([makePackage()]);
    createCheckoutMock.mockReset();
    getCheckoutMock.mockReset();
    useCheckoutIdentityPrecheckMock.mockReset();
    useCheckoutIdentityPrecheckMock.mockReturnValue({
      identityAssist: null,
      isChecking: false,
      isReady: true,
    });
    createPagarmeCardTokenMock.mockReset();
    writeTextMock.mockReset();
    vi.stubGlobal('navigator', {
      ...window.navigator,
      clipboard: {
        writeText: writeTextMock,
      },
    });
    window.localStorage.clear();
    window.sessionStorage.clear();
  });

  it('renders the new hero and loads the public packages commercially', async () => {
    renderPage();

    expect(screen.getByRole('heading', { name: /contrate seu evento em poucos minutos/i })).toBeInTheDocument();
    expect(await screen.findByRole('button', { name: /escolher este pacote/i })).toBeInTheDocument();
    expect(screen.getByText(/mais escolhido/i)).toBeInTheDocument();
    expect(screen.getByText(/telao ao vivo para os convidados/i)).toBeInTheDocument();
    expect(screen.getByText(/pagina do evento pronta para compartilhar/i)).toBeInTheDocument();
    expect(screen.getAllByText('R$ 199,00')).not.toHaveLength(0);
  });

  it('honors package deep links and opens the details step with the selected package', async () => {
    renderPage('/checkout/evento?package=casamento-essencial');

    expect(await screen.findByLabelText(/seu nome/i)).toBeInTheDocument();
    expect(screen.getByText(/^Seu pacote$/i)).toBeInTheDocument();
    expect(screen.getByText('Casamento Essencial')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /escolher este pacote/i })).not.toBeInTheDocument();
  });

  it('creates a pix checkout and moves the V2 into its own status state', async () => {
    const pendingResponse = makeResponse();
    createCheckoutMock.mockResolvedValue(pendingResponse);
    getCheckoutMock.mockResolvedValue(pendingResponse);

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));

    fillDetailsStep();
    fireEvent.click(screen.getByRole('button', { name: /continuar para pagamento/i }));

    expect(await screen.findByRole('button', { name: /gerar meu pix/i })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /gerar meu pix/i }));

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '48999771111',
      package_id: 1,
      payment: { method: 'pix' },
      event: {
        title: 'Casamento Camila e Bruno',
      },
    });

    await waitFor(() => {
      expect(refreshSessionMock).toHaveBeenCalledTimes(1);
    });

    expect(await screen.findByText(/pix gerado com sucesso/i)).toBeInTheDocument();
    expect(screen.getByText(/acompanhe seu pagamento/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /copiar codigo pix/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /gerar meu pix/i })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /copiar codigo pix/i }));

    await waitFor(() => {
      expect(writeTextMock).toHaveBeenCalledWith('000201010212');
    });

    expect(screen.getByRole('button', { name: /codigo pix copiado/i })).toBeInTheDocument();
  });

  it('surfaces the inline identity assist when pre-check suggests login', async () => {
    useCheckoutIdentityPrecheckMock.mockReturnValue({
      identityAssist: createIdentityState(),
      isChecking: false,
      isReady: true,
    });

    renderPage('/checkout/evento?v2=1&step=details');

    await waitFor(() => {
      expect(screen.getByText(/ja encontramos seu cadastro/i)).toBeInTheDocument();
    });

    const loginLink = screen.getByRole('link', { name: /entrar para continuar/i });

    expect(loginLink).toHaveAttribute('href', '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth');
  });

  it('stores a safe V2 resume draft and shows the login continuation when checkout creation finds an existing account', async () => {
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

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));
    fillDetailsStep();
    fireEvent.click(screen.getByRole('button', { name: /continuar para pagamento/i }));
    fireEvent.click(await screen.findByRole('button', { name: /gerar meu pix/i }));

    const loginLink = await screen.findByRole('link', { name: /entrar para continuar/i });

    expect(loginLink).toHaveAttribute('href', '/login?returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth%26package%3Dcasamento-essencial');

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      package_id: '1',
      payment_method: 'pix',
      source: 'identity_conflict',
      version: 1,
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
  }, 10000);

  it('stores a safe manual resume draft and sends the buyer to login when "Ja tenho conta" is used before payment', async () => {
    renderPageWithLoginRoute('/checkout/evento?package=casamento-essencial');

    await screen.findByLabelText(/seu nome/i);
    fillDetailsStep();

    fireEvent.click(screen.getByText(/ja tenho conta/i));

    expect(await screen.findByText(/^login-screen:/i)).toHaveTextContent(
      'returnTo=%2Fcheckout%2Fevento%3Fv2%3D1%26resume%3Dauth%26package%3Dcasamento-essencial',
    );

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      package_id: '1',
      payment_method: 'pix',
      source: 'manual_login',
      version: 1,
    });
    expect(draft.card_number).toBeUndefined();
    expect(draft.card_cvv).toBeUndefined();
  });

  it('formats a noisy WhatsApp input before continuing and still submits only digits in the payload', async () => {
    const pendingResponse = makeResponse();
    createCheckoutMock.mockResolvedValue(pendingResponse);
    getCheckoutMock.mockResolvedValue(pendingResponse);

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));

    fireEvent.change(screen.getByLabelText(/seu nome/i), {
      target: { value: 'Camila Rocha' },
    });
    fireEvent.change(screen.getByLabelText(/whatsapp/i), {
      target: { value: 'abc48999771111' },
    });
    fireEvent.change(screen.getByLabelText(/nome do evento/i), {
      target: { value: 'Casamento Camila e Bruno' },
    });

    expect(screen.getByLabelText(/whatsapp/i)).toHaveValue('(48) 99977-1111');

    await waitFor(() => {
      expect(useCheckoutIdentityPrecheckMock).toHaveBeenLastCalledWith({
        whatsapp: '(48) 99977-1111',
        email: '',
      });
    });

    fireEvent.click(screen.getByRole('button', { name: /continuar para pagamento/i }));
    fireEvent.click(await screen.findByRole('button', { name: /gerar meu pix/i }));

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      whatsapp: '48999771111',
      payment: { method: 'pix' },
    });
  });

  it('keeps the optional event schedule with date and time in the public checkout payload', async () => {
    const pendingResponse = makeResponse();
    createCheckoutMock.mockResolvedValue(pendingResponse);
    getCheckoutMock.mockResolvedValue(pendingResponse);

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /escolher este pacote/i }));
    fillDetailsStep();

    fireEvent.click(screen.getByRole('button', { name: /adicionar mais detalhes/i }));
    fireEvent.change(screen.getByLabelText(/quando seu evento acontece/i), {
      target: { value: '2026-11-15T18:30' },
    });

    fireEvent.click(screen.getByRole('button', { name: /continuar para pagamento/i }));
    fireEvent.click(await screen.findByRole('button', { name: /gerar meu pix/i }));

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      event: {
        event_date: '2026-11-15T18:30',
      },
    });
  });

  it('skips the login page and lands on payment when an authenticated buyer clicks "Ja tenho conta"', async () => {
    useAuthMock.mockReturnValue({ isAuthenticated: true, refreshSession: refreshSessionMock });

    renderPage('/checkout/evento?package=casamento-essencial');

    await screen.findByLabelText(/seu nome/i);
    fillDetailsStep();

    fireEvent.click(screen.getByText(/ja tenho conta/i));

    expect(await screen.findByRole('button', { name: /gerar meu pix/i })).toBeInTheDocument();
    expect(createCheckoutMock).not.toHaveBeenCalled();

    const draft = JSON.parse(
      window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY) ?? '{}',
    ) as Record<string, unknown>;

    expect(draft).toMatchObject({
      source: 'manual_login',
      package_id: '1',
    });
  });

  it('automatically resumes a pix draft after login inside the V2 flow', async () => {
    useAuthMock.mockReturnValue({ isAuthenticated: true, refreshSession: refreshSessionMock });
    const resumedResponse = makeResponse({
      onboarding: {
        title: 'Sessao retomada com sucesso',
        description: 'Seu checkout foi retomado na conta existente.',
        next_path: '/events/11',
      },
    });

    createCheckoutMock.mockResolvedValue(resumedResponse);
    getCheckoutMock.mockResolvedValue(resumedResponse);

    window.sessionStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify({
      version: 1,
      source: 'identity_conflict',
      saved_at: '2026-04-09T10:00:00Z',
      expires_at: '2099-04-09T10:00:00Z',
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: '',
      organization_name: '',
      package_id: '1',
      event_title: 'Casamento Camila e Bruno',
      event_type: 'wedding',
      event_date: '',
      event_city: '',
      event_description: '',
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
    }));

    renderPage('/checkout/evento?v2=1&resume=auth');

    await waitFor(() => {
      expect(createCheckoutMock).toHaveBeenCalledTimes(1);
    });

    expect(createCheckoutMock.mock.calls[0]?.[0]).toMatchObject({
      payment: { method: 'pix' },
      responsible_name: 'Camila Rocha',
      whatsapp: '48999771111',
      event: {
        title: 'Casamento Camila e Bruno',
      },
    });

    expect(await screen.findByText(/pix gerado com sucesso/i)).toBeInTheDocument();
    expect(window.sessionStorage.getItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY)).toBeNull();
  });

  it('keeps the resumed credit-card journey on the payment step even when the URL still has a package deep link', async () => {
    useAuthMock.mockReturnValue({ isAuthenticated: true, refreshSession: refreshSessionMock });

    window.sessionStorage.setItem(PUBLIC_CHECKOUT_V2_RESUME_DRAFT_STORAGE_KEY, JSON.stringify({
      version: 1,
      source: 'identity_conflict',
      saved_at: '2026-04-09T10:00:00Z',
      expires_at: '2099-04-09T10:00:00Z',
      responsible_name: 'Camila Rocha',
      whatsapp: '(48) 99977-1111',
      email: 'camila@example.com',
      organization_name: '',
      package_id: '1',
      event_title: 'Casamento Camila e Bruno',
      event_type: 'wedding',
      event_date: '',
      event_city: '',
      event_description: '',
      payment_method: 'credit_card',
      payer_document: '529.982.247-25',
      payer_phone: '(48) 99977-1111',
      address_street: 'Rua das Flores',
      address_number: '123',
      address_district: 'Centro',
      address_complement: '',
      address_zip_code: '88000-000',
      address_city: 'Florianopolis',
      address_state: 'SC',
    }));

    renderPage('/checkout/evento?resume=auth&package=casamento-essencial');

    expect(await screen.findByText(/os campos do cartao precisam ser preenchidos novamente/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /finalizar com cartao/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /continuar para pagamento/i })).not.toBeInTheDocument();
  });
});
