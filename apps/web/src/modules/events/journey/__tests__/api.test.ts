import { QueryClient } from '@tanstack/react-query';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';

import type { EventJourneyProjection, EventJourneyUpdatePayload } from '../types';
import {
  eventJourneyBuilderMutationOptions,
  eventJourneyBuilderQueryOptions,
  getEventJourneyBuilder,
  invalidateEventJourneyBuilderQueries,
  updateEventJourneyBuilder,
} from '../api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 31,
      uuid: 'journey-event-31',
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
      whatsapp_instance_id: 18,
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
        media_inbox_code: 'NOIVA2026',
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
        media_inbox_code: 'NOIVABOT',
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
        label: 'Publicacao no telao',
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
        description: 'Como a midia chega no evento.',
        position: 0,
        nodes: [],
      },
      {
        id: 'processing',
        label: 'Processamento',
        description: 'Como a midia e tratada.',
        position: 1,
        nodes: [],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Como a midia segue para aprovacao ou bloqueio.',
        position: 2,
        nodes: [],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Quais destinos recebem a midia.',
        position: 3,
        nodes: [],
      },
    ],
    warnings: [],
    simulation_presets: [],
    summary: {
      human_text: 'Quando a midia chega por WhatsApp privado, o Evento Vivo analisa risco e contexto com IA antes de publicar.',
    },
  };
}

function makeUpdatePayload(): EventJourneyUpdatePayload {
  return {
    moderation_mode: 'ai',
    modules: {
      wall: true,
    },
    intake_channels: {
      telegram: {
        enabled: true,
        bot_username: 'EventoVivoBot',
      },
    },
    media_intelligence: {
      reply_text_enabled: true,
      reply_text_mode: 'ai',
    },
  };
}

describe('event journey api', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads the read-only journey projection from the aggregated endpoint', async () => {
    const projection = makeProjection();

    vi.mocked(api.get).mockResolvedValue(projection);

    await expect(getEventJourneyBuilder(31)).resolves.toEqual(projection);

    expect(api.get).toHaveBeenCalledWith('/events/31/journey-builder');
  });

  it('sends the aggregated patch payload to the journey builder endpoint', async () => {
    const payload = makeUpdatePayload();
    const projection = makeProjection();

    vi.mocked(api.patch).mockResolvedValue(projection);

    await expect(updateEventJourneyBuilder(31, payload)).resolves.toEqual(projection);

    expect(api.patch).toHaveBeenCalledWith('/events/31/journey-builder', {
      body: payload,
    });
  });

  it('builds a dedicated queryOptions object for the journey builder projection', async () => {
    const projection = makeProjection();

    vi.mocked(api.get).mockResolvedValue(projection);

    const options = eventJourneyBuilderQueryOptions(31);

    expect(options.queryKey).toEqual(queryKeys.events.journeyBuilder('31'));
    await expect(options.queryFn()).resolves.toEqual(projection);
    expect(api.get).toHaveBeenCalledWith('/events/31/journey-builder');
  });

  it('invalidates the journey builder, event detail and related settings after save', async () => {
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });
    const invalidateQueriesSpy = vi
      .spyOn(queryClient, 'invalidateQueries')
      .mockResolvedValue(undefined);

    await invalidateEventJourneyBuilderQueries(queryClient, 31);

    expect(invalidateQueriesSpy).toHaveBeenCalledTimes(7);
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: queryKeys.events.all(),
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: queryKeys.events.detail('31'),
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: ['event-detail', '31'],
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: queryKeys.events.journeyBuilder('31'),
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: queryKeys.events.telegramOperationalStatus('31'),
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: ['event-content-moderation-settings', 31],
    });
    expect(invalidateQueriesSpy).toHaveBeenCalledWith({
      queryKey: ['event-media-intelligence-settings', 31],
    });
  });

  it('keeps the mutation success lifecycle pending until journey invalidation finishes', async () => {
    const queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
        mutations: { retry: false },
      },
    });
    const pendingInvalidations: Array<() => void> = [];

    vi.spyOn(queryClient, 'invalidateQueries').mockImplementation(
      () =>
        new Promise((resolve) => {
          pendingInvalidations.push(() => resolve(undefined));
        }),
    );

    const projection = makeProjection();
    const onSuccess = eventJourneyBuilderMutationOptions(queryClient, 31).onSuccess;
    let settled = false;

    const onSuccessPromise = onSuccess?.(projection, makeUpdatePayload(), undefined, undefined).then(() => {
      settled = true;
    });

    await Promise.resolve();

    expect(settled).toBe(false);
    expect(pendingInvalidations).toHaveLength(7);

    pendingInvalidations.forEach((resolve) => resolve());
    await onSuccessPromise;

    expect(settled).toBe(true);
  });
});
