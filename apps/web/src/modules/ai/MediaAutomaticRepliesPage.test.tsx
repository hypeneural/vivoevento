import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import MediaAutomaticRepliesPage from './MediaAutomaticRepliesPage';

const useAuthMock = vi.fn();
const getConfigurationMock = vi.fn();
const updateConfigurationMock = vi.fn();
const listCategoriesMock = vi.fn();
const createCategoryMock = vi.fn();
const updateCategoryMock = vi.fn();
const deleteCategoryMock = vi.fn();
const listPresetsMock = vi.fn();
const createPresetMock = vi.fn();
const updatePresetMock = vi.fn();
const deletePresetMock = vi.fn();
const runPromptTestMock = vi.fn();
const listPromptTestsMock = vi.fn();
const getPromptTestMock = vi.fn();
const listEventOptionsMock = vi.fn();
const listEventHistoryMock = vi.fn();
const getEventHistoryItemMock = vi.fn();

vi.mock('@/app/providers/AuthProvider', () => ({
  useAuth: () => useAuthMock(),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: vi.fn(),
  }),
}));

vi.mock('./api', () => ({
  aiMediaRepliesService: {
    getConfiguration: (...args: unknown[]) => getConfigurationMock(...args),
    updateConfiguration: (...args: unknown[]) => updateConfigurationMock(...args),
    listCategories: (...args: unknown[]) => listCategoriesMock(...args),
    createCategory: (...args: unknown[]) => createCategoryMock(...args),
    updateCategory: (...args: unknown[]) => updateCategoryMock(...args),
    deleteCategory: (...args: unknown[]) => deleteCategoryMock(...args),
    listPresets: (...args: unknown[]) => listPresetsMock(...args),
    createPreset: (...args: unknown[]) => createPresetMock(...args),
    updatePreset: (...args: unknown[]) => updatePresetMock(...args),
    deletePreset: (...args: unknown[]) => deletePresetMock(...args),
    runPromptTest: (...args: unknown[]) => runPromptTestMock(...args),
    listPromptTests: (...args: unknown[]) => listPromptTestsMock(...args),
    getPromptTest: (...args: unknown[]) => getPromptTestMock(...args),
    listEventOptions: (...args: unknown[]) => listEventOptionsMock(...args),
    listEventHistory: (...args: unknown[]) => listEventHistoryMock(...args),
    getEventHistoryItem: (...args: unknown[]) => getEventHistoryItemMock(...args),
  },
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return render(
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <MediaAutomaticRepliesPage />
      </QueryClientProvider>
    </MemoryRouter>,
  );
}

function activateTab(name: RegExp) {
  const tab = screen.getByRole('tab', { name });
  fireEvent.mouseDown(tab);
  fireEvent.click(tab);
}

describe('MediaAutomaticRepliesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    HTMLElement.prototype.scrollIntoView = vi.fn();

    useAuthMock.mockReturnValue({
      meUser: {
        id: 1,
        role: {
          key: 'platform-admin',
          name: 'platform-admin',
        },
      },
    });

    getConfigurationMock.mockResolvedValue({
      id: 1,
      reply_text_prompt: 'Use {nome_do_evento} de forma natural quando combinar com a imagem.',
      reply_text_fixed_templates: [
        'Memorias que fazem o coracao sorrir! 🎉📸',
      ],
      reply_prompt_preset_id: 10,
      reply_ai_rate_limit_enabled: true,
      reply_ai_rate_limit_max_messages: 10,
      reply_ai_rate_limit_window_minutes: 15,
      created_at: null,
      updated_at: null,
    });
    listCategoriesMock.mockResolvedValue([
      {
        id: 1,
        slug: 'casamento',
        name: 'Casamento',
        sort_order: 10,
        is_active: true,
        created_at: null,
        updated_at: null,
      },
      {
        id: 2,
        slug: 'corporativo',
        name: 'Corporativo',
        sort_order: 20,
        is_active: true,
        created_at: null,
        updated_at: null,
      },
      {
        id: 3,
        slug: 'festas',
        name: 'Festas',
        sort_order: 30,
        is_active: true,
        created_at: null,
        updated_at: null,
      },
    ]);
    listPresetsMock.mockResolvedValue([
      {
        id: 10,
        slug: 'casamentos',
        name: 'Casamentos',
        category: 'casamento',
        category_entry: {
          id: 1,
          slug: 'casamento',
          name: 'Casamento',
          sort_order: 10,
          is_active: true,
          created_at: null,
          updated_at: null,
        },
        description: 'Tom romantico.',
        prompt_template: 'Use um tom romantico e delicado.',
        sort_order: 10,
        is_active: true,
        created_by: 1,
        created_at: null,
        updated_at: null,
      },
      {
        id: 11,
        slug: 'corporativos',
        name: 'Corporativos',
        category: 'corporativo',
        category_entry: {
          id: 2,
          slug: 'corporativo',
          name: 'Corporativo',
          sort_order: 20,
          is_active: true,
          created_at: null,
          updated_at: null,
        },
        description: 'Tom leve e institucional.',
        prompt_template: 'Use um tom profissional e acolhedor.',
        sort_order: 20,
        is_active: true,
        created_by: 1,
        created_at: null,
        updated_at: null,
      },
    ]);
    updateConfigurationMock.mockResolvedValue({ success: true, data: {} });
    createCategoryMock.mockResolvedValue({
      id: 12,
      slug: '15anos',
      name: '15 anos',
      sort_order: 40,
      is_active: true,
      created_at: null,
      updated_at: null,
    });
    updateCategoryMock.mockResolvedValue({
      id: 1,
      slug: 'casamento',
      name: 'Casamento',
      sort_order: 10,
      is_active: true,
      created_at: null,
      updated_at: null,
    });
    deleteCategoryMock.mockResolvedValue(undefined);
    createPresetMock.mockResolvedValue({
      id: 12,
      slug: 'infantil',
      name: 'Infantil',
      category: 'festas',
      category_entry: {
        id: 3,
        slug: 'festas',
        name: 'Festas',
        sort_order: 30,
        is_active: true,
        created_at: null,
        updated_at: null,
      },
      description: 'Tom leve.',
      prompt_template: 'Use um tom alegre.',
      sort_order: 30,
      is_active: true,
      created_by: 1,
      created_at: null,
      updated_at: null,
    });
    updatePresetMock.mockResolvedValue({
      id: 10,
      slug: 'casamentos',
      name: 'Casamentos',
      category: 'casamento',
      category_entry: {
        id: 1,
        slug: 'casamento',
        name: 'Casamento',
        sort_order: 10,
        is_active: true,
        created_at: null,
        updated_at: null,
      },
      description: 'Tom romantico.',
      prompt_template: 'Use um tom romantico e delicado.',
      sort_order: 10,
      is_active: true,
      created_by: 1,
      created_at: null,
      updated_at: null,
    });
    deletePresetMock.mockResolvedValue(undefined);
    runPromptTestMock.mockResolvedValue({
      id: 100,
      trace_id: 'trace-100',
      user_id: 1,
      event_id: 42,
      preset_id: 10,
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      status: 'success',
      prompt_template: 'Use {nome_do_evento} de forma natural quando combinar com a imagem.',
      prompt_resolved: 'Use Formatura 2026 de forma natural quando combinar com a imagem.',
      prompt_variables: { nome_do_evento: 'Formatura 2026' },
      images: [
        { index: 0, original_name: 'foto-1.jpg', mime_type: 'image/jpeg', size_bytes: 123, sha256: 'abc' },
      ],
      request_payload: { model: 'openai/gpt-4.1-mini' },
      response_payload: { reply_text: 'Memorias que fazem o coracao sorrir! 🎉📸' },
      response_text: 'Memorias que fazem o coracao sorrir! 🎉📸',
      latency_ms: 812,
      error_message: null,
      created_at: null,
      updated_at: null,
    });
    listPromptTestsMock.mockResolvedValue({
      data: [
        {
          id: 100,
          trace_id: 'trace-100',
          user_id: 1,
          event_id: 42,
          preset_id: 10,
          provider_key: 'openrouter',
          model_key: 'openai/gpt-4.1-mini',
          status: 'success',
          prompt_template: 'Use {nome_do_evento}',
          prompt_resolved: 'Use Formatura 2026',
          prompt_variables: { nome_do_evento: 'Formatura 2026' },
          images: [],
          request_payload: {},
          response_payload: {},
          response_text: 'Memorias que fazem o coracao sorrir! 🎉📸',
          latency_ms: 812,
          error_message: null,
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { page: 1, per_page: 15, total: 1, last_page: 1 },
    });
    getPromptTestMock.mockResolvedValue({
      id: 100,
      trace_id: 'trace-100',
      user_id: 1,
      event_id: 42,
      preset_id: 10,
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      status: 'success',
      prompt_template: 'Use {nome_do_evento}',
      prompt_resolved: 'Use Formatura 2026',
      prompt_variables: { nome_do_evento: 'Formatura 2026' },
      images: [
        { index: 0, original_name: 'foto-1.jpg', mime_type: 'image/jpeg', size_bytes: 123, sha256: 'abc' },
      ],
      request_payload: { model: 'openai/gpt-4.1-mini' },
      response_payload: { reply_text: 'Memorias que fazem o coracao sorrir! 🎉📸' },
      response_text: 'Memorias que fazem o coracao sorrir! 🎉📸',
      latency_ms: 812,
      error_message: null,
      created_at: null,
      updated_at: null,
    });
    listEventOptionsMock.mockResolvedValue([
      {
        id: 42,
        title: 'Formatura 2026',
      },
      {
        id: 77,
        title: 'Casamento Ana e Pedro',
      },
    ]);
    listEventHistoryMock.mockResolvedValue({
      data: [
        {
          id: 200,
          event_id: 77,
          event_title: 'Casamento Ana e Pedro',
          event_media_id: 200,
          inbound_message_id: 88,
          provider_message_id: 'wamid.200',
          trace_id: 'trace-event-200',
          source_type: 'whatsapp',
          source_label: 'WhatsApp',
          sender_name: 'Anderson Marques',
          sender_phone: '5548998483594',
          sender_external_id: '5548998483594',
          message_type: 'image',
          media_type: 'image',
          mime_type: 'image/jpeg',
          preview_url: null,
          provider_key: 'openrouter',
          model_key: 'openai/gpt-4.1-mini',
          status: 'completed',
          decision: 'approve',
          prompt_template: 'Use {nome_do_evento}.',
          prompt_resolved: 'Use Casamento Ana e Pedro.',
          prompt_variables: { nome_do_evento: 'Casamento Ana e Pedro' },
          preset_name: 'Casamento romantico',
          preset_id: 10,
          prompt_instruction_source: 'event',
          prompt_preset_source: 'event',
          reply_text: 'Memorias que fazem o coracao sorrir!',
          short_caption: 'Noivos em destaque.',
          tags: ['casamento'],
          request_payload: { model: 'openai/gpt-4.1-mini' },
          response_payload: { reply_text: 'Memorias que fazem o coracao sorrir!' },
          error_message: null,
          run_status: 'completed',
          run_started_at: null,
          run_finished_at: null,
          completed_at: null,
          published_at: null,
          created_at: null,
          updated_at: null,
        },
      ],
      meta: { page: 1, per_page: 15, total: 1, last_page: 1 },
    });
    getEventHistoryItemMock.mockResolvedValue({
      id: 200,
      event_id: 77,
      event_title: 'Casamento Ana e Pedro',
      event_media_id: 200,
      inbound_message_id: 88,
      provider_message_id: 'wamid.200',
      trace_id: 'trace-event-200',
      source_type: 'whatsapp',
      source_label: 'WhatsApp',
      sender_name: 'Anderson Marques',
      sender_phone: '5548998483594',
      sender_external_id: '5548998483594',
      message_type: 'image',
      media_type: 'image',
      mime_type: 'image/jpeg',
      preview_url: null,
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      status: 'completed',
      decision: 'approve',
      prompt_template: 'Use {nome_do_evento}.',
      prompt_resolved: 'Use Casamento Ana e Pedro.',
      prompt_variables: { nome_do_evento: 'Casamento Ana e Pedro' },
      preset_name: 'Casamento romantico',
      preset_id: 10,
      prompt_instruction_source: 'event',
      prompt_preset_source: 'event',
      reply_text: 'Memorias que fazem o coracao sorrir!',
      short_caption: 'Noivos em destaque.',
      tags: ['casamento'],
      request_payload: { model: 'openai/gpt-4.1-mini' },
      response_payload: { reply_text: 'Memorias que fazem o coracao sorrir!' },
      error_message: null,
      run_status: 'completed',
      run_started_at: null,
      run_finished_at: null,
      completed_at: null,
      published_at: null,
      created_at: null,
      updated_at: null,
    });
  });

  it('renders the dedicated IA tabs and loads the standard configuration', async () => {
    renderPage();

    expect(screen.getByRole('tab', { name: /configuracao/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /teste do prompt/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /catalogo/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /historico/i })).toBeInTheDocument();

    expect(await screen.findByLabelText(/instrucao padrao/i)).toHaveValue(
      'Use {nome_do_evento} de forma natural quando combinar com a imagem.',
    );
    expect(screen.getByLabelText(/textos fixos padrao/i)).toHaveValue('Memorias que fazem o coracao sorrir! 🎉📸');
    expect(screen.getByLabelText(/limite de respostas por ia por participante/i)).toBeChecked();
    expect(screen.getByLabelText(/quantidade maxima/i)).toHaveValue(10);
    expect(screen.getByLabelText(/janela em minutos/i)).toHaveValue(15);
    expect(screen.getAllByText(/\{nome_do_evento\}/i).length).toBeGreaterThan(0);
  });

  it('persists the standard configuration with preset selection', async () => {
    renderPage();

    fireEvent.change(await screen.findByLabelText(/instrucao padrao/i), {
      target: { value: 'Use {nome_do_evento} apenas quando soar natural e mantenha a resposta curta.' },
    });
    fireEvent.change(screen.getByLabelText(/textos fixos padrao/i), {
      target: { value: 'Memorias que fazem o coracao sorrir! 🎉📸\nMomento de risadas e lembrancas! 📱🎉' },
    });
    fireEvent.change(screen.getByLabelText(/quantidade maxima/i), {
      target: { value: '8' },
    });
    fireEvent.change(screen.getByLabelText(/janela em minutos/i), {
      target: { value: '12' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar configuracao/i }));

    await waitFor(() => {
      expect(updateConfigurationMock).toHaveBeenCalledWith({
        reply_text_prompt: 'Use {nome_do_evento} apenas quando soar natural e mantenha a resposta curta.',
        reply_text_fixed_templates: [
          'Memorias que fazem o coracao sorrir! 🎉📸',
          'Momento de risadas e lembrancas! 📱🎉',
        ],
        reply_prompt_preset_id: 10,
        reply_ai_rate_limit_enabled: true,
        reply_ai_rate_limit_max_messages: 8,
        reply_ai_rate_limit_window_minutes: 12,
      });
    });
  });

  it('creates a new preset from the catalog tab', async () => {
    renderPage();

    activateTab(/catalogo/i);

    fireEvent.click(await screen.findByRole('button', { name: /novo preset/i }));
    fireEvent.change(screen.getByLabelText(/^nome$/i), {
      target: { value: 'Infantil' },
    });
    fireEvent.click(screen.getByLabelText(/categoria do preset/i));
    fireEvent.click((await screen.findAllByRole('option', { name: /^festas$/i }))[0]);
    fireEvent.change(screen.getByLabelText(/instrucao-base do preset/i), {
      target: { value: 'Use um tom alegre e delicado, coerente com a cena.' },
    });
    fireEvent.click(screen.getByRole('button', { name: /criar preset/i }));

    await waitFor(() => {
      expect(createPresetMock).toHaveBeenCalledWith(expect.objectContaining({
        name: 'Infantil',
        category: 'festas',
        prompt_template: 'Use um tom alegre e delicado, coerente com a cena.',
      }));
    });
  });

  it('fills the instruction text when a preset is selected in the prompt test', async () => {
    renderPage();

    activateTab(/teste do prompt/i);

    fireEvent.change(await screen.findByLabelText(/id do evento/i), {
      target: { value: '42' },
    });
    fireEvent.click(screen.getByLabelText(/preset do teste/i));
    fireEvent.click(await screen.findByText(/^Casamentos$/i));

    expect(await screen.findByLabelText(/texto de instrucao/i)).toHaveValue('Use um tom romantico e delicado.');

    const fileInput = screen.getByLabelText(/imagens do teste/i);
    const file = new File(['abc'], 'foto-1.jpg', { type: 'image/jpeg' });

    fireEvent.change(fileInput, {
      target: {
        files: [file],
      },
    });

    fireEvent.click(screen.getByRole('button', { name: /executar teste/i }));

    await waitFor(() => {
      expect(runPromptTestMock).toHaveBeenCalledWith({
        event_id: 42,
        provider_key: 'openrouter',
        model_key: 'openai/gpt-4.1-mini',
        prompt_template: 'Use um tom romantico e delicado.',
        preset_id: 10,
        images: [file],
      });
    });

    expect((await screen.findAllByText(/memorias que fazem o coracao sorrir/i)).length).toBeGreaterThan(0);
    expect(screen.getByText(/trace-100/i)).toBeInTheDocument();
  });

  it('loads the history tab and shows the selected test detail', async () => {
    renderPage();

    activateTab(/historico/i);

    expect((await screen.findAllByText(/memorias que fazem o coracao sorrir/i)).length).toBeGreaterThan(0);
    expect(await screen.findByText(/detalhe do teste/i)).toBeInTheDocument();
    expect(await screen.findByText(/trace-100/i)).toBeInTheDocument();
  });

  it('filters the preset catalog by category and instruction text', async () => {
    renderPage();

    activateTab(/catalogo/i);

    fireEvent.click(await screen.findByLabelText(/filtro de categoria do catalogo/i));
    fireEvent.click(await screen.findByRole('option', { name: /^casamento$/i }));

    expect(await screen.findByRole('button', { name: /casamentos/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /corporativos/i })).not.toBeInTheDocument();

    fireEvent.change(screen.getByLabelText(/buscar por nome ou texto da instrucao/i), {
      target: { value: 'profissional' },
    });

    expect(screen.queryByRole('button', { name: /casamentos/i })).not.toBeInTheDocument();
    expect(await screen.findByText(/nenhum preset encontrado com os filtros atuais/i)).toBeInTheDocument();
  });

  it('lists real event history and loads the selected production detail', async () => {
    renderPage();

    activateTab(/historico/i);

    expect(await screen.findByText(/historico de eventos reais/i)).toBeInTheDocument();

    fireEvent.click(screen.getByLabelText(/filtro de evento do historico real/i));
    fireEvent.click(await screen.findByRole('option', { name: /casamento ana e pedro/i }));

    await waitFor(() => {
      expect(listEventHistoryMock).toHaveBeenCalledWith(expect.objectContaining({
        event_id: 77,
      }));
    });

    fireEvent.click(await screen.findByRole('button', { name: /casamento ana e pedro/i }));

    await waitFor(() => {
      expect(getEventHistoryItemMock).toHaveBeenCalledWith(200);
    });

    expect(await screen.findByText(/detalhe do evento real/i)).toBeInTheDocument();
    expect(screen.getAllByText(/anderson marques/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/memorias que fazem o coracao sorrir/i).length).toBeGreaterThan(0);
  });
});
