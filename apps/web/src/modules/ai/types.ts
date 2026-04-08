export interface MediaIntelligenceGlobalSettings {
  id: number | null;
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
  reply_text_prompt: string;
  reply_text_fixed_templates: string[];
  reply_prompt_preset_id?: number | null;
  reply_ai_rate_limit_enabled: boolean;
  reply_ai_rate_limit_max_messages: number;
  reply_ai_rate_limit_window_minutes: number;
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
    index: number;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    sha256: string | null;
  }>;
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
  prompt_template: string | null;
  prompt_resolved: string | null;
  prompt_variables: Record<string, string>;
  preset_name: string | null;
  preset_id: number | null;
  prompt_instruction_source: string | null;
  prompt_preset_source: string | null;
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
