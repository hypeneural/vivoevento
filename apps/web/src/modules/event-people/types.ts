export type EventPersonType =
  | 'bride'
  | 'groom'
  | 'mother'
  | 'father'
  | 'sibling'
  | 'guest'
  | 'friend'
  | 'groomsman'
  | 'bridesmaid'
  | 'vendor'
  | 'staff'
  | 'speaker'
  | 'artist'
  | 'executive';

export type EventPersonSide = 'bride_side' | 'groom_side' | 'host_side' | 'company_side' | 'neutral';
export type EventPersonStatus = 'draft' | 'active' | 'hidden';
export type EventPersonAssignmentStatus = 'suggested' | 'confirmed' | 'rejected';
export type EventPersonReviewQueueStatus = 'pending' | 'conflict' | 'resolved' | 'ignored';
export type EventPersonReviewQueueType = 'unknown_person' | 'cluster_suggestion' | 'identity_conflict' | 'coverage_gap';
export type EventPersonReferencePhotoPurpose = 'avatar' | 'matching' | 'both';
export type EventPersonReferencePhotoStatus = 'active' | 'archived' | 'invalid';
export type EventPersonGroupStatus = 'active' | 'archived';
export type EventPersonGroupMembershipStatus = 'active' | 'archived';
export type EventCoverageState = 'missing' | 'weak' | 'ok' | 'strong';
export type EventCoverageTargetType = 'person' | 'pair' | 'group';
export type EventRelationalCollectionType = 'person_best_of' | 'pair_best_of' | 'group_best_of' | 'family_moment' | 'must_have_delivery';
export type EventRelationalCollectionStatus = 'draft' | 'active' | 'archived';
export type EventRelationalCollectionVisibility = 'internal' | 'public_ready';

export interface EventPersonMediaStat {
  media_count: number;
  solo_media_count: number;
  with_others_media_count: number;
  published_media_count: number;
  pending_media_count: number;
  best_media_id?: number | null;
  latest_media_id?: number | null;
  projected_at?: string | null;
}

export interface EventPersonRepresentativeFace {
  id: number;
  event_media_face_id: number;
  rank_score: number;
  quality_score: number | null;
  pose_bucket: string | null;
  context_hash: string | null;
  sync_status: string;
  last_synced_at: string | null;
  sync_payload: Record<string, unknown> | null;
  face?: {
    id: number | null;
    event_media_id: number | null;
    face_index: number | null;
    quality_score: number | null;
    quality_tier: string | null;
  };
}

export interface EventPersonReferencePhoto {
  id: number;
  source: string | null;
  event_media_id: number | null;
  event_media_face_id: number | null;
  reference_upload_media_id: number | null;
  purpose: EventPersonReferencePhotoPurpose | string | null;
  status: EventPersonReferencePhotoStatus | string | null;
  quality_score: number | null;
  is_primary_avatar: boolean;
  face?: {
    id: number | null;
    event_media_id: number | null;
    face_index: number | null;
    quality_score: number | null;
    quality_tier: string | null;
  };
  upload_media?: {
    id: number;
    original_filename: string;
    preview_url: string | null;
    original_url: string | null;
  } | null;
}

export interface EventPersonAvatarSummary {
  media_id: number | null;
  face_id: number | null;
}

export interface EventPersonPrimaryPhotoSummary {
  reference_photo_id?: number | null;
  selection_mode?: string | null;
  source?: string | null;
  media_id?: number | null;
  event_media_id?: number | null;
  event_media_face_id?: number | null;
  reference_upload_media_id?: number | null;
  best_media_id: number | null;
  latest_media_id: number | null;
}

export interface EventPersonRelationPersonSummary {
  id: number;
  display_name: string;
  type: string | null;
  side: string | null;
  status: string | null;
}

export interface EventPersonRelation {
  id: number;
  event_id: number;
  person_pair_key: string;
  relation_type: string;
  directionality: string;
  source: string;
  confidence: number | null;
  strength: number | null;
  is_primary: boolean;
  notes: string | null;
  person_a?: EventPersonRelationPersonSummary | null;
  person_b?: EventPersonRelationPersonSummary | null;
  other_person?: EventPersonRelationPersonSummary | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EventPeoplePresetPerson {
  key: string;
  label: string;
  role_key?: string;
  role_label?: string;
  role_family?: string;
  type: string;
  side: string;
  importance_rank: number;
  description?: string | null;
}

export interface EventPeoplePresetRelation {
  type: string;
  label: string;
  directionality: string;
}

export interface EventPeoplePresetGroup {
  key: string;
  label: string;
  role_family: string;
  member_role_keys: string[];
  importance_rank: number;
}

export interface EventPeopleCoverageTargetSeed {
  key: string;
  label: string;
  target_type: string;
  role_keys: string[];
  group_key: string | null;
  priority: number;
}

export interface EventPeoplePresetsResponse {
  event_type: string | null;
  model_key?: string | null;
  people: EventPeoplePresetPerson[];
  relations: EventPeoplePresetRelation[];
  groups?: EventPeoplePresetGroup[];
  coverage_targets?: EventPeopleCoverageTargetSeed[];
}

export interface EventPersonGroupStats {
  member_count: number;
  people_with_primary_photo_count: number;
  people_with_media_count: number;
  media_count: number;
  published_media_count: number;
}

export interface EventPersonGroupMembershipPersonSummary extends EventPersonRelationPersonSummary {
  has_primary_photo: boolean;
}

export interface EventPersonGroupMembership {
  id: number;
  event_person_group_id: number;
  event_person_id: number;
  role_label: string | null;
  source: string | null;
  confidence: number | null;
  status: EventPersonGroupMembershipStatus | string;
  person?: EventPersonGroupMembershipPersonSummary | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface EventCoverageTargetStat {
  coverage_state: EventCoverageState | string;
  score: number;
  resolved_entity_count: number;
  media_count: number;
  published_media_count: number;
  joint_media_count: number;
  people_with_primary_photo_count: number;
  reason_codes: string[];
  projected_at?: string | null;
}

export interface EventCoverageTargetPersonSummary {
  id: number;
  display_name: string;
  type: string | null;
  side: string | null;
  status: string | null;
}

export interface EventCoverageTargetGroupSummary {
  id: number;
  display_name: string;
  slug: string;
  importance_rank: number;
}

export interface EventCoverageTarget {
  id: number;
  key: string;
  label: string;
  target_type: EventCoverageTargetType | string;
  status: string;
  importance_rank: number;
  required_media_count: number;
  required_published_media_count: number;
  last_evaluated_at?: string | null;
  person_a?: EventCoverageTargetPersonSummary | null;
  person_b?: EventCoverageTargetPersonSummary | null;
  group?: EventCoverageTargetGroupSummary | null;
  stat?: EventCoverageTargetStat | null;
}

export interface EventCoverageAlertTargetSummary {
  id: number;
  key: string;
  label: string;
  coverage_state?: EventCoverageState | string | null;
}

export interface EventCoverageAlert {
  id: number;
  alert_key: string;
  severity: string;
  title: string;
  summary: string | null;
  status: string;
  last_evaluated_at?: string | null;
  target?: EventCoverageAlertTargetSummary | null;
}

export interface EventPeopleCoverageSummary {
  missing: number;
  weak: number;
  ok: number;
  strong: number;
  active_alerts: number;
  last_evaluated_at?: string | null;
}

export interface EventPeopleCoverageResponse {
  summary: EventPeopleCoverageSummary;
  targets: EventCoverageTarget[];
  alerts: EventCoverageAlert[];
}

export interface EventRelationalCollectionPersonSummary {
  id: number;
  display_name: string;
  type: string | null;
}

export interface EventRelationalCollectionGroupSummary {
  id: number;
  display_name: string;
  slug: string;
  group_type: string | null;
}

export interface EventRelationalCollectionMediaSummary {
  id: number;
  caption: string | null;
  preview_url: string | null;
  thumbnail_url: string | null;
  original_url: string | null;
  publication_status: string | null;
  moderation_status: string | null;
  created_at: string | null;
}

export interface EventRelationalCollectionItem {
  id: number;
  event_media_id: number;
  sort_order: number;
  match_score: number;
  matched_people_count: number;
  is_published: boolean;
  media?: EventRelationalCollectionMediaSummary | null;
}

export interface EventRelationalCollection {
  id: number;
  collection_key: string;
  collection_type: EventRelationalCollectionType | string;
  source_type: string;
  display_name: string;
  status: EventRelationalCollectionStatus | string;
  visibility: EventRelationalCollectionVisibility | string;
  share_token: string | null;
  public_url?: string | null;
  public_api_url?: string | null;
  item_count: number;
  published_item_count: number;
  person_a?: EventRelationalCollectionPersonSummary | null;
  person_b?: EventRelationalCollectionPersonSummary | null;
  group?: EventRelationalCollectionGroupSummary | null;
  metadata: Record<string, unknown>;
  generated_at: string | null;
  published_at: string | null;
  items: EventRelationalCollectionItem[];
}

export interface EventPeopleRelationalCollectionsSummary {
  total_collections: number;
  public_ready_collections: number;
  internal_collections: number;
  must_have_deliveries: number;
  last_generated_at: string | null;
}

export interface EventPeopleRelationalCollectionsResponse {
  summary: EventPeopleRelationalCollectionsSummary;
  collections: EventRelationalCollection[];
}

export interface EventPeoplePublicRelationalCollectionEvent {
  id: number;
  title: string;
  slug: string;
  event_type: string | null;
  starts_at: string | null;
  location_name: string | null;
  public_gallery_url: string | null;
  public_hub_url: string | null;
}

export interface EventPeoplePublicRelationalCollectionResponse {
  event: EventPeoplePublicRelationalCollectionEvent;
  collection: Pick<
    EventRelationalCollection,
    'id' | 'collection_key' | 'collection_type' | 'display_name' | 'metadata' | 'items' | 'person_a' | 'person_b' | 'group' | 'item_count'
  >;
}

export interface EventPersonGroup {
  id: number;
  event_id: number;
  display_name: string;
  slug: string;
  group_type: string | null;
  side: EventPersonSide | string | null;
  notes: string | null;
  importance_rank: number;
  status: EventPersonGroupStatus | string;
  stats: EventPersonGroupStats;
  memberships: EventPersonGroupMembership[];
  created_at: string | null;
  updated_at: string | null;
}

export interface EventPersonGroupPayload {
  display_name: string;
  group_type?: string | null;
  side?: EventPersonSide | string | null;
  importance_rank?: number | null;
  notes?: string | null;
  status?: EventPersonGroupStatus | string | null;
}

export interface EventPersonGroupMemberPayload {
  event_person_id: number;
  role_label?: string | null;
  source?: string | null;
  confidence?: number | null;
  status?: EventPersonGroupMembershipStatus | string | null;
}

export interface EventPerson {
  id: number;
  event_id: number;
  display_name: string;
  slug: string;
  type: EventPersonType | string | null;
  side: EventPersonSide | string | null;
  avatar_media_id: number | null;
  avatar_face_id: number | null;
  avatar?: EventPersonAvatarSummary | null;
  importance_rank: number;
  notes: string | null;
  status: EventPersonStatus | string;
  primary_photo?: EventPersonPrimaryPhotoSummary | null;
  stats?: EventPersonMediaStat[];
  reference_photos?: EventPersonReferencePhoto[];
  representative_faces?: EventPersonRepresentativeFace[];
  relations?: EventPersonRelation[];
  created_at: string | null;
  updated_at: string | null;
}

export interface EventPeopleOperationalStatus {
  people_active: number;
  people_draft: number;
  assignments_confirmed: number;
  review_queue_pending: number;
  review_queue_conflict: number;
  aws_sync_pending: number;
  aws_sync_failed: number;
}

export interface EventPeopleGraphPerson {
  id: number;
  display_name: string;
  role_key: string | null;
  role_label: string;
  role_family: string;
  type: string | null;
  side: string | null;
  status: string | null;
  avatar_url: string | null;
  importance_rank: number;
  media_count: number;
  published_media_count: number;
  has_primary_photo: boolean;
}

export interface EventPeopleGraphRelation {
  id: number;
  person_a_id: number;
  person_b_id: number;
  person_a_name: string | null;
  person_b_name: string | null;
  relation_type: string;
  directionality: string;
  source: string | null;
  strength: number | null;
  is_primary: boolean;
  notes: string | null;
  co_photo_count: number | null;
}

export interface EventPeopleGraphGroupSeed extends EventPeoplePresetGroup {
  current_member_count: number;
}

export interface EventPeopleGraphStats {
  people_count: number;
  relation_count: number;
  connected_people_count: number;
  principal_people_count: number;
  without_primary_photo_count: number;
}

export interface EventPeopleGraphFilters {
  statuses: string[];
  sides: string[];
  role_families: string[];
  relation_types: string[];
}

export interface EventPeopleGraphResponse {
  people: EventPeopleGraphPerson[];
  relations: EventPeopleGraphRelation[];
  groups: EventPeopleGraphGroupSeed[];
  stats: EventPeopleGraphStats;
  filters: EventPeopleGraphFilters;
}

export interface EventPersonFaceAssignment {
  id: number;
  event_person_id: number;
  event_media_face_id: number;
  source: string | null;
  confidence: number | null;
  status: EventPersonAssignmentStatus | string;
  reviewed_at: string | null;
  person: EventPerson | null;
}

export interface EventPersonFaceBBox {
  x: number;
  y: number;
  w: number;
  h: number;
}

export interface EventPersonFaceQuality {
  score: number | null;
  tier: string | null;
  rejection_reason: string | null;
}

export interface EventPersonConflictCandidate {
  id: number;
  display_name: string;
  type: string | null;
  side: string | null;
  status: string | null;
  assignment_status: string | null;
  source: string | null;
}

export interface EventPersonReviewQueuePayload {
  label?: string | null;
  question?: string | null;
  resolution?: string | null;
  event_media_id?: number | null;
  face_index?: number | null;
  event_person_id?: number | null;
  current_person_id?: number | null;
  quality_tier?: string | null;
  quality_score?: number | null;
  candidate_people?: EventPersonConflictCandidate[];
  [key: string]: unknown;
}

export interface EventPersonReviewQueueFaceSummary {
  id: number;
  event_media_id: number;
  face_index: number;
  bbox: EventPersonFaceBBox;
}

export interface EventPersonReviewQueuePersonSummary {
  id: number | null;
  display_name: string | null;
  type: string | null;
  side: string | null;
}

export interface EventPersonReviewQueueItem {
  id: number;
  event_id: number;
  queue_key: string;
  type: EventPersonReviewQueueType | string;
  status: EventPersonReviewQueueStatus | string;
  priority: number;
  event_person_id: number | null;
  event_media_face_id: number | null;
  payload: EventPersonReviewQueuePayload;
  last_signal_at: string | null;
  resolved_at: string | null;
  person?: EventPersonReviewQueuePersonSummary;
  face?: EventPersonReviewQueueFaceSummary;
}

export interface EventPersonReferencePhotoCandidate {
  assignment_id: number;
  event_media_face_id: number;
  event_media_id: number | null;
  face_index: number | null;
  quality_score: number | null;
  quality_tier: string | null;
  reviewed_at: string | null;
  media?: {
    id: number;
    caption: string | null;
    thumbnail_url: string | null;
    preview_url: string | null;
    original_url: string | null;
    created_at: string | null;
  } | null;
}

export interface EventMediaFacePeople {
  id: number;
  event_media_id: number;
  face_index: number;
  bbox: EventPersonFaceBBox;
  quality: EventPersonFaceQuality;
  assignments: EventPersonFaceAssignment[];
  current_assignment: EventPersonFaceAssignment | null;
  review_item: EventPersonReviewQueueItem | null;
}

export interface PaginatedApiMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id?: string;
}

export interface PaginatedApiResponse<T> {
  success: boolean;
  data: T[];
  meta: PaginatedApiMeta;
}

export interface EventPeopleListFilters {
  search?: string;
  status?: string;
  type?: string;
  side?: string;
  per_page?: number;
}

export interface EventPeopleReviewQueueFilters {
  status?: string;
  type?: string;
  per_page?: number;
}

export interface EventPeopleCreatePayload {
  display_name: string;
  type?: EventPersonType | string;
  side?: EventPersonSide | string;
  importance_rank?: number;
  notes?: string | null;
  status?: EventPersonStatus | string;
}

export interface EventPeopleUpdatePayload extends Partial<EventPeopleCreatePayload> {}

export interface EventPeopleRelationPayload {
  person_a_id: number;
  person_b_id: number;
  relation_type: string;
  directionality?: string;
  confidence?: number | null;
  strength?: number | null;
  is_primary?: boolean;
  notes?: string | null;
}

export interface ConfirmReviewItemPayload {
  person_id?: number;
  person?: EventPeopleCreatePayload;
}

export interface MergeReviewItemPayload {
  source_person_id: number;
  target_person_id: number;
}

export interface ConfirmReviewItemResponse {
  person: EventPerson;
  face: EventMediaFacePeople;
  review_item: EventPersonReviewQueueItem | null;
}

export interface IgnoreReviewItemResponse {
  review_item: EventPersonReviewQueueItem;
}

export interface MergeReviewItemResponse {
  source_person: EventPerson;
  target_person: EventPerson;
  review_item: EventPersonReviewQueueItem | null;
}

export interface SplitReviewItemResponse {
  person: EventPerson | null;
  face: EventMediaFacePeople;
  review_item: EventPersonReviewQueueItem | null;
}
