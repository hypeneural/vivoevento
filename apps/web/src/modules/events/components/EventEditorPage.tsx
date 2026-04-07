import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  CalendarDays,
  Clock3,
  Eye,
  Gamepad2,
  Globe,
  ImageIcon,
  Link2,
  Loader2,
  Lock,
  MessageSquare,
  Monitor,
  Plus,
  Save,
  ShieldCheck,
  Smartphone,
  Trash2,
  UploadCloud,
  UserCheck,
  Users,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useFieldArray, useForm, type UseFormReturn } from 'react-hook-form';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { useAuth } from '@/app/providers/AuthProvider';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';
import { WHATSAPP_SETTINGS_PATH } from '@/modules/whatsapp/paths';
import { whatsappService } from '@/modules/whatsapp/api';
import type { WhatsAppInstanceItem } from '@/modules/whatsapp/types';

import { eventsService } from '../services/events.service';
import {
  buildDefaultIntakeChannels,
  buildDefaultIntakeBlacklist,
  buildDefaultIntakeDefaults,
  buildEventIntakeFromDetail,
  resolveEventIntakeEntitlements,
  type EventBlacklistIdentityType,
  type EventIntakeBlacklistEntry,
  type EventIntakeEntitlements,
  type EventIntakeBlacklistSenderSummary,
} from '../intake';
import {
  identityTypeLabel,
  initialsFromName,
  senderPrimaryLabel,
  senderSecondaryLabel,
} from '../sender-utils';
import { TelegramOperationalStatusCard } from './TelegramOperationalStatusCard';
import {
  EVENT_MODERATION_LABELS,
  EVENT_MODERATION_OPTIONS,
  EVENT_MODULE_LABELS,
  EVENT_RETENTION_OPTIONS,
  EVENT_TYPE_LABELS,
  EVENT_TYPE_OPTIONS,
  EVENT_VISIBILITY_LABELS,
  EVENT_VISIBILITY_OPTIONS,
  type ApiEventType,
  type EventBrandingAssetKind,
  type EventDetailItem,
  type EventFormPayload,
  type EventTelegramOperationalStatus,
} from '../types';

const eventFormSchema = z.object({
  client_id: z.string(),
  title: z.string().trim().min(3, 'Informe um titulo com pelo menos 3 caracteres.').max(180, 'Maximo de 180 caracteres.'),
  event_type: z.enum(['wedding', 'birthday', 'fifteen', 'corporate', 'fair', 'graduation', 'other']),
  slug: z.string()
    .trim()
    .max(200, 'Maximo de 200 caracteres.')
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$|^$/, 'Use apenas letras minusculas, numeros e hifens.')
    .optional(),
  starts_at: z.string().optional(),
  ends_at: z.string().optional(),
  location_name: z.string().trim().max(180, 'Maximo de 180 caracteres.').optional(),
  description: z.string().trim().max(2000, 'Maximo de 2000 caracteres.').optional(),
  primary_color: z.string().regex(/^#[0-9a-fA-F]{6}$/, 'Cor principal invalida.'),
  secondary_color: z.string().regex(/^#[0-9a-fA-F]{6}$/, 'Cor secundaria invalida.'),
  cover_image_path: z.string().trim().max(255, 'Maximo de 255 caracteres.').optional(),
  logo_path: z.string().trim().max(255, 'Maximo de 255 caracteres.').optional(),
  visibility: z.enum(['public', 'private', 'unlisted']),
  moderation_mode: z.enum(['none', 'manual', 'ai']),
  retention_days: z.string().refine((value) => {
    const parsed = Number(value);

    return value.trim() !== '' && Number.isInteger(parsed) && parsed >= 1 && parsed <= 365;
  }, 'Selecione uma retencao valida.'),
  face_search: z.object({
    enabled: z.boolean(),
    allow_public_selfie_search: z.boolean(),
    selfie_retention_hours: z.string().regex(/^\d+$/, 'Informe um numero de horas valido.'),
  }),
  intake_defaults: z.object({
    whatsapp_instance_id: z.string(),
    whatsapp_instance_mode: z.enum(['shared', 'dedicated']),
  }),
  intake_channels: z.object({
    whatsapp_groups: z.object({
      enabled: z.boolean(),
      groups: z.array(z.object({
        group_external_id: z.string().trim().max(180, 'Maximo de 180 caracteres.'),
        group_name: z.string().trim().max(180, 'Maximo de 180 caracteres.').optional(),
        is_active: z.boolean(),
        auto_feedback_enabled: z.boolean(),
      })),
    }),
    whatsapp_direct: z.object({
      enabled: z.boolean(),
      media_inbox_code: z.string().trim().max(80, 'Maximo de 80 caracteres.').optional(),
      session_ttl_minutes: z.string().regex(/^\d*$/, 'Informe um numero de minutos valido.'),
    }),
    public_upload: z.object({
      enabled: z.boolean(),
    }),
    telegram: z.object({
      enabled: z.boolean(),
      bot_username: z.string().trim().max(80, 'Maximo de 80 caracteres.').optional(),
      media_inbox_code: z.string().trim().max(80, 'Maximo de 80 caracteres.').optional(),
      session_ttl_minutes: z.string().regex(/^\d*$/, 'Informe um numero de minutos valido.'),
    }),
  }),
  intake_blacklist: z.object({
    entries: z.array(z.object({
      id: z.number().nullable().optional(),
      identity_type: z.enum(['phone', 'lid', 'external_id']),
      identity_value: z.string().trim().max(180, 'Maximo de 180 caracteres.'),
      normalized_phone: z.string().trim().max(40, 'Maximo de 40 caracteres.').nullable().optional(),
      reason: z.string().trim().max(255, 'Maximo de 255 caracteres.').nullable().optional(),
      expires_at: z.string().optional(),
      is_active: z.boolean(),
    })),
  }),
  modules: z.object({
    live: z.boolean(),
    wall: z.boolean(),
    play: z.boolean(),
    hub: z.boolean(),
  }),
}).superRefine((values, context) => {
  if (values.starts_at && values.ends_at) {
    const startsAt = new Date(values.starts_at);
    const endsAt = new Date(values.ends_at);

    if (Number.isNaN(startsAt.getTime()) || Number.isNaN(endsAt.getTime()) || endsAt <= startsAt) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['ends_at'],
        message: 'A data final precisa ser posterior a inicial.',
      });
    }
  }

  const needsWhatsAppInstance = values.intake_channels.whatsapp_groups.enabled || values.intake_channels.whatsapp_direct.enabled;

  if (needsWhatsAppInstance && parseSelectInteger(values.intake_defaults.whatsapp_instance_id) === null) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_defaults', 'whatsapp_instance_id'],
      message: 'Selecione uma instancia WhatsApp para os canais do evento.',
    });
  }

  if (values.intake_channels.whatsapp_direct.enabled && !values.intake_channels.whatsapp_direct.media_inbox_code?.trim()) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_channels', 'whatsapp_direct', 'media_inbox_code'],
      message: 'Informe o codigo de ativacao do WhatsApp direto.',
    });
  }

  if (values.intake_channels.whatsapp_direct.enabled && !values.intake_channels.whatsapp_direct.session_ttl_minutes.trim()) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_channels', 'whatsapp_direct', 'session_ttl_minutes'],
      message: 'Informe o TTL da sessao privada em minutos.',
    });
  }

  if (values.intake_channels.telegram.enabled && !values.intake_channels.telegram.bot_username?.trim()) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_channels', 'telegram', 'bot_username'],
      message: 'Informe o username do bot do Telegram.',
    });
  }

  if (values.intake_channels.telegram.enabled && !values.intake_channels.telegram.media_inbox_code?.trim()) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_channels', 'telegram', 'media_inbox_code'],
      message: 'Informe o codigo de ativacao do Telegram.',
    });
  }

  if (values.intake_channels.telegram.enabled && !values.intake_channels.telegram.session_ttl_minutes.trim()) {
    context.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['intake_channels', 'telegram', 'session_ttl_minutes'],
      message: 'Informe o TTL da sessao privada do Telegram em minutos.',
    });
  }

  values.intake_blacklist.entries.forEach((entry, index) => {
    if (!entry.identity_value.trim()) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['intake_blacklist', 'entries', index, 'identity_value'],
        message: 'Informe o identificador que sera bloqueado.',
      });
    }
  });
});

type EventFormValues = z.infer<typeof eventFormSchema>;
type EventEditorMode = 'create' | 'edit';
type BrandingPreviewState = Partial<Record<EventBrandingAssetKind, string | null>>;

const moduleItems = [
  { key: 'live' as const, label: 'Galeria ao vivo', icon: ImageIcon, description: 'Galeria colaborativa para receber e organizar as fotos do evento.' },
  { key: 'wall' as const, label: 'Telao', icon: Monitor, description: 'Exibicao de fotos no telao com atualizacao em tempo real.' },
  { key: 'play' as const, label: 'Jogos', icon: Gamepad2, description: 'Jogos interativos com fotos do evento para engajar os convidados.' },
  { key: 'hub' as const, label: 'Links', icon: Globe, description: 'Pagina publica com os principais links e acessos do evento.' },
];

const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_UPLOAD_SIZE = 10 * 1024 * 1024;

function slugify(value: string) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 200);
}

function toDateTimeLocal(value?: string | null) {
  if (!value) return '';

  const date = new Date(value);
  const localDate = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));

  return localDate.toISOString().slice(0, 16);
}

function toIsoString(value?: string) {
  if (!value) return null;

  return new Date(value).toISOString();
}

function parsePositiveInteger(value: string): number | null {
  if (!value.trim()) {
    return null;
  }

  const parsed = Number(value);

  return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
}

function parseSelectInteger(value: string): number | null {
  if (value === 'none') {
    return null;
  }

  return parsePositiveInteger(value);
}

function buildRetentionOptions(currentValue: string) {
  const current = parsePositiveInteger(currentValue);

  if (current === null || EVENT_RETENTION_OPTIONS.some((option) => option.value === current)) {
    return EVENT_RETENTION_OPTIONS;
  }

  return [...EVENT_RETENTION_OPTIONS, { value: current, label: `${current} dias` }]
    .sort((left, right) => left.value - right.value);
}

function buildDefaultValues(): EventFormValues {
  const intakeDefaults = buildDefaultIntakeDefaults();
  const intakeChannels = buildDefaultIntakeChannels();
  const intakeBlacklist = buildDefaultIntakeBlacklist();

  return {
    client_id: 'none',
    title: '',
    event_type: 'wedding',
    slug: '',
    starts_at: '',
    ends_at: '',
    location_name: '',
    description: '',
    primary_color: '#f97316',
    secondary_color: '#1d4ed8',
    cover_image_path: '',
    logo_path: '',
    visibility: 'public',
    moderation_mode: 'manual',
    retention_days: '30',
    face_search: {
      enabled: false,
      allow_public_selfie_search: false,
      selfie_retention_hours: '24',
    },
    intake_defaults: {
      whatsapp_instance_id: intakeDefaults.whatsapp_instance_id ? String(intakeDefaults.whatsapp_instance_id) : 'none',
      whatsapp_instance_mode: intakeDefaults.whatsapp_instance_mode,
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: intakeChannels.whatsapp_groups.enabled,
        groups: intakeChannels.whatsapp_groups.groups,
      },
      whatsapp_direct: {
        enabled: intakeChannels.whatsapp_direct.enabled,
        media_inbox_code: intakeChannels.whatsapp_direct.media_inbox_code ?? '',
        session_ttl_minutes: String(intakeChannels.whatsapp_direct.session_ttl_minutes ?? 120),
      },
      public_upload: {
        enabled: intakeChannels.public_upload.enabled,
      },
      telegram: {
        enabled: intakeChannels.telegram.enabled,
        bot_username: intakeChannels.telegram.bot_username ?? '',
        media_inbox_code: intakeChannels.telegram.media_inbox_code ?? '',
        session_ttl_minutes: String(intakeChannels.telegram.session_ttl_minutes ?? 180),
      },
    },
    intake_blacklist: {
      entries: intakeBlacklist.entries,
    },
    modules: {
      live: true,
      wall: false,
      play: false,
      hub: true,
    },
  };
}

function buildFormValues(event: EventDetailItem): EventFormValues {
  const intakeState = buildEventIntakeFromDetail(event);

  return {
    client_id: event.client_id ? String(event.client_id) : 'none',
    title: event.title,
    event_type: event.event_type,
    slug: event.slug ?? '',
    starts_at: toDateTimeLocal(event.starts_at),
    ends_at: toDateTimeLocal(event.ends_at),
    location_name: event.location_name ?? '',
    description: event.description ?? '',
    primary_color: event.primary_color ?? '#f97316',
    secondary_color: event.secondary_color ?? '#1d4ed8',
    cover_image_path: event.cover_image_path ?? '',
    logo_path: event.logo_path ?? '',
    visibility: event.visibility ?? 'public',
    moderation_mode: event.moderation_mode ?? 'manual',
    retention_days: String(event.retention_days ?? 30),
    face_search: {
      enabled: event.face_search?.enabled ?? false,
      allow_public_selfie_search: event.face_search?.allow_public_selfie_search ?? false,
      selfie_retention_hours: String(event.face_search?.selfie_retention_hours ?? 24),
    },
    intake_defaults: {
      whatsapp_instance_id: intakeState.intake_defaults.whatsapp_instance_id
        ? String(intakeState.intake_defaults.whatsapp_instance_id)
        : 'none',
      whatsapp_instance_mode: intakeState.intake_defaults.whatsapp_instance_mode,
    },
    intake_channels: {
      whatsapp_groups: {
        enabled: intakeState.intake_channels.whatsapp_groups.enabled,
        groups: intakeState.intake_channels.whatsapp_groups.groups.map((group) => ({
          group_external_id: group.group_external_id,
          group_name: group.group_name ?? '',
          is_active: group.is_active,
          auto_feedback_enabled: group.auto_feedback_enabled,
        })),
      },
      whatsapp_direct: {
        enabled: intakeState.intake_channels.whatsapp_direct.enabled,
        media_inbox_code: intakeState.intake_channels.whatsapp_direct.media_inbox_code ?? '',
        session_ttl_minutes: String(intakeState.intake_channels.whatsapp_direct.session_ttl_minutes ?? 120),
      },
      public_upload: {
        enabled: intakeState.intake_channels.public_upload.enabled,
      },
      telegram: {
        enabled: intakeState.intake_channels.telegram.enabled,
        bot_username: intakeState.intake_channels.telegram.bot_username ?? '',
        media_inbox_code: intakeState.intake_channels.telegram.media_inbox_code ?? '',
        session_ttl_minutes: String(intakeState.intake_channels.telegram.session_ttl_minutes ?? 180),
      },
    },
    intake_blacklist: {
      entries: intakeState.intake_blacklist.entries.map((entry) => ({
        id: entry.id ?? null,
        identity_type: entry.identity_type,
        identity_value: entry.identity_value,
        normalized_phone: entry.normalized_phone ?? null,
        reason: entry.reason ?? '',
        expires_at: toDateTimeLocal(entry.expires_at),
        is_active: entry.is_active,
      })),
    },
    modules: {
      live: event.enabled_modules.includes('live'),
      wall: event.enabled_modules.includes('wall'),
      play: event.enabled_modules.includes('play'),
      hub: event.enabled_modules.includes('hub'),
    },
  };
}

function buildPayload(
  values: EventFormValues,
  organizationId?: number,
  options?: { includeIntake?: boolean },
): EventFormPayload {
  const payload: EventFormPayload = {
    organization_id: organizationId,
    client_id: values.client_id === 'none' ? null : Number(values.client_id),
    title: values.title.trim(),
    event_type: values.event_type,
    slug: values.slug?.trim() ? values.slug.trim() : null,
    starts_at: values.starts_at ? toIsoString(values.starts_at) : null,
    ends_at: values.ends_at ? toIsoString(values.ends_at) : null,
    location_name: values.location_name?.trim() ? values.location_name.trim() : null,
    description: values.description?.trim() ? values.description.trim() : null,
    branding: {
      primary_color: values.primary_color,
      secondary_color: values.secondary_color,
      cover_image_path: values.cover_image_path?.trim() || null,
      logo_path: values.logo_path?.trim() || null,
    },
    modules: values.modules,
    privacy: {
      visibility: values.visibility,
      moderation_mode: values.moderation_mode,
      retention_days: parsePositiveInteger(values.retention_days) ?? 30,
    },
    face_search: {
      enabled: values.face_search.enabled,
      allow_public_selfie_search: values.face_search.allow_public_selfie_search,
      selfie_retention_hours: Number(values.face_search.selfie_retention_hours),
    },
  };

  if (options?.includeIntake) {
    payload.intake_defaults = {
      whatsapp_instance_id: parseSelectInteger(values.intake_defaults.whatsapp_instance_id),
      whatsapp_instance_mode: values.intake_defaults.whatsapp_instance_mode,
    };
    payload.intake_channels = {
      whatsapp_groups: {
        enabled: values.intake_channels.whatsapp_groups.enabled,
        groups: values.intake_channels.whatsapp_groups.groups
          .map((group) => ({
            group_external_id: group.group_external_id.trim(),
            group_name: group.group_name?.trim() || null,
            is_active: group.is_active,
            auto_feedback_enabled: group.auto_feedback_enabled,
          }))
          .filter((group) => group.group_external_id.length > 0),
      },
      whatsapp_direct: {
        enabled: values.intake_channels.whatsapp_direct.enabled,
        media_inbox_code: values.intake_channels.whatsapp_direct.media_inbox_code?.trim() || null,
        session_ttl_minutes: values.intake_channels.whatsapp_direct.session_ttl_minutes.trim()
          ? Number(values.intake_channels.whatsapp_direct.session_ttl_minutes)
          : null,
      },
      public_upload: {
        enabled: values.intake_channels.public_upload.enabled,
      },
      telegram: {
        enabled: values.intake_channels.telegram.enabled,
        bot_username: values.intake_channels.telegram.bot_username?.trim() || null,
        media_inbox_code: values.intake_channels.telegram.media_inbox_code?.trim() || null,
        session_ttl_minutes: values.intake_channels.telegram.session_ttl_minutes.trim()
          ? Number(values.intake_channels.telegram.session_ttl_minutes)
          : null,
      },
    };
    payload.intake_blacklist = {
      entries: values.intake_blacklist.entries
        .map((entry) => ({
          id: entry.id ?? null,
          identity_type: entry.identity_type,
          identity_value: entry.identity_value.trim(),
          normalized_phone: entry.normalized_phone?.trim() || null,
          reason: entry.reason?.trim() || null,
          expires_at: entry.expires_at ? toIsoString(entry.expires_at) : null,
          is_active: entry.is_active,
        }))
        .filter((entry) => entry.identity_value.length > 0),
    };
  }

  return payload;
}

function formatDateTime(value?: string | null) {
  if (!value) {
    return 'Agora';
  }

  return new Date(value).toLocaleString('pt-BR');
}

interface EventIntakeChannelsSectionProps {
  mode: EventEditorMode;
  form: UseFormReturn<EventFormValues>;
  event: EventDetailItem | null;
  entitlements: EventIntakeEntitlements | null;
  telegramOperationalStatus: EventTelegramOperationalStatus | null;
  telegramOperationalStatusLoading: boolean;
  telegramOperationalStatusError: boolean;
  instances: WhatsAppInstanceItem[];
  instancesLoading: boolean;
  instancesError: boolean;
}

function EventIntakeChannelsSection({
  mode,
  form,
  event,
  entitlements,
  telegramOperationalStatus,
  telegramOperationalStatusLoading,
  telegramOperationalStatusError,
  instances,
  instancesLoading,
  instancesError,
}: EventIntakeChannelsSectionProps) {
  const isCreateMode = mode === 'create';
  const groupsFieldArray = useFieldArray({
    control: form.control,
    name: 'intake_channels.whatsapp_groups.groups',
  });

  const whatsappGroupsEnabled = form.watch('intake_channels.whatsapp_groups.enabled');
  const whatsappDirectEnabled = form.watch('intake_channels.whatsapp_direct.enabled');
  const publicUploadEnabled = form.watch('intake_channels.public_upload.enabled');
  const telegramEnabled = form.watch('intake_channels.telegram.enabled');
  const telegramBotUsername = form.watch('intake_channels.telegram.bot_username');
  const telegramInboxCode = form.watch('intake_channels.telegram.media_inbox_code');
  const whatsappInstanceMode = form.watch('intake_defaults.whatsapp_instance_mode');
  const groupCount = groupsFieldArray.fields.length;
  const whatsappEntitlements = entitlements ?? resolveEventIntakeEntitlements(null);
  const maxGroups = whatsappEntitlements.maxWhatsappGroups;
  const groupsLimitReached = maxGroups !== null && groupCount >= maxGroups;

  const renderAvailabilityBadge = (enabled: boolean, hint?: string) => (
    <Badge variant={enabled ? 'outline' : 'secondary'} title={hint}>
      {enabled ? 'Disponivel' : 'Bloqueado'}
    </Badge>
  );

  if (isCreateMode) {
    return (
      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
        <div className="mb-5">
          <h2 className="text-sm font-semibold">Canais de recebimento</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Salve o evento primeiro para configurar grupos, WhatsApp direto, instancia e link de envio com o evento ja provisionado.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold">WhatsApp grupos</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Vincule grupos ao evento com o codigo de ativacao depois do primeiro save.
                </p>
              </div>
              <Users className="h-4 w-4 text-primary" />
            </div>
          </div>

          <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold">WhatsApp direto</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Gere o codigo de envio e a sessao privada do evento apos criar o cadastro base.
                </p>
              </div>
              <MessageSquare className="h-4 w-4 text-primary" />
            </div>
          </div>

          <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm font-semibold">Link de upload</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  O slug de envio e gerado automaticamente e pode ser ativado no editor completo.
                </p>
              </div>
              <UploadCloud className="h-4 w-4 text-primary" />
            </div>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold">Canais de recebimento</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Configure por onde o evento recebe midias e qual instancia WhatsApp sera usada no intake.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          {renderAvailabilityBadge(whatsappEntitlements.whatsappGroupsEnabled || whatsappEntitlements.whatsappDirectEnabled, 'Capacidades WhatsApp resolvidas para este evento')}
          {renderAvailabilityBadge(whatsappEntitlements.publicUploadEnabled, 'Canal de upload publico')}
          {renderAvailabilityBadge(whatsappEntitlements.telegramEnabled, 'Telegram privado direto no bot')}
        </div>
      </div>

      <div className="space-y-4">
        <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold">Instancia WhatsApp padrao</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Grupos e WhatsApp direto usam esta instancia como origem padrao do evento.
              </p>
            </div>
            <Smartphone className="h-4 w-4 text-primary" />
          </div>

          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <FormField
              control={form.control}
              name="intake_defaults.whatsapp_instance_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Instancia</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione uma instancia" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="none">Sem instancia selecionada</SelectItem>
                      {parseSelectInteger(field.value) !== null
                        && !instances.some((instance) => instance.id === parseSelectInteger(field.value)) ? (
                        <SelectItem value={field.value}>
                          Instancia atual #{field.value}
                        </SelectItem>
                      ) : null}
                      {instances.map((instance) => (
                        <SelectItem key={instance.id} value={String(instance.id)}>
                          {instance.name} · {instance.status}{instance.is_default ? ' · padrao' : ''}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    {instancesLoading
                      ? 'Carregando instancias WhatsApp...'
                      : instancesError
                        ? 'Nao foi possivel carregar as instancias agora.'
                        : instances.length === 0
                          ? 'Nenhuma instancia encontrada para a organizacao atual.'
                          : 'Use a instancia compartilhada da organizacao ou escolha uma dedicada quando o pacote permitir.'}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="intake_defaults.whatsapp_instance_mode"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Modo da instancia</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o modo" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="shared" disabled={!whatsappEntitlements.sharedInstanceEnabled}>
                        Compartilhada
                      </SelectItem>
                      <SelectItem value="dedicated" disabled={!whatsappEntitlements.dedicatedInstanceEnabled}>
                        Dedicada
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    {whatsappInstanceMode === 'dedicated'
                      ? 'A instancia dedicada fica exclusiva para este evento quando o entitlement permitir.'
                      : 'A instancia compartilhada pode ser reaproveitada por outros eventos da mesma organizacao.'}
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          {instances.length === 0 ? (
            <div className="mt-4 rounded-2xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
              Configure uma instancia primeiro em <Link className="font-medium text-primary underline-offset-4 hover:underline" to={WHATSAPP_SETTINGS_PATH}>Configuracoes de WhatsApp</Link>.
            </div>
          ) : null}
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <div className="flex flex-wrap items-center gap-2">
                  <p className="text-sm font-semibold">WhatsApp grupos</p>
                  {renderAvailabilityBadge(
                    whatsappEntitlements.whatsappGroupsEnabled,
                    maxGroups !== null ? `Limite atual: ${maxGroups} grupo(s)` : 'Sem limite explicito no entitlement atual',
                  )}
                  {maxGroups !== null ? <Badge variant="outline">Limite {maxGroups}</Badge> : null}
                </div>
                <p className="mt-1 text-xs text-muted-foreground">
                  Vincule grupos observados ou cadastre manualmente o `group_external_id`. O autovinculo por `#ATIVAR#codigo` entra na proxima etapa.
                </p>
              </div>
              <FormField
                control={form.control}
                name="intake_channels.whatsapp_groups.enabled"
                render={({ field }) => (
                  <FormItem className="flex items-center gap-3">
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={field.onChange}
                        disabled={!whatsappEntitlements.whatsappGroupsEnabled}
                      />
                    </FormControl>
                  </FormItem>
                )}
              />
            </div>

            {whatsappGroupsEnabled ? (
              <div className="mt-4 space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="text-xs text-muted-foreground">
                    {groupCount === 0
                      ? 'Nenhum grupo vinculado ainda.'
                      : `${groupCount} grupo(s) configurado(s) para este evento.`}
                  </p>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => groupsFieldArray.append({
                      group_external_id: '',
                      group_name: '',
                      is_active: true,
                      auto_feedback_enabled: true,
                    })}
                    disabled={!whatsappEntitlements.whatsappGroupsEnabled || groupsLimitReached}
                  >
                    <Plus className="h-4 w-4" />
                    Adicionar grupo
                  </Button>
                </div>

                {groupsLimitReached ? (
                  <p className="text-xs text-amber-600">
                    O limite de grupos deste evento foi atingido.
                  </p>
                ) : null}

                {groupsFieldArray.fields.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                    Use o `phone` do grupo da Z-API como `group_external_id` e um nome amigavel para operacao.
                  </div>
                ) : null}

                {groupsFieldArray.fields.map((field, index) => (
                  <div key={field.id} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)_auto]">
                      <div className="space-y-2">
                        <FormLabel>Group external id</FormLabel>
                        <Input
                          {...form.register(`intake_channels.whatsapp_groups.groups.${index}.group_external_id`)}
                          placeholder="120363425796926861-group"
                        />
                        <FormMessage>
                          {form.formState.errors.intake_channels?.whatsapp_groups?.groups?.[index]?.group_external_id?.message}
                        </FormMessage>
                      </div>

                      <div className="space-y-2">
                        <FormLabel>Nome do grupo</FormLabel>
                        <Input
                          {...form.register(`intake_channels.whatsapp_groups.groups.${index}.group_name`)}
                          placeholder="Evento vivo 1"
                        />
                        <FormMessage>
                          {form.formState.errors.intake_channels?.whatsapp_groups?.groups?.[index]?.group_name?.message}
                        </FormMessage>
                      </div>

                      <div className="flex items-start justify-end">
                        <Button
                          type="button"
                          variant="ghost"
                          className="text-destructive hover:text-destructive"
                          onClick={() => groupsFieldArray.remove(index)}
                        >
                          <Trash2 className="h-4 w-4" />
                          Remover
                        </Button>
                      </div>
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                      <FormField
                        control={form.control}
                        name={`intake_channels.whatsapp_groups.groups.${index}.is_active`}
                        render={({ field: groupField }) => (
                          <FormItem className="rounded-2xl border border-border/60 bg-muted/20 p-3">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <FormLabel className="text-sm">Grupo ativo</FormLabel>
                                <FormDescription className="mt-1 text-xs">
                                  Desative sem perder o historico do binding.
                                </FormDescription>
                              </div>
                              <FormControl>
                                <Switch checked={groupField.value} onCheckedChange={groupField.onChange} />
                              </FormControl>
                            </div>
                          </FormItem>
                        )}
                      />

                      <FormField
                        control={form.control}
                        name={`intake_channels.whatsapp_groups.groups.${index}.auto_feedback_enabled`}
                        render={({ field: groupField }) => (
                          <FormItem className="rounded-2xl border border-border/60 bg-muted/20 p-3">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <FormLabel className="text-sm">Feedback automatico</FormLabel>
                                <FormDescription className="mt-1 text-xs">
                                  Mantem a flag operacional para as proximas fases de reacao.
                                </FormDescription>
                              </div>
                              <FormControl>
                                <Switch checked={groupField.value} onCheckedChange={groupField.onChange} />
                              </FormControl>
                            </div>
                          </FormItem>
                        )}
                      />
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="mt-4 rounded-2xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                Ative o canal para vincular grupos manualmente e preparar o intake por WhatsApp coletivo.
              </div>
            )}
          </div>

          <div className="space-y-4">
            <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold">WhatsApp direto</p>
                    {renderAvailabilityBadge(whatsappEntitlements.whatsappDirectEnabled)}
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Recebe fotos e videos por DM apos ativacao do codigo do evento.
                  </p>
                </div>
                <FormField
                  control={form.control}
                  name="intake_channels.whatsapp_direct.enabled"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-3">
                      <FormControl>
                        <Switch
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          disabled={!whatsappEntitlements.whatsappDirectEnabled}
                        />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </div>

              <div className="mt-4 grid gap-4">
                <FormField
                  control={form.control}
                  name="intake_channels.whatsapp_direct.media_inbox_code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Codigo do evento</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          value={field.value ?? ''}
                          placeholder="Ex: ANAEJOAO"
                          disabled={!whatsappDirectEnabled}
                        />
                      </FormControl>
                      <FormDescription>
                        O convidado envia esse codigo em DM para abrir a sessao privada de envio.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="intake_channels.whatsapp_direct.session_ttl_minutes"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>TTL da sessao privada (minutos)</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          type="number"
                          min={1}
                          max={4320}
                          disabled={!whatsappDirectEnabled}
                          placeholder="120"
                        />
                      </FormControl>
                      <FormDescription>
                        O comando `sair` e o encerramento automatico entram na proxima fase; o TTL ja fica persistido no canal.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </div>

            <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold">Link de upload</p>
                    {renderAvailabilityBadge(whatsappEntitlements.publicUploadEnabled)}
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Controla se o slug de upload do evento fica ativo como canal canonico de recebimento.
                  </p>
                </div>
                <FormField
                  control={form.control}
                  name="intake_channels.public_upload.enabled"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-3">
                      <FormControl>
                        <Switch
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          disabled={!whatsappEntitlements.publicUploadEnabled}
                        />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </div>

              {publicUploadEnabled && event?.upload_url ? (
                <div className="mt-4 rounded-2xl border border-border/60 bg-muted/20 p-3 text-sm text-muted-foreground">
                  URL atual: <span className="font-mono text-foreground">{event.upload_url}</span>
                </div>
              ) : null}
            </div>

            <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold">Telegram</p>
                    {renderAvailabilityBadge(whatsappEntitlements.telegramEnabled)}
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Intake privado direto no bot. O convidado entra por `/start CODIGO` ou pelo codigo puro, envia a midia no chat privado e sai com `SAIR`.
                  </p>
                </div>
                <FormField
                  control={form.control}
                  name="intake_channels.telegram.enabled"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-3">
                      <FormControl>
                        <Switch
                          checked={field.value}
                          onCheckedChange={field.onChange}
                          disabled={!whatsappEntitlements.telegramEnabled}
                        />
                      </FormControl>
                    </FormItem>
                  )}
                />
              </div>

              <div className="mt-4 grid gap-4">
                <FormField
                  control={form.control}
                  name="intake_channels.telegram.bot_username"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Username do bot</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          value={field.value ?? ''}
                          placeholder="Ex: eventovivoBot"
                          disabled={!telegramEnabled}
                        />
                      </FormControl>
                      <FormDescription>
                        Informe somente o username sem `@`. Ele sera usado para deep link e comunicacao operacional do canal.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="intake_channels.telegram.media_inbox_code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Codigo do evento no Telegram</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          value={field.value ?? ''}
                          placeholder="Ex: TGTEST406"
                          disabled={!telegramEnabled}
                        />
                      </FormControl>
                      <FormDescription>
                        Esse codigo abre a sessao privada do evento no bot e tambem vira o identificador do `EventChannel`.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="intake_channels.telegram.session_ttl_minutes"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>TTL da sessao privada do Telegram (minutos)</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          type="number"
                          min={1}
                          max={4320}
                          disabled={!telegramEnabled}
                          placeholder="180"
                        />
                      </FormControl>
                      <FormDescription>
                        A sessao fica associada ao chat privado do usuario enquanto estiver ativa. O bloqueio do evento tambem passa a valer para o Telegram por `ID externo`.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              {telegramEnabled ? (
                <div className="mt-4 rounded-2xl border border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
                  <p className="font-medium text-foreground">Deep link previsto</p>
                  <p className="mt-2 font-mono text-xs text-foreground">
                    {telegramBotUsername?.trim() && telegramInboxCode?.trim()
                      ? `https://t.me/${telegramBotUsername.trim().replace(/^@/, '')}?start=${telegramInboxCode.trim().toUpperCase()}`
                      : 'Preencha o username do bot e o codigo para gerar o deep link.'}
                  </p>
                  <p className="mt-2">
                    Escopo do V1: somente conversa privada, tipos `text`, `photo`, `video` e `document`, com feedback operacional e blacklist por remetente.
                  </p>
                </div>
              ) : null}

              <TelegramOperationalStatusCard
                status={telegramOperationalStatus}
                loading={telegramOperationalStatusLoading}
                isError={telegramOperationalStatusError}
              />
            </div>

            <div className="rounded-3xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
              <div className="flex items-start gap-3">
                <Lock className="mt-0.5 h-4 w-4 text-primary" />
                <div>
                  <p className="font-medium text-foreground">Operacao avancada do intake</p>
                  <p className="mt-1">
                    O editor agora controla grupos, DM, Telegram privado, upload, instancia e blacklist. Os proximos incrementos desta trilha ficam no refinamento de automacoes, analytics e observabilidade operacional do intake.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

interface EventBlacklistSectionProps {
  mode: EventEditorMode;
  form: UseFormReturn<EventFormValues>;
  event: EventDetailItem | null;
  entitlements: EventIntakeEntitlements | null;
}

function EventBlacklistSection({
  mode,
  form,
  event,
  entitlements,
}: EventBlacklistSectionProps) {
  const isCreateMode = mode === 'create';
  const entriesFieldArray = useFieldArray({
    control: form.control,
    name: 'intake_blacklist.entries',
  });

  const blacklistEnabled = entitlements?.blacklistEnabled ?? false;
  const senderSummaries = event?.intake_blacklist?.senders ?? [];

  const findEntryIndex = (identityType: EventBlacklistIdentityType, identityValue: string) => (
    form.getValues('intake_blacklist.entries').findIndex((entry) => (
      entry.identity_type === identityType
      && entry.identity_value.trim() === identityValue.trim()
    ))
  );

  const getEntryForSender = (sender: EventIntakeBlacklistSenderSummary): EventFormValues['intake_blacklist']['entries'][number] | null => {
    const index = findEntryIndex(sender.recommended_identity_type, sender.recommended_identity_value);

    return index >= 0 ? form.getValues(`intake_blacklist.entries.${index}`) : null;
  };

  const setSenderBlocked = (sender: EventIntakeBlacklistSenderSummary, checked: boolean) => {
    const index = findEntryIndex(sender.recommended_identity_type, sender.recommended_identity_value);

    if (index >= 0) {
      form.setValue(`intake_blacklist.entries.${index}.is_active`, checked, { shouldDirty: true, shouldValidate: true });
      return;
    }

    entriesFieldArray.append({
      id: null,
      identity_type: sender.recommended_identity_type,
      identity_value: sender.recommended_identity_value,
      normalized_phone: sender.recommended_normalized_phone ?? sender.sender_phone ?? null,
      reason: 'Bloqueado manualmente pelo gestor.',
      expires_at: '',
      is_active: checked,
    });
  };

  const setSenderExpiresAt = (sender: EventIntakeBlacklistSenderSummary, value: string) => {
    const index = findEntryIndex(sender.recommended_identity_type, sender.recommended_identity_value);

    if (index >= 0) {
      form.setValue(`intake_blacklist.entries.${index}.expires_at`, value, { shouldDirty: true, shouldValidate: true });
      return;
    }

    entriesFieldArray.append({
      id: null,
      identity_type: sender.recommended_identity_type,
      identity_value: sender.recommended_identity_value,
      normalized_phone: sender.recommended_normalized_phone ?? sender.sender_phone ?? null,
      reason: 'Bloqueado manualmente pelo gestor.',
      expires_at: value,
      is_active: true,
    });
  };

  if (isCreateMode) {
    return (
      <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
        <div className="flex items-start gap-3">
          <Lock className="mt-0.5 h-4 w-4 text-primary" />
          <div>
            <h2 className="text-sm font-semibold">Blacklist de remetentes</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Salve o evento primeiro para acompanhar quem ja enviou midias e configurar bloqueios temporarios por telefone, `@LID` ou `ID externo` do provider, como o Telegram.
            </p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
      <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold">Blacklist de remetentes</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Bloqueie remetentes por telefone, `@LID` ou `ID externo`, acompanhe quem ja enviou midias e defina bloqueios temporarios por evento.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Badge variant={blacklistEnabled ? 'outline' : 'secondary'}>
            {blacklistEnabled ? 'Bloqueio habilitado' : 'Bloqueio indisponivel'}
          </Badge>
          <Badge variant="outline">{senderSummaries.length} remetente(s)</Badge>
        </div>
      </div>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.9fr)]">
        <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
          <div className="mb-4 flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold">Remetentes relacionados ao evento</p>
              <p className="mt-1 text-xs text-muted-foreground">
                O switch cria ou atualiza o bloqueio recomendado do remetente. O prazo e opcional.
              </p>
            </div>
            <Badge variant="outline">Multicanal</Badge>
          </div>

          {senderSummaries.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
              Nenhum remetente relacionado ainda. Assim que grupos ou DMs enviarem conteudo, os remetentes aparecem aqui com contagem de midias.
            </div>
          ) : (
            <ScrollArea className="w-full">
              <div className="min-w-[760px]">
                <Table>
                  <TableHeader>
                    <TableRow className="border-border/40">
                      <TableHead>Remetente</TableHead>
                      <TableHead>Identidade</TableHead>
                      <TableHead>Midias</TableHead>
                      <TableHead>Ultima atividade</TableHead>
                      <TableHead className="w-[120px]">Bloquear</TableHead>
                      <TableHead className="w-[210px]">Bloqueio ate</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {senderSummaries.map((sender) => {
                      const entry = getEntryForSender(sender);
                      const isBlocked = entry?.is_active ?? sender.blocked;
                      const expiresAt = entry?.expires_at
                        ?? (sender.blocking_expires_at ? toDateTimeLocal(sender.blocking_expires_at) : '');

                      return (
                        <TableRow key={`${sender.recommended_identity_type}:${sender.recommended_identity_value}`} className="border-border/30">
                          <TableCell>
                            <div className="flex items-center gap-3">
                              <Avatar className="h-10 w-10 border border-border/60">
                                <AvatarImage src={sender.sender_avatar_url ?? undefined} alt={senderPrimaryLabel(sender)} />
                                <AvatarFallback>{initialsFromName(sender.sender_name)}</AvatarFallback>
                              </Avatar>
                              <div className="min-w-0">
                                <p className="truncate text-sm font-medium">{senderPrimaryLabel(sender)}</p>
                                <p className="truncate text-xs text-muted-foreground">{senderSecondaryLabel(sender)}</p>
                              </div>
                            </div>
                          </TableCell>
                          <TableCell>
                            <div className="space-y-1">
                              <Badge variant="outline">
                                {identityTypeLabel(sender.recommended_identity_type)} padrao
                              </Badge>
                              {sender.sender_lid ? (
                                <p className="truncate text-xs text-muted-foreground">{sender.sender_lid}</p>
                              ) : null}
                            </div>
                          </TableCell>
                          <TableCell>
                            <div className="space-y-1 text-sm">
                              <p>{sender.media_count} midia(s)</p>
                              <p className="text-xs text-muted-foreground">{sender.inbound_count} webhook(s)</p>
                            </div>
                          </TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {formatDateTime(sender.last_seen_at)}
                          </TableCell>
                          <TableCell>
                            <Switch
                              checked={isBlocked}
                              onCheckedChange={(checked) => setSenderBlocked(sender, checked)}
                              disabled={!blacklistEnabled}
                            />
                          </TableCell>
                          <TableCell>
                            <Input
                              type="datetime-local"
                              value={expiresAt}
                              onChange={(eventValue) => setSenderExpiresAt(sender, eventValue.target.value)}
                              disabled={!blacklistEnabled || !isBlocked}
                            />
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              </div>
            </ScrollArea>
          )}
        </div>

        <div className="rounded-3xl border border-border/60 bg-background/60 p-4">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold">Bloqueios configurados</p>
              <p className="mt-1 text-xs text-muted-foreground">
                Cadastre bloqueios manuais, ajuste prazo e ative ou desative sem perder historico.
              </p>
            </div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => entriesFieldArray.append({
                id: null,
                identity_type: 'phone',
                identity_value: '',
                normalized_phone: null,
                reason: '',
                expires_at: '',
                is_active: true,
              })}
              disabled={!blacklistEnabled}
            >
              <Plus className="h-4 w-4" />
              Novo bloqueio
            </Button>
          </div>

          {entriesFieldArray.fields.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-border/60 bg-muted/20 p-4 text-sm text-muted-foreground">
              Nenhum bloqueio configurado.
            </div>
          ) : (
            <div className="space-y-3">
              {entriesFieldArray.fields.map((field, index) => (
                <div key={field.id} className="rounded-2xl border border-border/60 bg-background/70 p-4">
                  <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                      control={form.control}
                      name={`intake_blacklist.entries.${index}.identity_type`}
                      render={({ field: currentField }) => (
                        <FormItem>
                          <FormLabel>Tipo</FormLabel>
                          <Select value={currentField.value} onValueChange={currentField.onChange}>
                            <FormControl>
                              <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                              </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                              <SelectItem value="phone">Telefone</SelectItem>
                              <SelectItem value="lid">@LID</SelectItem>
                              <SelectItem value="external_id">External ID</SelectItem>
                            </SelectContent>
                          </Select>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name={`intake_blacklist.entries.${index}.identity_value`}
                      render={({ field: currentField }) => (
                        <FormItem>
                          <FormLabel>Identificador</FormLabel>
                          <FormControl>
                            <Input {...currentField} value={currentField.value ?? ''} placeholder="554899999999 ou 11111111111111@lid" />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name={`intake_blacklist.entries.${index}.expires_at`}
                      render={({ field: currentField }) => (
                        <FormItem>
                          <FormLabel>Bloqueio ate</FormLabel>
                          <FormControl>
                            <Input {...currentField} type="datetime-local" value={currentField.value ?? ''} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name={`intake_blacklist.entries.${index}.is_active`}
                      render={({ field: currentField }) => (
                        <FormItem className="rounded-2xl border border-border/60 bg-muted/20 p-4">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <FormLabel className="text-sm">Bloqueio ativo</FormLabel>
                              <FormDescription className="mt-1 text-xs">
                                Desative para manter o cadastro sem bloquear novas mensagens.
                              </FormDescription>
                            </div>
                            <FormControl>
                              <Switch checked={currentField.value} onCheckedChange={currentField.onChange} disabled={!blacklistEnabled} />
                            </FormControl>
                          </div>
                        </FormItem>
                      )}
                    />
                  </div>

                  <div className="mt-4 grid gap-4">
                    <FormField
                      control={form.control}
                      name={`intake_blacklist.entries.${index}.reason`}
                      render={({ field: currentField }) => (
                        <FormItem>
                          <FormLabel>Motivo</FormLabel>
                          <FormControl>
                            <Textarea
                              {...currentField}
                              value={currentField.value ?? ''}
                              rows={2}
                              placeholder="Ex: bloqueado por curadoria do evento."
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <div className="flex justify-end">
                      <Button
                        type="button"
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={() => entriesFieldArray.remove(index)}
                      >
                        <Trash2 className="h-4 w-4" />
                        Remover
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

function EventFormSkeleton() {
  return (
    <div className="space-y-6">
      <Skeleton className="h-10 w-64" />
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_360px]">
        <div className="space-y-4">
          <Skeleton className="h-[420px] rounded-3xl" />
          <Skeleton className="h-[420px] rounded-3xl" />
          <Skeleton className="h-[340px] rounded-3xl" />
        </div>
        <Skeleton className="h-[540px] rounded-3xl" />
      </div>
    </div>
  );
}

interface EventEditorPageProps {
  mode: EventEditorMode;
}

export function EventEditorPage({ mode }: EventEditorPageProps) {
  const isEditMode = mode === 'edit';
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { meOrganization, can } = useAuth();

  const coverInputRef = useRef<HTMLInputElement>(null);
  const logoInputRef = useRef<HTMLInputElement>(null);

  const [slugManuallyEdited, setSlugManuallyEdited] = useState(isEditMode);
  const [brandingPreviewUrls, setBrandingPreviewUrls] = useState<BrandingPreviewState>({});
  const [uploadingKind, setUploadingKind] = useState<EventBrandingAssetKind | null>(null);

  const form = useForm<EventFormValues>({
    resolver: zodResolver(eventFormSchema),
    defaultValues: buildDefaultValues(),
  });

  const eventQuery = useQuery({
    queryKey: queryKeys.events.detail(id ?? ''),
    enabled: isEditMode && !!id,
    queryFn: () => eventsService.show(id ?? ''),
  });

  const clientsQuery = useQuery({
    queryKey: ['events', 'form', 'clients', meOrganization?.id ?? 'none'],
    enabled: !!meOrganization?.id,
    queryFn: () => eventsService.listClients(),
  });

  const whatsappInstancesQuery = useQuery({
    queryKey: ['events', 'form', 'whatsapp-instances', meOrganization?.id ?? 'none'],
    enabled: isEditMode && !!meOrganization?.id,
    queryFn: async () => {
      const response = await whatsappService.list({
        per_page: 100,
      });

      return response.data;
    },
  });

  const telegramOperationalStatusQuery = useQuery({
    queryKey: queryKeys.events.telegramOperationalStatus(id ?? ''),
    enabled: isEditMode && !!id,
    queryFn: () => eventsService.telegramOperationalStatus(id ?? ''),
  });

  useEffect(() => {
    if (!isEditMode || !eventQuery.data) {
      return;
    }

    form.reset(buildFormValues(eventQuery.data));
    setSlugManuallyEdited(true);
    setBrandingPreviewUrls({
      cover: eventQuery.data.cover_image_url ?? null,
      logo: eventQuery.data.logo_url ?? null,
    });
  }, [eventQuery.data, form, isEditMode]);

  const watchedTitle = form.watch('title');
  const watchedSlug = form.watch('slug');
  const watchedEventType = form.watch('event_type');
  const watchedStartsAt = form.watch('starts_at');
  const watchedLocation = form.watch('location_name');
  const watchedVisibility = form.watch('visibility');
  const watchedModeration = form.watch('moderation_mode');
  const watchedFaceSearch = form.watch('face_search');
  const watchedModules = form.watch('modules');
  const watchedColors = form.watch(['primary_color', 'secondary_color']);
  const watchedClientId = form.watch('client_id');
  const watchedIntakeChannels = form.watch('intake_channels');

  useEffect(() => {
    if (slugManuallyEdited) {
      return;
    }

    const generatedSlug = slugify(watchedTitle);

    if (form.getValues('slug') !== generatedSlug) {
      form.setValue('slug', generatedSlug, { shouldValidate: true, shouldDirty: false });
    }
  }, [form, slugManuallyEdited, watchedTitle]);

  const assetUploadMutation = useMutation({
    mutationFn: async ({
      file,
      kind,
      previousPath,
    }: {
      file: File;
      kind: EventBrandingAssetKind;
      previousPath?: string | null;
    }) => eventsService.uploadBrandingAsset(kind, file, previousPath),
    onMutate: ({ kind }) => {
      setUploadingKind(kind);
    },
    onSuccess: (asset) => {
      if (asset.kind === 'cover') {
        form.setValue('cover_image_path', asset.path, { shouldDirty: true, shouldValidate: true });
      } else {
        form.setValue('logo_path', asset.path, { shouldDirty: true, shouldValidate: true });
      }

      setBrandingPreviewUrls((current) => ({
        ...current,
        [asset.kind]: asset.url,
      }));

      toast({
        title: asset.kind === 'cover' ? 'Capa enviada' : 'Logo enviada',
        description: 'Arquivo salvo no storage e pronto para persistir no evento.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha no upload',
        description: error.message,
        variant: 'destructive',
      });
    },
    onSettled: () => {
      setUploadingKind(null);
    },
  });

  const saveMutation = useMutation({
    mutationFn: async (values: EventFormValues) => {
      if (!meOrganization?.id) {
        throw new Error('Nenhuma organizacao ativa encontrada para salvar o evento.');
      }

      const payload = buildPayload(values, meOrganization.id, {
        includeIntake: isEditMode,
      });

      if (isEditMode && id) {
        const event = await eventsService.update(id, payload);
        return { id: event.id, title: event.title };
      }

      const event = await eventsService.create(payload);
      return { id: event.id, title: event.title };
    },
    onSuccess: async (payload) => {
      await queryClient.invalidateQueries({ queryKey: queryKeys.events.all() });
      await queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(String(payload.id)) });

      toast({
        title: isEditMode ? 'Evento atualizado' : 'Evento criado',
        description: `"${payload.title}" foi salvo com sucesso.`,
      });

      navigate(isEditMode ? `/events/${payload.id}` : `/events/${payload.id}/edit`);
    },
    onError: (error: Error) => {
      toast({
        title: isEditMode ? 'Falha ao atualizar evento' : 'Falha ao criar evento',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const currentEvent = eventQuery.data ?? null;

  const coverPreview = brandingPreviewUrls.cover !== undefined
    ? brandingPreviewUrls.cover
    : currentEvent?.cover_image_url ?? null;
  const logoPreview = brandingPreviewUrls.logo !== undefined
    ? brandingPreviewUrls.logo
    : currentEvent?.logo_url ?? null;

  const previewModules = Object.entries(watchedModules)
    .filter(([, enabled]) => enabled)
    .map(([module]) => module as keyof typeof watchedModules);
  const startsAtPreview = watchedStartsAt ? new Date(watchedStartsAt).toLocaleString('pt-BR') : 'Data ainda nao definida';
  const selectedClientName = (clientsQuery.data ?? []).find((client) => String(client.id) === watchedClientId)?.name;
  const intakeEntitlements = isEditMode ? resolveEventIntakeEntitlements(currentEvent?.current_entitlements ?? null) : null;
  const whatsappInstances = (whatsappInstancesQuery.data ?? [])
    .slice()
    .sort((left, right) => {
      if (left.is_default !== right.is_default) {
        return left.is_default ? -1 : 1;
      }

      if (left.status !== right.status) {
        return left.status === 'connected' ? -1 : 1;
      }

      return left.name.localeCompare(right.name, 'pt-BR');
    });
  const canEdit = can('events.update');
  const canCreate = can('events.create');
  const saveDisabled = saveMutation.isPending || assetUploadMutation.isPending || (isEditMode ? !canEdit : !canCreate);

  const handleSubmit = form.handleSubmit((values) => saveMutation.mutate(values));

  const validateImageFile = (file: File) => {
    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      toast({
        title: 'Formato invalido',
        description: 'Use um arquivo JPG, PNG ou WebP.',
        variant: 'destructive',
      });
      return false;
    }

    if (file.size > MAX_UPLOAD_SIZE) {
      toast({
        title: 'Arquivo muito grande',
        description: 'A imagem deve ter no maximo 10 MB.',
        variant: 'destructive',
      });
      return false;
    }

    return true;
  };

  const handleBrandingUpload = (kind: EventBrandingAssetKind, file?: File | null) => {
    if (!file || !validateImageFile(file)) {
      return;
    }

    const previousPath = kind === 'cover'
      ? form.getValues('cover_image_path')
      : form.getValues('logo_path');

    assetUploadMutation.mutate({
      kind,
      file,
      previousPath,
    });
  };

  const handleBrandingRemoval = (kind: EventBrandingAssetKind) => {
    if (kind === 'cover') {
      form.setValue('cover_image_path', '', { shouldDirty: true, shouldValidate: true });
    } else {
      form.setValue('logo_path', '', { shouldDirty: true, shouldValidate: true });
    }

    setBrandingPreviewUrls((current) => ({
      ...current,
      [kind]: null,
    }));
  };

  if (!meOrganization) {
    return (
      <EmptyState
        title="Organizacao nao encontrada"
        description="Ative uma organizacao na sessao atual para criar ou editar eventos."
        action={(
          <Button asChild>
            <Link to="/events">Voltar para eventos</Link>
          </Button>
        )}
      />
    );
  }

  if (isEditMode && eventQuery.isLoading) {
    return <EventFormSkeleton />;
  }

  if (isEditMode && (eventQuery.isError || !eventQuery.data)) {
    return (
      <EmptyState
        title="Nao foi possivel carregar o evento"
        description={eventQuery.error instanceof Error ? eventQuery.error.message : 'Revise o ID do evento e tente novamente.'}
        action={(
          <Button asChild>
            <Link to="/events">Voltar para eventos</Link>
          </Button>
        )}
      />
    );
  }

  return (
    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-5">
      <PageHeader
        title={isEditMode ? 'Editar evento' : 'Criar evento'}
        description={isEditMode
          ? 'Atualize os dados reais do evento, incluindo capa, logo, links publicos e modulos ativos.'
          : 'Cadastre um evento com branding, clientes e operacao configurados direto pelo painel.'}
        actions={(
          <>
            <Button variant="outline" onClick={() => navigate(isEditMode && id ? `/events/${id}` : '/events')}>
              Cancelar
            </Button>

            {isEditMode && currentEvent ? (
              <Button asChild variant="outline">
                <Link to={`/events/${currentEvent.id}`}>
                  <Eye className="h-4 w-4" />
                  Ver detalhe
                </Link>
              </Button>
            ) : null}

            <Button className="gradient-primary border-0" onClick={handleSubmit} disabled={saveDisabled}>
              {saveMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
              {isEditMode ? 'Salvar alteracoes' : 'Criar evento'}
            </Button>
          </>
        )}
      />

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_360px]">
        <Form {...form}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
              <div className="mb-5">
                <h2 className="text-sm font-semibold">Informacoes principais</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Dados estruturais do evento, cliente, agenda e contexto operacional.
                </p>
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2 sm:col-span-2">
                  <p className="text-sm font-medium leading-none">Organizacao</p>
                  <Input value={meOrganization.name} disabled />
                  <p className="text-sm text-muted-foreground">A criacao usa a organizacao ativa da sua sessao.</p>
                </div>

                <FormField
                  control={form.control}
                  name="title"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>Nome do evento</FormLabel>
                      <FormControl>
                        <Input {...field} placeholder="Ex: Casamento Ana e Pedro" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="client_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Cliente</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Selecione um cliente" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="none">Sem cliente vinculado</SelectItem>
                          {(clientsQuery.data ?? []).map((client) => (
                            <SelectItem key={client.id} value={String(client.id)}>
                              {client.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormDescription>
                        {clientsQuery.isError
                          ? 'Nao foi possivel carregar os clientes agora.'
                          : 'Lista carregada diretamente da API para a organizacao ativa.'}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="event_type"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Tipo do evento</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Selecione um tipo" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {EVENT_TYPE_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="starts_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Inicio</FormLabel>
                      <FormControl>
                        <Input {...field} type="datetime-local" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="ends_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Fim</FormLabel>
                      <FormControl>
                        <Input {...field} type="datetime-local" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="location_name"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>Local</FormLabel>
                      <FormControl>
                        <Input {...field} placeholder="Ex: Espaco Villa Real, Sao Paulo" />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="description"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>Descricao</FormLabel>
                      <FormControl>
                        <Textarea {...field} rows={4} placeholder="Resumo operacional, briefing ou orientacoes do evento." />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
            </section>

            <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
              <div className="mb-5">
                <h2 className="text-sm font-semibold">Branding e ativos</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Capa e logo sao enviados pelo painel, salvos no storage e persistidos no evento ao salvar.
                </p>
              </div>

              <div className="grid gap-4">
                <FormField
                  control={form.control}
                  name="slug"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Slug publico</FormLabel>
                      <FormControl>
                        <Input
                          {...field}
                          placeholder="casamento-ana-e-pedro"
                          onChange={(event) => {
                            const sanitizedValue = slugify(event.target.value);

                            setSlugManuallyEdited(sanitizedValue.length > 0);
                            field.onChange(sanitizedValue);
                          }}
                        />
                      </FormControl>
                      <FormDescription>
                        O slug acompanha o titulo automaticamente enquanto voce nao editar manualmente este campo.
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                  <FormField
                    control={form.control}
                    name="primary_color"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Cor principal</FormLabel>
                        <div className="flex gap-2">
                          <FormControl>
                            <Input {...field} type="color" className="h-11 w-14 p-1" />
                          </FormControl>
                          <Input value={field.value} onChange={field.onChange} className="font-mono" />
                        </div>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="secondary_color"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Cor secundaria</FormLabel>
                        <div className="flex gap-2">
                          <FormControl>
                            <Input {...field} type="color" className="h-11 w-14 p-1" />
                          </FormControl>
                          <Input value={field.value} onChange={field.onChange} className="font-mono" />
                        </div>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                  <FormField
                    control={form.control}
                    name="cover_image_path"
                    render={({ field }) => (
                      <FormItem className="rounded-3xl border border-border/60 bg-background/60 p-4">
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <FormLabel>Imagem de capa</FormLabel>
                            <FormDescription>Recomendado para banners 16:9 do evento.</FormDescription>
                          </div>
                          <Badge variant="outline">Storage</Badge>
                        </div>

                        <div className="mt-4 overflow-hidden rounded-2xl border border-border/60">
                          {coverPreview ? (
                            <img src={coverPreview} alt="Preview da capa" className="aspect-[16/9] w-full object-cover" />
                          ) : (
                            <div
                              className="flex aspect-[16/9] items-center justify-center"
                              style={{
                                background: `linear-gradient(135deg, ${watchedColors[0]} 0%, ${watchedColors[1]} 100%)`,
                              }}
                            >
                              <CalendarDays className="h-10 w-10 text-white/80" />
                            </div>
                          )}
                        </div>

                        <input
                          ref={coverInputRef}
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          className="hidden"
                          onChange={(event) => {
                            handleBrandingUpload('cover', event.target.files?.[0]);
                            event.target.value = '';
                          }}
                        />

                        <div className="mt-4 flex flex-wrap gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => coverInputRef.current?.click()}
                            disabled={uploadingKind === 'cover'}
                          >
                            {uploadingKind === 'cover' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                            {field.value ? 'Trocar capa' : 'Enviar capa'}
                          </Button>

                          {field.value ? (
                            <Button
                              type="button"
                              variant="ghost"
                              className="text-destructive hover:text-destructive"
                              onClick={() => handleBrandingRemoval('cover')}
                            >
                              <Trash2 className="h-4 w-4" />
                              Remover
                            </Button>
                          ) : null}
                        </div>

                        {field.value ? (
                          <p className="mt-3 break-all text-xs text-muted-foreground">
                            Caminho salvo: {field.value}
                          </p>
                        ) : null}

                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name="logo_path"
                    render={({ field }) => (
                      <FormItem className="rounded-3xl border border-border/60 bg-background/60 p-4">
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <FormLabel>Logo do evento</FormLabel>
                            <FormDescription>Ideal para uso no hub, uploads e assinatura visual.</FormDescription>
                          </div>
                          <Badge variant="outline">Storage</Badge>
                        </div>

                        <div className="mt-4 flex min-h-[220px] items-center justify-center rounded-2xl border border-dashed border-border/60 bg-muted/20 p-6">
                          {logoPreview ? (
                            <img src={logoPreview} alt="Preview da logo" className="max-h-40 w-auto max-w-full object-contain" />
                          ) : (
                            <div className="text-center">
                              <ImageIcon className="mx-auto h-10 w-10 text-muted-foreground" />
                              <p className="mt-3 text-sm text-muted-foreground">Envie uma logo em PNG, JPG ou WebP.</p>
                            </div>
                          )}
                        </div>

                        <input
                          ref={logoInputRef}
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          className="hidden"
                          onChange={(event) => {
                            handleBrandingUpload('logo', event.target.files?.[0]);
                            event.target.value = '';
                          }}
                        />

                        <div className="mt-4 flex flex-wrap gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            onClick={() => logoInputRef.current?.click()}
                            disabled={uploadingKind === 'logo'}
                          >
                            {uploadingKind === 'logo' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                            {field.value ? 'Trocar logo' : 'Enviar logo'}
                          </Button>

                          {field.value ? (
                            <Button
                              type="button"
                              variant="ghost"
                              className="text-destructive hover:text-destructive"
                              onClick={() => handleBrandingRemoval('logo')}
                            >
                              <Trash2 className="h-4 w-4" />
                              Remover
                            </Button>
                          ) : null}
                        </div>

                        {field.value ? (
                          <p className="mt-3 break-all text-xs text-muted-foreground">
                            Caminho salvo: {field.value}
                          </p>
                        ) : null}

                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
              </div>
            </section>

            <section className="glass rounded-3xl border border-border/60 p-4 sm:p-6">
              <div className="mb-5">
                <h2 className="text-sm font-semibold">Operacao do evento</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Configure exposicao publica, moderacao, retencao e os modulos ativos.
                </p>
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <FormField
                  control={form.control}
                  name="visibility"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Visibilidade</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Selecione" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {EVENT_VISIBILITY_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="moderation_mode"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Moderacao</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Selecione" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {EVENT_MODERATION_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="retention_days"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Retencao</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Selecione" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {buildRetentionOptions(field.value).map((option) => (
                            <SelectItem key={option.value} value={String(option.value)}>
                              {option.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <div className="mt-6 grid gap-4 md:grid-cols-2">
                <div className="rounded-3xl border border-border/60 bg-background/60 p-4 md:col-span-2">
                  <div className="flex items-start justify-between gap-4">
                    <div>
                      <h3 className="text-sm font-semibold">Busca por selfie</h3>
                      <p className="mt-1 text-sm text-muted-foreground">
                        Controla se o evento prepara indexacao facial para busca por pessoa. Essa camada e opcional e independente da moderacao.
                      </p>
                    </div>
                    <FormField
                      control={form.control}
                      name="face_search.enabled"
                      render={({ field }) => (
                        <FormItem className="flex items-center gap-3">
                          <FormLabel className="sr-only">Habilitar busca por selfie</FormLabel>
                          <FormControl>
                            <Switch checked={field.value} onCheckedChange={field.onChange} />
                          </FormControl>
                        </FormItem>
                      )}
                    />
                  </div>

                  <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <FormField
                      control={form.control}
                      name="face_search.allow_public_selfie_search"
                      render={({ field }) => (
                        <FormItem className={`rounded-2xl border p-4 transition-colors ${watchedFaceSearch.enabled ? 'border-primary/20 bg-primary/5' : 'border-border/60 bg-muted/20'}`}>
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <FormLabel className="text-sm">Busca publica</FormLabel>
                              <FormDescription className="mt-1 text-xs">
                                Permite expor a experiencia publica de "encontre minhas fotos" para convidados.
                              </FormDescription>
                            </div>
                            <FormControl>
                              <Switch
                                checked={field.value}
                                onCheckedChange={field.onChange}
                                disabled={!watchedFaceSearch.enabled}
                              />
                            </FormControl>
                          </div>
                          <FormMessage />
                        </FormItem>
                      )}
                    />

                    <FormField
                      control={form.control}
                      name="face_search.selfie_retention_hours"
                      render={({ field }) => (
                        <FormItem className={`rounded-2xl border p-4 transition-colors ${watchedFaceSearch.enabled ? 'border-primary/20 bg-primary/5' : 'border-border/60 bg-muted/20'}`}>
                          <FormLabel>Retencao da selfie temporaria</FormLabel>
                          <FormControl>
                            <Input
                              {...field}
                              type="number"
                              min={1}
                              max={720}
                              disabled={!watchedFaceSearch.enabled}
                              placeholder="24"
                            />
                          </FormControl>
                          <FormDescription>
                            Numero de horas para descarte da selfie temporaria quando a busca publica estiver habilitada.
                          </FormDescription>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>
                </div>

                {moduleItems.map((module) => (
                  <FormField
                    key={module.key}
                    control={form.control}
                    name={`modules.${module.key}`}
                    render={({ field }) => (
                      <FormItem className={`rounded-3xl border p-4 transition-colors ${field.value ? 'border-primary/40 bg-primary/5' : 'border-border/60 bg-background/60'}`}>
                        <div className="flex items-start gap-4">
                          <div className="rounded-2xl bg-primary/10 p-3">
                            <module.icon className="h-5 w-5 text-primary" />
                          </div>
                          <div className="flex-1">
                            <div className="flex items-start justify-between gap-3">
                              <div>
                                <FormLabel className="text-sm">{module.label}</FormLabel>
                                <FormDescription className="mt-1 text-xs">
                                  {module.description}
                                </FormDescription>
                              </div>
                              <FormControl>
                                <Switch checked={field.value} onCheckedChange={field.onChange} />
                              </FormControl>
                            </div>
                          </div>
                        </div>
                      </FormItem>
                    )}
                  />
                ))}
              </div>
            </section>

            <EventIntakeChannelsSection
              mode={mode}
              form={form}
              event={currentEvent}
              entitlements={intakeEntitlements}
              telegramOperationalStatus={telegramOperationalStatusQuery.data ?? null}
              telegramOperationalStatusLoading={telegramOperationalStatusQuery.isLoading}
              telegramOperationalStatusError={telegramOperationalStatusQuery.isError}
              instances={whatsappInstances}
              instancesLoading={whatsappInstancesQuery.isLoading}
              instancesError={whatsappInstancesQuery.isError}
            />

            <EventBlacklistSection
              mode={mode}
              form={form}
              event={currentEvent}
              entitlements={intakeEntitlements}
            />
          </form>
        </Form>

        <aside className="space-y-4">
          <div className="glass rounded-3xl border border-border/60 p-4 sm:p-5 xl:sticky xl:top-20">
            <div className="mb-4 flex items-center gap-2">
              <Eye className="h-4 w-4 text-primary" />
              <h2 className="text-sm font-semibold">Preview operacional</h2>
            </div>

            <div className="overflow-hidden rounded-3xl border border-border/60 bg-background/70">
              {coverPreview ? (
                <img src={coverPreview} alt="Preview da capa" className="h-44 w-full object-cover" />
              ) : (
                <div
                  className="flex h-44 items-center justify-center"
                  style={{
                    background: `linear-gradient(135deg, ${watchedColors[0]} 0%, ${watchedColors[1]} 100%)`,
                  }}
                >
                  <CalendarDays className="h-10 w-10 text-white/80" />
                </div>
              )}

              <div className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      {currentEvent ? <EventStatusBadge status={currentEvent.status} /> : null}
                      <Badge variant="secondary">{EVENT_TYPE_LABELS[watchedEventType as ApiEventType]}</Badge>
                      <Badge variant="outline">{EVENT_VISIBILITY_LABELS[watchedVisibility]}</Badge>
                    </div>

                    <p className="mt-3 text-lg font-semibold">{watchedTitle || 'Nome do evento'}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{watchedLocation || 'Local ainda nao informado'}</p>
                  </div>

                  {logoPreview ? (
                    <div className="rounded-2xl border border-border/60 bg-white/90 p-2 shadow-sm">
                      <img src={logoPreview} alt="Logo do evento" className="h-12 w-12 object-contain" />
                    </div>
                  ) : null}
                </div>

                <div className="space-y-2 text-sm text-muted-foreground">
                  <p className="flex items-center gap-2">
                    <Clock3 className="h-4 w-4" />
                    {startsAtPreview}
                  </p>
                  <p className="flex items-center gap-2">
                    <ShieldCheck className="h-4 w-4" />
                    Moderacao {EVENT_MODERATION_LABELS[watchedModeration]}
                  </p>
                  <p className="flex items-center gap-2">
                    <UserCheck className="h-4 w-4" />
                    Cliente {selectedClientName ?? 'nao vinculado'}
                  </p>
                  <p className="flex items-center gap-2">
                    <ImageIcon className="h-4 w-4" />
                    Busca por selfie {watchedFaceSearch.enabled ? 'ativada' : 'desligada'}
                  </p>
                  <p className="flex items-center gap-2">
                    <Users className="h-4 w-4" />
                    Grupos {watchedIntakeChannels.whatsapp_groups.enabled ? 'ativos' : 'desligados'}
                  </p>
                  <p className="flex items-center gap-2">
                    <MessageSquare className="h-4 w-4" />
                    WhatsApp direto {watchedIntakeChannels.whatsapp_direct.enabled ? 'ativo' : 'desligado'}
                  </p>
                  <p className="flex items-center gap-2">
                    <MessageSquare className="h-4 w-4" />
                    Telegram privado {watchedIntakeChannels.telegram.enabled ? 'ativo' : 'desligado'}
                  </p>
                </div>

                <div className="flex flex-wrap gap-2">
                  {previewModules.length > 0 ? previewModules.map((module) => (
                    <Badge key={module} variant="outline">
                      {EVENT_MODULE_LABELS[module]}
                    </Badge>
                  )) : (
                    <span className="text-sm text-muted-foreground">Nenhum modulo ativo.</span>
                  )}
                </div>
              </div>
            </div>

            <div className="mt-4 space-y-3 rounded-3xl border border-border/60 bg-background/70 p-4">
              <h3 className="text-sm font-semibold">Links publicos previstos</h3>
              <div className="space-y-2 text-sm text-muted-foreground">
                <p className="flex items-center gap-2">
                  <Link2 className="h-4 w-4" />
                  Hub: `/e/{watchedSlug || slugify(watchedTitle) || 'slug-do-evento'}`
                </p>
                <p className="flex items-center gap-2">
                  <Globe className="h-4 w-4" />
                  Upload: `/upload/{currentEvent?.upload_slug ?? 'gerado-automaticamente'}`
                </p>
                <p className="flex items-center gap-2">
                  <Smartphone className="h-4 w-4" />
                  Canais detalhados {isEditMode ? 'configuraveis agora' : 'apos o primeiro save'}
                </p>
              </div>

              {currentEvent ? (
                <div className="flex flex-wrap gap-2">
                  <Button asChild size="sm" variant="outline">
                    <a href={currentEvent.public_url ?? undefined} target="_blank" rel="noreferrer">
                      Abrir hub
                    </a>
                  </Button>
                  <Button asChild size="sm" variant="outline">
                    <a href={currentEvent.upload_url ?? undefined} target="_blank" rel="noreferrer">
                      Abrir envio
                    </a>
                  </Button>
                </div>
              ) : null}
            </div>
          </div>
        </aside>
      </div>
    </motion.div>
  );
}
