import { describe, expect, it } from 'vitest';

import { resolveEventFaceSearchOperationalStatus } from './face-search-status';

const baseSettings = {
  id: 1,
  event_id: 10,
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
  recognition_enabled: false,
  search_backend_key: 'local_pgvector',
  fallback_backend_key: null,
  routing_policy: 'local_only',
  shadow_mode_percentage: 0,
  aws_region: 'us-east-1',
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
  aws_detection_attributes_json: ['DEFAULT'],
  delete_remote_vectors_on_event_close: false,
  created_at: null,
  updated_at: null,
} as const;

describe('resolveEventFaceSearchOperationalStatus', () => {
  it('returns disabled status when the event has not enabled facial search', () => {
    expect(resolveEventFaceSearchOperationalStatus({ ...baseSettings, enabled: false })).toEqual({
      label: 'Desligado',
      description: 'O reconhecimento facial ainda nao foi ativado para este evento.',
      notes: [],
      tone: 'neutral',
    });
  });

  it('returns local mode status before aws becomes the main lane', () => {
    expect(resolveEventFaceSearchOperationalStatus(baseSettings)).toEqual({
      label: 'Ligado localmente',
      description: 'A busca esta ativa em modo interno e ainda nao depende da estrutura principal da AWS.',
      notes: [],
      tone: 'info',
    });
  });

  it('returns preparing status when aws is selected without a provisioned collection', () => {
    expect(resolveEventFaceSearchOperationalStatus({
      ...baseSettings,
      recognition_enabled: true,
      search_backend_key: 'aws_rekognition',
      routing_policy: 'aws_primary_local_fallback',
    })).toEqual({
      label: 'Preparando estrutura',
      description: 'A estrutura da AWS ainda esta sendo criada para receber as fotos deste evento.',
      notes: [],
      tone: 'info',
    });
  });

  it('returns converging status when the aws summary says the legacy catalog is still catching up', () => {
    expect(resolveEventFaceSearchOperationalStatus({
      ...baseSettings,
      recognition_enabled: true,
      search_backend_key: 'aws_rekognition',
      routing_policy: 'aws_primary_local_fallback',
      aws_collection_id: 'eventovivo-face-search-event-10',
      allow_public_selfie_search: true,
      operational_summary: {
        status: 'converging',
        search_mode: 'users',
        collection_ready: true,
        catalog_ready: false,
        is_converging: true,
        internal_search_ready: true,
        guest_search_ready: false,
        requires_attention: true,
        counts: {
          total_media: 30,
          queued_media: 2,
          processing_media: 1,
          indexed_media: 24,
          failed_media: 1,
          skipped_media: 2,
          searchable_records: 18,
          distinct_ready_users: 7,
        },
      },
    })).toEqual({
      label: 'Indexando fotos antigas',
      description: 'O acervo antigo ainda esta convergindo antes de a busca ficar totalmente estavel para convidados.',
      notes: [
        '3 foto(s) antiga(s) ainda estao em preparacao para a busca.',
        '7 pessoa(s) ja estao prontas na busca principal da AWS.',
        '1 item(ns) tiveram falha e precisam de nova preparacao ou conferencia tecnica.',
      ],
      tone: 'warning',
    });
  });

  it('returns internal validation status when aws is ready but public search is still closed', () => {
    expect(resolveEventFaceSearchOperationalStatus({
      ...baseSettings,
      recognition_enabled: true,
      search_backend_key: 'aws_rekognition',
      routing_policy: 'aws_primary_local_fallback',
      aws_collection_id: 'eventovivo-face-search-event-10',
    })).toEqual({
      label: 'Pronto para validacao interna',
      description: 'A busca ja esta pronta para testes da equipe, mas ainda nao foi liberada para convidados.',
      notes: [],
      tone: 'info',
    });
  });

  it('returns ready status when public search is available', () => {
    expect(resolveEventFaceSearchOperationalStatus({
      ...baseSettings,
      recognition_enabled: true,
      search_backend_key: 'aws_rekognition',
      routing_policy: 'aws_primary_local_fallback',
      aws_collection_id: 'eventovivo-face-search-event-10',
      allow_public_selfie_search: true,
    })).toEqual({
      label: 'Pronto para convidados',
      description: 'Convidados ja podem enviar uma selfie para encontrar fotos publicadas deste evento.',
      notes: [],
      tone: 'success',
    });
  });
});
