import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { FolderTree, Loader2, Plus, Save, Sparkles, TestTube2, Trash2 } from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { ApiError } from '@/lib/api';
import { PageHeader } from '@/shared/components/PageHeader';

import { aiMediaRepliesService } from './api';
import type {
  ContentModerationGlobalSettings,
  MediaReplyEventHistoryItem,
  MediaReplyEventOption,
  MediaIntelligenceGlobalSettings,
  MediaReplyPromptCategory,
  MediaReplyPromptPreset,
  MediaReplyPromptTestRun,
  SaveMediaReplyPromptCategoryPayload,
  SaveMediaReplyPromptPresetPayload,
} from './types';

function templatesToTextarea(templates: string[]): string {
  return templates.join('\n');
}

function textareaToTemplates(value: string): string[] {
  return value
    .split(/\r?\n/u)
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function listToTextarea(items: string[]): string {
  return items.join('\n');
}

function textareaToList(value: string): string[] {
  return value
    .split(/\r?\n/u)
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function scopeLabel(value: string | null | undefined): string {
  switch (value) {
    case 'image_only':
      return 'Somente imagem';
    case 'image_and_text_context':
      return 'Imagem + texto';
    default:
      return value || 'Nao definido';
  }
}

function normalizedTextContextModeLabel(value: string | null | undefined): string {
  switch (value) {
    case 'none':
      return 'Sem texto';
    case 'body_only':
      return 'Somente corpo';
    case 'caption_only':
      return 'Somente caption';
    case 'body_plus_caption':
      return 'Corpo + caption';
    case 'operator_summary':
      return 'Resumo do operador';
    default:
      return value || 'Nao definido';
  }
}

function publishEligibilityLabel(value: string | null | undefined): string {
  switch (value) {
    case 'auto_publish':
      return 'Publicar automaticamente';
    case 'review_only':
      return 'Revisao manual';
    case 'reject':
      return 'Rejeitar';
    default:
      return value || 'Nao definido';
  }
}

function runStatusBadgeVariant(value: string | null | undefined): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (value) {
    case 'success':
      return 'default';
    case 'partial':
      return 'secondary';
    case 'failed':
      return 'destructive';
    default:
      return 'outline';
  }
}

function runStatusLabel(value: string | null | undefined): string {
  switch (value) {
    case 'success':
      return 'Sucesso';
    case 'partial':
      return 'Parcial';
    case 'failed':
      return 'Falha';
    default:
      return value || 'Nao informado';
  }
}

function policySourceLabel(value: string | null | undefined): string {
  switch (value) {
    case 'event_setting':
      return 'Evento';
    case 'global_setting':
      return 'Global';
    case 'preset':
      return 'Preset';
    case 'runtime_override':
      return 'Laboratorio';
    case 'provider_runtime':
      return 'Provider';
    case 'default_config':
      return 'Default';
    default:
      return value || 'Nao definido';
  }
}

function effectiveStateLabel(value: string | null | undefined): string {
  switch (value) {
    case 'published':
      return 'Publicado';
    case 'approved':
      return 'Aprovado';
    case 'pending_moderation':
      return 'Em moderacao';
    case 'rejected':
      return 'Rejeitado';
    case 'hidden':
      return 'Oculto';
    case 'processing':
      return 'Processando';
    case 'received':
      return 'Recebido';
    case 'error':
      return 'Erro';
    default:
      return value || 'Nao informado';
  }
}

function policyInheritanceLabel(value: string | null | undefined): string {
  switch (value) {
    case 'event_override':
      return 'Personalizado no evento';
    case 'global':
      return 'Herdando do global';
    case 'preset':
      return 'Somente preset';
    case 'runtime_fallback':
      return 'Fallback operacional';
    default:
      return 'Origem nao identificada';
  }
}

function effectiveStateBadgeVariant(value: string | null | undefined): 'default' | 'secondary' | 'destructive' | 'outline' {
  switch (value) {
    case 'published':
    case 'approved':
      return 'default';
    case 'rejected':
    case 'error':
      return 'destructive';
    case 'pending_moderation':
    case 'processing':
    case 'received':
      return 'secondary';
    default:
      return 'outline';
  }
}

function emptyPresetForm(): SaveMediaReplyPromptPresetPayload {
  return {
    slug: '',
    name: '',
    category: '',
    description: '',
    prompt_template: '',
    sort_order: 0,
    is_active: true,
  };
}

function emptyCategoryForm(): SaveMediaReplyPromptCategoryPayload {
  return {
    slug: '',
    name: '',
    sort_order: 0,
    is_active: true,
  };
}

export default function MediaAutomaticRepliesPage() {
  const { meUser: user } = useAuth();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const canManageGlobalAi = user?.role.key === 'super-admin' || user?.role.key === 'platform-admin';

  const [standardInstruction, setStandardInstruction] = useState('');
  const [standardFixedTemplates, setStandardFixedTemplates] = useState('');
  const [standardPresetId, setStandardPresetId] = useState<string>('none');
  const [aiReplyLimitEnabled, setAiReplyLimitEnabled] = useState(false);
  const [aiReplyLimitMaxMessages, setAiReplyLimitMaxMessages] = useState('10');
  const [aiReplyLimitWindowMinutes, setAiReplyLimitWindowMinutes] = useState('10');
  const [contextEnabled, setContextEnabled] = useState(false);
  const [contextProviderKey, setContextProviderKey] = useState<'vllm' | 'openrouter' | 'noop'>('vllm');
  const [contextModelKey, setContextModelKey] = useState('');
  const [contextMode, setContextMode] = useState<'enrich_only' | 'gate'>('enrich_only');
  const [contextScope, setContextScope] = useState<'image_only' | 'image_and_text_context'>('image_and_text_context');
  const [replyScope, setReplyScope] = useState<'image_only' | 'image_and_text_context'>('image_and_text_context');
  const [normalizedTextContextMode, setNormalizedTextContextMode] = useState<'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary'>('body_plus_caption');
  const [contextRequireJsonOutput, setContextRequireJsonOutput] = useState(true);
  const [contextPromptVersion, setContextPromptVersion] = useState('contextual-v2');
  const [contextResponseSchemaVersion, setContextResponseSchemaVersion] = useState('contextual-v2');
  const [contextTimeoutMs, setContextTimeoutMs] = useState('12000');
  const [contextFallbackMode, setContextFallbackMode] = useState<'review' | 'skip'>('review');
  const [contextPresetKey, setContextPresetKey] = useState('homologacao_livre');
  const [allowAlcohol, setAllowAlcohol] = useState(true);
  const [allowTobacco, setAllowTobacco] = useState(true);
  const [requiredPeopleContext, setRequiredPeopleContext] = useState<'optional' | 'required'>('optional');
  const [blockedTermsText, setBlockedTermsText] = useState('');
  const [allowedExceptionsText, setAllowedExceptionsText] = useState('');
  const [freeformInstruction, setFreeformInstruction] = useState('');
  const [safetyEnabled, setSafetyEnabled] = useState(false);
  const [safetyProviderKey, setSafetyProviderKey] = useState<'openai' | 'noop'>('openai');
  const [safetyMode, setSafetyMode] = useState<'enforced' | 'observe_only'>('enforced');
  const [safetyThresholdVersion, setSafetyThresholdVersion] = useState('foundation-v1');
  const [safetyFallbackMode, setSafetyFallbackMode] = useState<'review' | 'block'>('review');
  const [safetyScope, setSafetyScope] = useState<'image_only' | 'image_and_text_context'>('image_and_text_context');
  const [safetyNormalizedTextContextMode, setSafetyNormalizedTextContextMode] = useState<'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary'>('body_plus_caption');
  const [safetyReviewNudity, setSafetyReviewNudity] = useState('0.65');
  const [safetyReviewViolence, setSafetyReviewViolence] = useState('0.65');
  const [safetyReviewSelfHarm, setSafetyReviewSelfHarm] = useState('0.65');
  const [safetyBlockNudity, setSafetyBlockNudity] = useState('0.90');
  const [safetyBlockViolence, setSafetyBlockViolence] = useState('0.90');
  const [safetyBlockSelfHarm, setSafetyBlockSelfHarm] = useState('0.90');

  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [categoryForm, setCategoryForm] = useState<SaveMediaReplyPromptCategoryPayload>(emptyCategoryForm());
  const [selectedPresetId, setSelectedPresetId] = useState<number | null>(null);
  const [presetForm, setPresetForm] = useState<SaveMediaReplyPromptPresetPayload>(emptyPresetForm());
  const [catalogCategoryFilter, setCatalogCategoryFilter] = useState<string>('all');
  const [catalogPresetSearch, setCatalogPresetSearch] = useState('');

  const [testEventId, setTestEventId] = useState('');
  const [testProviderKey, setTestProviderKey] = useState<'vllm' | 'openrouter'>('openrouter');
  const [testModelKey, setTestModelKey] = useState('openai/gpt-4.1-mini');
  const [testPresetId, setTestPresetId] = useState<string>('none');
  const [testPromptTemplate, setTestPromptTemplate] = useState('');
  const [testObjectiveSafetyScopeOverride, setTestObjectiveSafetyScopeOverride] = useState<'inherit' | 'image_only' | 'image_and_text_context'>('inherit');
  const [testContextScopeOverride, setTestContextScopeOverride] = useState<'inherit' | 'image_only' | 'image_and_text_context'>('inherit');
  const [testReplyScopeOverride, setTestReplyScopeOverride] = useState<'inherit' | 'image_only' | 'image_and_text_context'>('inherit');
  const [testNormalizedTextContextModeOverride, setTestNormalizedTextContextModeOverride] = useState<'inherit' | 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary'>('inherit');
  const [testFiles, setTestFiles] = useState<File[]>([]);
  const [latestTestRun, setLatestTestRun] = useState<MediaReplyPromptTestRun | null>(null);

  const [historyEventId, setHistoryEventId] = useState('');
  const [historyProviderKey, setHistoryProviderKey] = useState<string>('all');
  const [historyStatus, setHistoryStatus] = useState<string>('all');
  const [selectedHistoryId, setSelectedHistoryId] = useState<number | null>(null);
  const [realHistoryEventId, setRealHistoryEventId] = useState<string>('all');
  const [realHistoryProviderKey, setRealHistoryProviderKey] = useState<string>('all');
  const [realHistoryStatus, setRealHistoryStatus] = useState<string>('all');
  const [realHistoryModelKey, setRealHistoryModelKey] = useState('');
  const [realHistoryPresetName, setRealHistoryPresetName] = useState('');
  const [realHistorySenderQuery, setRealHistorySenderQuery] = useState('');
  const [realHistoryDateFrom, setRealHistoryDateFrom] = useState('');
  const [realHistoryDateTo, setRealHistoryDateTo] = useState('');
  const [selectedRealHistoryId, setSelectedRealHistoryId] = useState<number | null>(null);

  const configurationQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'configuracao'],
    queryFn: () => aiMediaRepliesService.getConfiguration(),
    enabled: canManageGlobalAi,
  });
  const safetyConfigurationQuery = useQuery({
    queryKey: ['ia', 'moderacao-de-midia', 'safety-global'],
    queryFn: () => aiMediaRepliesService.getSafetyConfiguration(),
    enabled: canManageGlobalAi,
  });
  const categoriesQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'categorias'],
    queryFn: () => aiMediaRepliesService.listCategories(),
  });
  const presetsQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'presets'],
    queryFn: () => aiMediaRepliesService.listPresets(),
  });
  const eventOptionsQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'eventos'],
    queryFn: () => aiMediaRepliesService.listEventOptions(),
  });
  const historyQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'testes', historyEventId, historyProviderKey, historyStatus],
    queryFn: () => aiMediaRepliesService.listPromptTests({
      event_id: historyEventId.trim() !== '' ? Number(historyEventId) : null,
      provider_key: historyProviderKey !== 'all' ? historyProviderKey : null,
      status: historyStatus !== 'all' ? historyStatus : null,
      per_page: 15,
    }),
  });
  const realHistoryQuery = useQuery({
    queryKey: [
      'ia',
      'respostas-de-midia',
      'historico-eventos',
      realHistoryEventId,
      realHistoryProviderKey,
      realHistoryStatus,
      realHistoryModelKey,
      realHistoryPresetName,
      realHistorySenderQuery,
      realHistoryDateFrom,
      realHistoryDateTo,
    ],
    queryFn: () => aiMediaRepliesService.listEventHistory({
      event_id: realHistoryEventId !== 'all' ? Number(realHistoryEventId) : null,
      provider_key: realHistoryProviderKey !== 'all' ? realHistoryProviderKey : null,
      model_key: realHistoryModelKey.trim() !== '' ? realHistoryModelKey.trim() : null,
      status: realHistoryStatus !== 'all' ? realHistoryStatus : null,
      preset_name: realHistoryPresetName.trim() !== '' ? realHistoryPresetName.trim() : null,
      sender_query: realHistorySenderQuery.trim() !== '' ? realHistorySenderQuery.trim() : null,
      date_from: realHistoryDateFrom.trim() !== '' ? realHistoryDateFrom : null,
      date_to: realHistoryDateTo.trim() !== '' ? realHistoryDateTo : null,
      per_page: 15,
    }),
  });
  const historyDetailQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'testes', 'detalhe', selectedHistoryId],
    queryFn: () => aiMediaRepliesService.getPromptTest(selectedHistoryId as number),
    enabled: selectedHistoryId !== null,
  });
  const realHistoryDetailQuery = useQuery({
    queryKey: ['ia', 'respostas-de-midia', 'historico-eventos', 'detalhe', selectedRealHistoryId],
    queryFn: () => aiMediaRepliesService.getEventHistoryItem(selectedRealHistoryId as number),
    enabled: selectedRealHistoryId !== null,
  });

  useEffect(() => {
    setStandardInstruction(configurationQuery.data?.reply_text_prompt ?? '');
    setStandardFixedTemplates(templatesToTextarea(configurationQuery.data?.reply_text_fixed_templates ?? []));
    setStandardPresetId(configurationQuery.data?.reply_prompt_preset_id ? String(configurationQuery.data.reply_prompt_preset_id) : 'none');
    setAiReplyLimitEnabled(configurationQuery.data?.reply_ai_rate_limit_enabled ?? false);
    setAiReplyLimitMaxMessages(String(configurationQuery.data?.reply_ai_rate_limit_max_messages ?? 10));
    setAiReplyLimitWindowMinutes(String(configurationQuery.data?.reply_ai_rate_limit_window_minutes ?? 10));
    setContextEnabled(configurationQuery.data?.enabled ?? false);
    setContextProviderKey(configurationQuery.data?.provider_key === 'noop'
      ? 'noop'
      : configurationQuery.data?.provider_key === 'openrouter'
        ? 'openrouter'
        : 'vllm');
    setContextModelKey(configurationQuery.data?.model_key ?? '');
    setContextMode(configurationQuery.data?.mode === 'gate' ? 'gate' : 'enrich_only');
    setContextScope(configurationQuery.data?.context_scope === 'image_only' ? 'image_only' : 'image_and_text_context');
    setReplyScope(configurationQuery.data?.reply_scope === 'image_only' ? 'image_only' : 'image_and_text_context');
    setNormalizedTextContextMode((configurationQuery.data?.normalized_text_context_mode as typeof normalizedTextContextMode) ?? 'body_plus_caption');
    setContextRequireJsonOutput(configurationQuery.data?.require_json_output ?? true);
    setContextPromptVersion(configurationQuery.data?.prompt_version ?? 'contextual-v2');
    setContextResponseSchemaVersion(configurationQuery.data?.response_schema_version ?? 'contextual-v2');
    setContextTimeoutMs(String(configurationQuery.data?.timeout_ms ?? 12000));
    setContextFallbackMode(configurationQuery.data?.fallback_mode === 'skip' ? 'skip' : 'review');
    setContextPresetKey(configurationQuery.data?.contextual_policy_preset_key ?? 'homologacao_livre');
    setAllowAlcohol(configurationQuery.data?.allow_alcohol ?? true);
    setAllowTobacco(configurationQuery.data?.allow_tobacco ?? true);
    setRequiredPeopleContext(configurationQuery.data?.required_people_context === 'required' ? 'required' : 'optional');
    setBlockedTermsText(listToTextarea(configurationQuery.data?.blocked_terms ?? []));
    setAllowedExceptionsText(listToTextarea(configurationQuery.data?.allowed_exceptions ?? []));
    setFreeformInstruction(configurationQuery.data?.freeform_instruction ?? '');
  }, [
    configurationQuery.data?.allow_alcohol,
    configurationQuery.data?.allow_tobacco,
    configurationQuery.data?.allowed_exceptions,
    configurationQuery.data?.blocked_terms,
    configurationQuery.data?.context_scope,
    configurationQuery.data?.contextual_policy_preset_key,
    configurationQuery.data?.enabled,
    configurationQuery.data?.fallback_mode,
    configurationQuery.data?.freeform_instruction,
    configurationQuery.data?.mode,
    configurationQuery.data?.model_key,
    configurationQuery.data?.normalized_text_context_mode,
    configurationQuery.data?.prompt_version,
    configurationQuery.data?.provider_key,
    configurationQuery.data?.reply_text_prompt,
    configurationQuery.data?.reply_text_fixed_templates,
    configurationQuery.data?.reply_prompt_preset_id,
    configurationQuery.data?.reply_ai_rate_limit_enabled,
    configurationQuery.data?.reply_ai_rate_limit_max_messages,
    configurationQuery.data?.reply_ai_rate_limit_window_minutes,
    configurationQuery.data?.reply_scope,
    configurationQuery.data?.require_json_output,
    configurationQuery.data?.required_people_context,
    configurationQuery.data?.response_schema_version,
    configurationQuery.data?.timeout_ms,
  ]);

  useEffect(() => {
    setSafetyEnabled(safetyConfigurationQuery.data?.enabled ?? false);
    setSafetyProviderKey(safetyConfigurationQuery.data?.provider_key === 'noop' ? 'noop' : 'openai');
    setSafetyMode(safetyConfigurationQuery.data?.mode === 'observe_only' ? 'observe_only' : 'enforced');
    setSafetyThresholdVersion(safetyConfigurationQuery.data?.threshold_version ?? 'foundation-v1');
    setSafetyFallbackMode(safetyConfigurationQuery.data?.fallback_mode === 'block' ? 'block' : 'review');
    setSafetyScope(safetyConfigurationQuery.data?.analysis_scope === 'image_only' ? 'image_only' : 'image_and_text_context');
    setSafetyNormalizedTextContextMode((safetyConfigurationQuery.data?.normalized_text_context_mode as typeof safetyNormalizedTextContextMode) ?? 'body_plus_caption');
    setSafetyReviewNudity(String(safetyConfigurationQuery.data?.review_thresholds.nudity ?? 0.65));
    setSafetyReviewViolence(String(safetyConfigurationQuery.data?.review_thresholds.violence ?? 0.65));
    setSafetyReviewSelfHarm(String(safetyConfigurationQuery.data?.review_thresholds.self_harm ?? 0.65));
    setSafetyBlockNudity(String(safetyConfigurationQuery.data?.hard_block_thresholds.nudity ?? 0.9));
    setSafetyBlockViolence(String(safetyConfigurationQuery.data?.hard_block_thresholds.violence ?? 0.9));
    setSafetyBlockSelfHarm(String(safetyConfigurationQuery.data?.hard_block_thresholds.self_harm ?? 0.9));
  }, [
    safetyConfigurationQuery.data?.analysis_scope,
    safetyConfigurationQuery.data?.enabled,
    safetyConfigurationQuery.data?.fallback_mode,
    safetyConfigurationQuery.data?.hard_block_thresholds.nudity,
    safetyConfigurationQuery.data?.hard_block_thresholds.self_harm,
    safetyConfigurationQuery.data?.hard_block_thresholds.violence,
    safetyConfigurationQuery.data?.mode,
    safetyConfigurationQuery.data?.normalized_text_context_mode,
    safetyConfigurationQuery.data?.provider_key,
    safetyConfigurationQuery.data?.review_thresholds.nudity,
    safetyConfigurationQuery.data?.review_thresholds.self_harm,
    safetyConfigurationQuery.data?.review_thresholds.violence,
    safetyConfigurationQuery.data?.threshold_version,
  ]);

  useEffect(() => {
    if (selectedCategoryId === null) {
      setCategoryForm(emptyCategoryForm());

      return;
    }

    const category = categoriesQuery.data?.find((item) => item.id === selectedCategoryId);

    if (!category) {
      setSelectedCategoryId(null);
      setCategoryForm(emptyCategoryForm());

      return;
    }

    setCategoryForm({
      slug: category.slug,
      name: category.name,
      sort_order: category.sort_order,
      is_active: category.is_active,
    });
  }, [categoriesQuery.data, selectedCategoryId]);

  useEffect(() => {
    if (selectedPresetId === null) {
      setPresetForm(emptyPresetForm());

      return;
    }

    const preset = presetsQuery.data?.find((item) => item.id === selectedPresetId);

    if (!preset) {
      setSelectedPresetId(null);
      setPresetForm(emptyPresetForm());

      return;
    }

    setPresetForm({
      slug: preset.slug,
      name: preset.name,
      category: preset.category ?? '',
      description: preset.description ?? '',
      prompt_template: preset.prompt_template,
      sort_order: preset.sort_order,
      is_active: preset.is_active,
    });
  }, [presetsQuery.data, selectedPresetId]);

  useEffect(() => {
    if (testPresetId === 'none') {
      return;
    }

    const preset = presetsQuery.data?.find((item) => String(item.id) === testPresetId);

    if (!preset) {
      return;
    }

    setTestPromptTemplate(preset.prompt_template);
  }, [presetsQuery.data, testPresetId]);

  useEffect(() => {
    const firstId = historyQuery.data?.data?.[0]?.id ?? null;

    setSelectedHistoryId((current) => {
      if (current && historyQuery.data?.data.some((item) => item.id === current)) {
        return current;
      }

      return firstId;
    });
  }, [historyQuery.data]);

  useEffect(() => {
    const firstId = realHistoryQuery.data?.data?.[0]?.id ?? null;

    setSelectedRealHistoryId((current) => {
      if (current && realHistoryQuery.data?.data.some((item) => item.id === current)) {
        return current;
      }

      return firstId;
    });
  }, [realHistoryQuery.data]);

  const updateConfigurationMutation = useMutation({
    mutationFn: () => aiMediaRepliesService.updateConfiguration({
      enabled: contextEnabled,
      provider_key: contextProviderKey,
      model_key: contextModelKey.trim(),
      mode: contextMode,
      prompt_version: contextPromptVersion.trim() || null,
      response_schema_version: contextResponseSchemaVersion.trim() || null,
      timeout_ms: Number(contextTimeoutMs || '12000'),
      fallback_mode: contextFallbackMode,
      context_scope: contextScope,
      reply_scope: replyScope,
      normalized_text_context_mode: normalizedTextContextMode,
      require_json_output: contextRequireJsonOutput,
      contextual_policy_preset_key: contextPresetKey.trim() || null,
      policy_version: 'contextual-policy-v1',
      allow_alcohol: allowAlcohol,
      allow_tobacco: allowTobacco,
      required_people_context: requiredPeopleContext,
      blocked_terms: textareaToList(blockedTermsText),
      allowed_exceptions: textareaToList(allowedExceptionsText),
      freeform_instruction: freeformInstruction.trim() || null,
      reply_text_prompt: standardInstruction.trim(),
      reply_text_fixed_templates: textareaToTemplates(standardFixedTemplates),
      reply_prompt_preset_id: standardPresetId !== 'none' ? Number(standardPresetId) : null,
      reply_ai_rate_limit_enabled: aiReplyLimitEnabled,
      reply_ai_rate_limit_max_messages: Number(aiReplyLimitMaxMessages || '10'),
      reply_ai_rate_limit_window_minutes: Number(aiReplyLimitWindowMinutes || '10'),
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'configuracao'] });
      toast({
        title: 'Configuracao atualizada',
        description: 'A instrucao padrao e os textos fixos foram salvos.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar configuracao',
        description: 'Nao foi possivel atualizar a configuracao da area de IA.',
        variant: 'destructive',
      });
    },
  });

  const updateSafetyConfigurationMutation = useMutation({
    mutationFn: () => aiMediaRepliesService.updateSafetyConfiguration({
      enabled: safetyEnabled,
      provider_key: safetyProviderKey,
      mode: safetyMode,
      threshold_version: safetyThresholdVersion.trim() || null,
      fallback_mode: safetyFallbackMode,
      analysis_scope: safetyScope,
      normalized_text_context_mode: safetyNormalizedTextContextMode,
      hard_block_thresholds: {
        nudity: Number(safetyBlockNudity || '0.9'),
        violence: Number(safetyBlockViolence || '0.9'),
        self_harm: Number(safetyBlockSelfHarm || '0.9'),
      },
      review_thresholds: {
        nudity: Number(safetyReviewNudity || '0.65'),
        violence: Number(safetyReviewViolence || '0.65'),
        self_harm: Number(safetyReviewSelfHarm || '0.65'),
      },
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['ia', 'moderacao-de-midia', 'safety-global'] });
      toast({
        title: 'Safety objetiva atualizada',
        description: 'A politica global de safety foi salva.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar safety objetiva',
        description: 'Nao foi possivel atualizar a politica global de safety.',
        variant: 'destructive',
      });
    },
  });

  const saveCategoryMutation = useMutation({
    mutationFn: () => {
      if (selectedCategoryId === null) {
        return aiMediaRepliesService.createCategory(categoryForm);
      }

      return aiMediaRepliesService.updateCategory(selectedCategoryId, categoryForm);
    },
    onSuccess: async (category) => {
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'categorias'] });
      setSelectedCategoryId(category.id);
      toast({
        title: selectedCategoryId === null ? 'Categoria criada' : 'Categoria atualizada',
        description: 'A categoria do catalogo foi salva.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar categoria',
        description: 'Nao foi possivel atualizar o catalogo de categorias.',
        variant: 'destructive',
      });
    },
  });

  const deleteCategoryMutation = useMutation({
    mutationFn: (categoryId: number) => aiMediaRepliesService.deleteCategory(categoryId),
    onSuccess: async (_, categoryId) => {
      const removedCategory = categoriesQuery.data?.find((item) => item.id === categoryId);

      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'categorias'] });
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'presets'] });

      if (removedCategory && presetForm.category === removedCategory.slug) {
        setPresetForm((current) => ({ ...current, category: '' }));
      }

      setSelectedCategoryId(null);
      setCategoryForm(emptyCategoryForm());
      toast({
        title: 'Categoria removida',
        description: 'A categoria foi removida do catalogo.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao remover categoria',
        description: 'Nao foi possivel remover a categoria selecionada.',
        variant: 'destructive',
      });
    },
  });

  const savePresetMutation = useMutation({
    mutationFn: () => {
      if (selectedPresetId === null) {
        return aiMediaRepliesService.createPreset(presetForm);
      }

      return aiMediaRepliesService.updatePreset(selectedPresetId, presetForm);
    },
    onSuccess: async (preset) => {
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'presets'] });
      setSelectedPresetId(preset.id);
      toast({
        title: selectedPresetId === null ? 'Preset criado' : 'Preset atualizado',
        description: 'O catalogo de estilos de resposta foi salvo.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao salvar preset',
        description: 'Nao foi possivel atualizar o catalogo de presets.',
        variant: 'destructive',
      });
    },
  });

  const deletePresetMutation = useMutation({
    mutationFn: (presetId: number) => aiMediaRepliesService.deletePreset(presetId),
    onSuccess: async (_, presetId) => {
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'presets'] });

      if (String(presetId) === standardPresetId) {
        setStandardPresetId('none');
      }

      if (String(presetId) === testPresetId) {
        setTestPresetId('none');
      }

      setSelectedPresetId(null);
      setPresetForm(emptyPresetForm());
      toast({
        title: 'Preset removido',
        description: 'O preset foi removido do catalogo.',
      });
    },
    onError: () => {
      toast({
        title: 'Falha ao remover preset',
        description: 'Nao foi possivel remover o preset selecionado.',
        variant: 'destructive',
      });
    },
  });

  const runPromptTestMutation = useMutation({
    mutationFn: () => aiMediaRepliesService.runPromptTest({
      event_id: testEventId.trim() !== '' ? Number(testEventId) : null,
      provider_key: testProviderKey,
      model_key: testModelKey.trim(),
      prompt_template: testPromptTemplate.trim() || null,
      preset_id: testPresetId !== 'none' ? Number(testPresetId) : null,
      objective_safety_scope_override: testObjectiveSafetyScopeOverride !== 'inherit'
        ? testObjectiveSafetyScopeOverride
        : null,
      context_scope_override: testContextScopeOverride !== 'inherit'
        ? testContextScopeOverride
        : null,
      reply_scope_override: testReplyScopeOverride !== 'inherit'
        ? testReplyScopeOverride
        : null,
      normalized_text_context_mode_override: testNormalizedTextContextModeOverride !== 'inherit'
        ? testNormalizedTextContextModeOverride
        : null,
      images: testFiles,
    }),
    onSuccess: async (result) => {
      setLatestTestRun(result);
      await queryClient.invalidateQueries({ queryKey: ['ia', 'respostas-de-midia', 'testes'] });
      setSelectedHistoryId(result.id);
      toast({
        title: 'Teste executado',
        description: 'A resposta da IA foi gerada e armazenada no historico.',
      });
    },
    onError: (error: Error) => {
      const message = error instanceof ApiError && error.isValidation
        ? error.fieldError('images') ?? error.message
        : error.message;

      toast({
        title: 'Falha ao executar teste',
        description: message,
        variant: 'destructive',
      });
    },
  });

  const categories = categoriesQuery.data ?? [];
  const presets = presetsQuery.data ?? [];
  const eventOptions = eventOptionsQuery.data ?? [];
  const historyItems = historyQuery.data?.data ?? [];
  const realHistoryItems = realHistoryQuery.data?.data ?? [];
  const activeHistoryDetail = latestTestRun && latestTestRun.id === selectedHistoryId
    ? latestTestRun
    : historyDetailQuery.data ?? null;
  const activeRealHistoryDetail = realHistoryDetailQuery.data ?? null;
  const configurationLoading = canManageGlobalAi && configurationQuery.isLoading;
  const safetyConfigurationLoading = canManageGlobalAi && safetyConfigurationQuery.isLoading;
  const historyFiltersSummary = useMemo(() => ({
    event_id: historyEventId.trim() !== '' ? Number(historyEventId) : null,
    provider_key: historyProviderKey,
    status: historyStatus,
  }), [historyEventId, historyProviderKey, historyStatus]);
  const filteredPresets = useMemo(() => {
    const normalizedSearch = catalogPresetSearch.trim().toLowerCase();

    return presets.filter((preset) => {
      const matchesCategory = catalogCategoryFilter === 'all' || preset.category === catalogCategoryFilter;
      const matchesSearch = normalizedSearch === ''
        || preset.name.toLowerCase().includes(normalizedSearch)
        || preset.prompt_template.toLowerCase().includes(normalizedSearch);

      return matchesCategory && matchesSearch;
    });
  }, [catalogCategoryFilter, catalogPresetSearch, presets]);

  return (
    <motion.div initial={false} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Moderação de mídia"
        description="Centralize safety objetiva, bloqueio contextual, laboratório e histórico técnico da IA em uma única área."
      />

      <Tabs defaultValue="visao-geral">
        <TabsList className="flex-wrap bg-muted/50">
          <TabsTrigger value="visao-geral">Visão geral</TabsTrigger>
          <TabsTrigger value="safety">Safety objetiva</TabsTrigger>
          <TabsTrigger value="configuracao">Contexto do evento</TabsTrigger>
          <TabsTrigger value="teste">Laboratório</TabsTrigger>
          <TabsTrigger value="catalogo">Catálogo</TabsTrigger>
          <TabsTrigger value="historico">Historico</TabsTrigger>
        </TabsList>

        <TabsContent value="visao-geral" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-3">
            <div className="glass space-y-3 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Safety objetiva agora</h3>
                <p className="text-sm text-muted-foreground">
                  Gate objetivo por categoria nativa do provider, sem misturar regra contextual.
                </p>
              </div>
              {safetyConfigurationLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando safety...
                </div>
              ) : (
                <div className="space-y-2 text-sm text-muted-foreground">
                  <div>Estado: <span className="font-medium text-foreground">{safetyEnabled ? 'Ativo' : 'Desligado'}</span></div>
                  <div>Modo: <span className="font-medium text-foreground">{safetyMode}</span></div>
                  <div>Escopo: <span className="font-medium text-foreground">{scopeLabel(safetyScope)}</span></div>
                  <div>Fallback: <span className="font-medium text-foreground">{safetyFallbackMode}</span></div>
                </div>
              )}
            </div>

            <div className="glass space-y-3 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Bloqueio contextual agora</h3>
                <p className="text-sm text-muted-foreground">
                  Política estruturada do evento resolvida no backend antes do VLM.
                </p>
              </div>
              {configurationLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando contexto...
                </div>
              ) : (
                <div className="space-y-2 text-sm text-muted-foreground">
                  <div>Estado: <span className="font-medium text-foreground">{contextEnabled ? 'Ativo' : 'Desligado'}</span></div>
                  <div>Modo: <span className="font-medium text-foreground">{contextMode}</span></div>
                  <div>Escopo do gate: <span className="font-medium text-foreground">{scopeLabel(contextScope)}</span></div>
                  <div>Escopo da resposta: <span className="font-medium text-foreground">{scopeLabel(replyScope)}</span></div>
                  <div>Preset: <span className="font-medium text-foreground">{contextPresetKey || 'Nao definido'}</span></div>
                </div>
              )}
            </div>

            <div className="glass space-y-3 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Política efetiva agora</h3>
                <p className="text-sm text-muted-foreground">
                  Resumo rápido para operação leiga, antes de abrir configuração avançada.
                </p>
              </div>
              <div className="space-y-2 text-sm text-muted-foreground">
                <div>Álcool: <span className="font-medium text-foreground">{allowAlcohol ? 'Permitido' : 'Bloqueado'}</span></div>
                <div>Tabaco: <span className="font-medium text-foreground">{allowTobacco ? 'Permitido' : 'Bloqueado'}</span></div>
                <div>Pessoas na imagem: <span className="font-medium text-foreground">{requiredPeopleContext === 'required' ? 'Obrigatórias' : 'Opcionais'}</span></div>
                <div>Bloqueios adicionais: <span className="font-medium text-foreground">{textareaToList(blockedTermsText).length}</span></div>
                <div>Exceções permitidas: <span className="font-medium text-foreground">{textareaToList(allowedExceptionsText).length}</span></div>
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="safety" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Safety objetiva global</h3>
                <p className="text-sm text-muted-foreground">
                  Essa camada usa categorias nativas do provider e define block ou review antes do gate contextual.
                </p>
              </div>

              {!canManageGlobalAi ? (
                <div className="rounded-lg border border-border/60 bg-muted/30 p-4 text-sm text-muted-foreground">
                  Esta configuracao global esta disponivel apenas para super administradores e administradores da plataforma.
                </div>
              ) : safetyConfigurationLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando safety objetiva...
                </div>
              ) : safetyConfigurationQuery.isError ? (
                <div className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive">
                  Nao foi possivel carregar a politica global de safety.
                </div>
              ) : (
                <>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <Label htmlFor="safety-enabled">Safety ativa</Label>
                          <p className="mt-1 text-xs text-muted-foreground">Liga a moderação objetiva por imagem ou imagem + texto.</p>
                        </div>
                        <Switch
                          id="safety-enabled"
                          checked={safetyEnabled}
                          onCheckedChange={setSafetyEnabled}
                          disabled={updateSafetyConfigurationMutation.isPending}
                        />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="safety-mode">Modo do gate</Label>
                      <Select value={safetyMode} onValueChange={(value) => setSafetyMode(value as 'enforced' | 'observe_only')}>
                        <SelectTrigger id="safety-mode" aria-label="Modo do gate de safety">
                          <SelectValue placeholder="Selecione o modo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="enforced">Enforced</SelectItem>
                          <SelectItem value="observe_only">Observe only</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="space-y-2">
                      <Label htmlFor="safety-provider">Provider</Label>
                      <Select value={safetyProviderKey} onValueChange={(value) => setSafetyProviderKey(value as 'openai' | 'noop')}>
                        <SelectTrigger id="safety-provider" aria-label="Provider de safety">
                          <SelectValue placeholder="Selecione o provider" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="openai">OpenAI</SelectItem>
                          <SelectItem value="noop">Noop</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="safety-fallback">Fallback</Label>
                      <Select value={safetyFallbackMode} onValueChange={(value) => setSafetyFallbackMode(value as 'review' | 'block')}>
                        <SelectTrigger id="safety-fallback" aria-label="Fallback de safety">
                          <SelectValue placeholder="Selecione o fallback" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="review">Mandar para review</SelectItem>
                          <SelectItem value="block">Bloquear por falha</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="safety-scope">Escopo</Label>
                      <Select value={safetyScope} onValueChange={(value) => setSafetyScope(value as 'image_only' | 'image_and_text_context')}>
                        <SelectTrigger id="safety-scope" aria-label="Escopo da safety">
                          <SelectValue placeholder="Selecione o escopo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="image_only">Somente imagem</SelectItem>
                          <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="safety-threshold-version">Versao dos thresholds</Label>
                      <Input
                        id="safety-threshold-version"
                        value={safetyThresholdVersion}
                        onChange={(event) => setSafetyThresholdVersion(event.target.value)}
                        disabled={updateSafetyConfigurationMutation.isPending}
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="safety-normalized-text-context-mode">Modo do texto normalizado</Label>
                    <Select
                      value={safetyNormalizedTextContextMode}
                      onValueChange={(value) => setSafetyNormalizedTextContextMode(value as 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary')}
                    >
                      <SelectTrigger id="safety-normalized-text-context-mode" aria-label="Modo do texto normalizado da safety">
                        <SelectValue placeholder="Selecione o modo" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">Nenhum texto</SelectItem>
                        <SelectItem value="body_only">Somente body text</SelectItem>
                        <SelectItem value="caption_only">Somente legenda</SelectItem>
                        <SelectItem value="body_plus_caption">Body + legenda</SelectItem>
                        <SelectItem value="operator_summary">Resumo do operador</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="font-medium">Thresholds de review</div>
                      <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                          <Label htmlFor="safety-review-nudity">Nudez</Label>
                          <Input id="safety-review-nudity" value={safetyReviewNudity} onChange={(event) => setSafetyReviewNudity(event.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="safety-review-violence">Violência</Label>
                          <Input id="safety-review-violence" value={safetyReviewViolence} onChange={(event) => setSafetyReviewViolence(event.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="safety-review-self-harm">Autoagressão</Label>
                          <Input id="safety-review-self-harm" value={safetyReviewSelfHarm} onChange={(event) => setSafetyReviewSelfHarm(event.target.value)} />
                        </div>
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="font-medium">Thresholds de block</div>
                      <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                          <Label htmlFor="safety-block-nudity">Nudez</Label>
                          <Input id="safety-block-nudity" value={safetyBlockNudity} onChange={(event) => setSafetyBlockNudity(event.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="safety-block-violence">Violência</Label>
                          <Input id="safety-block-violence" value={safetyBlockViolence} onChange={(event) => setSafetyBlockViolence(event.target.value)} />
                        </div>
                        <div className="space-y-2">
                          <Label htmlFor="safety-block-self-harm">Autoagressão</Label>
                          <Input id="safety-block-self-harm" value={safetyBlockSelfHarm} onChange={(event) => setSafetyBlockSelfHarm(event.target.value)} />
                        </div>
                      </div>
                    </div>
                  </div>

                  <Button type="button" onClick={() => updateSafetyConfigurationMutation.mutate()} disabled={updateSafetyConfigurationMutation.isPending}>
                    {updateSafetyConfigurationMutation.isPending
                      ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                      : <Save className="mr-1 h-4 w-4" />}
                    Salvar safety objetiva
                  </Button>
                </>
              )}
            </div>

            <div className="glass rounded-xl p-6">
              <div className="flex items-center gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                <h3 className="font-semibold">Leitura operacional</h3>
              </div>
              <div className="mt-4 space-y-3 text-sm text-muted-foreground">
                <p>Safety objetiva decide por categorias nativas como nudez, violência e autoagressão.</p>
                <p>Quando o modo estiver em <strong>observe_only</strong>, o resultado continua rastreado mas não derruba a publicação sozinho.</p>
                <p>O escopo define se a moderação objetiva considera apenas a imagem ou também o texto normalizado associado ao envio.</p>
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="configuracao" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1.2fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Contexto do evento e resposta</h3>
                <p className="text-sm text-muted-foreground">
                  Defina a política estruturada do gate contextual e a resposta automática herdada pelos eventos.
                </p>
              </div>

              {!canManageGlobalAi ? (
                <div className="rounded-lg border border-border/60 bg-muted/30 p-4 text-sm text-muted-foreground">
                  Esta configuracao global esta disponivel apenas para super administradores e administradores da plataforma.
                </div>
              ) : configurationLoading ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando configuracao padrao...
                </div>
              ) : configurationQuery.isError ? (
                <div className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive">
                  Nao foi possivel carregar a configuracao global da area de IA.
                </div>
              ) : (
                <>
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <Label htmlFor="context-enabled">Gate contextual ativo</Label>
                          <p className="mt-1 text-xs text-muted-foreground">Liga a avaliação semântica da imagem antes da publicação.</p>
                        </div>
                        <Switch
                          id="context-enabled"
                          checked={contextEnabled}
                          onCheckedChange={setContextEnabled}
                          disabled={updateConfigurationMutation.isPending}
                        />
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <Label htmlFor="context-require-json">JSON estruturado obrigatório</Label>
                          <p className="mt-1 text-xs text-muted-foreground">Mantém o parser estável com schema estrito do domínio.</p>
                        </div>
                        <Switch
                          id="context-require-json"
                          checked={contextRequireJsonOutput}
                          onCheckedChange={setContextRequireJsonOutput}
                          disabled={updateConfigurationMutation.isPending}
                        />
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="space-y-2">
                      <Label htmlFor="context-provider">Provider contextual</Label>
                      <Select value={contextProviderKey} onValueChange={(value) => setContextProviderKey(value as 'vllm' | 'openrouter' | 'noop')}>
                        <SelectTrigger id="context-provider" aria-label="Provider contextual">
                          <SelectValue placeholder="Selecione o provider" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="vllm">vLLM</SelectItem>
                          <SelectItem value="openrouter">OpenRouter</SelectItem>
                          <SelectItem value="noop">Noop</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="context-mode">Modo do gate contextual</Label>
                      <Select value={contextMode} onValueChange={(value) => setContextMode(value as 'enrich_only' | 'gate')}>
                        <SelectTrigger id="context-mode" aria-label="Modo do gate contextual">
                          <SelectValue placeholder="Selecione o modo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="enrich_only">Apenas enriquecer</SelectItem>
                          <SelectItem value="gate">Bloquear</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="context-scope">Escopo do contexto</Label>
                      <Select value={contextScope} onValueChange={(value) => setContextScope(value as 'image_only' | 'image_and_text_context')}>
                        <SelectTrigger id="context-scope" aria-label="Escopo do contexto">
                          <SelectValue placeholder="Selecione o escopo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="image_only">Somente imagem</SelectItem>
                          <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="reply-scope">Escopo da resposta</Label>
                      <Select value={replyScope} onValueChange={(value) => setReplyScope(value as 'image_only' | 'image_and_text_context')}>
                        <SelectTrigger id="reply-scope" aria-label="Escopo da resposta">
                          <SelectValue placeholder="Selecione o escopo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="image_only">Somente imagem</SelectItem>
                          <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div className="space-y-2">
                      <Label htmlFor="context-model-key">Modelo</Label>
                      <Input id="context-model-key" value={contextModelKey} onChange={(event) => setContextModelKey(event.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="context-prompt-version">Versao do prompt</Label>
                      <Input id="context-prompt-version" value={contextPromptVersion} onChange={(event) => setContextPromptVersion(event.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="context-response-schema-version">Versao do schema</Label>
                      <Input id="context-response-schema-version" value={contextResponseSchemaVersion} onChange={(event) => setContextResponseSchemaVersion(event.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="context-timeout-ms">Timeout (ms)</Label>
                      <Input id="context-timeout-ms" value={contextTimeoutMs} onChange={(event) => setContextTimeoutMs(event.target.value)} />
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                      <Label htmlFor="context-fallback-mode">Fallback contextual</Label>
                      <Select value={contextFallbackMode} onValueChange={(value) => setContextFallbackMode(value as 'review' | 'skip')}>
                        <SelectTrigger id="context-fallback-mode" aria-label="Fallback contextual">
                          <SelectValue placeholder="Selecione o fallback" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="review">Mandar para review</SelectItem>
                          <SelectItem value="skip">Ignorar enriquecimento</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="context-preset-key">Preset contextual</Label>
                      <Select value={contextPresetKey} onValueChange={setContextPresetKey}>
                        <SelectTrigger id="context-preset-key" aria-label="Preset contextual">
                          <SelectValue placeholder="Selecione o preset" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="casamento_equilibrado">Casamento equilibrado</SelectItem>
                          <SelectItem value="casamento_rigido">Casamento rígido</SelectItem>
                          <SelectItem value="formatura">Formatura</SelectItem>
                          <SelectItem value="corporativo_restrito">Corporativo restrito</SelectItem>
                          <SelectItem value="aniversario_infantil">Aniversário infantil</SelectItem>
                          <SelectItem value="homologacao_livre">Homologação livre</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="context-normalized-text-mode">Modo do texto normalizado</Label>
                      <Select
                        value={normalizedTextContextMode}
                        onValueChange={(value) => setNormalizedTextContextMode(value as 'none' | 'body_only' | 'caption_only' | 'body_plus_caption' | 'operator_summary')}
                      >
                        <SelectTrigger id="context-normalized-text-mode" aria-label="Modo do texto normalizado do contexto">
                          <SelectValue placeholder="Selecione o modo" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="none">Nenhum texto</SelectItem>
                          <SelectItem value="body_only">Somente body text</SelectItem>
                          <SelectItem value="caption_only">Somente legenda</SelectItem>
                          <SelectItem value="body_plus_caption">Body + legenda</SelectItem>
                          <SelectItem value="operator_summary">Resumo do operador</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <Label htmlFor="allow-alcohol">Permitir álcool</Label>
                          <p className="mt-1 text-xs text-muted-foreground">Exceções continuam sendo respeitadas pelo gate.</p>
                        </div>
                        <Switch id="allow-alcohol" checked={allowAlcohol} onCheckedChange={setAllowAlcohol} />
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                      <div className="flex items-start justify-between gap-4">
                        <div>
                          <Label htmlFor="allow-tobacco">Permitir cigarro</Label>
                          <p className="mt-1 text-xs text-muted-foreground">Inclui cigarro, vape e tabaco no contexto do produto.</p>
                        </div>
                        <Switch id="allow-tobacco" checked={allowTobacco} onCheckedChange={setAllowTobacco} />
                      </div>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="required-people-context">Pessoas obrigatórias</Label>
                      <Select value={requiredPeopleContext} onValueChange={(value) => setRequiredPeopleContext(value as 'optional' | 'required')}>
                        <SelectTrigger id="required-people-context" aria-label="Presença de pessoas obrigatória">
                          <SelectValue placeholder="Selecione a regra" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="optional">Opcional</SelectItem>
                          <SelectItem value="required">Obrigatória</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="blocked-terms">Bloquear também</Label>
                      <Textarea
                        id="blocked-terms"
                        value={blockedTermsText}
                        onChange={(event) => setBlockedTermsText(event.target.value)}
                        rows={6}
                        placeholder={'mascaras\narmas cenicas'}
                      />
                      <p className="text-xs text-muted-foreground">Use um termo por linha para expandir o bloqueio contextual.</p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="allowed-exceptions">Exceções permitidas</Label>
                      <Textarea
                        id="allowed-exceptions"
                        value={allowedExceptionsText}
                        onChange={(event) => setAllowedExceptionsText(event.target.value)}
                        rows={6}
                        placeholder={'brinde com espumante\ncamera fotografica'}
                      />
                      <p className="text-xs text-muted-foreground">Use uma exceção por linha para aliviar falsos positivos previsíveis.</p>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="freeform-instruction">Instrução complementar do operador</Label>
                    <Textarea
                      id="freeform-instruction"
                      value={freeformInstruction}
                      onChange={(event) => setFreeformInstruction(event.target.value)}
                      rows={5}
                      placeholder="Opcional. Serve para ajuste fino depois do preset e dos toggles estruturados."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="ia-preset-padrao">Preset padrao</Label>
                    <Select value={standardPresetId} onValueChange={setStandardPresetId}>
                      <SelectTrigger id="ia-preset-padrao" aria-label="Preset padrao">
                        <SelectValue placeholder="Selecione um preset opcional" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">Sem preset padrao</SelectItem>
                        {presets.map((preset) => (
                          <SelectItem key={preset.id} value={String(preset.id)}>
                            {preset.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                      O preset padrao aplica um estilo base antes da instrucao padrao ou do texto do evento.
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="ia-instrucao-padrao">Instrucao padrao</Label>
                    <Textarea
                      id="ia-instrucao-padrao"
                      value={standardInstruction}
                      onChange={(event) => setStandardInstruction(event.target.value)}
                      rows={10}
                      disabled={updateConfigurationMutation.isPending}
                    />
                    <p className="text-xs text-muted-foreground">
                      A aplicacao resolve a variavel <code>{'{nome_do_evento}'}</code> no backend antes de enviar o prompt ao provider.
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="ia-textos-fixos-padrao">Textos fixos padrao</Label>
                    <Textarea
                      id="ia-textos-fixos-padrao"
                      value={standardFixedTemplates}
                      onChange={(event) => setStandardFixedTemplates(event.target.value)}
                      rows={6}
                      disabled={updateConfigurationMutation.isPending}
                      placeholder={'Memorias que fazem o coracao sorrir!\nMomento de risadas e lembrancas!'}
                    />
                    <p className="text-xs text-muted-foreground">
                      Use um texto por linha. Esses textos servem de fallback para eventos no modo de texto fixo aleatorio.
                    </p>
                  </div>

                  <div className="rounded-lg border border-border/60 bg-muted/20 p-4">
                    <div className="flex items-start justify-between gap-4">
                      <div className="space-y-1">
                        <Label htmlFor="ia-limite-resposta-ia">Limite de respostas por IA por participante</Label>
                        <p className="text-xs text-muted-foreground">
                          Quando ativo, a aplicacao responde apenas as primeiras N midias de um mesmo participante dentro da janela configurada.
                        </p>
                      </div>
                      <Switch
                        id="ia-limite-resposta-ia"
                        checked={aiReplyLimitEnabled}
                        onCheckedChange={setAiReplyLimitEnabled}
                        disabled={updateConfigurationMutation.isPending}
                      />
                    </div>

                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <Label htmlFor="ia-limite-quantidade">Quantidade maxima</Label>
                        <Input
                          id="ia-limite-quantidade"
                          type="number"
                          min={1}
                          max={100}
                          value={aiReplyLimitMaxMessages}
                          onChange={(event) => setAiReplyLimitMaxMessages(event.target.value)}
                          disabled={!aiReplyLimitEnabled || updateConfigurationMutation.isPending}
                        />
                      </div>

                      <div className="space-y-2">
                        <Label htmlFor="ia-limite-janela">Janela em minutos</Label>
                        <Input
                          id="ia-limite-janela"
                          type="number"
                          min={1}
                          max={1440}
                          value={aiReplyLimitWindowMinutes}
                          onChange={(event) => setAiReplyLimitWindowMinutes(event.target.value)}
                          disabled={!aiReplyLimitEnabled || updateConfigurationMutation.isPending}
                        />
                      </div>
                    </div>
                  </div>

                  <Button
                    type="button"
                    onClick={() => updateConfigurationMutation.mutate()}
                    disabled={updateConfigurationMutation.isPending}
                  >
                    {updateConfigurationMutation.isPending
                      ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                      : <Save className="mr-1 h-4 w-4" />}
                    Salvar configuracao
                  </Button>
                </>
              )}
            </div>

            <div className="space-y-6">
              <div className="glass rounded-xl p-6">
                <div className="flex items-center gap-2">
                  <Sparkles className="h-4 w-4 text-primary" />
                  <h3 className="font-semibold">Variaveis disponiveis</h3>
                </div>
                <div className="mt-4 rounded-lg border border-border/60 bg-muted/20 p-3 text-sm">
                  <div className="font-medium">{'{nome_do_evento}'}</div>
                  <div className="mt-1 text-muted-foreground">
                    Nome do evento resolvido no backend antes da chamada ao provider.
                  </div>
                </div>
              </div>

              <div className="glass rounded-xl p-6">
                <div className="flex items-center gap-2">
                  <FolderTree className="h-4 w-4 text-primary" />
                  <h3 className="font-semibold">Catalogo organizado</h3>
                </div>
                <p className="mt-3 text-sm text-muted-foreground">
                  O CRUD de categorias e presets agora fica centralizado na aba <strong>Catalogo</strong>, ao lado de
                  Historico.
                </p>
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="teste" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Laboratorio de homologacao</h3>
                <p className="text-sm text-muted-foreground">
                  Envie ate 3 imagens para validar safety, contexto, resposta automatica e elegibilidade final antes de promover a politica.
                </p>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="teste-evento-id">ID do evento</Label>
                  <Input
                    id="teste-evento-id"
                    value={testEventId}
                    onChange={(event) => setTestEventId(event.target.value)}
                    placeholder="Opcional"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="teste-preset">Preset</Label>
                  <Select value={testPresetId} onValueChange={setTestPresetId}>
                    <SelectTrigger id="teste-preset" aria-label="Preset do teste">
                      <SelectValue placeholder="Selecione um preset opcional" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">Sem preset</SelectItem>
                      {presets.map((preset) => (
                        <SelectItem key={preset.id} value={String(preset.id)}>
                          {preset.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="teste-provedor">Provedor</Label>
                  <Select value={testProviderKey} onValueChange={(value) => setTestProviderKey(value as 'vllm' | 'openrouter')}>
                    <SelectTrigger id="teste-provedor" aria-label="Provedor do teste">
                      <SelectValue placeholder="Selecione o provedor" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="openrouter">OpenRouter</SelectItem>
                      <SelectItem value="vllm">vLLM</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="teste-modelo">Modelo</Label>
                  <Input
                    id="teste-modelo"
                    value={testModelKey}
                    onChange={(event) => setTestModelKey(event.target.value)}
                    placeholder="openai/gpt-4.1-mini"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="teste-instrucao">Texto de instrucao</Label>
                <Textarea
                  id="teste-instrucao"
                  value={testPromptTemplate}
                  onChange={(event) => setTestPromptTemplate(event.target.value)}
                  rows={6}
                  placeholder="Opcional. Quando vazio, o sistema usa a combinacao herdada do evento ou da configuracao padrao."
                />
              </div>

              <div className="rounded-xl border border-border/60 bg-muted/20 p-4">
                <div className="space-y-1">
                  <div className="text-sm font-medium">Overrides controlados do laboratorio</div>
                  <p className="text-xs text-muted-foreground">
                    Estes ajustes valem apenas para o teste atual e nao alteram a politica produtiva.
                  </p>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="teste-safety-scope-override">Scope de safety objetiva</Label>
                    <Select value={testObjectiveSafetyScopeOverride} onValueChange={(value) => setTestObjectiveSafetyScopeOverride(value as typeof testObjectiveSafetyScopeOverride)}>
                      <SelectTrigger id="teste-safety-scope-override" aria-label="Scope de safety objetiva do laboratorio">
                        <SelectValue placeholder="Herdar" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="inherit">Herdar</SelectItem>
                        <SelectItem value="image_only">Somente imagem</SelectItem>
                        <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="teste-context-scope-override">Scope do gate contextual</Label>
                    <Select value={testContextScopeOverride} onValueChange={(value) => setTestContextScopeOverride(value as typeof testContextScopeOverride)}>
                      <SelectTrigger id="teste-context-scope-override" aria-label="Scope do gate contextual do laboratorio">
                        <SelectValue placeholder="Herdar" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="inherit">Herdar</SelectItem>
                        <SelectItem value="image_only">Somente imagem</SelectItem>
                        <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="teste-reply-scope-override">Scope da resposta</Label>
                    <Select value={testReplyScopeOverride} onValueChange={(value) => setTestReplyScopeOverride(value as typeof testReplyScopeOverride)}>
                      <SelectTrigger id="teste-reply-scope-override" aria-label="Scope da resposta do laboratorio">
                        <SelectValue placeholder="Herdar" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="inherit">Herdar</SelectItem>
                        <SelectItem value="image_only">Somente imagem</SelectItem>
                        <SelectItem value="image_and_text_context">Imagem + texto</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="teste-text-context-override">Contexto textual normalizado</Label>
                    <Select
                      value={testNormalizedTextContextModeOverride}
                      onValueChange={(value) => setTestNormalizedTextContextModeOverride(value as typeof testNormalizedTextContextModeOverride)}
                    >
                      <SelectTrigger id="teste-text-context-override" aria-label="Contexto textual normalizado do laboratorio">
                        <SelectValue placeholder="Herdar" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="inherit">Herdar</SelectItem>
                        <SelectItem value="none">Sem texto</SelectItem>
                        <SelectItem value="body_only">Somente corpo</SelectItem>
                        <SelectItem value="caption_only">Somente caption</SelectItem>
                        <SelectItem value="body_plus_caption">Corpo + caption</SelectItem>
                        <SelectItem value="operator_summary">Resumo do operador</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="teste-imagens">Imagens do teste</Label>
                <Input
                  id="teste-imagens"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  multiple
                  onChange={(event) => setTestFiles(Array.from(event.target.files ?? []).slice(0, 3))}
                />
                <p className="text-xs text-muted-foreground">
                  O teste aceita de 1 a 3 imagens, mas o pipeline produtivo continua congelado em 1 imagem por midia.
                </p>
                {testFiles.length > 0 ? (
                  <div className="rounded-lg border border-border/60 bg-muted/20 p-3 text-sm">
                    <div className="font-medium">Arquivos selecionados</div>
                    <ul className="mt-2 space-y-1 text-muted-foreground">
                      {testFiles.map((file) => (
                        <li key={`${file.name}-${file.size}`}>{file.name}</li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </div>

              <div className="flex flex-wrap gap-3">
                <Button
                  type="button"
                  onClick={() => runPromptTestMutation.mutate()}
                  disabled={runPromptTestMutation.isPending || testFiles.length === 0}
                >
                  {runPromptTestMutation.isPending
                    ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                    : <TestTube2 className="mr-1 h-4 w-4" />}
                  Executar teste
                </Button>

                <Button
                  type="button"
                  variant="outline"
                  onClick={() => runPromptTestMutation.mutate()}
                  disabled={runPromptTestMutation.isPending || latestTestRun === null || testFiles.length === 0}
                >
                  Repetir ultimo teste
                </Button>
              </div>
            </div>

            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Resultado do laboratorio</h3>
                <p className="text-sm text-muted-foreground">
                  O resultado combina resposta automatica, safety, gate contextual e projecao final do estado da midia.
                </p>
              </div>

              {runPromptTestMutation.isPending ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Homologando a politica...
                </div>
              ) : latestTestRun === null ? (
                <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                  Execute um teste para visualizar a politica efetiva, o resultado lado a lado e os dados tecnicos.
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={runStatusBadgeVariant(latestTestRun.status)}>
                      {runStatusLabel(latestTestRun.status)}
                    </Badge>
                    <Badge variant="outline">{latestTestRun.provider_key}</Badge>
                    <Badge variant="outline">{latestTestRun.model_key}</Badge>
                  </div>

                  <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                    <div className="font-medium">Politica efetiva agora</div>
                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                      <div>
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">Safety objetiva</div>
                        <div className="mt-1 text-foreground">
                          {scopeLabel((latestTestRun.policy_snapshot.safety?.analysis_scope as string | undefined) ?? null)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                          Origem: {policySourceLabel(latestTestRun.policy_sources.safety?.analysis_scope)}
                        </div>
                      </div>
                      <div>
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">Gate contextual</div>
                        <div className="mt-1 text-foreground">
                          {scopeLabel((latestTestRun.policy_snapshot.context?.context_scope as string | undefined) ?? null)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                          Origem: {policySourceLabel(latestTestRun.policy_sources.context?.context_scope)}
                        </div>
                      </div>
                      <div>
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">Scope da resposta</div>
                        <div className="mt-1 text-foreground">
                          {scopeLabel((latestTestRun.policy_snapshot.context?.reply_scope as string | undefined) ?? null)}
                        </div>
                        <div className="text-xs text-muted-foreground">
                          Origem: {policySourceLabel(latestTestRun.policy_sources.context?.reply_scope)}
                        </div>
                      </div>
                      <div>
                        <div className="text-xs uppercase tracking-wide text-muted-foreground">Texto normalizado</div>
                        <div className="mt-1 text-foreground">
                          {normalizedTextContextModeLabel((latestTestRun.policy_snapshot.context?.normalized_text_context_mode as string | undefined)
                            ?? (latestTestRun.policy_snapshot.safety?.normalized_text_context_mode as string | undefined)
                            ?? null)}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Safety objetiva</div>
                      <div className="mt-2 flex items-center gap-2">
                        <Badge variant={latestTestRun.final_summary.safety_is_blocking ? 'default' : 'outline'}>
                          {latestTestRun.final_summary.safety_is_blocking ? 'Bloqueante' : 'Observacao'}
                        </Badge>
                        <span className="text-muted-foreground">
                          {latestTestRun.safety_results[0]?.decision || 'Nao executado'}
                        </span>
                      </div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        <div>Pass: {latestTestRun.final_summary.safety_counts?.pass ?? 0}</div>
                        <div>Review: {latestTestRun.final_summary.safety_counts?.review ?? 0}</div>
                        <div>Block: {latestTestRun.final_summary.safety_counts?.block ?? 0}</div>
                        <div>Erro: {latestTestRun.final_summary.safety_counts?.error ?? 0}</div>
                      </div>
                      {latestTestRun.safety_results[0]?.error_message ? (
                        <div className="mt-2 text-xs text-destructive">{latestTestRun.safety_results[0].error_message}</div>
                      ) : null}
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Gate contextual</div>
                      <div className="mt-2 flex items-center gap-2">
                        <Badge variant={latestTestRun.final_summary.context_is_blocking ? 'default' : 'outline'}>
                          {latestTestRun.final_summary.context_is_blocking ? 'Bloqueante' : 'Enriquecimento'}
                        </Badge>
                        <span className="text-muted-foreground">
                          {latestTestRun.contextual_results[0]?.decision || 'Nao executado'}
                        </span>
                      </div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        <div>Approve: {latestTestRun.final_summary.context_counts?.approve ?? 0}</div>
                        <div>Review: {latestTestRun.final_summary.context_counts?.review ?? 0}</div>
                        <div>Reject: {latestTestRun.final_summary.context_counts?.reject ?? 0}</div>
                        <div>Erro: {latestTestRun.final_summary.context_counts?.error ?? 0}</div>
                      </div>
                      <div className="mt-2 text-xs text-muted-foreground">
                        {latestTestRun.contextual_results[0]?.reason || 'Sem motivo informado'}
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Resposta automatica</div>
                      <div className="mt-2 flex items-center gap-2">
                        <Badge variant={latestTestRun.final_summary.reply_status === 'success' ? 'default' : 'secondary'}>
                          {latestTestRun.final_summary.reply_status === 'success' ? 'Disponivel' : 'Falhou'}
                        </Badge>
                        <span className="text-muted-foreground">
                          {latestTestRun.response_text ? 'Texto gerado' : 'Sem texto'}
                        </span>
                      </div>
                      <div className="mt-2 text-foreground">{latestTestRun.response_text || 'Vazio'}</div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Elegibilidade final</div>
                      <div className="mt-2 flex flex-wrap items-center gap-2">
                        <Badge variant={effectiveStateBadgeVariant(latestTestRun.final_summary.final_effective_state)}>
                          {effectiveStateLabel(latestTestRun.final_summary.final_effective_state)}
                        </Badge>
                        <Badge variant="outline">
                          {publishEligibilityLabel(latestTestRun.final_summary.final_publish_eligibility)}
                        </Badge>
                      </div>
                      <div className="mt-2 text-muted-foreground">
                        {latestTestRun.final_summary.human_reason || 'Sem resumo operacional.'}
                      </div>
                      <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                        <div>Camadas bloqueantes: {(latestTestRun.final_summary.blocking_layers ?? []).join(', ') || 'nenhuma'}</div>
                        <div>Erros de avaliacao: {latestTestRun.final_summary.evaluation_errors_count ?? 0}</div>
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Prompt efetivo</div>
                      <pre className="mt-2 whitespace-pre-wrap break-words text-muted-foreground">{latestTestRun.prompt_resolved || 'Vazio'}</pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Dados tecnicos</div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        <div>Trace ID: {latestTestRun.trace_id}</div>
                        <div>Latencia: {latestTestRun.latency_ms ?? 0} ms</div>
                        <div>Imagens: {latestTestRun.images.length}</div>
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload enviado</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(latestTestRun.request_payload, null, 2)}
                      </pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload recebido</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(latestTestRun.response_payload ?? {}, null, 2)}
                      </pre>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </TabsContent>

        <TabsContent value="catalogo" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-[minmax(320px,380px)_minmax(0,1fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-semibold">Categorias</h3>
                  <p className="text-sm text-muted-foreground">
                    Organize os estilos por categorias reutilizaveis.
                  </p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    setSelectedCategoryId(null);
                    setCategoryForm(emptyCategoryForm());
                  }}
                >
                  <Plus className="mr-1 h-4 w-4" />
                  Nova categoria
                </Button>
              </div>

              <div className="space-y-3">
                {categoriesQuery.isLoading ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando categorias...
                  </div>
                ) : categories.length === 0 ? (
                  <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                    Nenhuma categoria cadastrada ainda.
                  </div>
                ) : (
                  categories.map((category) => (
                    <button
                      key={category.id}
                      type="button"
                      className={`w-full rounded-lg border p-3 text-left transition ${selectedCategoryId === category.id ? 'border-primary bg-primary/5' : 'border-border/60 bg-muted/20'}`}
                      onClick={() => setSelectedCategoryId(category.id)}
                    >
                      <div className="flex items-center justify-between gap-3">
                        <span className="font-medium">{category.name}</span>
                        <Badge variant={category.is_active ? 'default' : 'outline'}>
                          {category.is_active ? 'Ativa' : 'Inativa'}
                        </Badge>
                      </div>
                      <div className="mt-1 text-xs text-muted-foreground">
                        slug: {category.slug}
                      </div>
                    </button>
                  ))
                )}
              </div>

              <div className="grid gap-4">
                <div className="space-y-2">
                  <Label htmlFor="categoria-nome">Nome da categoria</Label>
                  <Input
                    id="categoria-nome"
                    value={categoryForm.name}
                    onChange={(event) => setCategoryForm((current) => ({ ...current, name: event.target.value }))}
                    disabled={!canManageGlobalAi || saveCategoryMutation.isPending}
                  />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="categoria-slug">Slug</Label>
                    <Input
                      id="categoria-slug"
                      value={categoryForm.slug ?? ''}
                      onChange={(event) => setCategoryForm((current) => ({ ...current, slug: event.target.value }))}
                      disabled={!canManageGlobalAi || saveCategoryMutation.isPending}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="categoria-ordem">Ordem</Label>
                    <Input
                      id="categoria-ordem"
                      type="number"
                      value={String(categoryForm.sort_order ?? 0)}
                      onChange={(event) => setCategoryForm((current) => ({ ...current, sort_order: Number(event.target.value || 0) }))}
                      disabled={!canManageGlobalAi || saveCategoryMutation.isPending}
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between gap-4 rounded-lg border border-border/60 bg-muted/20 p-3">
                  <div>
                    <div className="font-medium">Categoria ativa</div>
                    <div className="text-sm text-muted-foreground">
                      Categorias inativas nao aparecem para selecao em presets novos.
                    </div>
                  </div>
                  <Switch
                    aria-label="Categoria ativa"
                    checked={categoryForm.is_active}
                    onCheckedChange={(checked) => setCategoryForm((current) => ({ ...current, is_active: checked }))}
                    disabled={!canManageGlobalAi || saveCategoryMutation.isPending}
                  />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <Button
                    type="button"
                    onClick={() => saveCategoryMutation.mutate()}
                    disabled={!canManageGlobalAi || saveCategoryMutation.isPending}
                  >
                    {saveCategoryMutation.isPending
                      ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                      : <Save className="mr-1 h-4 w-4" />}
                    {selectedCategoryId === null ? 'Criar categoria' : 'Salvar categoria'}
                  </Button>

                  {selectedCategoryId !== null ? (
                    <Button
                      type="button"
                      variant="destructive"
                      onClick={() => deleteCategoryMutation.mutate(selectedCategoryId)}
                      disabled={!canManageGlobalAi || deleteCategoryMutation.isPending}
                    >
                      {deleteCategoryMutation.isPending
                        ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                        : <Trash2 className="mr-1 h-4 w-4" />}
                      Excluir categoria
                    </Button>
                  ) : null}
                </div>
              </div>
            </div>

            <div className="glass space-y-4 rounded-xl p-6">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <h3 className="font-semibold">Catalogo de presets</h3>
                  <p className="text-sm text-muted-foreground">
                    O preset e a instrucao-base do prompt. Ao selecionar um preset no teste, o texto de instrucao e preenchido automaticamente.
                  </p>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    setSelectedPresetId(null);
                    setPresetForm(emptyPresetForm());
                  }}
                >
                  <Plus className="mr-1 h-4 w-4" />
                  Novo preset
                </Button>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="catalogo-filtro-categoria">Filtrar por categoria</Label>
                  <Select value={catalogCategoryFilter} onValueChange={setCatalogCategoryFilter}>
                    <SelectTrigger id="catalogo-filtro-categoria" aria-label="Filtro de categoria do catalogo">
                      <SelectValue placeholder="Todas as categorias" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todas as categorias</SelectItem>
                      {categories.map((category) => (
                        <SelectItem key={category.id} value={category.slug}>
                          {category.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="catalogo-filtro-preset">Buscar por nome ou texto da instrucao</Label>
                  <Input
                    id="catalogo-filtro-preset"
                    value={catalogPresetSearch}
                    onChange={(event) => setCatalogPresetSearch(event.target.value)}
                    placeholder="Ex: romantico, corporativo, alegre"
                  />
                </div>
              </div>

              <div className="grid gap-3 md:grid-cols-2">
                {presetsQuery.isLoading ? (
                  <div className="col-span-full flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando presets...
                  </div>
                ) : filteredPresets.length === 0 ? (
                  <div className="col-span-full rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                    Nenhum preset encontrado com os filtros atuais.
                  </div>
                ) : (
                  filteredPresets.map((preset) => (
                    <button
                      key={preset.id}
                      type="button"
                      className={`rounded-lg border p-3 text-left transition ${selectedPresetId === preset.id ? 'border-primary bg-primary/5' : 'border-border/60 bg-muted/20'}`}
                      onClick={() => setSelectedPresetId(preset.id)}
                    >
                      <div className="flex items-center justify-between gap-3">
                        <span className="font-medium">{preset.name}</span>
                        <Badge variant={preset.is_active ? 'default' : 'outline'}>
                          {preset.is_active ? 'Ativo' : 'Inativo'}
                        </Badge>
                      </div>
                      <div className="mt-1 text-xs text-muted-foreground">
                        {preset.category_entry?.name ?? preset.category ?? 'Sem categoria'}
                      </div>
                    </button>
                  ))
                )}
              </div>

              <div className="grid gap-4">
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="preset-nome">Nome</Label>
                    <Input
                      id="preset-nome"
                      value={presetForm.name}
                      onChange={(event) => setPresetForm((current) => ({ ...current, name: event.target.value }))}
                      disabled={!canManageGlobalAi || savePresetMutation.isPending}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="preset-categoria">Categoria</Label>
                    <Select
                      value={presetForm.category && presetForm.category !== '' ? presetForm.category : 'none'}
                      onValueChange={(value) => setPresetForm((current) => ({ ...current, category: value === 'none' ? null : value }))}
                      disabled={!canManageGlobalAi || savePresetMutation.isPending}
                    >
                      <SelectTrigger id="preset-categoria" aria-label="Categoria do preset">
                        <SelectValue placeholder="Selecione uma categoria" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">Sem categoria</SelectItem>
                        {categories.filter((item) => item.is_active).map((category) => (
                          <SelectItem key={category.id} value={category.slug}>
                            {category.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="preset-slug">Slug</Label>
                    <Input
                      id="preset-slug"
                      value={presetForm.slug ?? ''}
                      onChange={(event) => setPresetForm((current) => ({ ...current, slug: event.target.value }))}
                      disabled={!canManageGlobalAi || savePresetMutation.isPending}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="preset-ordem">Ordem</Label>
                    <Input
                      id="preset-ordem"
                      type="number"
                      value={String(presetForm.sort_order ?? 0)}
                      onChange={(event) => setPresetForm((current) => ({ ...current, sort_order: Number(event.target.value || 0) }))}
                      disabled={!canManageGlobalAi || savePresetMutation.isPending}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="preset-descricao">Descricao</Label>
                  <Textarea
                    id="preset-descricao"
                    value={presetForm.description ?? ''}
                    onChange={(event) => setPresetForm((current) => ({ ...current, description: event.target.value }))}
                    rows={3}
                    disabled={!canManageGlobalAi || savePresetMutation.isPending}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="preset-template">Instrucao-base do preset</Label>
                  <Textarea
                    id="preset-template"
                    value={presetForm.prompt_template}
                    onChange={(event) => setPresetForm((current) => ({ ...current, prompt_template: event.target.value }))}
                    rows={6}
                    disabled={!canManageGlobalAi || savePresetMutation.isPending}
                  />
                </div>

                <div className="flex items-center justify-between gap-4 rounded-lg border border-border/60 bg-muted/20 p-3">
                  <div>
                    <div className="font-medium">Preset ativo</div>
                    <div className="text-sm text-muted-foreground">
                      Presets inativos nao aparecem para selecao no evento nem no teste do prompt.
                    </div>
                  </div>
                  <Switch
                    aria-label="Preset ativo"
                    checked={presetForm.is_active}
                    onCheckedChange={(checked) => setPresetForm((current) => ({ ...current, is_active: checked }))}
                    disabled={!canManageGlobalAi || savePresetMutation.isPending}
                  />
                </div>

                <div className="flex flex-wrap items-center gap-3">
                  <Button
                    type="button"
                    onClick={() => savePresetMutation.mutate()}
                    disabled={!canManageGlobalAi || savePresetMutation.isPending}
                  >
                    {savePresetMutation.isPending
                      ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                      : <Save className="mr-1 h-4 w-4" />}
                    {selectedPresetId === null ? 'Criar preset' : 'Salvar preset'}
                  </Button>

                  {selectedPresetId !== null ? (
                    <Button
                      type="button"
                      variant="destructive"
                      onClick={() => deletePresetMutation.mutate(selectedPresetId)}
                      disabled={!canManageGlobalAi || deletePresetMutation.isPending}
                    >
                      {deletePresetMutation.isPending
                        ? <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                        : <Trash2 className="mr-1 h-4 w-4" />}
                      Excluir preset
                    </Button>
                  ) : null}
                </div>
              </div>
            </div>
          </div>
        </TabsContent>

        <TabsContent value="historico" className="mt-6">
          <div className="grid gap-6 xl:grid-cols-[minmax(340px,420px)_minmax(0,1fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Historico de testes</h3>
                <p className="text-sm text-muted-foreground">
                  Filtros aplicados: evento {historyFiltersSummary.event_id ?? 'todos'}, provedor {historyFiltersSummary.provider_key}, status {historyFiltersSummary.status}.
                </p>
              </div>

              <div className="grid gap-3">
                <div className="space-y-2">
                  <Label htmlFor="historico-evento-id">ID do evento</Label>
                  <Input
                    id="historico-evento-id"
                    value={historyEventId}
                    onChange={(event) => setHistoryEventId(event.target.value)}
                    placeholder="Todos"
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="historico-provedor">Provedor</Label>
                  <Select value={historyProviderKey} onValueChange={setHistoryProviderKey}>
                    <SelectTrigger id="historico-provedor" aria-label="Filtro de provedor">
                      <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      <SelectItem value="openrouter">OpenRouter</SelectItem>
                      <SelectItem value="vllm">vLLM</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="historico-status">Status</Label>
                  <Select value={historyStatus} onValueChange={setHistoryStatus}>
                    <SelectTrigger id="historico-status" aria-label="Filtro de status">
                      <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      <SelectItem value="success">Sucesso</SelectItem>
                      <SelectItem value="failed">Falha</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-3">
                {historyQuery.isLoading ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando historico...
                  </div>
                ) : historyItems.length === 0 ? (
                  <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                    Nenhum teste encontrado com os filtros atuais.
                  </div>
                ) : (
                  historyItems.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      className={`w-full rounded-lg border p-3 text-left transition ${selectedHistoryId === item.id ? 'border-primary bg-primary/5' : 'border-border/60 bg-muted/20'}`}
                      onClick={() => setSelectedHistoryId(item.id)}
                    >
                      <div className="flex items-center justify-between gap-3">
                        <span className="font-medium">{item.response_text || 'Sem texto gerado'}</span>
                        <Badge variant={item.status === 'success' ? 'default' : 'destructive'}>
                          {item.status === 'success' ? 'Sucesso' : 'Falha'}
                        </Badge>
                      </div>
                      <div className="mt-1 text-xs text-muted-foreground">
                        {item.provider_key} • {item.model_key}
                      </div>
                    </button>
                  ))
                )}
              </div>
            </div>

            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Detalhe do teste</h3>
                <p className="text-sm text-muted-foreground">
                  Cada execucao fica rastreavel por trace ID, prompt efetivo, imagens e payload tecnico.
                </p>
              </div>

              {selectedHistoryId === null ? (
                <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                  Selecione um teste do historico para ver o detalhe completo.
                </div>
              ) : historyDetailQuery.isLoading && activeHistoryDetail === null ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando detalhe do teste...
                </div>
              ) : activeHistoryDetail === null ? (
                <div className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive">
                  Nao foi possivel carregar o detalhe deste teste.
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={activeHistoryDetail.status === 'success' ? 'default' : 'destructive'}>
                      {activeHistoryDetail.status === 'success' ? 'Sucesso' : 'Falha'}
                    </Badge>
                    <Badge variant="outline">{activeHistoryDetail.provider_key}</Badge>
                    <Badge variant="outline">{activeHistoryDetail.model_key}</Badge>
                    <Badge variant="outline">{activeHistoryDetail.trace_id}</Badge>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Prompt template</div>
                      <pre className="mt-2 whitespace-pre-wrap break-words text-muted-foreground">{activeHistoryDetail.prompt_template || 'Vazio'}</pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Prompt efetivo</div>
                      <pre className="mt-2 whitespace-pre-wrap break-words text-muted-foreground">{activeHistoryDetail.prompt_resolved || 'Vazio'}</pre>
                    </div>
                  </div>

                  <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                    <div className="font-medium">Imagens do teste</div>
                    <div className="mt-2 space-y-1 text-muted-foreground">
                      {activeHistoryDetail.images.map((image) => (
                        <div key={`${image.index}-${image.sha256}`}>
                          {image.original_name} • {image.mime_type} • {image.size_bytes} bytes
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="grid gap-4">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload enviado</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(activeHistoryDetail.request_payload, null, 2)}
                      </pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload recebido</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(activeHistoryDetail.response_payload ?? {}, null, 2)}
                      </pre>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(360px,460px)_minmax(0,1fr)]">
            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Historico de eventos reais</h3>
                <p className="text-sm text-muted-foreground">
                  Audite o comportamento da IA em producao real por evento, remetente, preset, modelo e periodo.
                </p>
              </div>

              <div className="grid gap-3">
                <div className="space-y-2">
                  <Label htmlFor="historico-real-evento">Evento</Label>
                  <Select value={realHistoryEventId} onValueChange={setRealHistoryEventId}>
                    <SelectTrigger id="historico-real-evento" aria-label="Filtro de evento do historico real">
                      <SelectValue placeholder="Todos os eventos" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos os eventos</SelectItem>
                      {eventOptions.map((eventOption) => (
                        <SelectItem key={eventOption.id} value={String(eventOption.id)}>
                          {eventOption.title}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="historico-real-provedor">Provedor</Label>
                    <Select value={realHistoryProviderKey} onValueChange={setRealHistoryProviderKey}>
                      <SelectTrigger id="historico-real-provedor" aria-label="Filtro de provedor do historico real">
                        <SelectValue placeholder="Todos" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="openrouter">OpenRouter</SelectItem>
                        <SelectItem value="vllm">vLLM</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="historico-real-status">Status</Label>
                    <Select value={realHistoryStatus} onValueChange={setRealHistoryStatus}>
                      <SelectTrigger id="historico-real-status" aria-label="Filtro de status do historico real">
                        <SelectValue placeholder="Todos" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="success">Sucesso</SelectItem>
                        <SelectItem value="failed">Falha</SelectItem>
                        <SelectItem value="completed">Concluido</SelectItem>
                        <SelectItem value="review">Revisao</SelectItem>
                        <SelectItem value="rejected">Rejeitado</SelectItem>
                        <SelectItem value="skipped">Ignorado</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="historico-real-modelo">Modelo</Label>
                    <Input
                      id="historico-real-modelo"
                      value={realHistoryModelKey}
                      onChange={(event) => setRealHistoryModelKey(event.target.value)}
                      placeholder="Ex: openai/gpt-4.1-mini"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="historico-real-preset">Nome do preset</Label>
                    <Input
                      id="historico-real-preset"
                      value={realHistoryPresetName}
                      onChange={(event) => setRealHistoryPresetName(event.target.value)}
                      placeholder="Ex: Casamento romantico"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="historico-real-remetente">Remetente</Label>
                  <Input
                    id="historico-real-remetente"
                    value={realHistorySenderQuery}
                    onChange={(event) => setRealHistorySenderQuery(event.target.value)}
                    placeholder="Telefone, nome ou identificador"
                  />
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="historico-real-data-inicial">Data inicial</Label>
                    <Input
                      id="historico-real-data-inicial"
                      type="date"
                      value={realHistoryDateFrom}
                      onChange={(event) => setRealHistoryDateFrom(event.target.value)}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="historico-real-data-final">Data final</Label>
                    <Input
                      id="historico-real-data-final"
                      type="date"
                      value={realHistoryDateTo}
                      onChange={(event) => setRealHistoryDateTo(event.target.value)}
                    />
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                {realHistoryQuery.isLoading ? (
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Carregando historico real...
                  </div>
                ) : realHistoryItems.length === 0 ? (
                  <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                    Nenhuma execucao real encontrada com os filtros atuais.
                  </div>
                ) : (
                  realHistoryItems.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      className={`w-full rounded-lg border p-3 text-left transition ${selectedRealHistoryId === item.id ? 'border-primary bg-primary/5' : 'border-border/60 bg-muted/20'}`}
                      onClick={() => setSelectedRealHistoryId(item.id)}
                    >
                      <div className="flex items-start gap-3">
                        {item.preview_url ? (
                          <img
                            src={item.preview_url}
                            alt={`Preview da midia ${item.id}`}
                            className="h-14 w-14 rounded-md border border-border/60 object-cover"
                          />
                        ) : (
                          <div className="flex h-14 w-14 items-center justify-center rounded-md border border-dashed border-border/60 text-[11px] text-muted-foreground">
                            Sem preview
                          </div>
                        )}

                        <div className="min-w-0 flex-1">
                          <div className="flex items-center justify-between gap-3">
                            <span className="truncate font-medium">{item.event_title || `Midia ${item.event_media_id}`}</span>
                            <Badge variant={effectiveStateBadgeVariant(item.effective_media_state)}>
                              {effectiveStateLabel(item.effective_media_state)}
                            </Badge>
                          </div>
                          <div className="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span>{item.source_label || item.source_type || 'Origem nao informada'}</span>
                            <span>{item.media_type || 'midia'}</span>
                            <span>{item.policy_label || 'Sem politica nomeada'}</span>
                          </div>
                          <div className="mt-1 text-xs text-muted-foreground">
                            {(item.sender_name || item.sender_phone || 'Remetente nao identificado')} - {item.provider_key || 'sem provedor'}
                          </div>
                          <div className="mt-2 text-sm text-foreground/90">
                            {item.human_reason || item.reason || item.reply_text || item.short_caption || 'Sem explicacao operacional.'}
                          </div>
                          <div className="mt-1 text-xs text-muted-foreground">
                            {item.text_context_summary || 'Sem resumo de contexto textual.'}
                          </div>
                        </div>
                      </div>
                    </button>
                  ))
                )}
              </div>
            </div>

            <div className="glass space-y-4 rounded-xl p-6">
              <div className="space-y-1">
                <h3 className="font-semibold">Detalhe do evento real</h3>
                <p className="text-sm text-muted-foreground">
                  Veja o prompt efetivo, payload tecnico, remetente, preset usado e o retorno completo da execucao produtiva.
                </p>
              </div>

              {selectedRealHistoryId === null ? (
                <div className="rounded-lg border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                  Selecione uma execucao real para ver o detalhe completo.
                </div>
              ) : realHistoryDetailQuery.isLoading && activeRealHistoryDetail === null ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Carregando detalhe do evento real...
                </div>
              ) : activeRealHistoryDetail === null ? (
                <div className="rounded-lg border border-destructive/30 p-4 text-sm text-destructive">
                  Nao foi possivel carregar o detalhe desta execucao real.
                </div>
              ) : (
                <div className="space-y-4">
                  <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={effectiveStateBadgeVariant(activeRealHistoryDetail.effective_media_state)}>
                      {effectiveStateLabel(activeRealHistoryDetail.effective_media_state)}
                    </Badge>
                    <Badge variant={activeRealHistoryDetail.status === 'failed' ? 'destructive' : 'outline'}>
                      Pipeline VLM: {activeRealHistoryDetail.status || 'sem status'}
                    </Badge>
                    {activeRealHistoryDetail.provider_key ? <Badge variant="outline">{activeRealHistoryDetail.provider_key}</Badge> : null}
                    {activeRealHistoryDetail.model_key ? <Badge variant="outline">{activeRealHistoryDetail.model_key}</Badge> : null}
                    {activeRealHistoryDetail.trace_id ? <Badge variant="outline">{activeRealHistoryDetail.trace_id}</Badge> : null}
                    {activeRealHistoryDetail.policy_label ? <Badge variant="outline">{activeRealHistoryDetail.policy_label}</Badge> : null}
                  </div>

                  <div className="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Miniatura</div>
                      <div className="mt-3">
                        {activeRealHistoryDetail.preview_url ? (
                          <img
                            src={activeRealHistoryDetail.preview_url}
                            alt={`Preview da midia ${activeRealHistoryDetail.id}`}
                            className="h-40 w-full rounded-lg border border-border/60 object-cover"
                          />
                        ) : (
                          <div className="flex h-40 items-center justify-center rounded-lg border border-dashed border-border/60 text-muted-foreground">
                            Sem preview
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                        <div className="font-medium">Leitura operacional</div>
                        <div className="mt-2 space-y-1 text-muted-foreground">
                          <div>Motivo humano: {activeRealHistoryDetail.human_reason || 'Nao informado'}</div>
                          <div>Politica ativa: {activeRealHistoryDetail.policy_label || activeRealHistoryDetail.preset_name || 'Sem preset'}</div>
                          <div>Heranca: {policyInheritanceLabel(activeRealHistoryDetail.policy_inheritance_mode)}</div>
                          <div>Texto considerado: {activeRealHistoryDetail.text_context_summary || 'Nao informado'}</div>
                        </div>
                      </div>

                      <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                        <div className="font-medium">Decisoes consolidadas</div>
                        <div className="mt-2 space-y-1 text-muted-foreground">
                          <div>Safety: {activeRealHistoryDetail.safety_decision || 'Nao informado'}</div>
                          <div>Contexto: {activeRealHistoryDetail.context_decision || 'Nao informado'}</div>
                          <div>Operador: {activeRealHistoryDetail.operator_decision || 'Nao informado'}</div>
                          <div>Publicacao: {activeRealHistoryDetail.publication_decision || 'Nao informado'}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Evento e remetente</div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        <div>Evento: {activeRealHistoryDetail.event_title || 'Nao informado'}</div>
                        <div>Remetente: {activeRealHistoryDetail.sender_name || 'Nao informado'}</div>
                        <div>Telefone: {activeRealHistoryDetail.sender_phone || 'Nao informado'}</div>
                        <div>Tipo da mensagem: {activeRealHistoryDetail.message_type || 'Nao informado'}</div>
                        <div>Midia: {activeRealHistoryDetail.media_type || 'Nao informado'}</div>
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Decisão e resposta</div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        <div>Preset: {activeRealHistoryDetail.preset_name || 'Sem preset'}</div>
                        <div>Origem do preset: {activeRealHistoryDetail.prompt_preset_source || 'Nao informada'}</div>
                        <div>Origem da instrucao: {activeRealHistoryDetail.prompt_instruction_source || 'Nao informada'}</div>
                        <div>Reason code: {activeRealHistoryDetail.reason_code || 'Nao informado'}</div>
                        <div>Confianca: {activeRealHistoryDetail.confidence_band || 'Nao informada'}</div>
                        <div>Elegibilidade: {activeRealHistoryDetail.publish_eligibility || 'Nao informada'}</div>
                        <div>Resposta: {activeRealHistoryDetail.reply_text || 'Sem resposta automatica'}</div>
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Políticas que bateram</div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        {activeRealHistoryDetail.matched_policies.length > 0
                          ? activeRealHistoryDetail.matched_policies.map((policy) => (
                            <div key={policy}>{policy}</div>
                          ))
                          : <div>Nenhuma política explícita registrada.</div>}
                      </div>
                    </div>

                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Exceções consideradas</div>
                      <div className="mt-2 space-y-1 text-muted-foreground">
                        {activeRealHistoryDetail.matched_exceptions.length > 0
                          ? activeRealHistoryDetail.matched_exceptions.map((policy) => (
                            <div key={policy}>{policy}</div>
                          ))
                          : <div>Nenhuma exceção aplicada.</div>}
                      </div>
                    </div>
                  </div>

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Prompt template</div>
                      <pre className="mt-2 whitespace-pre-wrap break-words text-muted-foreground">{activeRealHistoryDetail.prompt_template || 'Vazio'}</pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Prompt efetivo</div>
                      <pre className="mt-2 whitespace-pre-wrap break-words text-muted-foreground">{activeRealHistoryDetail.prompt_resolved || 'Vazio'}</pre>
                    </div>
                  </div>

                  <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                    <div className="font-medium">Variaveis resolvidas</div>
                    <pre className="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                      {JSON.stringify(activeRealHistoryDetail.prompt_variables ?? {}, null, 2)}
                    </pre>
                  </div>

                  <div className="grid gap-4">
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload enviado</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(activeRealHistoryDetail.request_payload ?? {}, null, 2)}
                      </pre>
                    </div>
                    <div className="rounded-lg border border-border/60 bg-muted/20 p-4 text-sm">
                      <div className="font-medium">Payload recebido</div>
                      <pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words text-xs text-muted-foreground">
                        {JSON.stringify(activeRealHistoryDetail.response_payload ?? {}, null, 2)}
                      </pre>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>
        </TabsContent>
      </Tabs>
    </motion.div>
  );
}
