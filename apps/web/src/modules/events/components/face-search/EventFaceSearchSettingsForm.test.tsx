import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EventFaceSearchSettingsForm } from './EventFaceSearchSettingsForm';

const settings = {
  id: 14,
  event_id: 42,
  provider_key: 'noop',
  embedding_model_key: 'face-embedding-foundation-v1',
  vector_store_key: 'pgvector',
  enabled: true,
  min_face_size_px: 96,
  min_quality_score: 0.6,
  search_threshold: 0.35,
  top_k: 50,
  allow_public_selfie_search: false,
  selfie_retention_hours: 24,
  created_at: null,
  updated_at: null,
} as const;

describe('EventFaceSearchSettingsForm', () => {
  it('submits the current settings as normalized payload', async () => {
    const onSubmit = vi.fn();

    render(
      <EventFaceSearchSettingsForm
        settings={settings}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar facesearch/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({
        enabled: true,
        provider_key: 'noop',
        embedding_model_key: 'face-embedding-foundation-v1',
        vector_store_key: 'pgvector',
        min_face_size_px: 96,
        min_quality_score: 0.6,
        search_threshold: 0.35,
        top_k: 50,
        allow_public_selfie_search: false,
        selfie_retention_hours: 24,
      });
    });
  });

  it('shows loading state while the mutation is pending', () => {
    render(
      <EventFaceSearchSettingsForm
        settings={settings}
        eventModerationMode="manual"
        isPending
        onSubmit={vi.fn()}
      />,
    );

    expect(screen.getByRole('button', { name: /salvando/i })).toBeDisabled();
    expect(screen.getByText(/continua fora do gate de moderacao/i)).toBeInTheDocument();
  });
});
