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

export interface EventPersonMediaStat {
  total_media: number;
  total_solo_media: number;
  total_group_media: number;
  total_published_media: number;
  total_pending_media: number;
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
  importance_rank: number;
  notes: string | null;
  status: EventPersonStatus | string;
  stats?: EventPersonMediaStat[];
  created_at: string | null;
  updated_at: string | null;
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
