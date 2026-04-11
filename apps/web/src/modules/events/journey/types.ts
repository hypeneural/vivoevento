import type { ApiEnvelope } from '@/lib/api-types';

import type { EventIntakeBlacklist, EventIntakeChannels, EventIntakeDefaults } from '../intake';
import type { ApiEventStatus, EventModerationMode, EventModuleKey } from '../types';

export type EventJourneyVersion = 'journey-builder-v1';
export type EventJourneyStageId = 'entry' | 'processing' | 'decision' | 'output';
export type EventJourneyNodeKind = 'entry' | 'process' | 'policy' | 'decision' | 'output';
export type EventJourneyNodeStatus = 'active' | 'inactive' | 'locked' | 'required' | 'unavailable';
export type EventJourneyBranchStatus = EventJourneyNodeStatus;
export type EventJourneyTextContextMode =
  | 'none'
  | 'body_only'
  | 'caption_only'
  | 'body_plus_caption'
  | 'operator_summary'
  | string;
export type EventJourneyAnalysisScope = 'image_only' | 'image_and_text_context' | string;
export type EventJourneyModerationSafetyMode = 'enforced' | 'observe_only' | string;
export type EventJourneyMediaIntelligenceMode = 'enrich_only' | 'gate' | string;
export type EventJourneyReplyTextMode = 'disabled' | 'ai' | 'fixed_random' | string;

export interface EventJourneyEventSummary {
  id: number;
  uuid: string;
  title: string;
  status: ApiEventStatus | string;
  moderation_mode: EventModerationMode | null;
  modules: Record<EventModuleKey, boolean>;
}

export interface EventJourneyContentModerationPreview {
  enabled: boolean;
  mode: EventJourneyModerationSafetyMode | null;
  fallback_mode: 'review' | 'block' | string | null;
  provider_key: string;
  analysis_scope: EventJourneyAnalysisScope | null;
  normalized_text_context_mode: EventJourneyTextContextMode | null;
  inherits_global: boolean;
}

export interface EventJourneyMediaIntelligencePreview {
  enabled: boolean;
  mode: EventJourneyMediaIntelligenceMode | null;
  fallback_mode: 'review' | 'skip' | string | null;
  provider_key: string;
  model_key: string | null;
  reply_text_enabled: boolean;
  reply_text_mode: EventJourneyReplyTextMode | null;
  context_scope: EventJourneyAnalysisScope | null;
  reply_scope: EventJourneyAnalysisScope | null;
  normalized_text_context_mode: EventJourneyTextContextMode | null;
  inherits_global: boolean;
}

export interface EventJourneyDestinations {
  gallery: boolean;
  wall: boolean;
  print: boolean;
}

export interface EventJourneySettings {
  moderation_mode: EventModerationMode | null;
  modules: Record<EventModuleKey, boolean>;
  content_moderation: EventJourneyContentModerationPreview;
  media_intelligence: EventJourneyMediaIntelligencePreview;
  destinations: EventJourneyDestinations;
}

export interface EventJourneyCapability {
  id: string;
  label: string;
  enabled: boolean;
  available: boolean;
  editable: boolean;
  reason: string | null;
  config_preview: Record<string, unknown>;
}

export interface EventJourneyBranch {
  id: string;
  label: string;
  target_node_id: string | null;
  active: boolean;
  status: EventJourneyBranchStatus;
  summary: string | null;
  conditions: Record<string, unknown>;
}

export interface EventJourneyNode {
  id: string;
  stage: EventJourneyStageId;
  kind: EventJourneyNodeKind;
  label: string;
  description: string;
  active: boolean;
  editable: boolean;
  status: EventJourneyNodeStatus;
  summary: string;
  config_preview: Record<string, unknown>;
  branches: EventJourneyBranch[];
  warnings: string[];
  meta: Record<string, unknown>;
}

export interface EventJourneyStage {
  id: EventJourneyStageId;
  label: string;
  description: string;
  position: number;
  nodes: EventJourneyNode[];
}

export interface EventJourneyScenario {
  id: string;
  label: string;
  description: string;
  input: Record<string, unknown>;
  expected_node_ids: string[];
}

export interface EventJourneySummary {
  human_text: string;
}

export type EventJourneySimulationOutcome =
  | 'approved'
  | 'review'
  | 'blocked'
  | 'inactive';

export interface EventJourneyBuiltScenario {
  id: string;
  label: string;
  description: string;
  input: Record<string, unknown>;
  available: boolean;
  unavailableReason: string | null;
  highlightedNodeIds: string[];
  highlightedEdgeIds: string[];
  humanText: string;
  outcome: EventJourneySimulationOutcome;
}

export interface EventJourneyProjection {
  version: EventJourneyVersion | string;
  event: EventJourneyEventSummary;
  intake_defaults: EventIntakeDefaults;
  intake_channels: EventIntakeChannels;
  settings: EventJourneySettings;
  capabilities: Record<string, EventJourneyCapability>;
  stages: EventJourneyStage[];
  warnings: string[];
  simulation_presets: EventJourneyScenario[];
  summary: EventJourneySummary;
}

export interface EventJourneyIntakeChannelsPatch {
  whatsapp_groups?: Partial<EventIntakeChannels['whatsapp_groups']>;
  whatsapp_direct?: Partial<EventIntakeChannels['whatsapp_direct']>;
  public_upload?: Partial<EventIntakeChannels['public_upload']>;
  telegram?: Partial<EventIntakeChannels['telegram']>;
}

export interface EventJourneyContentModerationPatch {
  inherit_global?: boolean;
  enabled?: boolean;
  provider_key?: 'openai' | 'noop' | string;
  mode?: EventJourneyModerationSafetyMode;
  threshold_version?: string | null;
  fallback_mode?: 'review' | 'block' | string;
  analysis_scope?: EventJourneyAnalysisScope;
  objective_safety_scope?: EventJourneyAnalysisScope;
  normalized_text_context_mode?: EventJourneyTextContextMode;
  hard_block_thresholds?: Partial<Record<'nudity' | 'violence' | 'self_harm', number>>;
  review_thresholds?: Partial<Record<'nudity' | 'violence' | 'self_harm', number>>;
}

export interface EventJourneyMediaIntelligencePatch {
  inherit_global?: boolean;
  enabled?: boolean;
  provider_key?: 'vllm' | 'openrouter' | 'noop' | string;
  model_key?: string;
  mode?: EventJourneyMediaIntelligenceMode;
  prompt_version?: string | null;
  approval_prompt?: string | null;
  freeform_instruction?: string | null;
  caption_style_prompt?: string | null;
  response_schema_version?: string | null;
  timeout_ms?: number;
  fallback_mode?: 'review' | 'skip' | string;
  context_scope?: EventJourneyAnalysisScope;
  reply_scope?: EventJourneyAnalysisScope;
  normalized_text_context_mode?: EventJourneyTextContextMode;
  contextual_policy_preset_key?: string | null;
  policy_version?: string | null;
  allow_alcohol?: boolean;
  allow_tobacco?: boolean;
  required_people_context?: 'optional' | 'required' | string;
  blocked_terms?: string[];
  allowed_exceptions?: string[];
  require_json_output?: boolean;
  reply_text_enabled?: boolean;
  reply_text_mode?: EventJourneyReplyTextMode;
  reply_prompt_override?: string | null;
  reply_fixed_templates?: string[];
  reply_prompt_preset_id?: number | null;
}

export interface EventJourneyUpdatePayload {
  moderation_mode?: EventModerationMode;
  modules?: Partial<Record<EventModuleKey, boolean>>;
  intake_defaults?: Partial<EventIntakeDefaults>;
  intake_channels?: EventJourneyIntakeChannelsPatch;
  intake_blacklist?: Pick<EventIntakeBlacklist, 'entries'>;
  content_moderation?: EventJourneyContentModerationPatch;
  media_intelligence?: EventJourneyMediaIntelligencePatch;
}

export type EventJourneyProjectionResponse = ApiEnvelope<EventJourneyProjection>;
