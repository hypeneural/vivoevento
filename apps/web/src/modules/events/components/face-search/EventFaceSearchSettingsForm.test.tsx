import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { EventFaceSearchSettingsForm } from './EventFaceSearchSettingsForm';

const settings = {
  id: 14,
  event_id: 42,
  provider_key: 'noop',
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
  recognition_enabled: false,
  search_backend_key: 'local_pgvector',
  fallback_backend_key: null,
  routing_policy: 'local_only',
  shadow_mode_percentage: 0,
  aws_region: 'eu-central-1',
  aws_collection_id: null,
  aws_collection_arn: null,
  aws_face_model_version: null,
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

    fireEvent.click(screen.getByRole('button', { name: /salvar reconhecimento facial/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith({
        enabled: true,
        provider_key: 'noop',
        embedding_model_key: 'face-embedding-foundation-v1',
        vector_store_key: 'pgvector',
        search_strategy: 'exact',
        min_face_size_px: 24,
        min_quality_score: 0.6,
        search_threshold: 0.5,
        top_k: 50,
        allow_public_selfie_search: false,
        selfie_retention_hours: 24,
        recognition_enabled: false,
        search_backend_key: 'local_pgvector',
        fallback_backend_key: null,
        routing_policy: 'local_only',
        shadow_mode_percentage: 0,
        aws_region: 'eu-central-1',
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
    expect(screen.getByText(/funciona separada da aprovacao das fotos/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /configuracao avancada e integracao aws/i })).toBeInTheDocument();
  });

  it('preserves compreface provider when it comes from the API', async () => {
    const onSubmit = vi.fn();

    render(
      <EventFaceSearchSettingsForm
        settings={{
          ...settings,
          provider_key: 'compreface',
          embedding_model_key: 'compreface-face-v1',
        }}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar reconhecimento facial/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
        provider_key: 'compreface',
        embedding_model_key: 'compreface-face-v1',
      }));
    });
  });

  it('preserves ann search strategy when it comes from the API', async () => {
    const onSubmit = vi.fn();

    render(
      <EventFaceSearchSettingsForm
        settings={{
          ...settings,
          search_strategy: 'ann',
        }}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar reconhecimento facial/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
        search_strategy: 'ann',
      }));
    });
  });

  it('preserves aws routing and thresholds when the api already returns an aws-configured event', async () => {
    const onSubmit = vi.fn();

    render(
      <EventFaceSearchSettingsForm
        settings={{
          ...settings,
          recognition_enabled: true,
          search_backend_key: 'aws_rekognition',
          fallback_backend_key: 'local_pgvector',
          routing_policy: 'aws_primary_local_fallback',
          shadow_mode_percentage: 25,
          aws_region: 'us-east-1',
          aws_collection_id: 'eventovivo-face-search-event-42',
          aws_collection_arn: 'arn:aws:rekognition:us-east-1:123456789012:collection/eventovivo-face-search-event-42',
          aws_face_model_version: '7.0',
          aws_search_mode: 'users',
          aws_index_quality_filter: 'AUTO',
          aws_search_faces_quality_filter: 'LOW',
          aws_search_users_quality_filter: 'HIGH',
          aws_search_face_match_threshold: 82,
          aws_search_user_match_threshold: 88,
          aws_associate_user_match_threshold: 77,
          aws_max_faces_per_image: 35,
          aws_index_profile_key: 'corporate_stage_event',
          aws_detection_attributes_json: ['DEFAULT'],
          delete_remote_vectors_on_event_close: true,
        }}
        eventModerationMode="ai"
        onSubmit={onSubmit}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /salvar reconhecimento facial/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
        recognition_enabled: true,
        search_backend_key: 'aws_rekognition',
        fallback_backend_key: 'local_pgvector',
        routing_policy: 'aws_primary_local_fallback',
        shadow_mode_percentage: 25,
        aws_region: 'us-east-1',
        aws_search_mode: 'users',
        aws_search_faces_quality_filter: 'LOW',
        aws_search_users_quality_filter: 'HIGH',
        aws_search_face_match_threshold: 82,
        aws_search_user_match_threshold: 88,
        aws_associate_user_match_threshold: 77,
        aws_max_faces_per_image: 35,
        aws_index_profile_key: 'corporate_stage_event',
        aws_detection_attributes_json: ['DEFAULT'],
        delete_remote_vectors_on_event_close: true,
      }));
    });

    fireEvent.click(screen.getByRole('button', { name: /configuracao avancada e integracao aws/i }));
    expect(screen.getByText(/estrutura aws pronta/i)).toBeInTheDocument();
    expect(screen.getAllByText(/eventovivo-face-search-event-42/i).length).toBeGreaterThan(0);
  });
});
