import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  Activity,
  ArrowLeft,
  ChevronDown,
  Eye,
  ExternalLink,
  GripVertical,
  Loader2,
  MonitorSmartphone,
  MousePointerClick,
  Plus,
  RotateCcw,
  Save,
  Smartphone,
  Trash2,
  Upload,
  Users,
} from 'lucide-react';
import { useSearchParams } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import type {
  ApiEvent,
  ApiEventHubSettingsResponse,
  ApiHubBlockKey,
  ApiHubButton,
  ApiHubButtonStyle,
  ApiHubBuilderConfig,
  ApiHubIconOption,
  ApiHubInfoGridItem,
  ApiHubPreset,
  ApiHubSocialItem,
  ApiHubSocialProviderKey,
  ApiHubSponsorItem,
  HubButtonIconKey,
} from '@/lib/api-types';
import { resolveAssetUrl } from '@/lib/assets';
import { cn } from '@/lib/utils';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';

import { eventsService } from '../events/services/events.service';
import { EVENT_STATUS_LABELS, type EventListItem } from '../events/types';
import {
  getEventHubInsights,
  getEventHubSettings,
  listHubPresets,
  storeHubPreset,
  updateEventHubSettings,
  uploadHubHeroImage,
  uploadHubSponsorLogo,
  type StoreHubPresetPayload,
  type UpdateEventHubSettingsPayload,
} from './api';
import {
  applyLayoutPreset,
  buildHubThemePreset,
  cloneBuilderConfig,
  ensureVisibleBlocks,
  hubBlockLabels,
  hubLayoutPresets,
  hubThemePresets,
  moveBlockOrder,
} from './hub-builder';
import { HubRenderer } from './HubRenderer';
import { getHubIcon } from './hub-icons';

type DraftState = {
  is_enabled: boolean;
  headline: string;
  subheadline: string;
  welcome_text: string;
  hero_image_path: string;
  button_style: ApiHubButtonStyle;
  builder_config: ApiHubBuilderConfig;
  buttons: ApiHubButton[];
};

type PreviewDevice = 'mobile' | 'desktop';

const themeTokenFields: Array<{ key: keyof ApiHubBuilderConfig['theme_tokens']; label: string }> = [
  { key: 'page_background', label: 'Fundo da pagina' },
  { key: 'page_accent', label: 'Acento principal' },
  { key: 'surface_background', label: 'Fundo dos blocos' },
  { key: 'surface_border', label: 'Borda dos blocos' },
  { key: 'text_primary', label: 'Texto principal' },
  { key: 'text_secondary', label: 'Texto secundario' },
  { key: 'hero_overlay_color', label: 'Sombra da capa' },
];

const hubSocialProviderOptions: Array<{
  value: ApiHubSocialProviderKey;
  label: string;
  helper: string;
  icon: HubButtonIconKey;
}> = [
  { value: 'instagram', label: 'Instagram', helper: '@perfil ou link direto', icon: 'instagram' },
  { value: 'whatsapp', label: 'WhatsApp', helper: 'Conversa, lista VIP ou suporte', icon: 'message-circle' },
  { value: 'tiktok', label: 'TikTok', helper: 'Highlights curtos do evento', icon: 'music' },
  { value: 'youtube', label: 'YouTube', helper: 'Trailer, aftermovie ou transmissao', icon: 'monitor' },
  { value: 'spotify', label: 'Spotify', helper: 'Playlist oficial ou esquenta', icon: 'music' },
  { value: 'website', label: 'Site', helper: 'Landing page ou lista externa', icon: 'link' },
  { value: 'map', label: 'Mapa', helper: 'Como chegar ou estacionamento', icon: 'map-pin' },
  { value: 'tickets', label: 'Ingressos', helper: 'Compra ou retirada', icon: 'ticket' },
];

const eventStatusPriority: Record<EventListItem['status'], number> = {
  active: 0,
  scheduled: 1,
  paused: 2,
  draft: 3,
  ended: 4,
  archived: 5,
};

const validHexColorPattern = /^#[0-9a-fA-F]{6}$/;

function withOrder(buttons: ApiHubButton[]) {
  return buttons.map((button, index) => ({ ...button, sort_order: index + 1 }));
}

function toDraft(payload: ApiEventHubSettingsResponse): DraftState {
  const fallbackTheme = buildHubThemePreset('midnight', payload.event);

  return {
    is_enabled: payload.settings.is_enabled,
    headline: payload.settings.headline ?? '',
    subheadline: payload.settings.subheadline ?? '',
    welcome_text: payload.settings.welcome_text ?? '',
    hero_image_path: payload.settings.hero_image_path ?? '',
    button_style: payload.settings.button_style ?? fallbackTheme.button_style,
    builder_config: payload.settings.builder_config ?? fallbackTheme.builder_config,
    buttons: withOrder(payload.settings.buttons),
  };
}

function blankToNull(value: string) {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function toPayload(state: DraftState): UpdateEventHubSettingsPayload {
  return {
    is_enabled: state.is_enabled,
    headline: blankToNull(state.headline),
    subheadline: blankToNull(state.subheadline),
    welcome_text: blankToNull(state.welcome_text),
    hero_image_path: blankToNull(state.hero_image_path),
    button_style: state.button_style,
    builder_config: state.builder_config,
    buttons: state.buttons.map((button) => ({
      id: button.id,
      type: button.type,
      preset_key: button.preset_key,
      label: button.label,
      icon: button.icon,
      href: button.type === 'custom' ? blankToNull(button.href ?? '') : null,
      is_visible: button.is_visible,
      opens_in_new_tab: button.opens_in_new_tab,
      background_color: button.background_color,
      text_color: button.text_color,
      outline_color: button.outline_color,
    })),
  };
}

function createCustomButton(): ApiHubButton {
  return {
    id: `custom-${Date.now()}`,
    type: 'custom',
    preset_key: null,
    label: 'Novo botao',
    icon: 'link',
    href: 'https://',
    resolved_url: 'https://',
    is_visible: true,
    is_available: true,
    opens_in_new_tab: true,
    background_color: null,
    text_color: null,
    outline_color: null,
    sort_order: 999,
  };
}

function createPresetButton(option: ApiEventHubSettingsResponse['options']['preset_actions'][number]): ApiHubButton {
  return {
    id: `preset-${option.preset_key}`,
    type: 'preset',
    preset_key: option.preset_key,
    label: option.label,
    icon: option.icon,
    href: null,
    resolved_url: option.resolved_url,
    is_visible: option.is_available,
    is_available: option.is_available,
    opens_in_new_tab: false,
    background_color: null,
    text_color: null,
    outline_color: null,
    sort_order: 999,
  };
}

function createSocialItem(provider: ApiHubSocialProviderKey = 'instagram'): ApiHubSocialItem {
  const definition = hubSocialProviderOptions.find((item) => item.value === provider) ?? hubSocialProviderOptions[0];

  return {
    id: `social-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    provider: definition.value,
    label: definition.label,
    href: '',
    icon: definition.icon,
    is_visible: true,
    opens_in_new_tab: true,
  };
}

function createInfoGridItem(): ApiHubInfoGridItem {
  return {
    id: `info-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    title: 'Novo destaque',
    value: 'Atualize aqui',
    description: '',
    icon: 'sparkles',
    is_visible: true,
  };
}

function createSponsorItem(): ApiHubSponsorItem {
  return {
    id: `sponsor-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    name: 'Novo parceiro',
    subtitle: '',
    logo_path: '',
    href: '',
    is_visible: true,
    opens_in_new_tab: true,
  };
}

function formatDateLabel(value?: string | null) {
  if (!value) return null;

  return new Date(value).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
}

function formatDateTimeLabel(value?: string | null) {
  if (!value) return null;

  return new Date(value).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatPercent(value: number) {
  return `${value.toFixed(2).replace('.', ',')}%`;
}

function toDateTimeLocalValue(value?: string | null) {
  if (!value) return '';

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return '';
  }

  const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

  return offsetDate.toISOString().slice(0, 16);
}

function toIsoDateTimeValue(value: string) {
  const trimmed = value.trim();

  if (trimmed === '') {
    return null;
  }

  const date = new Date(trimmed);

  if (Number.isNaN(date.getTime())) {
    return null;
  }

  return date.toISOString();
}

function splitIsoDateTimeParts(value?: string | null) {
  const normalized = toDateTimeLocalValue(value);

  if (normalized === '') {
    return { date: '', time: '' };
  }

  return {
    date: normalized.slice(0, 10),
    time: normalized.slice(11, 16),
  };
}

function mergeDateAndTimeParts(datePart: string, timePart: string) {
  const date = datePart.trim();

  if (date === '') {
    return null;
  }

  const time = timePart.trim() === '' ? '00:00' : timePart.trim();

  return toIsoDateTimeValue(`${date}T${time}`);
}

function normalizeColorValue(value: string | null | undefined, fallback = '#000000') {
  if (value && validHexColorPattern.test(value)) {
    return value;
  }

  return fallback;
}

function compareEventDate(first?: string | null, second?: string | null) {
  const firstValue = first ? Date.parse(first) : 0;
  const secondValue = second ? Date.parse(second) : 0;

  return secondValue - firstValue;
}

function sortHubEvents(a: EventListItem, b: EventListItem) {
  const priorityDelta = (eventStatusPriority[a.status] ?? 999) - (eventStatusPriority[b.status] ?? 999);

  if (priorityDelta !== 0) {
    return priorityDelta;
  }

  return compareEventDate(a.starts_at, b.starts_at);
}

export default function HubPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const heroInputRef = useRef<HTMLInputElement>(null);
  const sponsorLogoInputRef = useRef<HTMLInputElement>(null);
  const sponsorLogoTargetRef = useRef<string | null>(null);
  const [searchParams, setSearchParams] = useSearchParams();
  const [editingId, setEditingId] = useState<string | null>(null);
  const [dragId, setDragId] = useState<string | null>(null);
  const [dragSectionId, setDragSectionId] = useState<ApiHubBlockKey | null>(null);
  const [pendingSponsorLogoItemId, setPendingSponsorLogoItemId] = useState<string | null>(null);
  const [previewDevice, setPreviewDevice] = useState<PreviewDevice>('mobile');
  const [insightsWindow, setInsightsWindow] = useState<7 | 30 | 90>(30);
  const [draft, setDraft] = useState<DraftState | null>(null);
  const [presetName, setPresetName] = useState('');
  const [presetDescription, setPresetDescription] = useState('');
  const eventId = searchParams.get('event') ?? '';

  const eventsQuery = useQuery({
    queryKey: ['hub', 'events'],
    queryFn: () => eventsService.list({ module: 'hub', per_page: 50 }),
  });

  const events = eventsQuery.data?.data ?? [];
  const sortedEvents = useMemo(() => [...events].sort(sortHubEvents), [events]);
  const selectedEvent = useMemo(
    () => sortedEvents.find((event) => String(event.id) === eventId) ?? null,
    [eventId, sortedEvents],
  );

  const hubQuery = useQuery({
    queryKey: ['hub', 'settings', eventId],
    enabled: selectedEvent !== null,
    queryFn: () => getEventHubSettings(eventId),
  });

  const insightsQuery = useQuery({
    queryKey: ['hub', 'insights', eventId, insightsWindow],
    enabled: selectedEvent !== null,
    queryFn: () => getEventHubInsights(eventId, insightsWindow),
  });

  const presetsQuery = useQuery({
    queryKey: ['hub', 'presets'],
    queryFn: () => listHubPresets(),
  });

  useEffect(() => {
    setDraft(null);
    setEditingId(null);
  }, [eventId]);

  useEffect(() => {
    if (hubQuery.data) {
      setDraft(toDraft(hubQuery.data));
      setPresetName('');
      setPresetDescription('');
    }
  }, [hubQuery.data]);

  const saveMutation = useMutation({
    mutationFn: () => updateEventHubSettings(eventId, toPayload(draft as DraftState)),
    onSuccess: async (payload) => {
      await queryClient.invalidateQueries({ queryKey: ['hub', 'settings', eventId] });
      setDraft(toDraft(payload));
      toast({ title: 'Links salvos', description: 'Configuracoes atualizadas com sucesso.' });
    },
    onError: (error: Error) => toast({ title: 'Falha ao salvar', description: error.message, variant: 'destructive' }),
  });

  const heroUploadMutation = useMutation({
    mutationFn: (file: File) => uploadHubHeroImage(eventId, file, draft?.hero_image_path ?? null),
    onSuccess: (asset) => {
      setDraft((current) => current ? { ...current, hero_image_path: asset.path } : current);
      toast({ title: 'Imagem enviada', description: 'A imagem principal da pagina de links foi atualizada.' });
    },
    onError: (error: Error) => toast({ title: 'Falha no upload', description: error.message, variant: 'destructive' }),
  });

  const sponsorLogoUploadMutation = useMutation({
    mutationFn: ({ file, itemId, previousPath }: { file: File; itemId: string; previousPath?: string | null }) => (
      uploadHubSponsorLogo(eventId, file, previousPath)
    ),
    onSuccess: (asset, variables) => {
      updateSponsorItem(variables.itemId, { logo_path: asset.path });
      toast({ title: 'Logo enviada', description: 'A imagem do parceiro foi adicionada ao editor.' });
    },
    onError: (error: Error) => toast({ title: 'Falha no upload', description: error.message, variant: 'destructive' }),
    onSettled: () => {
      sponsorLogoTargetRef.current = null;
      setPendingSponsorLogoItemId(null);
    },
  });

  const savePresetMutation = useMutation({
    mutationFn: (payload: StoreHubPresetPayload) => storeHubPreset(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['hub', 'presets'] });
      setPresetName('');
      setPresetDescription('');
      toast({ title: 'Modelo salvo', description: 'Esse modelo agora pode ser reutilizado em outras paginas de links da organizacao.' });
    },
    onError: (error: Error) => toast({ title: 'Falha ao salvar modelo', description: error.message, variant: 'destructive' }),
  });

  const heroUrl = draft
    ? resolveAssetUrl(draft.hero_image_path) ?? hubQuery.data?.settings.hero_image_url ?? selectedEvent?.cover_image_url ?? null
    : null;

  const visibleButtons = useMemo(
    () => draft?.buttons.filter((button) => button.is_visible && (button.type === 'custom' || button.is_available)) ?? [],
    [draft],
  );
  const visibleSectionOrder = useMemo(
    () => draft ? ensureVisibleBlocks(draft.builder_config.block_order, draft.builder_config) : [],
    [draft],
  );
  const presetMap = useMemo(() => {
    const entries = hubQuery.data?.options.preset_actions ?? [];
    return new Map(entries.map((item) => [item.preset_key, item] as const));
  }, [hubQuery.data?.options.preset_actions]);
  const themePresetMap = useMemo(
    () => new Map(hubThemePresets.map((item) => [item.key, item] as const)),
    [],
  );
  const layoutPresetMap = useMemo(
    () => new Map(hubLayoutPresets.map((item) => [item.key, item] as const)),
    [],
  );
  const buttonInsightsMap = useMemo(
    () => new Map((insightsQuery.data?.buttons ?? []).map((item) => [item.button_id, item] as const)),
    [insightsQuery.data?.buttons],
  );

  function selectEvent(nextEventId: string) {
    const next = new URLSearchParams(searchParams);
    next.set('event', nextEventId);
    setSearchParams(next);
  }

  function clearSelectedEvent() {
    const next = new URLSearchParams(searchParams);
    next.delete('event');
    setSearchParams(next);
  }

  function updateDraft<K extends keyof DraftState>(key: K, value: DraftState[K]) {
    setDraft((current) => (current ? { ...current, [key]: value } : current));
  }

  function updateBuilderConfig(next: ApiHubBuilderConfig) {
    setDraft((current) => (current ? { ...current, builder_config: next } : current));
  }

  function mutateBuilder(mutator: (builder: ApiHubBuilderConfig) => ApiHubBuilderConfig) {
    setDraft((current) => (
      current
        ? { ...current, builder_config: mutator(cloneBuilderConfig(current.builder_config)) }
        : current
    ));
  }

  function updateButton(buttonId: string, patch: Partial<ApiHubButton>) {
    setDraft((current) => current ? {
      ...current,
      buttons: withOrder(current.buttons.map((button) => (
        button.id === buttonId ? { ...button, ...patch } : button
      ))),
    } : current);
  }

  function updateButtonColor(
    buttonId: string,
    field: 'background_color' | 'text_color' | 'outline_color',
    value: string | null,
  ) {
    updateButton(buttonId, { [field]: value } as Pick<ApiHubButton, typeof field>);
  }

  function updateSocialItem(itemId: string, patch: Partial<ApiHubSocialItem>) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        social_strip: {
          ...builder.blocks.social_strip,
          items: builder.blocks.social_strip.items.map((item) => (
            item.id === itemId ? { ...item, ...patch } : item
          )),
        },
      },
    }));
  }

  function updateInfoGridItem(itemId: string, patch: Partial<ApiHubInfoGridItem>) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        info_grid: {
          ...builder.blocks.info_grid,
          items: builder.blocks.info_grid.items.map((item) => (
            item.id === itemId ? { ...item, ...patch } : item
          )),
        },
      },
    }));
  }

  function addInfoGridItem() {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        info_grid: {
          ...builder.blocks.info_grid,
          enabled: true,
          items: [...builder.blocks.info_grid.items, createInfoGridItem()],
        },
      },
      block_order: ensureVisibleBlocks([...builder.block_order, 'info_grid'], {
        ...builder,
        blocks: {
          ...builder.blocks,
          info_grid: {
            ...builder.blocks.info_grid,
            enabled: true,
          },
        },
      }),
    }));
  }

  function removeInfoGridItem(itemId: string) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        info_grid: {
          ...builder.blocks.info_grid,
          items: builder.blocks.info_grid.items.filter((item) => item.id !== itemId),
        },
      },
    }));
  }

  function updateSponsorItem(itemId: string, patch: Partial<ApiHubSponsorItem>) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        sponsor_strip: {
          ...builder.blocks.sponsor_strip,
          items: builder.blocks.sponsor_strip.items.map((item) => (
            item.id === itemId ? { ...item, ...patch } : item
          )),
        },
      },
    }));
  }

  function addSponsorItem() {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        sponsor_strip: {
          ...builder.blocks.sponsor_strip,
          enabled: true,
          items: [...builder.blocks.sponsor_strip.items, createSponsorItem()],
        },
      },
      block_order: ensureVisibleBlocks([...builder.block_order, 'sponsor_strip'], {
        ...builder,
        blocks: {
          ...builder.blocks,
          sponsor_strip: {
            ...builder.blocks.sponsor_strip,
            enabled: true,
          },
        },
      }),
    }));
  }

  function removeSponsorItem(itemId: string) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        sponsor_strip: {
          ...builder.blocks.sponsor_strip,
          items: builder.blocks.sponsor_strip.items.filter((item) => item.id !== itemId),
        },
      },
    }));
  }

  function updateCountdownBlock(patch: Partial<ApiHubBuilderConfig['blocks']['countdown']>) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        countdown: {
          ...builder.blocks.countdown,
          ...patch,
        },
      },
    }));
  }

  function addSocialItem(provider: ApiHubSocialProviderKey = 'instagram') {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        social_strip: {
          ...builder.blocks.social_strip,
          enabled: true,
          items: [...builder.blocks.social_strip.items, createSocialItem(provider)],
        },
      },
      block_order: ensureVisibleBlocks([...builder.block_order, 'social_strip'], {
        ...builder,
        blocks: {
          ...builder.blocks,
          social_strip: {
            ...builder.blocks.social_strip,
            enabled: true,
          },
        },
      }),
    }));
  }

  function removeSocialItem(itemId: string) {
    mutateBuilder((builder) => ({
      ...builder,
      blocks: {
        ...builder.blocks,
        social_strip: {
          ...builder.blocks.social_strip,
          items: builder.blocks.social_strip.items.filter((item) => item.id !== itemId),
        },
      },
    }));
  }

  function reorderButton(targetId: string) {
    if (!dragId) return;

    setDraft((current) => {
      if (!current) return current;
      const next = [...current.buttons];
      const from = next.findIndex((button) => button.id === dragId);
      const to = next.findIndex((button) => button.id === targetId);
      if (from < 0 || to < 0 || from === to) return current;
      const [moved] = next.splice(from, 1);
      next.splice(to, 0, moved);
      return { ...current, buttons: withOrder(next) };
    });

    setDragId(null);
  }

  function reorderSection(targetId: ApiHubBlockKey) {
    if (!dragSectionId) return;

    mutateBuilder((builder) => ({
      ...builder,
      block_order: moveBlockOrder(ensureVisibleBlocks(builder.block_order, builder), dragSectionId, targetId),
    }));

    setDragSectionId(null);
  }

  function updateThemeToken(key: keyof ApiHubBuilderConfig['theme_tokens'], value: string) {
    mutateBuilder((builder) => ({
      ...builder,
      theme_tokens: { ...builder.theme_tokens, [key]: value },
    }));
  }

  function setBlockEnabled(blockKey: ApiHubBlockKey, enabled: boolean) {
    mutateBuilder((builder) => {
      if (blockKey === 'hero') builder.blocks.hero.enabled = enabled;
      if (blockKey === 'meta_cards') builder.blocks.meta_cards.enabled = enabled;
      if (blockKey === 'welcome') builder.blocks.welcome.enabled = enabled;
      if (blockKey === 'countdown') builder.blocks.countdown.enabled = enabled;
      if (blockKey === 'info_grid') builder.blocks.info_grid.enabled = enabled;
      if (blockKey === 'cta_list') builder.blocks.cta_list.enabled = enabled;
      if (blockKey === 'social_strip') builder.blocks.social_strip.enabled = enabled;
      if (blockKey === 'sponsor_strip') builder.blocks.sponsor_strip.enabled = enabled;

      const nextOrder = enabled
        ? [...builder.block_order, blockKey]
        : builder.block_order.filter((item) => item !== blockKey);

      builder.block_order = ensureVisibleBlocks(nextOrder, builder);

      return builder;
    });
  }

  function handleHeroSelection(fileList: FileList | null) {
    const file = fileList?.[0];
    if (!file) return;
    heroUploadMutation.mutate(file);
  }

  function openHeroPicker() {
    heroInputRef.current?.click();
  }

  function handleSponsorLogoSelection(fileList: FileList | null) {
    const file = fileList?.[0];
    const itemId = sponsorLogoTargetRef.current ?? pendingSponsorLogoItemId;

    if (!file || !itemId || !draft) {
      sponsorLogoTargetRef.current = null;
      setPendingSponsorLogoItemId(null);
      return;
    }

    const currentItem = draft.builder_config.blocks.sponsor_strip.items.find((item) => item.id === itemId);

    sponsorLogoUploadMutation.mutate({
      file,
      itemId,
      previousPath: currentItem?.logo_path ?? null,
    });
  }

  function openSponsorLogoPicker(itemId: string) {
    sponsorLogoTargetRef.current = itemId;
    setPendingSponsorLogoItemId(itemId);
    sponsorLogoInputRef.current?.click();
  }

  function currentBrandingEvent(): Pick<ApiEvent, 'primary_color' | 'secondary_color' | 'title' | 'starts_at' | 'location_name' | 'description'> {
    return {
      title: selectedEvent?.title ?? null,
      starts_at: selectedEvent?.starts_at ?? null,
      location_name: selectedEvent?.location_name ?? null,
      description: selectedEvent?.description ?? null,
      primary_color: selectedEvent?.primary_color ?? null,
      secondary_color: selectedEvent?.secondary_color ?? null,
    };
  }

  function applyLayout(layoutKey: ApiHubBuilderConfig['layout_key']) {
    updateBuilderConfig(applyLayoutPreset(draft!.builder_config, layoutKey));
  }

  function applyTheme(themeKey: ApiHubBuilderConfig['theme_key']) {
    setDraft((current) => {
      if (!current) return current;

      const nextTheme = buildHubThemePreset(themeKey, currentBrandingEvent());
      const orderedPresetButtons = nextTheme.preset_button_order.flatMap((presetKey) => {
        const existingButton = current.buttons.find((button) => button.preset_key === presetKey);
        const option = presetMap.get(presetKey);
        const baseButton = existingButton ?? (option ? createPresetButton(option) : null);

        if (!baseButton) {
          return [];
        }

        const override = nextTheme.preset_button_overrides[presetKey];
        const isAvailable = option?.is_available ?? baseButton.is_available;

        return [{
          ...baseButton,
          label: override.label,
          icon: override.icon,
          background_color: override.background_color,
          text_color: override.text_color,
          outline_color: override.outline_color,
          resolved_url: option?.resolved_url ?? baseButton.resolved_url,
          is_available: isAvailable,
          is_visible: baseButton.is_visible && isAvailable,
        }];
      });

      const themedCustomButtons = current.buttons
        .filter((button) => button.type === 'custom')
        .map((button) => ({
          ...button,
          background_color: null,
          text_color: null,
          outline_color: null,
        }));

      const remainingPresetButtons = current.buttons
        .filter((button) => button.type === 'preset' && button.preset_key && !nextTheme.preset_button_order.includes(button.preset_key))
        .map((button) => ({
          ...button,
          background_color: null,
          text_color: null,
          outline_color: null,
        }));

      return {
        ...current,
        headline: nextTheme.content_copy.headline,
        subheadline: nextTheme.content_copy.subheadline,
        welcome_text: nextTheme.content_copy.welcome_text,
        builder_config: nextTheme.builder_config,
        button_style: nextTheme.button_style,
        buttons: withOrder([...orderedPresetButtons, ...remainingPresetButtons, ...themedCustomButtons]),
      };
    });
  }

  function resetBuilder() {
    applyTheme('midnight');
  }

  function applySavedPreset(preset: ApiHubPreset) {
    setDraft((current) => {
      if (!current) return current;

      const nextBuilder = cloneBuilderConfig(preset.payload.builder_config);
      const nextButtons = withOrder(
        preset.payload.buttons.map((button) => {
          if (button.type === 'preset' && button.preset_key) {
            const option = presetMap.get(button.preset_key);
            const isAvailable = option?.is_available ?? false;

            return {
              ...button,
              resolved_url: option?.resolved_url ?? null,
              is_available: isAvailable,
              is_visible: button.is_visible && isAvailable,
            };
          }

          return {
            ...button,
            resolved_url: button.href ?? null,
            is_available: true,
          };
        }),
      );

      if (nextBuilder.blocks.countdown.target_mode === 'event_start') {
        nextBuilder.blocks.countdown.target_at = selectedEvent?.starts_at ?? null;
        nextBuilder.blocks.countdown.enabled = Boolean(selectedEvent?.starts_at);
      }

      return {
        ...current,
        button_style: { ...preset.payload.button_style },
        builder_config: nextBuilder,
        buttons: nextButtons,
      };
    });

    toast({
      title: 'Modelo aplicado',
      description: 'O modelo foi aplicado nesta pagina de links. O conteudo do evento atual foi mantido e os links oficiais foram recalculados.',
    });
  }

  function saveCurrentPreset() {
    if (!draft) {
      return;
    }

    const name = presetName.trim();

    if (name === '') {
      toast({ title: 'Nome obrigatorio', description: 'Defina um nome para salvar o modelo da pagina de links.', variant: 'destructive' });
      return;
    }

    savePresetMutation.mutate({
      event_id: selectedEvent?.id ?? null,
      name,
      description: presetDescription.trim() === '' ? null : presetDescription.trim(),
      button_style: { ...draft.button_style },
      builder_config: cloneBuilderConfig(draft.builder_config),
      buttons: draft.buttons.map((button) => ({
        id: button.id,
        type: button.type,
        preset_key: button.preset_key,
        label: button.label,
        icon: button.icon,
        href: button.type === 'custom' ? blankToNull(button.href ?? '') : null,
        is_visible: button.is_visible,
        opens_in_new_tab: button.opens_in_new_tab,
        background_color: button.background_color,
        text_color: button.text_color,
        outline_color: button.outline_color,
      })),
    });
  }

  if (eventsQuery.isLoading) return <LoaderState />;
  if (eventsQuery.isError) return <ErrorState title="Falha ao carregar eventos" description={(eventsQuery.error as Error).message} />;
  if (sortedEvents.length === 0) return <EmptyState />;
  if (!selectedEvent) return <EventPickerState events={sortedEvents} invalidEventId={eventId} onSelect={selectEvent} />;
  if (hubQuery.isLoading || !draft || !hubQuery.data) return <LoaderState />;
  if (hubQuery.isError) return <ErrorState title="Falha ao carregar o editor de Links" description={(hubQuery.error as Error).message} />;

  const orderedBlockCards = [
    ...visibleSectionOrder,
    ...(['hero', 'meta_cards', 'welcome', 'countdown', 'info_grid', 'cta_list', 'social_strip', 'sponsor_strip'] as ApiHubBlockKey[]).filter((key) => !visibleSectionOrder.includes(key)),
  ];

  const previewEvent = hubQuery.data.event;
  const previewHub = {
    headline: draft.headline || previewEvent.title,
    subheadline: draft.subheadline || [formatDateLabel(previewEvent.starts_at), previewEvent.location_name].filter(Boolean).join(' - '),
    welcome_text: draft.welcome_text || previewEvent.description,
    hero_image_url: heroUrl,
    button_style: draft.button_style,
    builder_config: draft.builder_config,
    buttons: visibleButtons,
    is_enabled: draft.is_enabled,
  };

  const eventSubtitle = [formatDateLabel(selectedEvent.starts_at), selectedEvent.location_name].filter(Boolean).join(' - ');
  const countdownCustomParts = splitIsoDateTimeParts(draft.builder_config.blocks.countdown.target_at);
  const activeLayoutLabel = hubLayoutPresets.find((preset) => preset.key === draft.builder_config.layout_key)?.label ?? draft.builder_config.layout_key;
  const activeThemeLabel = hubThemePresets.find((preset) => preset.key === draft.builder_config.theme_key)?.label ?? draft.builder_config.theme_key;
  const timelineWithMovement = insightsQuery.data?.timeline.filter((point) => point.page_views > 0 || point.button_clicks > 0) ?? [];

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <input
        ref={heroInputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        className="hidden"
        onChange={(event) => {
          handleHeroSelection(event.target.files);
          event.target.value = '';
        }}
      />
      <input
        ref={sponsorLogoInputRef}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        className="hidden"
        onChange={(event) => {
          handleSponsorLogoSelection(event.target.files);
          event.target.value = '';
        }}
      />

      <PageHeader
        title="Links do evento"
        description="Escolha um modelo pronto, ajuste os blocos e monte a pagina de links do evento."
        actions={(
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="ghost" size="sm" onClick={clearSelectedEvent}>
              <ArrowLeft className="mr-1.5 h-4 w-4" />
              Escolher outro evento
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => window.open(hubQuery.data.links.hub_url ?? selectedEvent.public_url ?? '#', '_blank', 'noopener,noreferrer')}>
              <ExternalLink className="mr-1.5 h-4 w-4" />
              Abrir pagina de links
            </Button>
            <Button type="button" size="sm" onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Save className="mr-1.5 h-4 w-4" />}
              Salvar
            </Button>
          </div>
        )}
      />

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.18fr)_420px]">
        <div className="space-y-6">
          <SectionCard title="Contexto do evento" description="Selecione o evento, publique ou pause a pagina e acompanhe o contexto atual." defaultOpen>
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
              <div className="space-y-2">
                <Label>Evento</Label>
                <Select value={eventId} onValueChange={selectEvent}>
                  <SelectTrigger><SelectValue placeholder="Selecione um evento" /></SelectTrigger>
                  <SelectContent>
                    {sortedEvents.map((event) => <SelectItem key={event.id} value={String(event.id)}>{event.title}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div className="rounded-2xl border border-border/60 bg-background/70 px-4 py-3">
                <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Endereco</p>
                <p className="mt-1 text-sm font-medium">{selectedEvent.slug}</p>
              </div>
              <div className="flex items-center gap-3 rounded-2xl border border-border/60 bg-background/70 px-4 py-3">
                <div>
                  <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Publicacao</p>
                  <p className="mt-1 text-sm font-medium">{draft.is_enabled ? 'Ativa' : 'Pausada'}</p>
                </div>
                <Switch checked={draft.is_enabled} onCheckedChange={(checked) => updateDraft('is_enabled', checked)} />
              </div>
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
              <Badge variant="secondary">{selectedEvent.title}</Badge>
              <Badge variant="outline">{EVENT_STATUS_LABELS[selectedEvent.status]}</Badge>
              {eventSubtitle ? <Badge variant="outline">{eventSubtitle}</Badge> : null}
              <Badge variant="outline">{visibleButtons.length} botoes publicados</Badge>
              <Badge variant="outline">{activeLayoutLabel}</Badge>
              <Badge variant="outline">{activeThemeLabel}</Badge>
            </div>
          </SectionCard>

          <SectionCard title="Performance da pagina" description="Visualizacoes e cliques reais do publico para orientar ajustes com base no uso." defaultOpen={false}>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Periodo analisado</p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {insightsQuery.data?.generated_at
                    ? `Atualizado em ${formatDateTimeLabel(insightsQuery.data.generated_at)}`
                    : 'Carregando desempenho da pagina de links.'}
                </p>
              </div>
              <div className="w-full sm:w-[180px]">
                <Select value={String(insightsWindow)} onValueChange={(value) => setInsightsWindow(Number(value) as 7 | 30 | 90)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="7">Ultimos 7 dias</SelectItem>
                    <SelectItem value="30">Ultimos 30 dias</SelectItem>
                    <SelectItem value="90">Ultimos 90 dias</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {insightsQuery.isLoading ? (
              <div className="flex min-h-[160px] items-center justify-center rounded-3xl border border-dashed border-border/60 bg-background/40 text-sm text-muted-foreground">
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Carregando metricas da pagina de links...
              </div>
            ) : insightsQuery.isError || !insightsQuery.data ? (
              <div className="rounded-3xl border border-destructive/20 bg-destructive/5 px-5 py-4 text-sm text-muted-foreground">
                Nao foi possivel carregar os dados da pagina de links agora.
              </div>
            ) : (
              <>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                  <StatsCard
                    title="Visualizacoes"
                    value={insightsQuery.data.summary.page_views}
                    icon={Eye}
                    description={`${insightsWindow} dias`}
                  />
                  <StatsCard
                    title="Visitantes unicos"
                    value={insightsQuery.data.summary.unique_visitors}
                    icon={Users}
                    description="Baseado nas aberturas da pagina"
                  />
                  <StatsCard
                    title="Cliques nos botoes"
                    value={insightsQuery.data.summary.button_clicks}
                    icon={MousePointerClick}
                    description={`${insightsQuery.data.summary.active_buttons} botoes com clique`}
                  />
                  <StatsCard
                    title="Taxa de clique"
                    value={formatPercent(insightsQuery.data.summary.ctr)}
                    icon={Activity}
                    description="Cliques por view no periodo"
                  />
                </div>

                <div className="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_320px]">
                  <div className="rounded-3xl border border-border/60 bg-background/50 p-4">
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold">Timeline recente</p>
                        <p className="mt-1 text-sm text-muted-foreground">Visualizacoes e cliques por dia para validar o impacto das mudancas na pagina.</p>
                      </div>
                      {insightsQuery.isFetching ? <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /> : null}
                    </div>
                    <div className="mt-4 max-h-[320px] space-y-2 overflow-auto pr-1">
                      {timelineWithMovement.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border/60 bg-background/40 px-4 py-6 text-sm text-muted-foreground">
                          Ainda nao houve movimento suficiente para montar a timeline recente.
                        </div>
                      ) : timelineWithMovement.map((point) => (
                        <div key={point.date} className="grid grid-cols-[110px_repeat(3,minmax(0,1fr))] items-center gap-3 rounded-2xl border border-border/50 bg-background/60 px-3 py-2 text-sm">
                          <span className="font-medium">
                            {point.page_views > 0 || point.button_clicks > 0
                              ? new Date(`${point.date}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })
                              : null}
                          </span>
                          <span className="text-muted-foreground">{point.page_views} visualizacoes</span>
                          <span className="text-muted-foreground">{point.button_clicks} cliques</span>
                          <span className="text-right font-medium">{formatPercent(point.ctr)}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="rounded-3xl border border-border/60 bg-background/50 p-4">
                    <p className="text-sm font-semibold">Top botoes</p>
                    <p className="mt-1 text-sm text-muted-foreground">Os botoes que mais puxaram a navegacao da pagina neste periodo.</p>
                    <div className="mt-4 space-y-3">
                      {insightsQuery.data.top_buttons.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-border/60 bg-background/40 px-4 py-6 text-sm text-muted-foreground">
                          Ainda nao ha cliques suficientes para ranquear os botoes.
                        </div>
                      ) : insightsQuery.data.top_buttons.map((button) => (
                        <div key={button.button_id} className="rounded-2xl border border-border/50 bg-background/60 px-4 py-3">
                          <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                              <p className="truncate text-sm font-semibold">{button.label}</p>
                              <div className="mt-1 flex flex-wrap gap-2">
                                <Badge variant="secondary">{button.type === 'preset' ? 'Oficial' : button.type === 'social' ? 'Rede social' : button.type === 'sponsor' ? 'Parceiro' : 'Personalizado'}</Badge>
                                {button.preset_key ? <Badge variant="outline">{button.preset_key}</Badge> : null}
                              </div>
                            </div>
                            <Badge variant="outline">{button.clicks} cliques</Badge>
                          </div>
                          {button.last_clicked_at ? (
                            <p className="mt-2 text-xs text-muted-foreground">Ultimo clique em {formatDateTimeLabel(button.last_clicked_at)}</p>
                          ) : null}
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </>
            )}
          </SectionCard>

          <SectionCard title="Modelos prontos" description="Ao trocar o tema, a pagina aplica estrutura, botoes, icones e organizacao base de uma vez." defaultOpen={false}>
            <div className="space-y-5">
              <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div className="space-y-1">
                    <p className="text-sm font-semibold">Modelos salvos da organizacao</p>
                    <p className="text-sm text-muted-foreground">
                      Salve aqui modelos reaproveitaveis da pagina de links com tema, blocos, botoes e cores, sem misturar o conteudo de cada evento.
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={resetBuilder}>
                      <RotateCcw className="mr-1.5 h-4 w-4" />
                      Restaurar base
                    </Button>
                    <Button type="button" size="sm" onClick={saveCurrentPreset} disabled={savePresetMutation.isPending}>
                      {savePresetMutation.isPending ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Save className="mr-1.5 h-4 w-4" />}
                      Salvar modelo atual
                    </Button>
                  </div>
                </div>

                <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
                  <div className="space-y-2">
                    <Label>Nome do modelo</Label>
                    <Input value={presetName} onChange={(event) => setPresetName(event.target.value)} placeholder="Ex.: Casamento clean com parceiros" maxLength={120} />
                  </div>
                  <div className="space-y-2">
                    <Label>Observacao interna</Label>
                    <Input value={presetDescription} onChange={(event) => setPresetDescription(event.target.value)} placeholder="Para casamentos com RSVP, playlist e parceiros" maxLength={180} />
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Modelos salvos</p>
                  <Badge variant="outline">{presetsQuery.data?.length ?? 0} modelo(s)</Badge>
                </div>

                {presetsQuery.isLoading ? (
                  <div className="rounded-3xl border border-dashed border-border/60 bg-background/40 px-5 py-8 text-center text-sm text-muted-foreground">
                    Carregando modelos salvos da organizacao...
                  </div>
                ) : presetsQuery.isError ? (
                  <div className="rounded-3xl border border-destructive/30 bg-destructive/5 px-5 py-8 text-center text-sm text-muted-foreground">
                    Nao foi possivel carregar a biblioteca de modelos agora.
                  </div>
                ) : (presetsQuery.data?.length ?? 0) === 0 ? (
                  <div className="rounded-3xl border border-dashed border-border/60 bg-background/40 px-5 py-8 text-center text-sm text-muted-foreground">
                    Ainda nao ha modelos salvos. Monte um visual base e clique em salvar para reutilizar em outras paginas de links desta organizacao.
                  </div>
                ) : (
                  <div className="grid gap-4 xl:grid-cols-2">
                    {presetsQuery.data?.map((preset) => (
                      <SavedPresetCard
                        key={preset.id}
                        preset={preset}
                        themeLabel={themePresetMap.get(preset.theme_key)?.label ?? preset.theme_key}
                        layoutLabel={layoutPresetMap.get(preset.layout_key)?.label ?? preset.layout_key}
                        onApply={() => applySavedPreset(preset)}
                      />
                    ))}
                  </div>
                )}
              </div>

              <div className="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
                <div className="space-y-3">
                  <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Estruturas base</p>
                  {hubLayoutPresets.map((preset) => (
                    <LayoutPresetCard
                      key={preset.key}
                      preset={preset}
                      active={draft.builder_config.layout_key === preset.key}
                      onClick={() => applyLayout(preset.key)}
                    />
                  ))}
                </div>
                <div className="space-y-3">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Temas completos</p>
                    <p className="text-xs text-muted-foreground">Deslize para ver mais opcoes por perfil de evento.</p>
                  </div>
                  <ScrollArea className="w-full whitespace-nowrap rounded-3xl border border-border/60 bg-background/40 p-3">
                    <div className="flex gap-4 pb-3">
                      {hubThemePresets.map((preset) => (
                        <div key={preset.key} className="min-w-[320px] max-w-[360px] flex-none">
                          <ThemePresetCard
                            preset={preset}
                            active={draft.builder_config.theme_key === preset.key}
                            onClick={() => applyTheme(preset.key)}
                          />
                        </div>
                      ))}
                    </div>
                    <ScrollBar orientation="horizontal" />
                  </ScrollArea>
                </div>
              </div>
            </div>
          </SectionCard>

          <SectionCard title="Capa e conteudo" description="Texto principal, imagem de capa e fallback visual do topo da pagina." defaultOpen>
            <div className="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
              <div className="overflow-hidden rounded-[1.75rem] border border-border/60 bg-background/80">
                <div className="relative min-h-[220px] px-5 pb-5 pt-6 text-white" style={{ background: `linear-gradient(155deg, ${selectedEvent.primary_color ?? '#0f172a'}, ${selectedEvent.secondary_color ?? '#1d4ed8'})` }}>
                  {heroUrl ? <img src={heroUrl} alt={draft.headline || selectedEvent.title} className="absolute inset-0 h-full w-full object-cover opacity-30" /> : null}
                  <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/10 to-black/60" />
                  <div className="relative space-y-3">
                    <Badge className="border-0 bg-white/16 text-white hover:bg-white/16">Previa</Badge>
                    <div>
                      <h3 className="text-xl font-semibold">{draft.headline || selectedEvent.title}</h3>
                      <p className="text-sm text-white/82">{draft.subheadline || eventSubtitle || 'Subtitulo do evento'}</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="space-y-4">
                <div className="rounded-3xl border border-dashed border-border bg-background/60 p-5">
                  <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                      <p className="text-sm font-semibold">Imagem de capa</p>
                      <p className="text-sm text-muted-foreground">Cole um caminho manual ou envie uma imagem daqui. JPG, PNG ou WebP.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" variant="outline" size="sm" onClick={openHeroPicker} disabled={heroUploadMutation.isPending}>
                        {heroUploadMutation.isPending ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Upload className="mr-1.5 h-4 w-4" />}
                        Enviar imagem
                      </Button>
                      <Button type="button" variant="ghost" size="sm" onClick={() => updateDraft('hero_image_path', '')}>
                        <Trash2 className="mr-1.5 h-4 w-4" />
                        Usar capa do evento
                      </Button>
                    </div>
                  </div>
                  <div className="mt-4 space-y-2">
                    <Label>Caminho ou URL manual da imagem</Label>
                    <Input value={draft.hero_image_path} onChange={(event) => updateDraft('hero_image_path', event.target.value)} placeholder="https://... ou /storage/..." />
                    <p className="text-xs text-muted-foreground">O upload salva no storage do evento. Se preferir, cole uma URL publica ou um caminho interno.</p>
                  </div>
                </div>

                <div className="grid gap-4">
                  <div className="space-y-2">
                    <Label>Titulo</Label>
                    <Input value={draft.headline} onChange={(event) => updateDraft('headline', event.target.value)} placeholder={selectedEvent.title} />
                  </div>
                  <div className="space-y-2">
                    <Label>Subtitulo</Label>
                    <Input value={draft.subheadline} onChange={(event) => updateDraft('subheadline', event.target.value)} placeholder={eventSubtitle || 'Data e local do evento'} />
                  </div>
                  <div className="space-y-2">
                    <Label>Mensagem de boas-vindas</Label>
                    <Textarea value={draft.welcome_text} onChange={(event) => updateDraft('welcome_text', event.target.value)} rows={4} />
                  </div>
                </div>
              </div>
            </div>
          </SectionCard>

          <SectionCard title="Blocos e estilo" description="Ligue, ordene e ajuste os blocos da pagina sem sair da estrutura principal dos Links." defaultOpen={false}>
            <div className="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
              <div className="space-y-3 rounded-3xl border border-border/60 bg-background/60 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">Ordem visivel</p>
                {visibleSectionOrder.map((blockKey) => (
                  <div
                    key={blockKey}
                    draggable
                    onDragStart={() => setDragSectionId(blockKey)}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={() => reorderSection(blockKey)}
                    className="flex items-center gap-3 rounded-2xl border border-border/60 bg-background/80 px-3 py-3"
                  >
                    <div className="inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-dashed border-border text-muted-foreground">
                      <GripVertical className="h-4 w-4" />
                    </div>
                    <div>
                      <p className="text-sm font-medium">{hubBlockLabels[blockKey]}</p>
                      <p className="text-xs text-muted-foreground">Arraste para mudar a sequencia</p>
                    </div>
                  </div>
                ))}
              </div>

              <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-2">
                  {orderedBlockCards.map((blockKey) => (
                    <BlockCard
                      key={blockKey}
                      title={hubBlockLabels[blockKey]}
                      enabled={blockKey === 'hero'
                        ? draft.builder_config.blocks.hero.enabled
                        : blockKey === 'meta_cards'
                          ? draft.builder_config.blocks.meta_cards.enabled
                          : blockKey === 'welcome'
                            ? draft.builder_config.blocks.welcome.enabled
                            : blockKey === 'countdown'
                              ? draft.builder_config.blocks.countdown.enabled
                              : blockKey === 'info_grid'
                                ? draft.builder_config.blocks.info_grid.enabled
                            : blockKey === 'cta_list'
                              ? draft.builder_config.blocks.cta_list.enabled
                              : blockKey === 'social_strip'
                                ? draft.builder_config.blocks.social_strip.enabled
                                : draft.builder_config.blocks.sponsor_strip.enabled}
                      onToggle={(checked) => setBlockEnabled(blockKey, checked)}
                    >
                      {blockKey === 'hero' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Altura</Label>
                            <Select value={draft.builder_config.blocks.hero.height} onValueChange={(value: ApiHubBuilderConfig['blocks']['hero']['height']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, hero: { ...builder.blocks.hero, height: value } } }))}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="sm">Compacto</SelectItem><SelectItem value="md">Medio</SelectItem><SelectItem value="lg">Alto</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="space-y-2">
                            <Label>Escurecimento</Label>
                            <Input type="number" min={0} max={90} value={draft.builder_config.blocks.hero.overlay_opacity} onChange={(event) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, hero: { ...builder.blocks.hero, overlay_opacity: Math.max(0, Math.min(90, Number(event.target.value) || 0)) } } }))} />
                          </div>
                        </div>
                      ) : null}
                      {blockKey === 'meta_cards' ? (
                        <div className="space-y-2">
                          <Label>Estilo</Label>
                          <Select value={draft.builder_config.blocks.meta_cards.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['meta_cards']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, meta_cards: { ...builder.blocks.meta_cards, style: value } } }))}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem value="glass">Vidro</SelectItem><SelectItem value="solid">Solido</SelectItem><SelectItem value="minimal">Discreto</SelectItem></SelectContent>
                          </Select>
                        </div>
                      ) : null}
                      {blockKey === 'welcome' ? (
                        <div className="space-y-2">
                          <Label>Estilo</Label>
                          <Select value={draft.builder_config.blocks.welcome.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['welcome']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, welcome: { ...builder.blocks.welcome, style: value } } }))}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem value="card">Cartao</SelectItem><SelectItem value="inline">Linha</SelectItem><SelectItem value="bubble">Destaque</SelectItem></SelectContent>
                          </Select>
                        </div>
                      ) : null}
                      {blockKey === 'countdown' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Estilo</Label>
                            <Select value={draft.builder_config.blocks.countdown.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['countdown']['style']) => updateCountdownBlock({ style: value })}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="cards">Quadros</SelectItem><SelectItem value="inline">Linha</SelectItem><SelectItem value="minimal">Discreto</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                              <Label>Origem da data</Label>
                              <Select value={draft.builder_config.blocks.countdown.target_mode} onValueChange={(value: ApiHubBuilderConfig['blocks']['countdown']['target_mode']) => updateCountdownBlock({ target_mode: value, enabled: value === 'event_start' ? Boolean(selectedEvent.starts_at) : Boolean(draft.builder_config.blocks.countdown.target_at) })}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="event_start">Data do evento</SelectItem><SelectItem value="custom">Data manual</SelectItem></SelectContent>
                              </Select>
                            </div>
                            <div className="rounded-2xl border border-border/60 px-4 py-3">
                              <p className="text-sm font-medium">{draft.builder_config.blocks.countdown.target_mode === 'event_start' ? 'Inicio do evento' : 'Data personalizada'}</p>
                              <p className="text-xs text-muted-foreground">{draft.builder_config.blocks.countdown.target_mode === 'event_start' ? (eventSubtitle || 'Use a data cadastrada no evento') : 'Defina uma data especifica para a contagem'}</p>
                            </div>
                          </div>
                        </div>
                      ) : null}
                      {blockKey === 'info_grid' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Estilo</Label>
                            <Select value={draft.builder_config.blocks.info_grid.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['info_grid']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, info_grid: { ...builder.blocks.info_grid, style: value } } }))}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="cards">Cartoes</SelectItem><SelectItem value="minimal">Discreto</SelectItem><SelectItem value="highlight">Destaque</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                              <Label>Colunas</Label>
                              <Select value={String(draft.builder_config.blocks.info_grid.columns)} onValueChange={(value) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, info_grid: { ...builder.blocks.info_grid, columns: Number(value) as 2 | 3 } } }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="2">2 colunas</SelectItem><SelectItem value="3">3 colunas</SelectItem></SelectContent>
                              </Select>
                            </div>
                            <div className="rounded-2xl border border-border/60 px-4 py-3">
                              <p className="text-sm font-medium">{draft.builder_config.blocks.info_grid.items.length} item(ns)</p>
                              <p className="text-xs text-muted-foreground">Destaques e informacoes praticas</p>
                            </div>
                          </div>
                        </div>
                      ) : null}
                      {blockKey === 'cta_list' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Estilo</Label>
                            <Select value={draft.builder_config.blocks.cta_list.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['cta_list']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, cta_list: { ...builder.blocks.cta_list, style: value } } }))}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="solid">Solido</SelectItem><SelectItem value="outline">Contorno</SelectItem><SelectItem value="soft">Suave</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                              <Label>Tamanho</Label>
                              <Select value={draft.builder_config.blocks.cta_list.size} onValueChange={(value: ApiHubBuilderConfig['blocks']['cta_list']['size']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, cta_list: { ...builder.blocks.cta_list, size: value } } }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="sm">Compacto</SelectItem><SelectItem value="md">Medio</SelectItem><SelectItem value="lg">Grande</SelectItem></SelectContent>
                              </Select>
                            </div>
                            <div className="space-y-2">
                              <Label>Icone</Label>
                              <Select value={draft.builder_config.blocks.cta_list.icon_position} onValueChange={(value: ApiHubBuilderConfig['blocks']['cta_list']['icon_position']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, cta_list: { ...builder.blocks.cta_list, icon_position: value } } }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="left">Esquerda</SelectItem><SelectItem value="top">Topo</SelectItem></SelectContent>
                              </Select>
                            </div>
                          </div>
                        </div>
                      ) : null}
                      {blockKey === 'sponsor_strip' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Estilo</Label>
                            <Select value={draft.builder_config.blocks.sponsor_strip.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['sponsor_strip']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, sponsor_strip: { ...builder.blocks.sponsor_strip, style: value } } }))}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="logos">Logos</SelectItem><SelectItem value="cards">Cartoes</SelectItem><SelectItem value="compact">Compacto</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="rounded-2xl border border-border/60 px-4 py-3">
                            <p className="text-sm font-medium">{draft.builder_config.blocks.sponsor_strip.items.length} parceiro(s)</p>
                            <p className="text-xs text-muted-foreground">Faixa de patrocinadores e apoiadores</p>
                          </div>
                        </div>
                      ) : null}
                      {blockKey === 'social_strip' ? (
                        <div className="grid gap-3">
                          <div className="space-y-2">
                            <Label>Estilo</Label>
                            <Select value={draft.builder_config.blocks.social_strip.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['social_strip']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, social_strip: { ...builder.blocks.social_strip, style: value } } }))}>
                              <SelectTrigger><SelectValue /></SelectTrigger>
                              <SelectContent><SelectItem value="icons">Icones</SelectItem><SelectItem value="chips">Etiquetas</SelectItem><SelectItem value="cards">Cartoes</SelectItem></SelectContent>
                            </Select>
                          </div>
                          <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-2">
                              <Label>Tamanho</Label>
                              <Select value={draft.builder_config.blocks.social_strip.size} onValueChange={(value: ApiHubBuilderConfig['blocks']['social_strip']['size']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, social_strip: { ...builder.blocks.social_strip, size: value } } }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="sm">Compacto</SelectItem><SelectItem value="md">Medio</SelectItem><SelectItem value="lg">Grande</SelectItem></SelectContent>
                              </Select>
                            </div>
                            <div className="rounded-2xl border border-border/60 px-4 py-3">
                              <p className="text-sm font-medium">{draft.builder_config.blocks.social_strip.items.length} item(ns)</p>
                              <p className="text-xs text-muted-foreground">Redes e links contextuais do evento</p>
                            </div>
                          </div>
                        </div>
                      ) : null}
                    </BlockCard>
                  ))}
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                  {themeTokenFields.map((field) => (
                    <ColorField key={field.key} label={field.label} value={draft.builder_config.theme_tokens[field.key]} onChange={(value) => updateThemeToken(field.key, value)} />
                  ))}
                  {(['background_color', 'text_color', 'outline_color'] as const).map((field) => (
                    <ColorField key={field} label={field === 'background_color' ? 'Fundo padrao do botao' : field === 'text_color' ? 'Texto padrao do botao' : 'Borda padrao do botao'} value={draft.button_style[field]} onChange={(value) => updateDraft('button_style', { ...draft.button_style, [field]: value })} />
                  ))}
                </div>
              </div>
            </div>
          </SectionCard>

          <SectionCard title="Contagem regressiva do evento" description="Mostre uma contagem regressiva viva na pagina usando a data de inicio do evento ou uma data manual para lancamentos e momentos especiais." defaultOpen={false}>
            <div className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-border/60 bg-background/60 p-4">
                <div>
                  <p className="text-sm font-semibold">Contagem regressiva</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {draft.builder_config.blocks.countdown.enabled
                      ? 'A contagem regressiva esta ativa e aparece na pagina respeitando a ordem dos blocos.'
                      : 'Monte a configuracao antes de ligar o bloco na pagina publica.'}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => updateCountdownBlock({
                      target_mode: 'event_start',
                      target_at: selectedEvent.starts_at ?? null,
                      enabled: Boolean(selectedEvent.starts_at),
                    })}
                  >
                    Usar data do evento
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setBlockEnabled('countdown', !draft.builder_config.blocks.countdown.enabled)}
                    disabled={draft.builder_config.blocks.countdown.target_mode === 'event_start' && !selectedEvent.starts_at}
                  >
                    {draft.builder_config.blocks.countdown.enabled ? 'Desativar bloco' : 'Ativar bloco'}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_320px]">
                <div className="space-y-4 rounded-3xl border border-border/60 bg-background/70 p-4">
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label>Origem da contagem</Label>
                      <Select
                        value={draft.builder_config.blocks.countdown.target_mode}
                        onValueChange={(value: ApiHubBuilderConfig['blocks']['countdown']['target_mode']) => updateCountdownBlock({
                          target_mode: value,
                          target_at: value === 'event_start' ? (selectedEvent.starts_at ?? null) : draft.builder_config.blocks.countdown.target_at,
                          enabled: value === 'event_start'
                            ? Boolean(selectedEvent.starts_at)
                            : Boolean(draft.builder_config.blocks.countdown.target_at),
                        })}
                      >
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="event_start">Usar data de inicio do evento</SelectItem>
                          <SelectItem value="custom">Escolher data manual</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label>Estilo da contagem</Label>
                      <Select value={draft.builder_config.blocks.countdown.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['countdown']['style']) => updateCountdownBlock({ style: value })}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                          <SelectItem value="cards">Quadros numericos</SelectItem>
                          <SelectItem value="inline">Linha compacta</SelectItem>
                          <SelectItem value="minimal">Faixa discreta</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  {draft.builder_config.blocks.countdown.target_mode === 'custom' ? (
                    <div className="grid gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <Label>Data manual</Label>
                        <Input
                          type="date"
                          value={countdownCustomParts.date}
                          onChange={(event) => {
                            const nextTarget = mergeDateAndTimeParts(event.target.value, countdownCustomParts.time);
                            updateCountdownBlock({
                              target_at: nextTarget,
                              enabled: Boolean(nextTarget),
                            });
                          }}
                        />
                      </div>
                      <div className="space-y-2">
                        <Label>Hora manual</Label>
                        <Input
                          type="time"
                          step="60"
                          value={countdownCustomParts.time}
                          onChange={(event) => {
                            const nextTarget = mergeDateAndTimeParts(countdownCustomParts.date, event.target.value);
                            updateCountdownBlock({
                              target_at: nextTarget,
                              enabled: Boolean(nextTarget),
                            });
                          }}
                        />
                      </div>
                      <p className="text-xs text-muted-foreground md:col-span-2">
                        Use este modo para aftermovie, abertura do wall, estreia de jogo ou qualquer momento especial. Se preencher apenas a data, o horario padrao sera 00:00.
                      </p>
                    </div>
                  ) : (
                    <div className="rounded-2xl border border-border/60 bg-background/50 px-4 py-3">
                      <p className="text-sm font-medium">Data herdada do evento</p>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {selectedEvent.starts_at
                          ? `A contagem usa ${formatDateTimeLabel(selectedEvent.starts_at)} como alvo.`
                          : 'Este evento ainda nao tem data de inicio cadastrada. Defina uma data no evento ou troque para data manual.'}
                      </p>
                    </div>
                  )}

                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label>Titulo do bloco</Label>
                      <Input value={draft.builder_config.blocks.countdown.title} onChange={(event) => updateCountdownBlock({ title: event.target.value })} placeholder="Falta pouco" />
                    </div>
                    <div className="space-y-2">
                      <Label>Mensagem apos zerar</Label>
                      <Input value={draft.builder_config.blocks.countdown.completed_message} onChange={(event) => updateCountdownBlock({ completed_message: event.target.value })} placeholder="O evento ja comecou" />
                    </div>
                  </div>
                </div>

                <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
                  <p className="text-sm font-semibold">Comportamento</p>
                  <p className="mt-1 text-sm text-muted-foreground">Controle se a contagem continua visivel depois que o horario chega.</p>

                  <div className="mt-4 space-y-3">
                    <div className="flex items-center justify-between rounded-2xl border border-border/60 px-4 py-3">
                      <div>
                        <p className="text-sm font-medium">Ocultar apos iniciar</p>
                        <p className="text-xs text-muted-foreground">Se ligado, o bloco some quando a contagem chegar a zero.</p>
                      </div>
                      <Switch checked={draft.builder_config.blocks.countdown.hide_after_start} onCheckedChange={(checked) => updateCountdownBlock({ hide_after_start: checked })} />
                    </div>

                    <div className="rounded-2xl border border-border/60 px-4 py-3">
                      <p className="text-sm font-medium">Alvo atual</p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {draft.builder_config.blocks.countdown.target_mode === 'event_start'
                          ? (selectedEvent.starts_at ? formatDateTimeLabel(selectedEvent.starts_at) : 'Sem data no evento')
                          : (draft.builder_config.blocks.countdown.target_at ? formatDateTimeLabel(draft.builder_config.blocks.countdown.target_at) : 'Sem data manual')}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </SectionCard>

          <SectionCard title="Grade de informacoes" description="Monte uma grade com orientacoes praticas, dress code, horario, hashtag, estacionamento ou qualquer destaque util do evento." defaultOpen={false}>
            <div className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-border/60 bg-background/60 p-4">
                <div>
                  <p className="text-sm font-semibold">Grade de informacoes</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {draft.builder_config.blocks.info_grid.enabled
                      ? 'A grade esta ativa nos Links e entra como bloco proprio na pagina.'
                      : 'Voce pode montar os destaques antes de publicar o bloco.'}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" size="sm" onClick={addInfoGridItem}>
                    <Plus className="mr-1.5 h-4 w-4" />
                    Adicionar destaque
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setBlockEnabled('info_grid', !draft.builder_config.blocks.info_grid.enabled)}
                  >
                    {draft.builder_config.blocks.info_grid.enabled ? 'Desativar bloco' : 'Ativar bloco'}
                  </Button>
                </div>
              </div>

              <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_160px_160px]">
                  <div className="space-y-2">
                    <Label>Titulo da secao</Label>
                    <Input value={draft.builder_config.blocks.info_grid.title} onChange={(event) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, info_grid: { ...builder.blocks.info_grid, title: event.target.value } } }))} placeholder="Informacoes importantes" />
                  </div>
                  <div className="space-y-2">
                    <Label>Estilo</Label>
                    <Select value={draft.builder_config.blocks.info_grid.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['info_grid']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, info_grid: { ...builder.blocks.info_grid, style: value } } }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent><SelectItem value="cards">Cartoes</SelectItem><SelectItem value="minimal">Discreto</SelectItem><SelectItem value="highlight">Destaque</SelectItem></SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Colunas</Label>
                    <Select value={String(draft.builder_config.blocks.info_grid.columns)} onValueChange={(value) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, info_grid: { ...builder.blocks.info_grid, columns: Number(value) as 2 | 3 } } }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent><SelectItem value="2">2 colunas</SelectItem><SelectItem value="3">3 colunas</SelectItem></SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                {draft.builder_config.blocks.info_grid.items.map((item) => {
                  const ItemIcon = getHubIcon(item.icon);

                  return (
                    <div key={item.id} className="rounded-3xl border border-border/60 bg-background/70 p-4">
                      <div className="flex items-start gap-3">
                        <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-border/60 bg-muted/40">
                          <ItemIcon className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-semibold">{item.title}</p>
                          <p className="mt-1 text-xs text-muted-foreground">{item.value}</p>
                          {item.description ? <p className="mt-1 text-xs text-muted-foreground">{item.description}</p> : null}
                        </div>
                        <Switch checked={item.is_visible} onCheckedChange={(checked) => updateInfoGridItem(item.id, { is_visible: checked })} />
                        <Button type="button" variant="ghost" size="sm" onClick={() => removeInfoGridItem(item.id)}>Remover</Button>
                      </div>

                      <div className="mt-4 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <div className="space-y-2">
                          <Label>Icone</Label>
                          <HubIconSelect value={item.icon} options={hubQuery.data.options.icons} onChange={(value) => updateInfoGridItem(item.id, { icon: value })} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                          <div className="space-y-2">
                            <Label>Titulo</Label>
                            <Input value={item.title} onChange={(event) => updateInfoGridItem(item.id, { title: event.target.value })} />
                          </div>
                          <div className="space-y-2">
                            <Label>Valor</Label>
                            <Input value={item.value} onChange={(event) => updateInfoGridItem(item.id, { value: event.target.value })} />
                          </div>
                        </div>
                      </div>

                      <div className="mt-4 space-y-2">
                        <Label>Descricao curta</Label>
                        <Input value={item.description ?? ''} onChange={(event) => updateInfoGridItem(item.id, { description: event.target.value })} placeholder="Ex.: estacionamento liberado a partir das 18h" />
                      </div>
                    </div>
                  );
                })}
              </div>

              {draft.builder_config.blocks.info_grid.items.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-border/60 bg-background/40 px-5 py-8 text-center text-sm text-muted-foreground">
                  Adicione cartoes para informar dress code, horario, hashtag oficial, estacionamento, lista VIP ou orientacoes rapidas.
                </div>
              ) : null}
            </div>
          </SectionCard>

          <SectionCard title="Faixa de redes" description="Agrupe redes e atalhos contextuais como um bloco proprio, com visual mais claro do que uma lista solta de botoes." defaultOpen={false}>
            <div className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-border/60 bg-background/60 p-4">
                <div>
                  <p className="text-sm font-semibold">Faixa de redes</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {draft.builder_config.blocks.social_strip.enabled
                      ? 'O bloco esta ativo na pagina e pode ser reordenado com os demais.'
                      : 'O bloco esta desativado, mas voce pode montar a faixa de redes antes de ligar.'}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" size="sm" onClick={() => addSocialItem('instagram')}>
                    <Plus className="mr-1.5 h-4 w-4" />
                    Adicionar rede
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setBlockEnabled('social_strip', !draft.builder_config.blocks.social_strip.enabled)}
                  >
                    {draft.builder_config.blocks.social_strip.enabled ? 'Desativar bloco' : 'Ativar bloco'}
                  </Button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                {draft.builder_config.blocks.social_strip.items.map((item) => {
                  const provider = hubSocialProviderOptions.find((option) => option.value === item.provider) ?? hubSocialProviderOptions[0];
                  const ItemIcon = getHubIcon(item.icon);
                  const socialStats = buttonInsightsMap.get(item.id);

                  return (
                    <div key={item.id} className="rounded-3xl border border-border/60 bg-background/70 p-4">
                      <div className="flex items-start gap-3">
                        <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-border/60 bg-muted/40">
                          <ItemIcon className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold">{item.label}</p>
                            <Badge variant="secondary">{provider.label}</Badge>
                            {socialStats && socialStats.clicks > 0 ? <Badge variant="outline">{socialStats.clicks} cliques</Badge> : null}
                          </div>
                          <p className="mt-1 text-xs text-muted-foreground">{provider.helper}</p>
                          {socialStats?.last_clicked_at ? <p className="mt-1 text-xs text-muted-foreground">Ultimo clique em {formatDateTimeLabel(socialStats.last_clicked_at)}</p> : null}
                        </div>
                        <Switch checked={item.is_visible} onCheckedChange={(checked) => updateSocialItem(item.id, { is_visible: checked })} />
                        <Button type="button" variant="ghost" size="sm" onClick={() => removeSocialItem(item.id)}>Remover</Button>
                      </div>

                      <div className="mt-4 grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)_180px]">
                        <div className="space-y-2">
                          <Label>Rede</Label>
                          <SocialProviderSelect
                            value={item.provider}
                            onChange={(value) => {
                              const option = hubSocialProviderOptions.find((entry) => entry.value === value) ?? hubSocialProviderOptions[0];
                              updateSocialItem(item.id, {
                                provider: value,
                                label: item.label === provider.label ? option.label : item.label,
                                icon: option.icon,
                              });
                            }}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label>URL</Label>
                          <Input value={item.href ?? ''} onChange={(event) => updateSocialItem(item.id, { href: event.target.value })} placeholder="https://... ou /rota" />
                        </div>
                        <div className="space-y-2">
                          <Label>Rotulo</Label>
                          <Input value={item.label} onChange={(event) => updateSocialItem(item.id, { label: event.target.value })} />
                        </div>
                      </div>

                      <div className="mt-4 flex items-center justify-between rounded-2xl border border-border/60 px-4 py-3">
                        <div>
                          <p className="text-sm font-medium">Abrir em nova aba</p>
                          <p className="text-xs text-muted-foreground">Mantem a pagina aberta quando o visitante segue para fora.</p>
                        </div>
                        <Switch checked={item.opens_in_new_tab} onCheckedChange={(checked) => updateSocialItem(item.id, { opens_in_new_tab: checked })} />
                      </div>
                    </div>
                  );
                })}
              </div>

              {draft.builder_config.blocks.social_strip.items.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-border/60 bg-background/40 px-5 py-8 text-center text-sm text-muted-foreground">
                  Adicione Instagram, WhatsApp, TikTok, playlist, mapa ou ingressos para montar a faixa social da pagina.
                </div>
              ) : null}
            </div>
          </SectionCard>

          <SectionCard title="Patrocinadores e parceiros" description="Monte uma faixa de logos e cartoes para patrocinadores, apoiadores, marcas parceiras e fornecedores oficiais do evento." defaultOpen={false}>
            <div className="space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-border/60 bg-background/60 p-4">
                <div>
                  <p className="text-sm font-semibold">Faixa de parceiros</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {draft.builder_config.blocks.sponsor_strip.enabled
                      ? 'O bloco esta ativo e pode receber logos clicaveis ou apenas marca institucional.'
                      : 'Cadastre os parceiros antes de ligar a faixa na pagina publica.'}
                  </p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button type="button" variant="outline" size="sm" onClick={addSponsorItem}>
                    <Plus className="mr-1.5 h-4 w-4" />
                    Adicionar parceiro
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => setBlockEnabled('sponsor_strip', !draft.builder_config.blocks.sponsor_strip.enabled)}
                  >
                    {draft.builder_config.blocks.sponsor_strip.enabled ? 'Desativar bloco' : 'Ativar bloco'}
                  </Button>
                </div>
              </div>

              <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
                <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px]">
                  <div className="space-y-2">
                    <Label>Titulo da secao</Label>
                    <Input value={draft.builder_config.blocks.sponsor_strip.title} onChange={(event) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, sponsor_strip: { ...builder.blocks.sponsor_strip, title: event.target.value } } }))} placeholder="Patrocinadores" />
                  </div>
                  <div className="space-y-2">
                    <Label>Estilo</Label>
                    <Select value={draft.builder_config.blocks.sponsor_strip.style} onValueChange={(value: ApiHubBuilderConfig['blocks']['sponsor_strip']['style']) => mutateBuilder((builder) => ({ ...builder, blocks: { ...builder.blocks, sponsor_strip: { ...builder.blocks.sponsor_strip, style: value } } }))}>
                      <SelectTrigger><SelectValue /></SelectTrigger>
                      <SelectContent><SelectItem value="logos">Logos</SelectItem><SelectItem value="cards">Cartoes</SelectItem><SelectItem value="compact">Compacto</SelectItem></SelectContent>
                    </Select>
                  </div>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                {draft.builder_config.blocks.sponsor_strip.items.map((item) => {
                  const sponsorStats = buttonInsightsMap.get(item.id);
                  const logoUrl = resolveAssetUrl(item.logo_path) || item.logo_path || null;

                  return (
                    <div key={item.id} className="rounded-3xl border border-border/60 bg-background/70 p-4">
                      <div className="flex items-start gap-3">
                        <div className="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl border border-border/60 bg-muted/40">
                          {logoUrl ? (
                            <img src={logoUrl} alt={item.name} className="h-full w-full object-cover" />
                          ) : (
                            <span className="text-sm font-semibold text-muted-foreground">{item.name.slice(0, 2).toUpperCase()}</span>
                          )}
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold">{item.name}</p>
                            {sponsorStats && sponsorStats.clicks > 0 ? <Badge variant="outline">{sponsorStats.clicks} cliques</Badge> : null}
                          </div>
                          {item.subtitle ? <p className="mt-1 text-xs text-muted-foreground">{item.subtitle}</p> : <p className="mt-1 text-xs text-muted-foreground">Logo e link institucional do parceiro.</p>}
                          {sponsorStats?.last_clicked_at ? <p className="mt-1 text-xs text-muted-foreground">Ultimo clique em {formatDateTimeLabel(sponsorStats.last_clicked_at)}</p> : null}
                        </div>
                        <Switch checked={item.is_visible} onCheckedChange={(checked) => updateSponsorItem(item.id, { is_visible: checked })} />
                        <Button type="button" variant="ghost" size="sm" onClick={() => removeSponsorItem(item.id)}>Remover</Button>
                      </div>

                      <div className="mt-4 grid gap-4">
                        <div className="grid gap-4 lg:grid-cols-2">
                          <div className="space-y-2">
                            <Label>Nome</Label>
                            <Input value={item.name} onChange={(event) => updateSponsorItem(item.id, { name: event.target.value })} />
                          </div>
                          <div className="space-y-2">
                            <Label>Subtitulo</Label>
                            <Input value={item.subtitle ?? ''} onChange={(event) => updateSponsorItem(item.id, { subtitle: event.target.value })} placeholder="Ex.: patrocinador master" />
                          </div>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                          <div className="space-y-2">
                            <div className="flex items-center justify-between gap-3">
                              <Label>Imagem da marca</Label>
                              <div className="flex flex-wrap gap-2">
                                <Button
                                  type="button"
                                  variant="outline"
                                  size="sm"
                                  onClick={() => openSponsorLogoPicker(item.id)}
                                  disabled={sponsorLogoUploadMutation.isPending}
                                >
                                  {sponsorLogoUploadMutation.isPending && pendingSponsorLogoItemId === item.id
                                    ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                                    : <Upload className="mr-1.5 h-4 w-4" />}
                                  Enviar logo
                                </Button>
                                <Button type="button" variant="ghost" size="sm" onClick={() => updateSponsorItem(item.id, { logo_path: '' })}>
                                  Limpar
                                </Button>
                              </div>
                            </div>
                            <Input value={item.logo_path ?? ''} onChange={(event) => updateSponsorItem(item.id, { logo_path: event.target.value })} placeholder="https://... ou /storage/..." />
                            <p className="text-xs text-muted-foreground">Voce pode enviar a logo agora ou colar um caminho manual.</p>
                          </div>
                          <div className="space-y-2">
                            <Label>Link do parceiro</Label>
                            <Input value={item.href ?? ''} onChange={(event) => updateSponsorItem(item.id, { href: event.target.value })} placeholder="https://... ou /rota" />
                          </div>
                        </div>
                      </div>

                      <div className="mt-4 flex items-center justify-between rounded-2xl border border-border/60 px-4 py-3">
                        <div>
                          <p className="text-sm font-medium">Abrir em nova aba</p>
                          <p className="text-xs text-muted-foreground">Ideal para patrocinadores com site proprio ou pagina de campanha.</p>
                        </div>
                        <Switch checked={item.opens_in_new_tab} onCheckedChange={(checked) => updateSponsorItem(item.id, { opens_in_new_tab: checked })} />
                      </div>
                    </div>
                  );
                })}
              </div>

              {draft.builder_config.blocks.sponsor_strip.items.length === 0 ? (
                <div className="rounded-3xl border border-dashed border-border/60 bg-background/40 px-5 py-8 text-center text-sm text-muted-foreground">
                  Adicione logos e links de patrocinadores, apoiadores, parceiros comerciais ou fornecedores oficiais.
                </div>
              ) : null}
            </div>
          </SectionCard>

          <SectionCard title="Botoes da pagina" description="Arraste para ordenar, refine icones e cores e deixe o tema como base antes de personalizar cada botao." defaultOpen={false}>
            <div className="mb-4 flex justify-end">
              <Button type="button" variant="outline" size="sm" onClick={() => updateDraft('buttons', withOrder([...draft.buttons, createCustomButton()]))}>
                <Plus className="mr-1.5 h-4 w-4" />
                Novo botao
              </Button>
            </div>
            <div className="space-y-4">
              {draft.buttons.map((button) => {
                const Icon = getHubIcon(button.icon);
                const presetMeta = button.preset_key ? presetMap.get(button.preset_key) : null;
                const buttonStats = buttonInsightsMap.get(button.id);
                return (
                  <div key={button.id} draggable onDragStart={() => setDragId(button.id)} onDragOver={(event) => event.preventDefault()} onDrop={() => reorderButton(button.id)} className="rounded-3xl border border-border/60 bg-background/70 p-4">
                    <div className="flex items-start gap-3">
                      <div className="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-dashed border-border text-muted-foreground"><GripVertical className="h-4 w-4" /></div>
                      <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-border/60 bg-muted/40"><Icon className="h-5 w-5" /></div>
                      <div className="min-w-0 flex-1">
                        {editingId === button.id ? <Input value={button.label} onChange={(event) => updateButton(button.id, { label: event.target.value })} onBlur={() => setEditingId(null)} autoFocus /> : <button type="button" onDoubleClick={() => setEditingId(button.id)} className="text-left text-sm font-semibold">{button.label}</button>}
                        <div className="mt-1 flex flex-wrap gap-2">
                          <Badge variant="secondary">{button.type === 'preset' ? 'Oficial' : 'Personalizado'}</Badge>
                          {button.preset_key ? <Badge variant="outline">{button.preset_key}</Badge> : null}
                          {button.type === 'preset' ? <Badge variant={button.is_available ? 'outline' : 'secondary'}>{button.is_available ? 'Disponivel' : 'Modulo indisponivel'}</Badge> : null}
                          {buttonStats && buttonStats.clicks > 0 ? <Badge variant="outline">{buttonStats.clicks} cliques</Badge> : null}
                        </div>
                        <p className="mt-2 text-xs text-muted-foreground">{button.type === 'preset' ? (presetMeta?.description ?? 'Acao ligada aos links publicos do evento.') : (button.href ?? 'Defina uma URL customizada para esse botao.')}</p>
                        {buttonStats?.last_clicked_at ? <p className="mt-1 text-xs text-muted-foreground">Ultimo clique em {formatDateTimeLabel(buttonStats.last_clicked_at)}</p> : null}
                      </div>
                      <Switch checked={button.is_visible} onCheckedChange={(checked) => updateButton(button.id, { is_visible: checked })} />
                      {button.type === 'custom' ? <Button type="button" variant="ghost" size="sm" onClick={() => updateDraft('buttons', withOrder(draft.buttons.filter((item) => item.id !== button.id)))}>Remover</Button> : null}
                    </div>
                    <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,220px)_repeat(3,minmax(0,1fr))]">
                      <div className="space-y-2">
                        <Label>Icone</Label>
                        <HubIconSelect value={button.icon} options={hubQuery.data.options.icons} onChange={(value) => updateButton(button.id, { icon: value })} />
                      </div>
                      <NullableColorField label="Fundo" value={button.background_color} fallbackValue={draft.button_style.background_color} onChange={(value) => updateButtonColor(button.id, 'background_color', value)} />
                      <NullableColorField label="Texto" value={button.text_color} fallbackValue={draft.button_style.text_color} onChange={(value) => updateButtonColor(button.id, 'text_color', value)} />
                      <NullableColorField label="Borda" value={button.outline_color} fallbackValue={draft.button_style.outline_color} onChange={(value) => updateButtonColor(button.id, 'outline_color', value)} />
                    </div>
                    {button.type === 'custom' ? (
                      <div className="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_140px]">
                        <div className="space-y-2">
                          <Label>URL</Label>
                          <Input value={button.href ?? ''} onChange={(event) => updateButton(button.id, { href: event.target.value, resolved_url: event.target.value })} placeholder="https://... ou /rota" />
                        </div>
                        <div className="flex items-end justify-between rounded-2xl border border-border/60 px-4 py-3">
                          <div>
                            <p className="text-sm font-medium">Nova aba</p>
                            <p className="text-xs text-muted-foreground">Ideal para links externos</p>
                          </div>
                          <Switch checked={button.opens_in_new_tab} onCheckedChange={(checked) => updateButton(button.id, { opens_in_new_tab: checked })} />
                        </div>
                      </div>
                    ) : null}
                  </div>
                );
              })}
            </div>
          </SectionCard>
        </div>

        <aside className="space-y-4 xl:sticky xl:top-24 xl:self-start">
          <div className="flex items-center justify-end gap-2">
            <Button variant={previewDevice === 'mobile' ? 'secondary' : 'ghost'} size="sm" onClick={() => setPreviewDevice('mobile')}><Smartphone className="mr-1.5 h-4 w-4" />Celular</Button>
            <Button variant={previewDevice === 'desktop' ? 'secondary' : 'ghost'} size="sm" onClick={() => setPreviewDevice('desktop')}><MonitorSmartphone className="mr-1.5 h-4 w-4" />Computador</Button>
          </div>
          <div className={cn('glass overflow-auto rounded-[2rem] border border-border/60 xl:max-h-[calc(100vh-8rem)]', previewDevice === 'mobile' ? 'mx-auto max-w-[360px]' : '')}>
            <HubRenderer event={previewEvent} hub={previewHub} previewMode className={previewDevice === 'mobile' ? 'min-h-[760px]' : 'min-h-[780px]'} innerClassName={previewDevice === 'desktop' ? 'max-w-3xl' : ''} />
          </div>
        </aside>
      </div>
    </motion.div>
  );
}

function LoaderState() {
  return (
    <div className="flex min-h-[50vh] items-center justify-center">
      <Loader2 className="h-6 w-6 animate-spin text-primary" />
    </div>
  );
}

function EmptyState() {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title="Links do evento" description="Nao existe evento com Links habilitado para configurar." />
      <div className="rounded-3xl border border-dashed border-border bg-muted/30 px-6 py-12 text-center text-sm text-muted-foreground">
        Crie um evento com modulo Links para editar esta pagina.
      </div>
    </motion.div>
  );
}

function ErrorState({ title, description }: { title: string; description: string }) {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader title={title} description="Nao foi possivel montar a tela do editor agora." />
      <div className="rounded-3xl border border-destructive/30 bg-destructive/5 px-6 py-8 text-sm text-muted-foreground">
        {description}
      </div>
    </motion.div>
  );
}

function EventPickerState({
  events,
  invalidEventId,
  onSelect,
}: {
  events: EventListItem[];
  invalidEventId: string;
  onSelect: (eventId: string) => void;
}) {
  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
      <PageHeader
        title="Links do evento"
        description="Escolha um evento com o modulo Links para abrir o editor."
      />

      {invalidEventId ? (
        <div className="rounded-3xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-950">
          O evento informado na URL nao esta disponivel nesta lista. Selecione um evento abaixo para continuar.
        </div>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-3">
        {events.map((event) => {
          const coverUrl = resolveAssetUrl(event.cover_image_path) ?? event.cover_image_url ?? null;
          const subtitle = [formatDateLabel(event.starts_at), event.location_name].filter(Boolean).join(' - ');

          return (
            <button
              key={event.id}
              type="button"
              onClick={() => onSelect(String(event.id))}
              className="group overflow-hidden rounded-[1.75rem] border border-border/60 bg-background/70 text-left transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-xl hover:shadow-primary/5"
            >
              <div
                className="relative min-h-[180px] px-5 pb-5 pt-5 text-white"
                style={{ background: `linear-gradient(145deg, ${event.primary_color ?? '#0f172a'}, ${event.secondary_color ?? '#1d4ed8'})` }}
              >
                {coverUrl ? <img src={coverUrl} alt={event.title} className="absolute inset-0 h-full w-full object-cover opacity-30" /> : null}
                <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/20 to-black/75" />
                <div className="relative flex h-full flex-col justify-between gap-6">
                  <div className="flex flex-wrap gap-2">
                    <Badge className="border-0 bg-white/16 text-white hover:bg-white/16">{EVENT_STATUS_LABELS[event.status]}</Badge>
                    <Badge className="border-0 bg-white/12 text-white hover:bg-white/12">Links ativos</Badge>
                  </div>
                  <div className="space-y-2">
                    <h2 className="text-xl font-semibold leading-tight">{event.title}</h2>
                    {subtitle ? <p className="text-sm text-white/85">{subtitle}</p> : null}
                  </div>
                </div>
              </div>
              <div className="space-y-4 p-5">
                <div className="flex flex-wrap gap-2">
                  <Badge variant="outline">{event.slug}</Badge>
                  {event.public_url ? <Badge variant="outline">Link publico pronto</Badge> : null}
                </div>
                <div className="flex items-center justify-between gap-3">
                  <p className="text-sm text-muted-foreground">Abrir o editor da pagina de links deste evento.</p>
                  <span className="text-sm font-semibold text-primary">Editar links</span>
                </div>
              </div>
            </button>
          );
        })}
      </div>
    </motion.div>
  );
}

function SectionCard({
  title,
  description,
  children,
  defaultOpen = true,
}: {
  title: string;
  description: string;
  children: ReactNode;
  defaultOpen?: boolean;
}) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <section className="glass rounded-3xl border border-border/60 p-5">
      <button
        type="button"
        onClick={() => setIsOpen((current) => !current)}
        className="flex w-full items-start justify-between gap-4 text-left"
      >
        <div className="mb-4">
          <h2 className="text-sm font-semibold">{title}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
        <span className="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-2xl border border-border/60 bg-background/70">
          <ChevronDown className={cn('h-4 w-4 transition-transform', isOpen ? 'rotate-180' : '')} />
        </span>
      </button>
      {isOpen ? children : null}
    </section>
  );
}

function ColorField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <div className="flex gap-2">
        <Input type="color" value={normalizeColorValue(value)} onChange={(event) => onChange(event.target.value)} className="h-11 w-14 p-1" />
        <Input value={value} onChange={(event) => onChange(event.target.value)} className="font-mono" />
      </div>
    </div>
  );
}

function NullableColorField({
  label,
  value,
  fallbackValue,
  onChange,
}: {
  label: string;
  value: string | null;
  fallbackValue: string;
  onChange: (value: string | null) => void;
}) {
  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-3">
        <Label>{label}</Label>
        <Button type="button" variant="ghost" size="sm" className="h-auto px-0 text-xs text-muted-foreground" onClick={() => onChange(null)}>
          Herdar tema
        </Button>
      </div>
      <div className="flex gap-2">
        <Input type="color" value={normalizeColorValue(value ?? fallbackValue, fallbackValue)} onChange={(event) => onChange(event.target.value)} className="h-11 w-14 p-1" />
        <Input value={value ?? ''} onChange={(event) => onChange(event.target.value.trim() === '' ? null : event.target.value)} placeholder={fallbackValue} className="font-mono" />
      </div>
    </div>
  );
}

function HubIconSelect({
  value,
  options,
  onChange,
}: {
  value: HubButtonIconKey;
  options: ApiHubIconOption[];
  onChange: (value: HubButtonIconKey) => void;
}) {
  const selected = options.find((option) => option.value === value) ?? options[0];
  const SelectedIcon = getHubIcon(selected?.value ?? value);

  return (
    <Select value={value} onValueChange={(nextValue: HubButtonIconKey) => onChange(nextValue)}>
      <SelectTrigger>
        <span className="flex items-center gap-2">
          <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
            <SelectedIcon className="h-4 w-4" />
          </span>
          <span>{selected?.label ?? 'Selecione'}</span>
        </span>
      </SelectTrigger>
      <SelectContent>
        {options.map((option) => {
          const Icon = getHubIcon(option.value);

          return (
            <SelectItem key={option.value} value={option.value}>
              <span className="flex items-center gap-2">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
                  <Icon className="h-4 w-4" />
                </span>
                <span>{option.label}</span>
              </span>
            </SelectItem>
          );
        })}
      </SelectContent>
    </Select>
  );
}

function SocialProviderSelect({
  value,
  onChange,
}: {
  value: ApiHubSocialProviderKey;
  onChange: (value: ApiHubSocialProviderKey) => void;
}) {
  const selected = hubSocialProviderOptions.find((option) => option.value === value) ?? hubSocialProviderOptions[0];
  const SelectedIcon = getHubIcon(selected.icon);

  return (
    <Select value={value} onValueChange={(nextValue: ApiHubSocialProviderKey) => onChange(nextValue)}>
      <SelectTrigger>
        <span className="flex items-center gap-2">
          <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
            <SelectedIcon className="h-4 w-4" />
          </span>
          <span>{selected.label}</span>
        </span>
      </SelectTrigger>
      <SelectContent>
        {hubSocialProviderOptions.map((option) => {
          const Icon = getHubIcon(option.icon);

          return (
            <SelectItem key={option.value} value={option.value}>
              <span className="flex items-center gap-2">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-border/60 bg-muted/40">
                  <Icon className="h-4 w-4" />
                </span>
                <span>{option.label}</span>
              </span>
            </SelectItem>
          );
        })}
      </SelectContent>
    </Select>
  );
}

function LayoutPresetCard({
  preset,
  active,
  onClick,
}: {
  preset: typeof hubLayoutPresets[number];
  active: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn('w-full rounded-3xl border p-4 text-left transition-colors', active ? 'border-primary bg-primary/5' : 'border-border/60 bg-background/70 hover:border-primary/40')}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">{preset.label}</p>
          <p className="mt-2 text-sm text-muted-foreground">{preset.description}</p>
        </div>
        {active ? <Badge variant="secondary">Ativo</Badge> : null}
      </div>
      <div className="mt-4 grid grid-cols-4 gap-2">
        <span className="h-16 rounded-2xl border border-border/60 bg-muted/60" />
        <span className="h-16 rounded-2xl border border-border/60 bg-background" />
        <span className="h-16 rounded-2xl border border-border/60 bg-background" />
        <span className="h-16 rounded-2xl border border-border/60 bg-muted/40" />
      </div>
    </button>
  );
}

function ThemePresetCard({
  preset,
  active,
  onClick,
}: {
  preset: typeof hubThemePresets[number];
  active: boolean;
  onClick: () => void;
}) {
  const layoutLabel = hubLayoutPresets.find((item) => item.key === preset.layout_key)?.label ?? preset.layout_key;

  return (
    <button
      type="button"
      onClick={onClick}
      className={cn('w-full rounded-3xl border p-4 text-left transition-colors', active ? 'border-primary bg-primary/5' : 'border-border/60 bg-background/70 hover:border-primary/40')}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold">{preset.label}</p>
            <Badge variant="outline">{preset.mood}</Badge>
            {active ? <Badge variant="secondary">Ativo</Badge> : null}
          </div>
          <p className="mt-1 text-xs uppercase tracking-[0.14em] text-muted-foreground">Estrutura base: {layoutLabel}</p>
        </div>
        <div className="flex gap-2">
          {preset.swatches.map((swatch) => (
            <span
              key={swatch}
              className="inline-flex h-8 w-8 rounded-2xl border"
              style={{ backgroundColor: swatch, borderColor: swatch === '#ffffff' ? '#cbd5e1' : 'transparent' }}
            />
          ))}
        </div>
      </div>
      <div className="mt-4 overflow-hidden rounded-[1.5rem] border border-border/60">
        <div
          className="space-y-4 px-4 pb-4 pt-5"
          style={{ background: `linear-gradient(155deg, ${preset.swatches[0]}, ${preset.swatches[1]})` }}
        >
          <span className="inline-flex rounded-full bg-white/16 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-white">
            {preset.label}
          </span>
          <div className="space-y-1 text-white">
            <p className="text-lg font-semibold">Capa editorial</p>
            <p className="text-sm text-white/80">{preset.summary}</p>
          </div>
          <div className="grid grid-cols-3 gap-2">
            <span className="h-10 rounded-2xl bg-white/18" />
            <span className="h-10 rounded-2xl bg-white/12" />
            <span className="h-10 rounded-2xl bg-white/12" />
          </div>
        </div>
        <div className="space-y-3 bg-background/90 px-4 py-4">
          <p className="text-sm text-muted-foreground">{preset.description}</p>
          <div className="flex flex-wrap gap-2">
            {preset.recommended_for.map((item) => (
              <Badge key={item} variant="outline">{item}</Badge>
            ))}
          </div>
        </div>
      </div>
    </button>
  );
}

function SavedPresetCard({
  preset,
  themeLabel,
  layoutLabel,
  onApply,
}: {
  preset: ApiHubPreset;
  themeLabel: string;
  layoutLabel: string;
  onApply: () => void;
}) {
  return (
    <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold">{preset.name}</p>
            <Badge variant="secondary">{themeLabel}</Badge>
            <Badge variant="outline">{layoutLabel}</Badge>
          </div>
          {preset.description ? (
            <p className="mt-2 text-sm text-muted-foreground">{preset.description}</p>
          ) : (
            <p className="mt-2 text-sm text-muted-foreground">Modelo salvo para reaproveitar estrutura, blocos, cores e botoes entre paginas de links da mesma organizacao.</p>
          )}
        </div>
        <Button type="button" size="sm" onClick={onApply}>
          Aplicar nesta pagina
        </Button>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {preset.source_event ? <Badge variant="outline">Origem: {preset.source_event.title}</Badge> : null}
        {preset.creator ? <Badge variant="outline">Criado por {preset.creator.name}</Badge> : null}
        {preset.created_at ? <Badge variant="outline">Salvo em {formatDateLabel(preset.created_at)}</Badge> : null}
      </div>
    </div>
  );
}

function BlockCard({
  title,
  enabled,
  onToggle,
  children,
}: {
  title: string;
  enabled: boolean;
  onToggle: (checked: boolean) => void;
  children: ReactNode;
}) {
  return (
    <div className="rounded-3xl border border-border/60 bg-background/70 p-4">
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">{title}</p>
          <p className="mt-1 text-xs text-muted-foreground">{enabled ? 'Bloco ativo na pagina publica.' : 'Bloco desativado na pagina publica.'}</p>
        </div>
        <Switch checked={enabled} onCheckedChange={onToggle} />
      </div>
      {children}
    </div>
  );
}
