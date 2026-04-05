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
});
