import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import { EventFaceSearchSettingsCard } from './EventFaceSearchSettingsCard';

const getEventFaceSearchSettingsMock = vi.fn();
const updateEventFaceSearchSettingsMock = vi.fn();
const getEventFaceSearchHealthMock = vi.fn();
const reindexEventFaceSearchMock = vi.fn();
const reconcileEventFaceSearchMock = vi.fn();
const deleteEventFaceSearchCollectionMock = vi.fn();
const toastMock = vi.fn();

vi.mock('../../api', () => ({
  getEventFaceSearchSettings: (...args: unknown[]) => getEventFaceSearchSettingsMock(...args),
  updateEventFaceSearchSettings: (...args: unknown[]) => updateEventFaceSearchSettingsMock(...args),
  getEventFaceSearchHealth: (...args: unknown[]) => getEventFaceSearchHealthMock(...args),
  reindexEventFaceSearch: (...args: unknown[]) => reindexEventFaceSearchMock(...args),
  reconcileEventFaceSearch: (...args: unknown[]) => reconcileEventFaceSearchMock(...args),
  deleteEventFaceSearchCollection: (...args: unknown[]) => deleteEventFaceSearchCollectionMock(...args),
}));

vi.mock('@/hooks/use-toast', () => ({
  useToast: () => ({
    toast: toastMock,
  }),
}));

vi.mock('./EventFaceSearchSettingsForm', () => ({
  EventFaceSearchSettingsForm: () => <div>Form mockado de FaceSearch</div>,
}));

const awsSettings = {
  id: 14,
  event_id: 42,
  provider_key: 'compreface',
  embedding_model_key: 'face-embedding-foundation-v1',
  vector_store_key: 'pgvector',
  search_strategy: 'exact',
  enabled: true,
  min_face_size_px: 24,
  min_quality_score: 0.6,
  search_threshold: 0.5,
  top_k: 50,
  allow_public_selfie_search: false,
  selfie_retention_hours: 24,
  recognition_enabled: true,
  search_backend_key: 'aws_rekognition',
  fallback_backend_key: 'local_pgvector',
  routing_policy: 'aws_primary_local_fallback',
  shadow_mode_percentage: 10,
  aws_region: 'eu-central-1',
  aws_collection_id: 'eventovivo-face-search-event-42',
  aws_collection_arn: 'arn:aws:rekognition:eu-central-1:123456789012:collection/eventovivo-face-search-event-42',
  aws_face_model_version: '7.0',
  aws_search_mode: 'faces',
  aws_index_quality_filter: 'AUTO',
  aws_search_faces_quality_filter: 'NONE',
  aws_search_users_quality_filter: 'NONE',
  aws_search_face_match_threshold: 80,
  aws_search_user_match_threshold: 80,
  aws_associate_user_match_threshold: 75,
  aws_max_faces_per_image: 100,
  aws_index_profile_key: 'social_gallery_event',
  aws_detection_attributes_json: ['DEFAULT', 'FACE_OCCLUDED'],
  delete_remote_vectors_on_event_close: false,
  created_at: null,
  updated_at: null,
} as const;

function renderCard() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <EventFaceSearchSettingsCard eventId={42} eventModerationMode="ai" />
    </QueryClientProvider>,
  );
}

describe('EventFaceSearchSettingsCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getEventFaceSearchSettingsMock.mockResolvedValue(awsSettings);
  });

  it('shows collection status and renders the latest health snapshot after a manual health check', async () => {
    getEventFaceSearchHealthMock.mockResolvedValue({
      backend_key: 'aws_rekognition',
      status: 'healthy',
      checked_at: '2026-04-09T12:00:00Z',
      collection: {
        collection_id: awsSettings.aws_collection_id,
      },
      checks: {
        identity: 'ok',
        collection: 'ok',
        list_faces: 'ok',
      },
    });

    renderCard();

    expect(await screen.findByText(/pronto para validacao interna/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /ferramentas tecnicas e diagnostico/i }));

    expect(await screen.findByText(/estrutura aws pronta/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /verificar aws/i }));

    await waitFor(() => {
      expect(getEventFaceSearchHealthMock).toHaveBeenCalledWith(42);
    });

    expect((await screen.findAllByText(/ultima verificacao/i)).length).toBeGreaterThan(0);
    expect(screen.getByText('Saudavel')).toBeInTheDocument();
    expect(screen.getByText('2026-04-09T12:00:00Z')).toBeInTheDocument();
  });

  it('runs aws operational actions from the panel', async () => {
    reindexEventFaceSearchMock.mockResolvedValue({
      status: 'queued',
      backend_key: 'aws_rekognition',
      queued_media_count: 12,
    });
    reconcileEventFaceSearchMock.mockResolvedValue({
      status: 'queued',
      backend_key: 'aws_rekognition',
      job: 'reconcile_collection',
    });
    deleteEventFaceSearchCollectionMock.mockResolvedValue({
      status: 'deleted',
      backend_key: 'aws_rekognition',
      collection_id: awsSettings.aws_collection_id,
    });

    renderCard();

    expect(await screen.findByText(/form mockado de facesearch/i)).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /ferramentas tecnicas e diagnostico/i }));
    fireEvent.click(screen.getByRole('button', { name: /preparar fotos antigas/i }));
    fireEvent.click(screen.getByRole('button', { name: /conferir indexacao/i }));
    fireEvent.click(screen.getByRole('button', { name: /apagar estrutura aws/i }));

    await waitFor(() => {
      expect(reindexEventFaceSearchMock).toHaveBeenCalledWith(42);
      expect(reconcileEventFaceSearchMock).toHaveBeenCalledWith(42);
      expect(deleteEventFaceSearchCollectionMock).toHaveBeenCalledWith(42);
    });
  });
});
