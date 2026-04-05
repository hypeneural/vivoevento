import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EventContentModerationSettingsForm } from './EventContentModerationSettingsForm';

const settings = {
  id: 10,
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
} as const;

describe('EventContentModerationSettingsForm', () => {
  it('submits the current settings as normalized numeric payload', async () => {
    const onSubmit = vi.fn();

    render(
      <EventContentModerationSettingsForm
        settings={settings}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar safety/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({
        enabled: true,
        provider_key: 'openai',
        mode: 'enforced',
        threshold_version: 'foundation-v1',
        fallback_mode: 'review',
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
      });
    });
  });

  it('shows loading state while the mutation is pending', () => {
    render(
      <EventContentModerationSettingsForm
        settings={settings}
        eventModerationMode="manual"
        isPending
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: /salvando/i })).toBeDisabled();
    expect(screen.getByText(/so viram gate quando o evento estiver em modo ai/i)).toBeInTheDocument();
  });
});
