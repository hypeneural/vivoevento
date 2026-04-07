import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import PartnersPage from './PartnersPage';
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

  it('forwards the search filter to the partners API query', async () => {
    renderPage();

    await screen.findByText('Cerimonial API');

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
    fireEvent.change(screen.getByPlaceholderText(/nome do owner/i), {
      target: { value: 'Owner Novo' },
    });
    fireEvent.change(screen.getByPlaceholderText(/owner@parceiro.com/i), {
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

    const addStaffAction = await screen.findByRole('button', { name: /adicionar staff/i });
    fireEvent.click(addStaffAction);
    fireEvent.change(screen.getByPlaceholderText(/nome do membro/i), {
      target: { value: 'Staff Novo' },
    });
    fireEvent.change(screen.getByPlaceholderText(/staff@parceiro.com/i), {
      target: { value: 'staff@partner.test' },
    });
    const addStaffButtons = screen.getAllByRole('button', { name: /adicionar staff/i });
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

  it('does not request partners when the session has no global partners permission', () => {
    useAuthMock.mockReturnValue({
      can: () => false,
    });

    renderPage();

    expect(screen.getByText(/acesso indisponivel/i)).toBeInTheDocument();
    expect(listPartnersMock).not.toHaveBeenCalled();
  });
});
