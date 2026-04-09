import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { TooltipProvider } from '@/components/ui/tooltip';

import MediaAutomaticRepliesPage from './MediaAutomaticRepliesPage';

const useAuthMock = vi.fn();
const getConfigurationMock = vi.fn();
const updateConfigurationMock = vi.fn();
const getSafetyConfigurationMock = vi.fn();
const updateSafetyConfigurationMock = vi.fn();
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
    getSafetyConfiguration: (...args: unknown[]) => getSafetyConfigurationMock(...args),
    updateSafetyConfiguration: (...args: unknown[]) => updateSafetyConfigurationMock(...args),
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
        <TooltipProvider delayDuration={0}>
          <MediaAutomaticRepliesPage />
        </TooltipProvider>
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
      enabled: true,
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      mode: 'gate',
      prompt_version: 'contextual-v2',
      response_schema_version: 'contextual-v2',
      timeout_ms: 12000,
      fallback_mode: 'review',
      context_scope: 'image_only',
      reply_scope: 'image_and_text_context',
      normalized_text_context_mode: 'caption_only',
      require_json_output: true,
      contextual_policy_preset_key: 'corporativo_restrito',
      policy_version: 'contextual-policy-v1',
      allow_alcohol: false,
      allow_tobacco: false,
      required_people_context: 'required',
      blocked_terms: ['copos', 'charutos'],
      allowed_exceptions: ['palestrante no palco'],
      freeform_instruction: 'Prefira review quando a imagem mostrar somente objetos.',
      reply_text_prompt: 'Use {nome_do_evento} de forma natural quando combinar com a imagem.',
      reply_text_fixed_templates: ['Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸'],
      reply_prompt_preset_id: 10,
      reply_ai_rate_limit_enabled: true,
      reply_ai_rate_limit_max_messages: 10,
      reply_ai_rate_limit_window_minutes: 15,
      created_at: null,
      updated_at: null,
    });

    getSafetyConfigurationMock.mockResolvedValue({
      id: 1,
      enabled: true,
      provider_key: 'openai',
      mode: 'observe_only',
      threshold_version: 'global-wedding-v1',
      hard_block_thresholds: {
        nudity: 0.97,
        violence: 0.95,
        self_harm: 0.94,
      },
      review_thresholds: {
        nudity: 0.62,
        violence: 0.63,
        self_harm: 0.64,
      },
      fallback_mode: 'review',
      analysis_scope: 'image_only',
      objective_safety_scope: 'image_only',
      normalized_text_context_mode: 'caption_only',
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
        category_entry: null,
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
        category: 'festas',
        category_entry: null,
        description: 'Tom profissional.',
        prompt_template: 'Use um tom profissional e acolhedor.',
        sort_order: 20,
        is_active: true,
        created_by: 1,
        created_at: null,
        updated_at: null,
      },
    ]);

    updateConfigurationMock.mockResolvedValue({ success: true, data: {} });
    updateSafetyConfigurationMock.mockResolvedValue({ success: true, data: {} });
    createCategoryMock.mockResolvedValue({ id: 12, slug: '15anos', name: '15 anos', sort_order: 40, is_active: true, created_at: null, updated_at: null });
    updateCategoryMock.mockResolvedValue(undefined);
    deleteCategoryMock.mockResolvedValue(undefined);
    createPresetMock.mockResolvedValue({
      id: 12,
      slug: 'infantil',
      name: 'Infantil',
      category: 'festas',
      category_entry: null,
      description: 'Tom leve.',
      prompt_template: 'Use um tom alegre.',
      sort_order: 30,
      is_active: true,
      created_by: 1,
      created_at: null,
      updated_at: null,
    });
    updatePresetMock.mockResolvedValue(undefined);
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
      images: [{ index: 0, original_name: 'foto-1.jpg', mime_type: 'image/jpeg', size_bytes: 123, sha256: 'abc' }],
      safety_results: [{
        image_index: 0,
        original_name: 'foto-1.jpg',
        mime_type: 'image/jpeg',
        size_bytes: 123,
        sha256: 'abc',
        decision: 'pass',
        blocked: false,
        review_required: false,
        category_scores: { nudity: 0.01, violence: 0, self_harm: 0 },
        reason_codes: ['safety.pass'],
        error_message: null,
      }],
      contextual_results: [{
        image_index: 0,
        original_name: 'foto-1.jpg',
        mime_type: 'image/jpeg',
        size_bytes: 123,
        sha256: 'abc',
        decision: 'approve',
        review_required: false,
        reason: 'A imagem representa o contexto da formatura.',
        reason_code: 'context.match.event',
        matched_policies: ['preset:formatura'],
        matched_exceptions: [],
        input_scope_used: 'image_and_text_context',
        input_types_considered: ['image', 'text'],
        confidence_band: 'high',
        publish_eligibility: 'auto_publish',
        short_caption: 'Celebracao no palco.',
        reply_text: 'Memorias que fazem o coracao sorrir! Ã°Å¸Å½â€°Ã°Å¸â€œÂ¸',
        tags: ['formatura'],
        response_schema_version: 'contextual-v2',
        mode_applied: 'gate',
        normalized_text_context: null,
        normalized_text_context_mode: 'caption_only',
        error_message: null,
      }],
      final_summary: {
        images_evaluated: 1,
        reply_status: 'success',
        safety_is_blocking: true,
        context_is_blocking: true,
        safety_counts: { pass: 1, review: 0, block: 0, error: 0 },
        context_counts: { approve: 1, review: 0, reject: 0, error: 0 },
        blocking_layers: [],
        reason_codes: ['safety.pass', 'context.match.event'],
        evaluation_errors_count: 0,
        final_publish_eligibility: 'auto_publish',
        final_effective_state: 'approved',
        human_reason: 'A homologacao sugere publicacao automatica com a politica atual.',
      },
      policy_snapshot: {
        safety: { analysis_scope: 'image_only', normalized_text_context_mode: 'caption_only' },
        context: {
          context_scope: 'image_and_text_context',
          reply_scope: 'image_only',
          normalized_text_context_mode: 'caption_only',
        },
      },
      policy_sources: {
        safety: { analysis_scope: 'global_setting' },
        context: { context_scope: 'event_setting', reply_scope: 'event_setting' },
      },
      request_payload: { model: 'openai/gpt-4.1-mini' },
      response_payload: { reply_text: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸' },
      response_text: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
      latency_ms: 812,
      error_message: null,
      created_at: null,
      updated_at: null,
    });

    listPromptTestsMock.mockResolvedValue({
      data: [{
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
        safety_results: [],
        contextual_results: [],
        final_summary: {},
        policy_snapshot: {},
        policy_sources: {},
        request_payload: {},
        response_payload: {},
        response_text: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
        latency_ms: 812,
        error_message: null,
        created_at: null,
        updated_at: null,
      }],
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
      images: [{ index: 0, original_name: 'foto-1.jpg', mime_type: 'image/jpeg', size_bytes: 123, sha256: 'abc' }],
      safety_results: [{
        image_index: 0,
        original_name: 'foto-1.jpg',
        mime_type: 'image/jpeg',
        size_bytes: 123,
        sha256: 'abc',
        decision: 'pass',
        blocked: false,
        review_required: false,
        category_scores: { nudity: 0.01, violence: 0, self_harm: 0 },
        reason_codes: ['safety.pass'],
        error_message: null,
      }],
      contextual_results: [{
        image_index: 0,
        original_name: 'foto-1.jpg',
        mime_type: 'image/jpeg',
        size_bytes: 123,
        sha256: 'abc',
        decision: 'approve',
        review_required: false,
        reason: 'A imagem representa o contexto da formatura.',
        reason_code: 'context.match.event',
        matched_policies: ['preset:formatura'],
        matched_exceptions: [],
        input_scope_used: 'image_and_text_context',
        input_types_considered: ['image', 'text'],
        confidence_band: 'high',
        publish_eligibility: 'auto_publish',
        short_caption: 'Celebracao no palco.',
        reply_text: 'Memorias que fazem o coracao sorrir! Ã°Å¸Å½â€°Ã°Å¸â€œÂ¸',
        tags: ['formatura'],
        response_schema_version: 'contextual-v2',
        mode_applied: 'gate',
        normalized_text_context: null,
        normalized_text_context_mode: 'caption_only',
        error_message: null,
      }],
      final_summary: {
        images_evaluated: 1,
        reply_status: 'success',
        safety_is_blocking: true,
        context_is_blocking: true,
        safety_counts: { pass: 1, review: 0, block: 0, error: 0 },
        context_counts: { approve: 1, review: 0, reject: 0, error: 0 },
        blocking_layers: [],
        reason_codes: ['safety.pass', 'context.match.event'],
        evaluation_errors_count: 0,
        final_publish_eligibility: 'auto_publish',
        final_effective_state: 'approved',
        human_reason: 'A homologacao sugere publicacao automatica com a politica atual.',
      },
      policy_snapshot: {
        safety: { analysis_scope: 'image_only', normalized_text_context_mode: 'caption_only' },
        context: {
          context_scope: 'image_and_text_context',
          reply_scope: 'image_only',
          normalized_text_context_mode: 'caption_only',
        },
      },
      policy_sources: {
        safety: { analysis_scope: 'global_setting' },
        context: { context_scope: 'event_setting', reply_scope: 'event_setting' },
      },
      request_payload: { model: 'openai/gpt-4.1-mini' },
      response_payload: { reply_text: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸' },
      response_text: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
      latency_ms: 812,
      error_message: null,
      created_at: null,
      updated_at: null,
    });

    listEventOptionsMock.mockResolvedValue([
      { id: 42, title: 'Formatura 2026' },
      { id: 77, title: 'Casamento Ana e Pedro' },
    ]);

    const eventHistoryItem = {
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
      preview_url: 'https://cdn.test/event-media/200-preview.jpg',
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      status: 'completed',
      decision: 'approve',
      effective_media_state: 'published',
      safety_decision: 'pending',
      context_decision: 'approved',
      operator_decision: 'none',
      publication_decision: 'published',
      reason: 'A imagem combina com o evento.',
      human_reason: 'A imagem combina com o evento.',
      reason_code: 'context.match.event',
      matched_policies: ['preset:casamento_equilibrado'],
      matched_exceptions: ['brinde com espumante'],
      input_scope_used: 'image_and_text_context',
      input_types_considered: ['image', 'text'],
      confidence_band: 'high',
      publish_eligibility: 'auto_publish',
      policy_label: 'Casamento romantico',
      policy_inheritance_mode: 'preset',
      text_context_summary: 'A decisao usou imagem + texto normalizado.',
      prompt_template: 'Use {nome_do_evento}.',
      prompt_resolved: 'Use Casamento Ana e Pedro.',
      prompt_variables: { nome_do_evento: 'Casamento Ana e Pedro' },
      preset_name: 'Casamento romantico',
      preset_id: 10,
      prompt_instruction_source: 'event',
      prompt_preset_source: 'event',
      normalized_text_context: 'Texto recebido do convidado',
      normalized_text_context_mode: 'body_only',
      context_scope: 'image_and_text_context',
      reply_scope: 'image_only',
      policy_snapshot: { contextual_policy_preset_key: 'casamento_equilibrado' },
      policy_sources: { allow_alcohol: 'preset' },
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
    };

    listEventHistoryMock.mockResolvedValue({
      data: [eventHistoryItem],
      meta: { page: 1, per_page: 15, total: 1, last_page: 1 },
    });
    getEventHistoryItemMock.mockResolvedValue(eventHistoryItem);
  });

  it('renders the media moderation tabs and loads the contextual configuration summary', async () => {
    renderPage();

    expect(screen.getByRole('tab', { name: /^básico$/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /^avançado$/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /^auditoria$/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /vis.*o geral/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /seguran.*objetiva/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /contexto do evento/i })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /laborat.rio/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /cat.logo/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /historico/i })).not.toBeInTheDocument();

    expect(await screen.findByText(/bloqueio contextual agora/i)).toBeInTheDocument();
    expect(screen.getByText(/seguran.*objetiva agora/i)).toBeInTheDocument();
    expect(await screen.findByText(/somente monitorar/i)).toBeInTheDocument();
    expect(await screen.findByText(/bloquear antes de publicar/i)).toBeInTheDocument();
    expect(screen.queryByText(/\bobserve_only\b/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/\benrich_only\b/i)).not.toBeInTheDocument();

    activateTab(/contexto do evento/i);

    expect(await screen.findByLabelText(/instrucao principal da resposta/i)).toHaveValue(
      'Use {nome_do_evento} de forma natural quando combinar com a imagem.',
    );
    expect(screen.getByLabelText(/gate contextual ativo/i)).toBeChecked();
    expect(screen.getByLabelText(/permitir alc.*ol/i)).not.toBeChecked();
    expect(screen.getByLabelText(/permitir cigarro/i)).not.toBeChecked();

    activateTab(/^auditoria$/i);

    expect(await screen.findByRole('tab', { name: /laborat.rio/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /historico/i })).toBeInTheDocument();
  });

  it('persists the contextual configuration with preset selection', async () => {
    renderPage();

    activateTab(/contexto do evento/i);

    fireEvent.change(await screen.findByLabelText(/instrucao principal da resposta/i), {
      target: { value: 'Use {nome_do_evento} apenas quando soar natural e mantenha a resposta curta.' },
    });
    fireEvent.change(screen.getByLabelText(/respostas prontas de apoio/i), {
      target: { value: 'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸\nMomento de risadas e lembrancas! ðŸ“±ðŸŽ‰' },
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
        enabled: true,
        provider_key: 'openrouter',
        model_key: 'openai/gpt-4.1-mini',
        mode: 'gate',
        prompt_version: 'contextual-v2',
        response_schema_version: 'contextual-v2',
        timeout_ms: 12000,
        fallback_mode: 'review',
        context_scope: 'image_only',
        reply_scope: 'image_and_text_context',
        normalized_text_context_mode: 'caption_only',
        require_json_output: true,
        contextual_policy_preset_key: 'corporativo_restrito',
        policy_version: 'contextual-policy-v1',
        allow_alcohol: false,
        allow_tobacco: false,
        required_people_context: 'required',
        blocked_terms: ['copos', 'charutos'],
        allowed_exceptions: ['palestrante no palco'],
        freeform_instruction: 'Prefira review quando a imagem mostrar somente objetos.',
        reply_text_prompt: 'Use {nome_do_evento} apenas quando soar natural e mantenha a resposta curta.',
        reply_text_fixed_templates: [
          'Memorias que fazem o coracao sorrir! ðŸŽ‰ðŸ“¸',
          'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
        ],
        reply_prompt_preset_id: 10,
        reply_ai_rate_limit_enabled: true,
        reply_ai_rate_limit_max_messages: 8,
        reply_ai_rate_limit_window_minutes: 12,
      });
    });
  });

  it('persists the objective safety configuration', async () => {
    renderPage();

    activateTab(/^avançado$/i);
    activateTab(/seguran.*objetiva/i);

    fireEvent.change(await screen.findByLabelText(/perfil de sensibilidade/i, { selector: 'input' }), {
      target: { value: 'global-safety-v2' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar seguranca objetiva/i }));

    await waitFor(() => {
      expect(updateSafetyConfigurationMock).toHaveBeenCalledWith({
        enabled: true,
        provider_key: 'openai',
        mode: 'observe_only',
        threshold_version: 'global-safety-v2',
        fallback_mode: 'review',
        analysis_scope: 'image_only',
        normalized_text_context_mode: 'caption_only',
        hard_block_thresholds: {
          nudity: 0.97,
          violence: 0.95,
          self_harm: 0.94,
        },
        review_thresholds: {
          nudity: 0.62,
          violence: 0.63,
          self_harm: 0.64,
        },
      });
    });
  });

  it('shows an explanatory tooltip in the objective safety tab', async () => {
    renderPage();

    activateTab(/seguran.*objetiva/i);

    const helpButton = await screen.findByRole('button', { name: /como a seguran.* decide/i });

    fireEvent.mouseEnter(helpButton);
    fireEvent.focus(helpButton);

    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent(/bloqueia automaticamente ou apenas sinaliza para acompanhamento/i);
    });
  });

  it('creates a new preset from the catalog tab', async () => {
    renderPage();

    activateTab(/^avançado$/i);
    activateTab(/cat.logo/i);

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

    activateTab(/^auditoria$/i);
    activateTab(/laborat.rio/i);

    fireEvent.change(await screen.findByLabelText(/id do evento/i), {
      target: { value: '42' },
    });
    fireEvent.click(screen.getByLabelText(/preset do teste/i));
    fireEvent.click(await screen.findByText(/^Casamentos$/i));
    fireEvent.click(screen.getByLabelText(/o que entra na seguranca objetiva do laboratorio/i));
    fireEvent.click(await screen.findByRole('option', { name: /somente imagem/i }));
    fireEvent.click(screen.getByLabelText(/o que entra no bloqueio contextual do laboratorio/i));
    fireEvent.click(await screen.findByRole('option', { name: /imagem \+ texto/i }));
    fireEvent.click(screen.getByLabelText(/o que entra na resposta automatica do laboratorio/i));
    fireEvent.click(await screen.findByRole('option', { name: /somente imagem/i }));
    fireEvent.click(screen.getByLabelText(/contexto textual normalizado do laboratorio/i));
    fireEvent.click(await screen.findByRole('option', { name: /somente caption/i }));

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
        objective_safety_scope_override: 'image_only',
        context_scope_override: 'image_and_text_context',
        reply_scope_override: 'image_only',
        normalized_text_context_mode_override: 'caption_only',
        images: [file],
      });
    });

    expect(await screen.findByText(/politica efetiva agora/i)).toBeInTheDocument();
    expect(screen.getByText(/publicar automaticamente/i)).toBeInTheDocument();
    expect(screen.getAllByText(/seguran.*objetiva/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/bloqueio contextual/i).length).toBeGreaterThan(0);
  }, 10000);

  it('shows the empty laboratory state before running and keeps quick replay disabled', async () => {
    renderPage();

    activateTab(/^auditoria$/i);
    activateTab(/laborat.rio/i);

    expect(await screen.findByText(/resultado do laboratorio/i)).toBeInTheDocument();
    expect(screen.getByText(/execute um teste para visualizar a politica efetiva/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /repetir ultimo teste/i })).toBeDisabled();
  });

  it('replays the last successful test even when the current file selection is empty', async () => {
    renderPage();

    activateTab(/^auditoria$/i);
    activateTab(/laborat.rio/i);

    fireEvent.change(await screen.findByLabelText(/id do evento/i), {
      target: { value: '42' },
    });

    const fileInput = screen.getByLabelText(/imagens do teste/i);
    const file = new File(['abc'], 'foto-1.jpg', { type: 'image/jpeg' });

    fireEvent.change(fileInput, {
      target: {
        files: [file],
      },
    });

    fireEvent.click(screen.getByRole('button', { name: /executar teste/i }));

    await waitFor(() => {
      expect(runPromptTestMock).toHaveBeenCalledTimes(1);
    });

    fireEvent.change(fileInput, {
      target: {
        files: [],
      },
    });

    expect(screen.getByRole('button', { name: /repetir ultimo teste/i })).not.toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: /repetir ultimo teste/i }));

    await waitFor(() => {
      expect(runPromptTestMock).toHaveBeenCalledTimes(2);
    });

    expect(runPromptTestMock).toHaveBeenNthCalledWith(2, {
      event_id: 42,
      provider_key: 'openrouter',
      model_key: 'openai/gpt-4.1-mini',
      prompt_template: null,
      preset_id: null,
      objective_safety_scope_override: null,
      context_scope_override: null,
      reply_scope_override: null,
      normalized_text_context_mode_override: null,
      images: [file],
    });
  });

  it('renders an inline laboratory error state when the execution request fails', async () => {
    runPromptTestMock.mockReset();
    runPromptTestMock.mockRejectedValueOnce(new Error('Provider indisponivel para homologacao.'));

    renderPage();

    activateTab(/^auditoria$/i);
    activateTab(/laborat.rio/i);

    const fileInput = await screen.findByLabelText(/imagens do teste/i);
    const file = new File(['abc'], 'foto-1.jpg', { type: 'image/jpeg' });

    fireEvent.change(fileInput, {
      target: {
        files: [file],
      },
    });

    fireEvent.click(screen.getByRole('button', { name: /executar teste/i }));

    expect(await screen.findByText(/falha ao executar o laboratorio/i)).toBeInTheDocument();
    expect(screen.getByText(/provider indisponivel para homologacao\./i)).toBeInTheDocument();
  });

  it('lists real event history and loads the selected production detail', async () => {
    renderPage();

    activateTab(/^auditoria$/i);
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
    expect(screen.getByLabelText(/filtro de elegibilidade do historico real/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/filtro de estado efetivo do historico real/i)).toBeInTheDocument();
    expect(screen.getByText(/context.match.event/i)).toBeInTheDocument();
    expect(screen.getByText(/publicar automaticamente/i)).toBeInTheDocument();
    expect(screen.getAllByText(/anderson marques/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/casamento romantico/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/a imagem combina com o evento\./i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/a decisao usou imagem \+ texto normalizado\./i).length).toBeGreaterThan(0);
    expect(screen.getByText(/snapshot da politica efetiva/i)).toBeInTheDocument();
    expect(screen.getByText(/origem de cada campo da politica/i)).toBeInTheDocument();
    expect(screen.getAllByAltText(/preview da midia 200/i).length).toBeGreaterThan(0);
  });
});
