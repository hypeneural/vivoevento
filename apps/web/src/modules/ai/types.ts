export interface MediaIntelligenceGlobalSettings {
  id: number | null;
  enabled: boolean;
  provider_key: 'vllm' | 'openrouter' | 'noop' | string;
  model_key: string;
  mode: 'enrich_only' | 'gate' | string;
  prompt_version: string | null;
  response_schema_version: string | null;
  timeout_ms: number;
  fallback_mode: 'review' | 'skip' | string;
  context_scope: 'image_only' | 'image_and_text_context' | string;
  reply_scope: 'image_only' | 'image_and_text_context' | string;
  normalized_text_context_mode: 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary' | string;
  require_json_output: boolean;
  contextual_policy_preset_key: string | null;
  policy_version: string | null;
  allow_alcohol: boolean;
  allow_tobacco: boolean;
  required_people_context: 'optional' | 'required' | string;
  blocked_terms: string[];
  allowed_exceptions: string[];
  freeform_instruction: string | null;
  reply_text_prompt: string;
  reply_text_fixed_templates: string[];
  reply_prompt_preset_id: number | null;
  reply_prompt_preset?: MediaReplyPromptPreset | null;
  reply_ai_rate_limit_enabled: boolean;
  reply_ai_rate_limit_max_messages: number;
  reply_ai_rate_limit_window_minutes: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface ContentModerationGlobalSettings {
  id: number | null;
  enabled: boolean;
  provider_key: 'openai' | 'noop' | string;
  mode: 'enforced' | 'observe_only' | string;
  threshold_version: string | null;
  hard_block_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  review_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  fallback_mode: 'review' | 'block' | string;
  analysis_scope: 'image_only' | 'image_and_text_context' | string;
  objective_safety_scope: 'image_only' | 'image_and_text_context' | string;
  normalized_text_context_mode: 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary' | string;
  created_at: string | null;
  updated_at: string | null;
}

export interface MediaReplyPromptCategory {
  id: number;
  slug: string;
  name: string;
  sort_order: number;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface SaveMediaReplyPromptCategoryPayload {
  slug?: string | null;
  name: string;
  sort_order?: number;
  is_active: boolean;
}

export interface UpdateMediaIntelligenceGlobalSettingsPayload {
  enabled: boolean;
  provider_key: 'vllm' | 'openrouter' | 'noop' | string;
  model_key: string;
  mode: 'enrich_only' | 'gate' | string;
  prompt_version?: string | null;
  response_schema_version?: string | null;
  timeout_ms: number;
  fallback_mode: 'review' | 'skip' | string;
  context_scope: 'image_only' | 'image_and_text_context' | string;
  reply_scope: 'image_only' | 'image_and_text_context' | string;
  normalized_text_context_mode: 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary' | string;
  require_json_output: boolean;
  contextual_policy_preset_key?: string | null;
  policy_version?: string | null;
  allow_alcohol: boolean;
  allow_tobacco: boolean;
  required_people_context: 'optional' | 'required' | string;
  blocked_terms: string[];
  allowed_exceptions: string[];
  freeform_instruction?: string | null;
  reply_text_prompt: string;
  reply_text_fixed_templates: string[];
  reply_prompt_preset_id?: number | null;
  reply_ai_rate_limit_enabled: boolean;
  reply_ai_rate_limit_max_messages: number;
  reply_ai_rate_limit_window_minutes: number;
}

export interface UpdateContentModerationGlobalSettingsPayload {
  enabled: boolean;
  provider_key: 'openai' | 'noop' | string;
  mode: 'enforced' | 'observe_only' | string;
  threshold_version?: string | null;
  fallback_mode: 'review' | 'block' | string;
  analysis_scope: 'image_only' | 'image_and_text_context' | string;
  normalized_text_context_mode: 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary' | string;
  hard_block_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
  review_thresholds: {
    nudity: number;
    violence: number;
    self_harm: number;
  };
}

export interface MediaReplyPromptPreset {
  id: number;
  slug: string;
  name: string;
  category: string | null;
  category_entry?: MediaReplyPromptCategory | null;
  description: string | null;
  prompt_template: string;
  sort_order: number;
  is_active: boolean;
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SaveMediaReplyPromptPresetPayload {
  slug?: string | null;
  name: string;
  category?: string | null;
  description?: string | null;
  prompt_template: string;
  sort_order?: number;
  is_active: boolean;
}

export interface RunMediaReplyPromptTestPayload {
  event_id?: number | null;
  provider_key: 'vllm' | 'openrouter';
  model_key: string;
  prompt_template?: string | null;
  preset_id?: number | null;
  objective_safety_scope_override?: 'image_only' | 'image_and_text_context' | null;
  context_scope_override?: 'image_only' | 'image_and_text_context' | null;
  reply_scope_override?: 'image_only' | 'image_and_text_context' | null;
  normalized_text_context_mode_override?: 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary' | null;
  images: File[];
}

export interface MediaReplyPromptTestRun {
  id: number;
  trace_id: string;
  user_id: number | null;
  event_id: number | null;
  preset_id: number | null;
  preset?: MediaReplyPromptPreset | null;
  provider_key: string;
  model_key: string;
  status: 'success' | 'failed' | string;
  prompt_template: string | null;
  prompt_resolved: string | null;
  prompt_variables: Record<string, string>;
  images: Array<{
    index?: number;
    image_index?: number;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
  }>;
  safety_results: Array<{
    image_index: number | null;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
    decision: string;
    blocked?: boolean;
    review_required?: boolean;
    category_scores?: Record<string, number>;
    input_scope_used?: string | null;
    input_path_used?: string | null;
    normalized_text_context?: string | null;
    normalized_text_context_mode?: string | null;
    reason_codes?: string[];
    error_message?: string | null;
  }>;
  contextual_results: Array<{
    image_index: number | null;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
    decision: string;
    review_required?: boolean;
    reason?: string | null;
    reason_code?: string | null;
    matched_policies?: string[];
    matched_exceptions?: string[];
    input_scope_used?: string | null;
    input_types_considered?: string[];
    confidence_band?: string | null;
    publish_eligibility?: string | null;
    short_caption?: string | null;
    reply_text?: string | null;
    tags?: string[];
    response_schema_version?: string | null;
    mode_applied?: string | null;
    normalized_text_context?: string | null;
    normalized_text_context_mode?: string | null;
    error_message?: string | null;
  }>;
  final_summary: {
    images_evaluated?: number;
    reply_status?: 'success' | 'failed' | string;
    safety_is_blocking?: boolean;
    context_is_blocking?: boolean;
    safety_counts?: Record<string, number>;
    context_counts?: Record<string, number>;
    blocking_layers?: string[];
    reason_codes?: string[];
    evaluation_errors_count?: number;
    final_publish_eligibility?: 'auto_publish' | 'review_only' | 'reject' | string;
    final_effective_state?: string | null;
    human_reason?: string | null;
  };
  policy_snapshot: {
    safety?: Record<string, unknown>;
    context?: Record<string, unknown>;
  };
  policy_sources: {
    safety?: Record<string, string>;
    context?: Record<string, string>;
  };
  request_payload: Record<string, unknown>;
  response_payload: Record<string, unknown> | null;
  response_text: string | null;
  latency_ms: number | null;
  error_message: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface MediaReplyEventOption {
  id: number;
  title: string;
}

export interface MediaReplyEventHistoryItem {
  id: number;
  event_id: number | null;
  event_title: string | null;
  event_media_id: number;
  inbound_message_id: number | null;
  provider_message_id: string | null;
  trace_id: string | null;
  source_type: string | null;
  source_label: string | null;
  sender_name: string | null;
  sender_phone: string | null;
  sender_external_id: string | null;
  message_type: string | null;
  media_type: string | null;
  mime_type: string | null;
  preview_url: string | null;
  provider_key: string | null;
  model_key: string | null;
  status: string | null;
  decision: string | null;
  effective_media_state: string | null;
  safety_decision: string | null;
  context_decision: string | null;
  operator_decision: string | null;
  publication_decision: string | null;
  human_reason: string | null;
  reason: string | null;
  reason_code: string | null;
  matched_policies: string[];
  matched_exceptions: string[];
  input_scope_used: string | null;
  input_types_considered: string[];
  confidence_band: 'low' | 'medium' | 'high' | string | null;
  publish_eligibility: 'auto_publish' | 'review_only' | 'reject' | string | null;
  policy_label: string | null;
  policy_inheritance_mode: string | null;
  prompt_template: string | null;
  prompt_resolved: string | null;
  prompt_variables: Record<string, string>;
  preset_name: string | null;
  preset_id: number | null;
  prompt_instruction_source: string | null;
  prompt_preset_source: string | null;
  normalized_text_context?: string | null;
  normalized_text_context_mode?: string | null;
  context_scope?: string | null;
  reply_scope?: string | null;
  text_context_summary?: string | null;
  policy_snapshot: Record<string, unknown>;
  policy_sources: Record<string, unknown>;
  reply_text: string | null;
  short_caption: string | null;
  tags: string[];
  request_payload: Record<string, unknown>;
  response_payload: Record<string, unknown>;
  error_message: string | null;
  run_status: string | null;
  run_started_at: string | null;
  run_finished_at: string | null;
  completed_at: string | null;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}
