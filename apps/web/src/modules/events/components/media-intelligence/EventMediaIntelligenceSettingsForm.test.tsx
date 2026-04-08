import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EventMediaIntelligenceSettingsForm } from './EventMediaIntelligenceSettingsForm';

const settings = {
  id: 12,
  event_id: 42,
  enabled: true,
  provider_key: 'vllm',
  model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
  mode: 'enrich_only',
  prompt_version: 'foundation-v1',
  approval_prompt: 'Avalie a imagem e retorne JSON.',
  caption_style_prompt: 'Legenda curta e positiva.',
  response_schema_version: 'foundation-v1',
  timeout_ms: 12000,
  fallback_mode: 'review',
  require_json_output: true,
  reply_text_mode: 'ai',
  reply_text_enabled: true,
  reply_prompt_override: 'Use o nome do evento se ele ajudar naturalmente.',
  reply_prompt_preset_id: null,
  reply_fixed_templates: [],
  created_at: null,
  updated_at: null,
} as const;

describe('EventMediaIntelligenceSettingsForm', () => {
  it('submits the current settings as normalized payload', async () => {
    const onSubmit = vi.fn();

    render(
      <EventMediaIntelligenceSettingsForm
        settings={settings}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar vlm/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({
        enabled: true,
        provider_key: 'vllm',
        model_key: 'Qwen/Qwen2.5-VL-3B-Instruct',
        mode: 'enrich_only',
        prompt_version: 'foundation-v1',
        approval_prompt: 'Avalie a imagem e retorne JSON.',
        caption_style_prompt: 'Legenda curta e positiva.',
        response_schema_version: 'foundation-v1',
        timeout_ms: 12000,
        fallback_mode: 'review',
        require_json_output: true,
        reply_text_mode: 'ai',
        reply_prompt_override: 'Use o nome do evento se ele ajudar naturalmente.',
        reply_prompt_preset_id: null,
        reply_fixed_templates: [],
      });
    });
  });

  it('shows loading state while the mutation is pending', () => {
    render(
      <EventMediaIntelligenceSettingsForm
        settings={settings}
        eventModerationMode="manual"
        isPending
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: /salvando/i })).toBeDisabled();
    expect(screen.getByText(/o gate so faz efeito quando o evento estiver em modo ai/i)).toBeInTheDocument();
  });

  it('preserves openrouter when it comes from the API', async () => {
    const onSubmit = vi.fn();

    render(
      <EventMediaIntelligenceSettingsForm
        settings={{ ...settings, provider_key: 'openrouter', model_key: 'openai/gpt-4.1-mini' }}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar vlm/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({
          provider_key: 'openrouter',
          model_key: 'openai/gpt-4.1-mini',
        }),
      );
    });
  });

  it('submits null when the event reply override is cleared', async () => {
    const onSubmit = vi.fn();

    render(
      <EventMediaIntelligenceSettingsForm
        settings={settings}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.change(screen.getByLabelText(/texto de instrucao do evento/i), {
      target: { value: '' },
    });
    fireEvent.click(screen.getByRole('button', { name: /salvar vlm/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({
          reply_text_mode: 'ai',
          reply_prompt_override: null,
          reply_prompt_preset_id: null,
          reply_fixed_templates: [],
        }),
      );
    });
  });

  it('submits fixed templates when the response mode is texto fixo aleatorio', async () => {
    const onSubmit = vi.fn();

    render(
      <EventMediaIntelligenceSettingsForm
        settings={{
          ...settings,
          reply_text_mode: 'fixed_random',
          reply_text_enabled: true,
          reply_prompt_override: null,
          reply_fixed_templates: [
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
          ],
        }}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar vlm/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({
          reply_text_mode: 'fixed_random',
          reply_prompt_override: null,
          reply_prompt_preset_id: null,
          reply_fixed_templates: [
            'Memorias que fazem o coracao sorrir! 🎉📸',
            'Momento de risadas e lembrancas! 📱🎉',
          ],
        }),
      );
    });
  });

  it('renders visible labels in portugues for the automatic response flow', () => {
    render(
      <EventMediaIntelligenceSettingsForm
        settings={settings}
        eventModerationMode="ai"
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByText(/tipo de resposta automatica/i)).toBeInTheDocument();
    expect(screen.getByText(/contrato da resposta automatica/i)).toBeInTheDocument();
    expect(screen.getByText(/^provedor$/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/texto de instrucao do evento/i)).toBeInTheDocument();
  });
});
