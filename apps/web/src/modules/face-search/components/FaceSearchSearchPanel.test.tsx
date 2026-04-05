import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';

import type { ApiFaceSearchRequestSummary } from '@/lib/api-types';
import { FaceSearchSearchPanel } from './FaceSearchSearchPanel';

function createRequestMeta(overrides: Partial<ApiFaceSearchRequestSummary> = {}): ApiFaceSearchRequestSummary {
  return {
    id: 1,
    event_id: 10,
    requester_type: 'guest',
    requester_user_id: null,
    status: 'completed',
    consent_version: 'v1',
    selfie_storage_strategy: 'memory_only',
    faces_detected: 1,
    query_face_quality_score: 0.91,
    top_k: 20,
    best_distance: 0.12,
    result_photo_ids: [],
    created_at: '2026-04-02T12:00:00Z',
    expires_at: '2026-04-02T18:00:00Z',
    ...overrides,
  };
}

it('requires consent before allowing public selfie search submission', () => {
  const onSubmit = vi.fn();

  render(
    <FaceSearchSearchPanel
      title="Buscar minhas fotos"
      description="Envie uma selfie."
      submitLabel="Buscar agora"
      isPending={false}
      requireConsent
      onSubmit={onSubmit}
    />,
  );

  const input = screen.getByTestId('face-search-file-input') as HTMLInputElement;
  const button = screen.getByRole('button', { name: /buscar agora/i });

  fireEvent.change(input, {
    target: {
      files: [new File(['selfie'], 'selfie.jpg', { type: 'image/jpeg' })],
    },
  });

  expect(button).toBeDisabled();

  fireEvent.click(screen.getByRole('checkbox'));
  expect(button).not.toBeDisabled();

  fireEvent.click(button);

  expect(onSubmit).toHaveBeenCalledTimes(1);
  expect(onSubmit.mock.calls[0][0].file.name).toBe('selfie.jpg');
  expect(onSubmit.mock.calls[0][0].consentAccepted).toBe(true);
});

it('renders an empty-state message when a processed search has no results', () => {
  render(
    <FaceSearchSearchPanel
      title="Busca interna"
      description="Painel do operador."
      submitLabel="Buscar"
      isPending={false}
      requestMeta={createRequestMeta()}
      results={[]}
      onSubmit={() => {}}
    />,
  );

  expect(screen.getByText(/nenhuma foto encontrada para esta selfie/i)).toBeInTheDocument();
});
