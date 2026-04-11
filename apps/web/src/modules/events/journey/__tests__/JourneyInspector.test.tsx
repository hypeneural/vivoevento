import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { buildJourneyGraph } from '../buildJourneyGraph';
import { JourneyInspector } from '../JourneyInspector';
import type { EventJourneyBuiltScenario, EventJourneyProjection } from '../types';

const getEventContentModerationSettingsMock = vi.fn();
const getEventMediaIntelligenceSettingsMock = vi.fn();
const updateEventJourneyBuilderMock = vi.fn();
const invalidateEventJourneyBuilderQueriesMock = vi.fn();
const listPresetsMock = vi.fn();
const toastMock = vi.fn();

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

vi.mock('@/modules/events/api', () => ({
  getEventContentModerationSettings: (...args: unknown[]) => getEventContentModerationSettingsMock(...args),
  getEventMediaIntelligenceSettings: (...args: unknown[]) => getEventMediaIntelligenceSettingsMock(...args),
}));

vi.mock('@/modules/ai/api', () => ({
  aiMediaRepliesService: {
    listPresets: (...args: unknown[]) => listPresetsMock(...args),
  },
}));

vi.mock('../api', async () => {
  const actual = await vi.importActual<object>('../api');

  return {
    ...actual,
    updateEventJourneyBuilder: (...args: unknown[]) => updateEventJourneyBuilderMock(...args),
    invalidateEventJourneyBuilderQueries: (...args: unknown[]) => invalidateEventJourneyBuilderQueriesMock(...args),
  };
});

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 42,
      uuid: 'event-42',
      title: 'Casamento Ana e Pedro',
      status: 'active',
      moderation_mode: 'ai',
      modules: {
        live: true,
        wall: true,
        hub: true,
        play: false,
      },
    },
    intake_defaults: {
      whatsapp_instance_id: 7,
      whatsapp_instance_mode: 'shared',
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: true,
        groups: [
          {
            group_external_id: 'grupo-1',
            group_name: 'Familia',
            is_active: true,
            auto_feedback_enabled: true,
          },
        ],
      },
      whatsapp_direct: {
        enabled: true,
        media_inbox_code: 'ANA2026',
        session_ttl_minutes: 180,
      },
      public_upload: {
        enabled: true,
      },
      telegram: {
        enabled: true,
        bot_username: 'EventoVivoBot',
        media_inbox_code: 'BOT2026',
        session_ttl_minutes: 180,
      },
    },
    settings: {
      moderation_mode: 'ai',
      modules: {
        live: true,
        wall: true,
        hub: true,
        play: false,
      },
      content_moderation: {
        enabled: true,
        mode: 'enforced',
        fallback_mode: 'review',
        provider_key: 'openai',
        analysis_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      media_intelligence: {
        enabled: true,
        mode: 'gate',
        fallback_mode: 'review',
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        reply_text_enabled: true,
        reply_text_mode: 'ai',
        context_scope: 'image_and_text_context',
        reply_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      destinations: {
        gallery: true,
        wall: true,
        print: false,
      },
    },
    capabilities: {},
    stages: [
      {
        id: 'entry',
        label: 'Entrada',
        description: 'Entrada',
        position: 0,
        nodes: [
          {
            id: 'entry_whatsapp_direct',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp privado',
            description: 'Privado',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por codigo privado.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'processing',
        label: 'Processamento',
        description: 'Processamento',
        position: 1,
        nodes: [
          {
            id: 'processing_safety_ai',
            stage: 'processing',
            kind: 'process',
            label: 'Safety',
            description: 'Safety',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Analisa risco objetivo.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_media_intelligence',
            stage: 'processing',
            kind: 'process',
            label: 'MediaIntelligence',
            description: 'MediaIntelligence',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Analisa contexto.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Decisao',
        position: 2,
        nodes: [
          {
            id: 'decision_event_moderation_mode',
            stage: 'decision',
            kind: 'decision',
            label: 'Modo de moderacao',
            description: 'Modo de moderacao',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Moderacao por IA ativa.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Saida',
        position: 3,
        nodes: [
          {
            id: 'output_reply_text',
            stage: 'output',
            kind: 'output',
            label: 'Resposta automatica',
            description: 'Resposta automatica',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Resposta por IA ativa.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
    ],
    warnings: [],
    simulation_presets: [],
    summary: {
      human_text: 'Resumo base',
    },
  };
}

function makeContentModerationSettings() {
  return {
    id: 11,
    event_id: 42,
    enabled: true,
    provider_key: 'openai',
    mode: 'enforced',
    threshold_version: 'foundation-v1',
    hard_block_thresholds: {
      nudity: 0.9,
      violence: 0.91,
      self_harm: 0.92,
    },
    review_thresholds: {
      nudity: 0.6,
      violence: 0.61,
      self_harm: 0.62,
    },
    fallback_mode: 'review',
    created_at: null,
    updated_at: null,
  };
}

function makeMediaIntelligenceSettings() {
  return {
    id: 22,
    event_id: 42,
    enabled: true,
    provider_key: 'vllm',
    model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
    mode: 'gate',
    prompt_version: 'foundation-v1',
    approval_prompt: 'Avalie a imagem',
    caption_style_prompt: 'Legenda curta',
    response_schema_version: 'foundation-v1',
    timeout_ms: 12000,
    fallback_mode: 'review',
    require_json_output: true,
    reply_text_mode: 'ai',
    reply_text_enabled: true,
    reply_prompt_override: null,
    reply_fixed_templates: [],
    reply_prompt_preset_id: null,
    reply_prompt_preset: null,
    created_at: null,
    updated_at: null,
  };
}

function makeScenario(): EventJourneyBuiltScenario {
  return {
    id: 'photo_whatsapp_private_with_caption',
    label: 'Foto com legenda',
    description: 'Simula uma foto recebida pelo WhatsApp privado com legenda.',
    input: {},
    available: true,
    unavailableReason: null,
    highlightedNodeIds: [
      'entry_whatsapp_direct',
      'processing_safety_ai',
      'decision_event_moderation_mode',
      'output_reply_text',
    ],
    highlightedEdgeIds: [],
    humanText: 'Neste cenario, uma foto chega por WhatsApp privado com legenda. A jornada aprova a midia e encerra com mensagem automatica.',
    outcome: 'approved',
  };
}

function renderInspector(
  nodeId: string | null,
  mode: 'panel' | 'drawer' = 'panel',
  options: {
    selectedScenario?: EventJourneyBuiltScenario | null;
    onClearScenario?: () => void;
  } = {},
) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });
  const projection = makeProjection();
  const graph = buildJourneyGraph(projection);
  const selectedNode = nodeId ? graph.nodes.find((node) => node.id === nodeId) ?? null : null;

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <JourneyInspector
          mode={mode}
          open={Boolean(selectedNode)}
          eventId={42}
          projection={projection}
          selectedNode={selectedNode}
          selectedScenario={options.selectedScenario ?? null}
          onClearScenario={options.onClearScenario}
          scenarios={[]}
          technicalDetailsOpen={false}
        />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('JourneyInspector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    HTMLElement.prototype.scrollIntoView = vi.fn();
    getEventContentModerationSettingsMock.mockResolvedValue(makeContentModerationSettings());
    getEventMediaIntelligenceSettingsMock.mockResolvedValue(makeMediaIntelligenceSettings());
    listPresetsMock.mockResolvedValue([]);
    updateEventJourneyBuilderMock.mockResolvedValue(makeProjection());
    invalidateEventJourneyBuilderQueriesMock.mockResolvedValue(undefined);
  });

  it('renders the empty inspector state when no node is selected', () => {
    renderInspector(null);

    expect(screen.getByText('Nenhuma etapa selecionada')).toBeInTheDocument();
  });

  it('renders the active scenario explanation and clears it through the callback', () => {
    const onClearScenario = vi.fn();

    renderInspector(null, 'panel', {
      selectedScenario: makeScenario(),
      onClearScenario,
    });

    expect(screen.getByText('Simulacao ativa')).toBeInTheDocument();
    expect(screen.getByText('Foto com legenda')).toBeInTheDocument();
    expect(screen.getByText(/Entrada: WhatsApp particular/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Limpar simulacao' }));

    expect(onClearScenario).toHaveBeenCalledTimes(1);
  });

  it('saves the moderation mode through the aggregated journey patch', async () => {
    renderInspector('decision_event_moderation_mode');

    fireEvent.click(screen.getByLabelText('Revisao manual'));
    fireEvent.click(screen.getByRole('button', { name: 'Salvar modo de moderacao' }));

    await waitFor(() => {
      expect(updateEventJourneyBuilderMock).toHaveBeenCalledWith(42, {
        moderation_mode: 'manual',
      });
    });

    expect(toastMock).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Modo de moderacao atualizado',
    }));
  });

  it('saves the WhatsApp private channel through the aggregated journey patch', async () => {
    renderInspector('entry_whatsapp_direct');

    fireEvent.change(screen.getByLabelText('Inbox code'), {
      target: { value: 'ANA-PRIVADO' },
    });
    fireEvent.change(screen.getByLabelText('TTL da sessao (min)'), {
      target: { value: '240' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Salvar WhatsApp privado' }));

    await waitFor(() => {
      expect(updateEventJourneyBuilderMock).toHaveBeenCalledWith(42, {
        intake_channels: {
          whatsapp_direct: {
            enabled: true,
            media_inbox_code: 'ANA-PRIVADO',
            session_ttl_minutes: 240,
          },
        },
      });
    });

    expect(toastMock).toHaveBeenCalledWith(expect.objectContaining({
      title: 'WhatsApp privado atualizado',
    }));
  });

  it('loads the detailed safety settings and saves them through the journey endpoint', async () => {
    renderInspector('processing_safety_ai');

    expect(await screen.findByText('Analise de risco do evento')).toBeInTheDocument();
    expect(getEventContentModerationSettingsMock).toHaveBeenCalledWith(42);

    await waitFor(() => {
      expect(screen.queryByText('Carregando detalhes da analise de risco...')).not.toBeInTheDocument();
    });
    fireEvent.click(screen.getByLabelText('Habilitar safety'));
    fireEvent.click(screen.getByRole('button', { name: 'Salvar safety' }));

    await waitFor(() => {
      expect(updateEventJourneyBuilderMock).toHaveBeenCalledWith(42, {
        content_moderation: expect.objectContaining({
          enabled: false,
          provider_key: 'openai',
          fallback_mode: 'review',
        }),
      });
    });
  });

  it('loads the detailed media intelligence settings and saves them through the journey endpoint', async () => {
    renderInspector('output_reply_text');

    expect(await screen.findByText('Contexto e resposta automatica')).toBeInTheDocument();
    expect(getEventMediaIntelligenceSettingsMock).toHaveBeenCalledWith(42);
    await waitFor(() => {
      expect(screen.queryByText('Carregando detalhes de contexto e resposta automatica...')).not.toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText(/texto de instrucao do evento/i), {
      target: { value: 'Responder com o nome do casal quando fizer sentido.' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar vlm/i }));

    await waitFor(() => {
      expect(updateEventJourneyBuilderMock).toHaveBeenCalledWith(42, {
        media_intelligence: expect.objectContaining({
          enabled: true,
          provider_key: 'vllm',
          mode: 'gate',
          fallback_mode: 'review',
          reply_text_enabled: true,
          reply_text_mode: 'ai',
          reply_prompt_override: 'Responder com o nome do casal quando fizer sentido.',
        }),
      });
    });
  });

  it('renders the mobile drawer variant with explicit title and description', async () => {
    renderInspector('output_reply_text', 'drawer');

    expect(screen.getByTestId('journey-inspector-drawer')).toBeInTheDocument();
    expect(screen.getByText('Etapa selecionada')).toBeInTheDocument();
    expect(screen.getByText('Revise e ajuste a etapa escolhida sem sair do mapa visual.')).toBeInTheDocument();
    expect(await screen.findByText('Contexto e resposta automatica')).toBeInTheDocument();
  });
});
