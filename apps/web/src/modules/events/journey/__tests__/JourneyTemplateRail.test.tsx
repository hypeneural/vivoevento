import { fireEvent, render, screen, within } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import {
  buildJourneyTemplatePreview,
  type JourneyTemplateId,
} from '../buildJourneyTemplatePreview';
import { JourneyTemplateRail } from '../JourneyTemplateRail';
import type { EventJourneyProjection } from '../types';

function makeProjection(): EventJourneyProjection {
  return {
    version: 'journey-builder-v1',
    event: {
      id: 42,
      uuid: 'event-42',
      title: 'Casamento Ana e Pedro',
      status: 'active',
      moderation_mode: 'manual',
      modules: {
        live: true,
        wall: false,
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
        enabled: false,
        groups: [],
      },
      whatsapp_direct: {
        enabled: true,
        media_inbox_code: 'ANA2026',
        session_ttl_minutes: 180,
      },
      public_upload: {
        enabled: false,
      },
      telegram: {
        enabled: false,
        bot_username: null,
        media_inbox_code: null,
        session_ttl_minutes: 180,
      },
    },
    settings: {
      moderation_mode: 'manual',
      modules: {
        live: true,
        wall: false,
        hub: true,
        play: false,
      },
      content_moderation: {
        enabled: false,
        mode: 'observe_only',
        fallback_mode: 'review',
        provider_key: 'openai',
        analysis_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      media_intelligence: {
        enabled: false,
        mode: 'enrich_only',
        fallback_mode: 'review',
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        reply_text_enabled: false,
        reply_text_mode: 'disabled',
        context_scope: 'image_and_text_context',
        reply_scope: 'image_and_text_context',
        normalized_text_context_mode: 'body_plus_caption',
        inherits_global: false,
      },
      destinations: {
        gallery: true,
        wall: false,
        print: false,
      },
    },
    capabilities: {
      supports_wall_output: {
        id: 'supports_wall_output',
        label: 'Telao',
        enabled: false,
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
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_whatsapp_groups',
            stage: 'entry',
            kind: 'entry',
            label: 'WhatsApp grupos',
            description: 'Grupos',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'WhatsApp grupos desligado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_public_upload',
            stage: 'entry',
            kind: 'entry',
            label: 'Link de envio',
            description: 'Upload',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Link de envio desligado.',
            config_preview: { available: true },
            branches: [],
            warnings: [],
            meta: {},
          },
          {
            id: 'entry_telegram',
            stage: 'entry',
            kind: 'entry',
            label: 'Telegram',
            description: 'Telegram',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Telegram desligado.',
            config_preview: { available: true },
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
        nodes: [],
      },
      {
        id: 'decision',
        label: 'Decisao',
        description: 'Decisao',
        position: 2,
        nodes: [],
      },
      {
        id: 'output',
        label: 'Saida',
        description: 'Saida',
        position: 3,
        nodes: [
          {
            id: 'output_wall',
            stage: 'output',
            kind: 'output',
            label: 'Telao',
            description: 'Telao',
            active: false,
            editable: true,
            status: 'inactive',
            summary: 'Telao desligado ou indisponivel.',
            config_preview: { available: true, enabled: false, module_enabled: false },
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
      human_text: 'Resumo base.',
    },
  };
}

function TemplateHarness({
  onSave,
}: {
  onSave: () => void;
}) {
  const projection = makeProjection();
  const [activeTemplateId, setActiveTemplateId] = useState<JourneyTemplateId | null>(null);

  return (
    <JourneyTemplateRail
      projection={projection}
      activeTemplatePreview={activeTemplateId ? buildJourneyTemplatePreview(projection, activeTemplateId) : null}
      onApplyTemplate={setActiveTemplateId}
      onDiscardTemplate={() => setActiveTemplateId(null)}
      onSaveTemplate={onSave}
    />
  );
}

describe('JourneyTemplateRail', () => {
  it('opens the template comparison dialog and only applies the draft after confirmation', () => {
    render(<TemplateHarness onSave={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Aprovacao direta' }));

    const dialog = screen.getByRole('alertdialog');

    expect(within(dialog).getByRole('heading', { name: 'Aprovacao direta' })).toBeInTheDocument();
    expect(within(dialog).getByText(/diff local antes do save/i)).toBeInTheDocument();
    expect(screen.queryByText(/Rascunho local ativo/i)).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Aplicar ao rascunho' }));

    const activeDraftAlert = screen.getByRole('alert');

    expect(within(activeDraftAlert).getByText('Rascunho local ativo')).toBeInTheDocument();
    expect(within(activeDraftAlert).getByText('Aprovacao direta')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Salvar template' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Descartar rascunho' })).toBeInTheDocument();
  });

  it('lets the user discard the local draft before saving', () => {
    render(<TemplateHarness onSave={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Revisao manual' }));
    fireEvent.click(screen.getByRole('button', { name: 'Aplicar ao rascunho' }));
    fireEvent.click(screen.getByRole('button', { name: 'Descartar rascunho' }));

    expect(screen.queryByText('Rascunho local ativo')).not.toBeInTheDocument();
  });

  it('delegates the final save action only after a template has been applied', () => {
    const onSave = vi.fn();

    render(<TemplateHarness onSave={onSave} />);

    fireEvent.click(screen.getByRole('button', { name: 'IA moderando' }));
    fireEvent.click(screen.getByRole('button', { name: 'Aplicar ao rascunho' }));
    fireEvent.click(screen.getByRole('button', { name: 'Salvar template' }));

    expect(onSave).toHaveBeenCalledTimes(1);
  });
});
