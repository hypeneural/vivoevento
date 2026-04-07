import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PartnersPage from './PartnersPage';
import type { EventListItem } from '@/modules/events/types';
import type { PaginatedPartnersResponse, PartnerDetailItem, PartnerListItem } from './types';

const useAuthMock = vi.fn();
const listPartnersMock = vi.fn();
const showPartnerMock = vi.fn();
const createPartnerMock = vi.fn();
const updatePartnerMock = vi.fn();
const suspendPartnerMock = vi.fn();
const removePartnerMock = vi.fn();
const listEventsMock = vi.fn();
const listClientsMock = vi.fn();
const listStaffMock = vi.fn();
const inviteStaffMock = vi.fn();
const listGrantsMock = vi.fn();
const createGrantMock = vi.fn();
const listActivityMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('./api', () => ({
  partnersService: {
    list: (...args: unknown[]) => listPartnersMock(...args),
    show: (...args: unknown[]) => showPartnerMock(...args),
    create: (...args: unknown[]) => createPartnerMock(...args),
    update: (...args: unknown[]) => updatePartnerMock(...args),
    suspend: (...args: unknown[]) => suspendPartnerMock(...args),
    remove: (...args: unknown[]) => removePartnerMock(...args),
    listEvents: (...args: unknown[]) => listEventsMock(...args),
    listClients: (...args: unknown[]) => listClientsMock(...args),
    listStaff: (...args: unknown[]) => listStaffMock(...args),
    inviteStaff: (...args: unknown[]) => inviteStaffMock(...args),
    listGrants: (...args: unknown[]) => listGrantsMock(...args),
    createGrant: (...args: unknown[]) => createGrantMock(...args),
    listActivity: (...args: unknown[]) => listActivityMock(...args),
  },
}));

function makePartner(overrides: Partial<PartnerListItem> = {}): PartnerListItem {
  return {
    id: 10,
    uuid: 'partner-uuid',
    type: 'partner',
    name: 'Cerimonial API',
    legal_name: 'Cerimonial API LTDA',
    trade_name: 'Cerimonial API',
    document_number: '00.000.000/0001-00',
    slug: 'cerimonial-api',
    email: 'contato@cerimonialapi.test',
    billing_email: 'financeiro@cerimonialapi.test',
    phone: '11999990000',
    logo_path: null,
    timezone: 'America/Sao_Paulo',
    status: 'active',
    segment: 'cerimonialista',
    notes: null,
    clients_count: 4,
    events_count: 7,
    active_events_count: 3,
    team_size: 2,
    active_bonus_grants_count: 1,
    current_subscription: {
      plan_key: 'pro-parceiro',
      plan_name: 'Pro Parceiro',
      status: 'active',
      billing_cycle: 'monthly',
    },
    revenue: {
      currency: 'BRL',
      subscription_cents: 9900,
      event_package_cents: 19900,
      total_cents: 29800,
    },
    stats_refreshed_at: '2026-04-07T12:00:00Z',
    owner: {
      id: 22,
      name: 'Maria Owner',
      email: 'maria@cerimonialapi.test',
      phone: null,
    },
    created_at: '2026-04-07T12:00:00Z',
    updated_at: '2026-04-07T12:00:00Z',
    ...overrides,
  };
}

function makePartnersResponse(partners: PartnerListItem[] = [makePartner()]): PaginatedPartnersResponse {
  return {
    success: true,
    data: partners,
    meta: {
      page: 1,
      per_page: 15,
      total: partners.length,
      last_page: 1,
      request_id: 'test-request',
    },
  };
}

function makePartnerDetail(overrides: Partial<PartnerDetailItem> = {}): PartnerDetailItem {
  return {
    ...makePartner(),
    events_summary: {
      total: 7,
      active: 3,
      draft: 1,
      bonus: 1,
      manual_override: 0,
      single_purchase: 2,
      subscription_covered: 4,
    },
    clients_summary: {
      total: 4,
    },
    staff_summary: {
      total: 2,
      owners: 1,
    },
    grants_summary: {
      active_bonus: 1,
      active_manual_override: 0,
    },
    latest_activity: [],
    ...overrides,
  };
}

function makeEmptyPaginatedResponse() {
  return {
    success: true,
    data: [],
    meta: {
      page: 1,
      per_page: 10,
      total: 0,
      last_page: 1,
      request_id: 'test-request',
    },
  };
}

function makeEvent(overrides: Partial<EventListItem> = {}): EventListItem {
  return {
    id: 88,
    uuid: 'event-uuid',
    organization_id: 10,
    client_id: null,
    title: 'Casamento Teste',
    slug: 'casamento-teste',
    upload_slug: 'casamento-teste-upload',
    event_type: 'wedding',
    status: 'active',
    commercial_mode: 'subscription_covered',
    visibility: 'private',
    moderation_mode: 'manual',
    starts_at: '2026-04-10T18:00:00Z',
    ends_at: null,
    location_name: 'Sao Paulo',
    cover_image_path: null,
    cover_image_url: null,
    primary_color: null,
    secondary_color: null,
    public_url: null,
    upload_url: null,
    created_at: '2026-04-07T12:00:00Z',
    organization_name: 'Cerimonial API',
    client_name: null,
    enabled_modules: ['hub'],
    media_count: 0,
    wall: null,
    ...overrides,
  };
}

function makeEventsResponse(events: EventListItem[] = [makeEvent()]) {
  return {
    success: true,
    data: events,
    meta: {
      page: 1,
      per_page: 10,
      total: events.length,
      last_page: 1,
      request_id: 'test-request',
    },
  };
}

function makePaginationMeta(overrides: Partial<PaginatedPartnersResponse['meta']> = {}) {
  return {
    page: 1,
    per_page: 10,
    total: 1,
    last_page: 1,
    request_id: 'test-request',
    ...overrides,
  };
}

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <PartnersPage />
    </QueryClientProvider>,
  );
}

describe('PartnersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    HTMLElement.prototype.hasPointerCapture = vi.fn(() => false);
    HTMLElement.prototype.setPointerCapture = vi.fn();
    HTMLElement.prototype.releasePointerCapture = vi.fn();
    HTMLElement.prototype.scrollIntoView = vi.fn();

    useAuthMock.mockReturnValue({
      can: (permission: string) => permission === 'partners.view.any',
    });

    listPartnersMock.mockResolvedValue(makePartnersResponse());
    showPartnerMock.mockResolvedValue(makePartnerDetail());
    createPartnerMock.mockResolvedValue(makePartner({ id: 11, name: 'Cerimonial Novo' }));
    updatePartnerMock.mockResolvedValue(makePartner());
    suspendPartnerMock.mockResolvedValue(makePartner({ status: 'suspended' }));
    removePartnerMock.mockResolvedValue(undefined);
    listEventsMock.mockResolvedValue(makeEmptyPaginatedResponse());
    listClientsMock.mockResolvedValue(makeEmptyPaginatedResponse());
    listStaffMock.mockResolvedValue(makeEmptyPaginatedResponse());
    inviteStaffMock.mockResolvedValue({});
    listGrantsMock.mockResolvedValue(makeEmptyPaginatedResponse());
    createGrantMock.mockResolvedValue({});
    listActivityMock.mockResolvedValue(makeEmptyPaginatedResponse());
  });

  it('loads partners from the API instead of the local mock list', async () => {
    renderPage();

    expect(await screen.findByText('Cerimonial API')).toBeInTheDocument();
    expect(screen.getByText('cerimonialista')).toBeInTheDocument();
    expect(screen.getByText('Pro Parceiro')).toBeInTheDocument();
    expect(screen.getByText(/3 ativos \/ 7 total/i)).toBeInTheDocument();
    expect(screen.queryByText('Studio Lumiere')).not.toBeInTheDocument();

    expect(listPartnersMock).toHaveBeenCalledWith(expect.objectContaining({
      page: 1,
      per_page: 15,
      sort_by: 'created_at',
      sort_direction: 'desc',
    }));
  });

  it('keeps filters collapsed by default and expands them on demand', async () => {
    renderPage();

    await screen.findByText('Cerimonial API');

    expect(screen.queryByPlaceholderText(/buscar por nome/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /filtros e ordenacao/i }));

    expect(screen.getByPlaceholderText(/buscar por nome/i)).toBeInTheDocument();
  });

  it('forwards the search filter to the partners API query', async () => {
    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /filtros e ordenacao/i }));

    fireEvent.change(screen.getByPlaceholderText(/buscar por nome/i), {
      target: { value: 'Horizonte' },
    });

    await waitFor(() => {
      expect(listPartnersMock).toHaveBeenCalledWith(expect.objectContaining({
        search: 'Horizonte',
        page: 1,
      }));
    });
  });

  it('forwards the plan code filter to the partners API query', async () => {
    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /filtros e ordenacao/i }));

    fireEvent.change(screen.getByLabelText(/codigo do plano/i), {
      target: { value: 'pro-parceiro' },
    });

    await waitFor(() => {
      expect(listPartnersMock).toHaveBeenCalledWith(expect.objectContaining({
        plan_code: 'pro-parceiro',
        page: 1,
      }));
    });
  });

  it('opens the detailed admin sheet and loads partner subresources', async () => {
    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));

    await waitFor(() => {
      expect(showPartnerMock).toHaveBeenCalledWith(10);
      expect(listEventsMock).toHaveBeenCalledWith(10, expect.objectContaining({ per_page: 8 }));
      expect(listClientsMock).toHaveBeenCalledWith(10, expect.objectContaining({ per_page: 8 }));
      expect(listStaffMock).toHaveBeenCalledWith(10, expect.objectContaining({ per_page: 25 }));
      expect(listGrantsMock).toHaveBeenCalledWith(10, expect.objectContaining({ per_page: 10 }));
      expect(listActivityMock).toHaveBeenCalledWith(10, expect.objectContaining({ per_page: 10 }));
    });

    expect(await screen.findByText(/visao administrativa global/i)).toBeInTheDocument();
  });

  it('renders translated role labels inside partner staff details', async () => {
    listStaffMock.mockResolvedValue({
      success: true,
      data: [
        {
          id: 10,
          role_key: 'partner-owner',
          is_owner: true,
          status: 'active',
          invited_at: null,
          joined_at: '2026-04-07T12:00:00Z',
          user: {
            id: 22,
            name: 'Maria Team',
            email: 'maria@partner.test',
            phone: null,
          },
        },
      ],
      meta: makePaginationMeta(),
    });

    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));
    fireEvent.click(await screen.findByRole('tab', { name: /equipe/i }));

    expect(await screen.findByText(/propriet/i)).toBeInTheDocument();
    expect(screen.queryByText(/^owner$/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/partner-owner/i)).not.toBeInTheDocument();
  });

  it('forwards event filters and pagination inside the detailed admin sheet', async () => {
    listEventsMock.mockResolvedValue({
      success: true,
      data: [makeEvent()],
      meta: makePaginationMeta({
        per_page: 8,
        total: 2,
        last_page: 2,
      }),
    });

    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));
    const eventsTab = await screen.findByRole('tab', { name: /eventos/i });
    fireEvent.mouseDown(eventsTab);
    fireEvent.click(eventsTab);

    await waitFor(() => {
      expect(eventsTab).toHaveAttribute('data-state', 'active');
    });

    fireEvent.change(await screen.findByLabelText(/buscar eventos/i), {
      target: { value: 'Wedding' },
    });
    fireEvent.change(screen.getByLabelText(/modo comercial/i), {
      target: { value: 'single_purchase' },
    });
    fireEvent.change(screen.getByLabelText(/eventos por pagina/i), {
      target: { value: '15' },
    });

    await waitFor(() => {
      expect(listEventsMock).toHaveBeenLastCalledWith(10, expect.objectContaining({
        search: 'Wedding',
        commercial_mode: 'single_purchase',
        per_page: 15,
        page: 1,
      }));
    });

    expect(await screen.findByText(/pagina 1 de 2/i)).toBeInTheDocument();
    fireEvent.click(screen.getByText(/^proxima$/i));

    await waitFor(() => {
      expect(listEventsMock).toHaveBeenLastCalledWith(10, expect.objectContaining({
        search: 'Wedding',
        commercial_mode: 'single_purchase',
        per_page: 15,
        page: 2,
      }));
    });
  }, 10000);

  it('forwards client filters inside the detailed admin sheet', async () => {
    listClientsMock.mockResolvedValue({
      success: true,
      data: [
        {
          id: 55,
          name: 'Cliente API',
          email: 'cliente@api.test',
          phone: null,
          events_count: 2,
        },
      ],
      meta: makePaginationMeta({
        per_page: 8,
      }),
    });

    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));
    const clientsTab = await screen.findByRole('tab', { name: /clientes/i });
    fireEvent.mouseDown(clientsTab);
    fireEvent.click(clientsTab);

    await waitFor(() => {
      expect(clientsTab).toHaveAttribute('data-state', 'active');
    });

    fireEvent.change(await screen.findByLabelText(/buscar clientes/i), {
      target: { value: 'Ana' },
    });
    fireEvent.change(screen.getByLabelText(/recorte de clientes/i), {
      target: { value: 'with_events' },
    });
    fireEvent.change(screen.getByLabelText(/clientes por pagina/i), {
      target: { value: '15' },
    });

    await waitFor(() => {
      expect(listClientsMock).toHaveBeenLastCalledWith(10, expect.objectContaining({
        search: 'Ana',
        has_events: true,
        per_page: 15,
        page: 1,
      }));
    });
  });

  it('forwards grant filters inside the detailed admin sheet', async () => {
    listGrantsMock.mockResolvedValue({
      success: true,
      data: [
        {
          id: 99,
          event_id: 88,
          event_title: 'Casamento Teste',
          source_type: 'bonus',
          status: 'active',
          reason: 'Campanha',
          created_at: '2026-04-07T12:00:00Z',
          expires_at: null,
        },
      ],
      meta: makePaginationMeta(),
    });

    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));
    const grantsTab = await screen.findByRole('tab', { name: /concess/i });
    fireEvent.mouseDown(grantsTab);
    fireEvent.click(grantsTab);

    await waitFor(() => {
      expect(grantsTab).toHaveAttribute('data-state', 'active');
    });

    fireEvent.change(await screen.findByLabelText(/tipo de concessao/i), {
      target: { value: 'manual_override' },
    });
    fireEvent.change(screen.getByLabelText(/status da concessao/i), {
      target: { value: 'expired' },
    });
    fireEvent.change(screen.getByLabelText(/concessoes por pagina/i), {
      target: { value: '25' },
    });

    await waitFor(() => {
      expect(listGrantsMock).toHaveBeenLastCalledWith(10, expect.objectContaining({
        source_type: 'manual_override',
        status: 'expired',
        per_page: 25,
        page: 1,
      }));
    });
  });

  it('creates a partner from the admin form when the user has manage access', async () => {
    useAuthMock.mockReturnValue({
      can: (permission: string) => ['partners.view.any', 'partners.manage.any'].includes(permission),
    });

    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /novo parceiro/i }));
    fireEvent.change(screen.getByLabelText(/nome comercial/i), {
      target: { value: 'Cerimonial Novo' },
    });
    fireEvent.change(screen.getByLabelText(/e-mail principal/i), {
      target: { value: 'novo@partner.test' },
    });
    fireEvent.change(screen.getByPlaceholderText(/nome do responsavel principal/i), {
      target: { value: 'Owner Novo' },
    });
    fireEvent.change(screen.getByPlaceholderText(/responsavel@parceiro.com/i), {
      target: { value: 'owner@partner.test' },
    });

    fireEvent.click(screen.getByRole('button', { name: /cadastrar parceiro/i }));

    await waitFor(() => {
      expect(createPartnerMock).toHaveBeenCalledWith(expect.objectContaining({
        name: 'Cerimonial Novo',
        email: 'novo@partner.test',
        owner: expect.objectContaining({
          name: 'Owner Novo',
          email: 'owner@partner.test',
        }),
      }));
    });
  });

  it('updates and suspends a partner from row actions when the user has manage access', async () => {
    useAuthMock.mockReturnValue({
      can: (permission: string) => ['partners.view.any', 'partners.manage.any'].includes(permission),
    });

    renderPage();

    await screen.findByText('Cerimonial API');

    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));
    fireEvent.click(await screen.findByRole('button', { name: /^editar$/i }));
    fireEvent.change(screen.getByLabelText(/nome comercial/i), {
      target: { value: 'Cerimonial Editado' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar alteracoes/i }));

    await waitFor(() => {
      expect(updatePartnerMock).toHaveBeenCalledWith(10, expect.objectContaining({
        name: 'Cerimonial Editado',
      }));
    });

    fireEvent.click(await screen.findByRole('button', { name: /^suspender$/i }));
    fireEvent.change(screen.getByLabelText(/motivo/i), {
      target: { value: 'Revisao administrativa' },
    });
    fireEvent.click(screen.getByRole('button', { name: /suspender parceiro/i }));

    await waitFor(() => {
      expect(suspendPartnerMock).toHaveBeenCalledWith(10, expect.objectContaining({
        reason: 'Revisao administrativa',
      }));
    });
  });

  it('adds staff from the detailed admin sheet when the user has manage access', async () => {
    useAuthMock.mockReturnValue({
      can: (permission: string) => ['partners.view.any', 'partners.manage.any'].includes(permission),
    });

    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));

    const addStaffAction = await screen.findByRole('button', { name: /adicionar membro/i });
    fireEvent.click(addStaffAction);
    fireEvent.change(screen.getByPlaceholderText(/nome do membro/i), {
      target: { value: 'Staff Novo' },
    });
    fireEvent.change(screen.getByPlaceholderText(/staff@parceiro.com/i), {
      target: { value: 'staff@partner.test' },
    });
    const addStaffButtons = screen.getAllByRole('button', { name: /adicionar membro/i });
    fireEvent.click(addStaffButtons[addStaffButtons.length - 1]);

    await waitFor(() => {
      expect(inviteStaffMock).toHaveBeenCalledWith(10, expect.objectContaining({
        user: expect.objectContaining({
          name: 'Staff Novo',
          email: 'staff@partner.test',
        }),
        role_key: 'partner-manager',
      }));
    });
  });

  it('creates a grant from the detailed admin sheet when the user has manage access', async () => {
    useAuthMock.mockReturnValue({
      can: (permission: string) => ['partners.view.any', 'partners.manage.any'].includes(permission),
    });
    listEventsMock.mockResolvedValue(makeEventsResponse());

    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));

    fireEvent.click(await screen.findByRole('button', { name: /criar concessao/i }));
    fireEvent.keyDown(screen.getAllByRole('combobox')[0], { key: 'ArrowDown' });
    fireEvent.click(await screen.findByRole('option', { name: /casamento teste/i }));
    fireEvent.change(screen.getByLabelText(/motivo/i), {
      target: { value: 'Campanha comercial' },
    });
    const createGrantButtons = screen.getAllByRole('button', { name: /criar concessao/i });
    fireEvent.click(createGrantButtons[createGrantButtons.length - 1]);

    await waitFor(() => {
      expect(createGrantMock).toHaveBeenCalledWith(10, expect.objectContaining({
        event_id: 88,
        source_type: 'bonus',
        reason: 'Campanha comercial',
      }));
    });
  });

  it('confirms removing an empty partner from the detailed admin sheet when the user has manage access', async () => {
    useAuthMock.mockReturnValue({
      can: (permission: string) => ['partners.view.any', 'partners.manage.any'].includes(permission),
    });

    renderPage();

    await screen.findByText('Cerimonial API');
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }));

    fireEvent.click(await screen.findByRole('button', { name: /remover vazio/i }));
    const removeButtons = await screen.findAllByRole('button', { name: /remover vazio/i });
    fireEvent.click(removeButtons[removeButtons.length - 1]);

    await waitFor(() => {
      expect(removePartnerMock).toHaveBeenCalledWith(10);
    });
  });

  it('does not request partners when the session has no global partners permission', () => {
    useAuthMock.mockReturnValue({
      can: () => false,
    });

    renderPage();

    expect(screen.getByText(/acesso indisponivel/i)).toBeInTheDocument();
    expect(listPartnersMock).not.toHaveBeenCalled();
  });
});
