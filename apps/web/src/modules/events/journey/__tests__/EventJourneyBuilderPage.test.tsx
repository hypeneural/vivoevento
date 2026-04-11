import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import EventJourneyBuilderPage from '@/modules/events/pages/EventJourneyBuilderPage';

import type { EventJourneyProjection } from '../types';

const getEventJourneyBuilderMock = vi.fn();
const updateEventJourneyBuilderMock = vi.fn();
const invalidateEventJourneyBuilderQueriesMock = vi.fn();
const fitViewMock = vi.fn();
const journeyFlowCanvasPropsSpy = vi.fn();

vi.mock('@/modules/events/journey/api', () => {
  return {
    eventJourneyBuilderQueryOptions: (eventId: number | string) => ({
      queryKey: ['events', 'journey-builder', String(eventId)] as const,
      queryFn: () => getEventJourneyBuilderMock(String(eventId)),
    }),
    updateEventJourneyBuilder: (...args: unknown[]) => updateEventJourneyBuilderMock(...args),
    invalidateEventJourneyBuilderQueries: (...args: unknown[]) => invalidateEventJourneyBuilderQueriesMock(...args),
  };
});

vi.mock('@/modules/events/journey/JourneyFlowCanvas', () => {
  return {
    JourneyFlowCanvas: (props: {
      graph: { nodes: Array<{ id: string }> };
      selectedNodeId: string | null;
      highlightedNodeIds?: string[];
      highlightedEdgeIds?: string[];
      onSelectedNodeIdChange?: (nodeId: string | null) => void;
      onReady?: (controls: { fitView: () => void }) => void;
    }) => {
      journeyFlowCanvasPropsSpy(props);

      if (props.graph.nodes.length === 0) {
        return <div>A jornada ainda nao tem etapas projetadas</div>;
      }

      return (
        <div data-testid="journey-flow-canvas">
          <button type="button" onClick={() => props.onReady?.({ fitView: fitViewMock })}>
            Registrar canvas
          </button>
          <button
            type="button"
            onClick={() => props.onSelectedNodeIdChange?.('decision_event_moderation_mode')}
          >
            Selecionar no
          </button>
          <div>{props.selectedNodeId ? `Selecionado: ${props.selectedNodeId}` : 'Sem selecao'}</div>
          <div>Nos destacados: {props.highlightedNodeIds?.join(',') ?? ''}</div>
          <div>Arestas destacadas: {props.highlightedEdgeIds?.join(',') ?? ''}</div>
        </div>
      );
    },
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
        available: true,
        editable: true,
        groups: [],
      },
      whatsapp_direct: {
        enabled: true,
        available: true,
        editable: true,
        media_inbox_code: 'ANA2026',
        session_ttl_minutes: 180,
      },
      public_upload: {
        enabled: true,
        available: true,
        editable: true,
      },
      telegram: {
        enabled: true,
        available: true,
        editable: true,
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
    capabilities: {
      supports_wall_output: {
        id: 'supports_wall_output',
        label: 'Telao',
        enabled: true,
        available: true,
        editable: true,
        reason: null,
        config_preview: {},
      },
    },
    stages: [
      {
        id: 'entry',
        label: 'Entrada',
        description: 'Como fotos e videos chegam ao evento.',
        position: 0,
        nodes: [
          {
            id: 'entry_whatsapp_direct',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp privado',
            description: 'Recebe fotos por conversa privada.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por codigo privado.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_whatsapp_groups',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp grupos',
            description: 'Recebe fotos por grupos.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por grupos.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_telegram',
            stage: 'entry',
            kind: 'entry',
            label: 'Telegram',
            description: 'Recebe fotos pelo bot.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias pelo bot.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_public_upload',
            stage: 'entry',
            kind: 'entry',
            label: 'Link de envio',
            description: 'Recebe fotos por link.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Recebe midias por link.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_receive_feedback', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'processing',
        label: 'Processamento',
        description: 'Como a midia e salva, preparada e analisada.',
        position: 320,
        nodes: [
          {
            id: 'processing_receive_feedback',
            stage: 'processing',
            kind: 'process',
            label: 'Confirmar recebimento',
            description: 'Feedback inicial.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Envia feedback inicial.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_download_media', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_download_media',
            stage: 'processing',
            kind: 'process',
            label: 'Salvar midia',
            description: 'Etapa tecnica.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Obrigatorio.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'processing_prepare_variants', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'processing_prepare_variants',
            stage: 'processing',
            kind: 'process',
            label: 'Preparar variantes',
            description: 'Etapa tecnica.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Obrigatorio.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'decision_event_moderation_mode', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Quais condicoes alteram o caminho da midia.',
        position: 640,
        nodes: [
          {
            id: 'decision_event_moderation_mode',
            stage: 'decision',
            kind: 'decision',
            label: 'Modo de moderacao do evento',
            description: 'Define se aprova direto ou manda para revisao.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Moderacao por IA ativa.',
            config_preview: {},
            branches: [
              { id: 'ai', label: 'IA', target_node_id: 'decision_safety_result', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'manual', label: 'Manual', target_node_id: 'output_silence', active: false, status: 'inactive', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_safety_result',
            stage: 'decision',
            kind: 'decision',
            label: 'Resultado do safety',
            description: 'Pode aprovar, revisar ou bloquear.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Bloqueio por risco forte e revisao quando houver duvida.',
            config_preview: {},
            branches: [
              { id: 'approved', label: 'Aprovado', target_node_id: 'decision_context_gate', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'review', label: 'Review', target_node_id: 'output_silence', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'blocked', label: 'Bloqueado', target_node_id: 'output_reply_text', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_context_gate',
            stage: 'decision',
            kind: 'decision',
            label: 'Gate de contexto',
            description: 'Valida contexto da midia e texto.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Pode aprovar ou pedir revisao.',
            config_preview: {},
            branches: [
              { id: 'approved', label: 'Aprovado', target_node_id: 'decision_media_type', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'review', label: 'Review', target_node_id: 'output_silence', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_media_type',
            stage: 'decision',
            kind: 'decision',
            label: 'Tipo da midia',
            description: 'Separa foto e video.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Distingue foto e video.',
            config_preview: {},
            branches: [
              { id: 'photo', label: 'Foto', target_node_id: 'decision_caption_presence', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'video', label: 'Video', target_node_id: 'output_gallery', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
          {
            id: 'decision_caption_presence',
            stage: 'decision',
            kind: 'decision',
            label: 'Legenda existe?',
            description: 'Decide se passa por texto contextual.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Legendas mudam a resposta final.',
            config_preview: {},
            branches: [
              { id: 'with_caption', label: 'Com legenda', target_node_id: 'output_reaction_final', active: true, status: 'active', summary: null, conditions: {} },
              { id: 'without_caption', label: 'Sem legenda', target_node_id: 'output_gallery', active: true, status: 'active', summary: null, conditions: {} },
            ],
            warnings: [],
            meta: {},
          },
        ],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Como a jornada responde e publica.',
        position: 960,
        nodes: [
          {
            id: 'output_reaction_final',
            stage: 'output',
            kind: 'output',
            label: 'Reacao final',
            description: 'Confirma para o remetente.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Envia reacao final.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'output_reply_text', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_reply_text',
            stage: 'output',
            kind: 'output',
            label: 'Resposta automatica',
            description: 'Responde com texto.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Resposta por IA ativa.',
            config_preview: {},
            branches: [{ id: 'default', label: 'Padrao', target_node_id: 'output_gallery', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_gallery',
            stage: 'output',
            kind: 'output',
            label: 'Publicar na galeria',
            description: 'Destino obrigatorio da V1.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Galeria sempre ligada.',
            config_preview: {},
            branches: [{ id: 'wall_enabled', label: 'Telao ligado', target_node_id: 'output_wall', active: true, status: 'active', summary: null, conditions: {} }],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_wall',
            stage: 'output',
            kind: 'output',
            label: 'Publicar no telao',
            description: 'Publica quando o modulo estiver ativo.',
            active: true,
            editable: true,
            status: 'active',
            summary: 'Telao habilitado.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'output_silence',
            stage: 'output',
            kind: 'output',
            label: 'Silencio operacional',
            description: 'Interrompe a publicacao visivel.',
            active: true,
            editable: false,
            status: 'required',
            summary: 'Sem destinos visiveis.',
            config_preview: {},
            branches: [],
            warnings: [],
            meta: {},
          },
        ],
      },
    ],
    warnings: ['Telegram esta ativo e depende do mesmo inbox code do evento.'],
    simulation_presets: [],
    summary: {
      human_text: 'Quando uma foto chega pelo WhatsApp, o sistema analisa com IA, responde e publica na galeria e no telao.',
    },
  };
}

function renderJourneyBuilderPage(initialEntry = '/events/42/flow') {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter
        initialEntries={[initialEntry]}
        future={{ v7_relativeSplatPath: true, v7_startTransition: true }}
      >
        <Routes>
          <Route path="/events/:id/flow" element={<EventJourneyBuilderPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('EventJourneyBuilderPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    updateEventJourneyBuilderMock.mockResolvedValue(makeProjection());
    invalidateEventJourneyBuilderQueriesMock.mockResolvedValue(undefined);
  });

  it('keeps a stable loading state while the projection is pending', async () => {
    getEventJourneyBuilderMock.mockReturnValue(new Promise(() => {}));

    renderJourneyBuilderPage();

    await waitFor(() => {
      expect(getEventJourneyBuilderMock).toHaveBeenCalledWith('42');
    });

    expect(screen.getByText('Carregando jornada da midia...')).toBeInTheDocument();
  });

  it('renders the guided journey shell with summary, legend and empty inspector', async () => {
    getEventJourneyBuilderMock.mockResolvedValue(makeProjection());

    renderJourneyBuilderPage();

    expect(await screen.findByRole('heading', { name: 'Jornada da midia' })).toBeInTheDocument();
    expect(screen.getByText('Como o evento trata cada foto ou video recebido')).toBeInTheDocument();
    expect(screen.getByText('Resumo humano da jornada')).toBeInTheDocument();
    expect(screen.getByText('Templates guiados')).toBeInTheDocument();
    expect(screen.getByText('Simulador de cenarios')).toBeInTheDocument();
    expect(screen.getByText(/Quando uma foto chega pelo WhatsApp/i)).toBeInTheDocument();
    expect(screen.getByText('Centralizar fluxo')).toBeInTheDocument();
    expect(screen.getByText('Ver detalhes tecnicos')).toBeInTheDocument();
    expect(screen.getByText('Legenda fixa da jornada')).toBeInTheDocument();
    expect(screen.getAllByText('Entrada').length).toBeGreaterThan(0);
    expect(screen.getByText('Nenhuma etapa selecionada')).toBeInTheDocument();
    expect(screen.getByTestId('journey-flow-canvas')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Foto com legenda' }));

    await waitFor(() => {
      expect(
        screen.getAllByText(/Neste cenario, uma foto chega por WhatsApp privado com legenda\./i).length,
      ).toBeGreaterThan(0);
    });
    expect(screen.getAllByText('Simulacao ativa').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Foto com legenda').length).toBeGreaterThan(0);
    expect(
      journeyFlowCanvasPropsSpy.mock.calls.at(-1)?.[0].highlightedNodeIds,
    ).toEqual(
      expect.arrayContaining([
        'entry_whatsapp_direct',
        'decision_caption_presence',
        'output_gallery',
      ]),
    );
    expect(screen.getAllByText(/Caminho destacado/i).length).toBeGreaterThan(0);

    fireEvent.click(screen.getByRole('button', { name: 'Selecionar no' }));

    expect(await screen.findByText('Modo de moderacao')).toBeInTheDocument();
    expect(screen.getByText('Moderacao por IA ativa.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Registrar canvas' }));
    fireEvent.click(screen.getByRole('button', { name: 'Centralizar fluxo' }));

    expect(fitViewMock).toHaveBeenCalledTimes(1);

    fireEvent.click(screen.getByRole('button', { name: 'Ver detalhes tecnicos' }));

    expect(await screen.findByText('Detalhes tecnicos da projection')).toBeInTheDocument();
    expect(screen.getByText('Warnings')).toBeInTheDocument();

    fireEvent.click(screen.getAllByRole('button', { name: 'Limpar simulacao' })[0]);

    expect(await screen.findByText(/Quando uma foto chega pelo WhatsApp/i)).toBeInTheDocument();
    expect(updateEventJourneyBuilderMock).not.toHaveBeenCalled();
  });

  it('applies a guided template locally before the real save', async () => {
    getEventJourneyBuilderMock.mockResolvedValue(makeProjection());

    renderJourneyBuilderPage();

    expect(await screen.findByRole('heading', { name: 'Jornada da midia' })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Aprovacao direta' }));
    expect(screen.getByText(/Publica sem fila manual nem analises de IA/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Aplicar ao rascunho' }));

    expect(await screen.findByText('Rascunho local ativo')).toBeInTheDocument();
    expect(screen.getByText(/aprova automaticamente/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Salvar template' }));

    await waitFor(() => {
      expect(updateEventJourneyBuilderMock).toHaveBeenCalledWith('42', {
        moderation_mode: 'none',
        content_moderation: {
          enabled: false,
        },
        media_intelligence: {
          enabled: false,
          reply_text_enabled: false,
          reply_text_mode: 'disabled',
        },
      });
    });
  });

  it('renders a permission-oriented error when the projection returns 403', async () => {
    getEventJourneyBuilderMock.mockRejectedValue({ status: 403 });

    renderJourneyBuilderPage();

    expect(await screen.findByText('Falha ao carregar a jornada')).toBeInTheDocument();
    expect(screen.getByText('Voce nao tem permissao para visualizar a jornada deste evento.')).toBeInTheDocument();
  });

  it('renders the empty-stage fallback when the backend returns no visual stages', async () => {
    getEventJourneyBuilderMock.mockResolvedValue({
      ...makeProjection(),
      stages: [],
    });

    renderJourneyBuilderPage();

    expect(await screen.findByText('A jornada ainda nao tem etapas projetadas')).toBeInTheDocument();
  });
});
