import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle,
  Bot,
  Cable,
  Loader2,
  MessageSquareText,
  Save,
  Settings2,
  ShieldCheck,
  Sparkles,
} from 'lucide-react';
import { type ReactNode, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { aiMediaRepliesService } from '@/modules/ai/api';
import type { MediaReplyPromptPreset } from '@/modules/ai/types';
import { EventContentModerationSettingsForm } from '@/modules/events/components/content-moderation/EventContentModerationSettingsForm';
import { EventMediaIntelligenceSettingsForm } from '@/modules/events/components/media-intelligence/EventMediaIntelligenceSettingsForm';
import {
  getEventContentModerationSettings,
  getEventMediaIntelligenceSettings,
  type UpdateEventContentModerationSettingsPayload,
  type UpdateEventMediaIntelligenceSettingsPayload,
} from '@/modules/events/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Drawer, DrawerContent, DrawerDescription, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useToast } from '@/hooks/use-toast';
import type {
  ApiEventContentModerationSettings,
  ApiEventMediaIntelligenceSettings,
} from '@/lib/api-types';
import { cn } from '@/lib/utils';

import { buildJourneyInspectorDraft } from './buildJourneyInspectorDraft';
import {
  mergeJourneyContentModerationSettings,
  mergeJourneyMediaIntelligenceSettings,
  type JourneyTemplatePreview,
} from './buildJourneyTemplatePreview';
import type { JourneyGraphNode } from './buildJourneyGraph';
import { invalidateEventJourneyBuilderQueries, updateEventJourneyBuilder } from './api';
import {
  describeJourneyStatus,
  getJourneyNodeCopy,
  humanizeJourneyStageLabel,
  humanizeJourneyStatusLabel,
  humanizeJourneyText,
} from './journeyCopy';
import type {
  EventJourneyBuiltScenario,
  EventJourneyProjection,
  EventJourneyUpdatePayload,
} from './types';
import {
  type EventJourneyDirtyFields,
  toJourneyUpdatePayload,
} from './toJourneyUpdatePayload';

type JourneyInspectorMode = 'panel' | 'drawer';
type JourneyEditableSectionKind =
  | 'moderation-mode'
  | 'whatsapp-direct'
  | 'whatsapp-groups'
  | 'telegram'
  | 'public-upload'
  | 'content-moderation'
  | 'media-intelligence'
  | 'readonly';

interface JourneyInspectorProps {
  mode: JourneyInspectorMode;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  eventId: number | string;
  projection: EventJourneyProjection;
  selectedNode: JourneyGraphNode | null;
  selectedScenario?: EventJourneyBuiltScenario | null;
  onClearScenario?: () => void;
  scenarios: EventJourneyBuiltScenario[];
  technicalDetailsOpen: boolean;
  templateDraftPreview?: JourneyTemplatePreview | null;
}

interface JourneyInspectorSection {
  kind: JourneyEditableSectionKind;
  title: string;
  description: string;
  icon: typeof Settings2;
}

const moderationModeSchema = z.object({
  moderation_mode: z.enum(['none', 'manual', 'ai']),
});

const whatsappDirectSchema = z.object({
  enabled: z.boolean(),
  media_inbox_code: z.string().trim().max(80, 'Use ate 80 caracteres.'),
  session_ttl_minutes: z.string().regex(/^\d+$/, 'Informe um numero inteiro de minutos.'),
}).superRefine((value, ctx) => {
  if (value.enabled && value.media_inbox_code.trim().length === 0) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['media_inbox_code'],
      message: 'Informe o inbox code quando o canal estiver ativo.',
    });
  }
});

const whatsappGroupsSchema = z.object({
  enabled: z.boolean(),
});

const telegramSchema = z.object({
  enabled: z.boolean(),
  bot_username: z.string().trim().max(120, 'Use ate 120 caracteres.'),
  media_inbox_code: z.string().trim().max(80, 'Use ate 80 caracteres.'),
  session_ttl_minutes: z.string().regex(/^\d+$/, 'Informe um numero inteiro de minutos.'),
}).superRefine((value, ctx) => {
  if (!value.enabled) {
    return;
  }

  if (value.bot_username.trim().length === 0) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['bot_username'],
      message: 'Informe o username do bot quando o Telegram estiver ativo.',
    });
  }

  if (value.media_inbox_code.trim().length === 0) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['media_inbox_code'],
      message: 'Informe o inbox code quando o Telegram estiver ativo.',
    });
  }
});

const publicUploadSchema = z.object({
  enabled: z.boolean(),
});

type ModerationModeFormValues = z.infer<typeof moderationModeSchema>;
type WhatsAppDirectFormValues = z.infer<typeof whatsappDirectSchema>;
type WhatsAppGroupsFormValues = z.infer<typeof whatsappGroupsSchema>;
type TelegramFormValues = z.infer<typeof telegramSchema>;
type PublicUploadFormValues = z.infer<typeof publicUploadSchema>;

function resolveInspectorSection(nodeId: string | null): JourneyInspectorSection | null {
  switch (nodeId) {
    case 'decision_event_moderation_mode':
      return {
        kind: 'moderation-mode',
        title: 'Regra principal de aprovacao',
        description: 'Define se a midia aprova direto, passa por revisao manual ou usa IA.',
        icon: Sparkles,
      };
    case 'entry_whatsapp_direct':
      return {
        kind: 'whatsapp-direct',
        title: 'WhatsApp privado',
        description: 'Liga o recebimento privado e ajusta inbox code e expiracao da sessao.',
        icon: MessageSquareText,
      };
    case 'entry_whatsapp_groups':
      return {
        kind: 'whatsapp-groups',
        title: 'WhatsApp grupos',
        description: 'Liga o recebimento por grupos ja vinculados ao evento.',
        icon: MessageSquareText,
      };
    case 'entry_telegram':
      return {
        kind: 'telegram',
        title: 'Telegram',
        description: 'Controla o bot, o inbox code e a expiracao do contexto de envio.',
        icon: Bot,
      };
    case 'entry_public_upload':
      return {
        kind: 'public-upload',
        title: 'Link de envio',
        description: 'Liga ou desliga o link publico e o QR code de upload do evento.',
        icon: Cable,
      };
    case 'processing_safety_ai':
    case 'decision_safety_result':
      return {
        kind: 'content-moderation',
        title: 'Analise de risco do evento',
        description: 'Ajusta a etapa que percebe risco antes da publicacao.',
        icon: ShieldCheck,
      };
    case 'processing_media_intelligence':
    case 'decision_context_gate':
    case 'output_reply_text':
      return {
        kind: 'media-intelligence',
        title: 'Contexto e resposta automatica',
        description: 'Ajusta a IA que entende a imagem, a legenda e a resposta automatica.',
        icon: Sparkles,
      };
    default:
      return nodeId
        ? {
            kind: 'readonly',
            title: 'Inspector desta etapa',
            description: 'Esta etapa ainda nao ganhou um formulario guiado nesta tela.',
            icon: Settings2,
          }
        : null;
  }
}

function buildContentModerationPatchPayload(payload: UpdateEventContentModerationSettingsPayload): EventJourneyUpdatePayload {
  return {
    content_moderation: payload,
  };
}

function buildMediaIntelligencePatchPayload(payload: UpdateEventMediaIntelligenceSettingsPayload): EventJourneyUpdatePayload {
  return {
    media_intelligence: {
      ...payload,
      reply_text_enabled: payload.reply_text_mode !== 'disabled',
    },
  };
}

function buildDirtyPayload(
  projection: EventJourneyProjection,
  updater: (draft: ReturnType<typeof buildJourneyInspectorDraft>) => void,
  dirtyFields: EventJourneyDirtyFields<ReturnType<typeof buildJourneyInspectorDraft>>,
) {
  const draft = buildJourneyInspectorDraft(projection);

  updater(draft);

  return toJourneyUpdatePayload(draft, dirtyFields);
}

function InspectorMetaCard({
  projection,
  selectedNode,
  scenarios,
  technicalDetailsOpen,
}: {
  projection: EventJourneyProjection;
  selectedNode: JourneyGraphNode | null;
  scenarios: EventJourneyBuiltScenario[];
  technicalDetailsOpen: boolean;
}) {
  if (!technicalDetailsOpen) {
    return null;
  }

  return (
    <Card className="border-white/70 bg-white/90 shadow-sm">
      <CardHeader className="pb-3">
        <CardTitle className="text-base">Detalhes da configuracao atual</CardTitle>
        <CardDescription>
          Informacoes de apoio para conferir o estado desta tela.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Versao interna</p>
            <p className="mt-2 font-mono text-sm">{projection.version}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Simulacoes</p>
            <p className="mt-2 font-mono text-sm">{scenarios.length}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Alertas</p>
            <p className="mt-2 font-mono text-sm">{projection.warnings.length}</p>
          </div>
          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">Etapa aberta</p>
            <p className="mt-2 font-mono text-xs">{selectedNode?.id ?? 'nenhum'}</p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function InspectorDraftHint() {
  return (
    <div className="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">
      <p className="font-medium">Rascunho local</p>
      <p className="mt-1">
        As alteracoes ficam apenas neste painel ate voce salvar. O mapa visual so assume a nova configuracao depois do salvamento.
      </p>
    </div>
  );
}

function scenarioOutcomeMeta(outcome: EventJourneyBuiltScenario['outcome']) {
  switch (outcome) {
    case 'approved':
      return {
        label: 'Aprovado',
        className: 'border-emerald-200 bg-emerald-100 text-emerald-800',
      };
    case 'review':
      return {
        label: 'Em revisao',
        className: 'border-amber-200 bg-amber-100 text-amber-800',
      };
    case 'blocked':
      return {
        label: 'Bloqueado',
        className: 'border-rose-200 bg-rose-100 text-rose-800',
      };
    default:
      return {
        label: 'Inativo',
        className: 'border-slate-200 bg-slate-100 text-slate-700',
      };
  }
}

function buildScenarioPathLabels(
  projection: EventJourneyProjection,
  scenario: EventJourneyBuiltScenario,
) {
  const nodeById = new Map(
    projection.stages.flatMap((stage) =>
      stage.nodes.map((node) => {
        const copy = getJourneyNodeCopy(node);

        return [node.id, { stageLabel: humanizeJourneyStageLabel(stage.id), nodeLabel: copy.label }] as const;
      }),
    ),
  );

  return scenario.highlightedNodeIds
    .map((nodeId) => {
      const node = nodeById.get(nodeId);

      if (!node) {
        return null;
      }

      return `${node.stageLabel}: ${node.nodeLabel}`;
    })
    .filter((value): value is string => value !== null);
}

function JourneyScenarioInspectorCard({
  projection,
  scenario,
  onClear,
}: {
  projection: EventJourneyProjection;
  scenario: EventJourneyBuiltScenario;
  onClear?: () => void;
}) {
  const outcome = scenarioOutcomeMeta(scenario.outcome);
  const pathLabels = buildScenarioPathLabels(projection, scenario);

  return (
    <Card className="border-sky-200 bg-sky-50 shadow-none">
      <CardHeader className="space-y-3 pb-3">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <CardTitle className="text-base">Simulacao ativa</CardTitle>
            <CardDescription>{humanizeJourneyText(scenario.description)}</CardDescription>
          </div>
          <Badge className={outcome.className}>{outcome.label}</Badge>
        </div>
        <p className="text-sm font-medium text-foreground">{humanizeJourneyText(scenario.label)}</p>
      </CardHeader>
      <CardContent className="space-y-4 text-sm">
        <p className="leading-6 text-foreground/90">{humanizeJourneyText(scenario.humanText)}</p>

        {pathLabels.length > 0 ? (
          <div className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-muted-foreground">
              Caminho destacado
            </p>
            <ol className="space-y-2 text-sm text-foreground/90">
              {pathLabels.map((label, index) => (
                <li key={`${label}-${index}`} className="rounded-xl border border-sky-200 bg-white px-3 py-2">
                  {index + 1}. {label}
                </li>
              ))}
            </ol>
          </div>
        ) : null}

        {onClear ? (
          <div className="flex justify-end">
            <Button type="button" variant="outline" size="sm" onClick={onClear}>
              Limpar simulacao
            </Button>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

function JourneyModerationModeForm({
  value,
  disabled,
  isPending,
  onSubmit,
}: {
  value: ModerationModeFormValues['moderation_mode'];
  disabled: boolean;
  isPending: boolean;
  onSubmit: (values: ModerationModeFormValues) => void | Promise<void>;
}) {
  const form = useForm<ModerationModeFormValues>({
    resolver: zodResolver(moderationModeSchema),
    defaultValues: {
      moderation_mode: value,
    },
  });

  useEffect(() => {
    form.reset({ moderation_mode: value });
  }, [form, value]);

  const submit = form.handleSubmit((values) => onSubmit(values));

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <InspectorDraftHint />

        <FormField
          control={form.control}
          name="moderation_mode"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Modo principal do evento</FormLabel>
              <FormControl>
                <RadioGroup
                  value={field.value}
                  onValueChange={field.onChange}
                  disabled={disabled || isPending}
                  className="gap-3"
                >
                  {[
                    { value: 'none', label: 'Aprovacao direta', description: 'Publica sem revisao manual nem IA.' },
                    { value: 'manual', label: 'Revisao manual', description: 'Toda midia passa por operador.' },
                    { value: 'ai', label: 'IA moderando', description: 'A IA decide e pode acionar a analise de risco e a leitura de contexto.' },
                  ].map((option) => (
                    <label
                      key={option.value}
                      className={cn(
                        'flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors',
                        field.value === option.value ? 'border-primary bg-primary/5' : 'border-slate-200 bg-white',
                        (disabled || isPending) ? 'cursor-not-allowed opacity-60' : 'hover:border-primary/50',
                      )}
                    >
                      <RadioGroupItem value={option.value} aria-label={option.label} />
                      <div className="space-y-1">
                        <p className="font-medium text-foreground">{option.label}</p>
                        <p className="text-sm text-muted-foreground">{option.description}</p>
                      </div>
                    </label>
                  ))}
                </RadioGroup>
              </FormControl>
              <FormDescription>
                Esta etapa define a trilha principal antes de a analise de risco e a leitura de contexto influenciarem o caminho.
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending || !form.formState.isDirty}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar modo de moderacao'}
          </Button>
        </div>
      </form>
    </Form>
  );
}

function JourneyWhatsAppDirectForm({
  value,
  disabled,
  isPending,
  onSubmit,
}: {
  value: {
    enabled: boolean;
    media_inbox_code: string | null;
    session_ttl_minutes: number | null;
  };
  disabled: boolean;
  isPending: boolean;
  onSubmit: (values: WhatsAppDirectFormValues) => void | Promise<void>;
}) {
  const form = useForm<WhatsAppDirectFormValues>({
    resolver: zodResolver(whatsappDirectSchema),
    defaultValues: {
      enabled: value.enabled,
      media_inbox_code: value.media_inbox_code ?? '',
      session_ttl_minutes: String(value.session_ttl_minutes ?? 120),
    },
  });

  useEffect(() => {
    form.reset({
      enabled: value.enabled,
      media_inbox_code: value.media_inbox_code ?? '',
      session_ttl_minutes: String(value.session_ttl_minutes ?? 120),
    });
  }, [form, value.enabled, value.media_inbox_code, value.session_ttl_minutes]);

  const submit = form.handleSubmit((values) => onSubmit(values));
  const enabled = form.watch('enabled');

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <InspectorDraftHint />

        <FormField
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <FormItem className="rounded-2xl border border-slate-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <FormLabel>WhatsApp privado ativo</FormLabel>
                  <FormDescription>
                    Recebe foto e video por conversa privada com o inbox code do evento.
                  </FormDescription>
                </div>
                <FormControl>
                  <Switch
                    aria-label="Ativar WhatsApp privado"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={disabled || isPending}
                  />
                </FormControl>
              </div>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="grid gap-4 md:grid-cols-2">
          <FormField
            control={form.control}
            name="media_inbox_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Inbox code</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending || !enabled} placeholder="NOIVA2026" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="session_ttl_minutes"
            render={({ field }) => (
              <FormItem>
                <FormLabel>TTL da sessao (min)</FormLabel>
                <FormControl>
                  <Input {...field} inputMode="numeric" disabled={disabled || isPending || !enabled} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending || !form.formState.isDirty}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar WhatsApp privado'}
          </Button>
        </div>
      </form>
    </Form>
  );
}

function JourneyWhatsAppGroupsForm({
  eventId,
  value,
  disabled,
  isPending,
  onSubmit,
}: {
  eventId: number | string;
  value: {
    enabled: boolean;
    groupCount: number;
    groups: Array<{ group_external_id: string; group_name?: string | null }>;
  };
  disabled: boolean;
  isPending: boolean;
  onSubmit: (values: WhatsAppGroupsFormValues) => void | Promise<void>;
}) {
  const form = useForm<WhatsAppGroupsFormValues>({
    resolver: zodResolver(whatsappGroupsSchema),
    defaultValues: {
      enabled: value.enabled,
    },
  });

  useEffect(() => {
    form.reset({ enabled: value.enabled });
  }, [form, value.enabled]);

  const submit = form.handleSubmit((values) => onSubmit(values));

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <InspectorDraftHint />

        <FormField
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <FormItem className="rounded-2xl border border-slate-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <FormLabel>Receber por grupos</FormLabel>
                  <FormDescription>
                    Liga ou desliga os grupos ja vinculados. O cadastro detalhado dos grupos continua no editor completo.
                  </FormDescription>
                </div>
                <FormControl>
                  <Switch
                    aria-label="Ativar WhatsApp grupos"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={disabled || isPending}
                  />
                </FormControl>
              </div>
            </FormItem>
          )}
        />

        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
          <p className="font-medium text-foreground">Grupos vinculados: {value.groupCount}</p>
          {value.groups.length > 0 ? (
            <div className="mt-3 flex flex-wrap gap-2">
              {value.groups.map((group) => (
                <Badge key={group.group_external_id} variant="outline">
                  {group.group_name?.trim() || group.group_external_id}
                </Badge>
              ))}
            </div>
          ) : (
            <p className="mt-1">Nenhum grupo foi vinculado ainda.</p>
          )}
        </div>

        <div className="flex justify-between gap-3">
          <Button type="button" variant="outline" asChild>
            <Link to={`/events/${eventId}`}>Abrir editor completo</Link>
          </Button>
          <Button type="submit" disabled={disabled || isPending || !form.formState.isDirty}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar WhatsApp grupos'}
          </Button>
        </div>
      </form>
    </Form>
  );
}

function JourneyTelegramForm({
  value,
  disabled,
  isPending,
  onSubmit,
}: {
  value: {
    enabled: boolean;
    bot_username: string | null;
    media_inbox_code: string | null;
    session_ttl_minutes: number | null;
  };
  disabled: boolean;
  isPending: boolean;
  onSubmit: (values: TelegramFormValues) => void | Promise<void>;
}) {
  const form = useForm<TelegramFormValues>({
    resolver: zodResolver(telegramSchema),
    defaultValues: {
      enabled: value.enabled,
      bot_username: value.bot_username ?? '',
      media_inbox_code: value.media_inbox_code ?? '',
      session_ttl_minutes: String(value.session_ttl_minutes ?? 180),
    },
  });

  useEffect(() => {
    form.reset({
      enabled: value.enabled,
      bot_username: value.bot_username ?? '',
      media_inbox_code: value.media_inbox_code ?? '',
      session_ttl_minutes: String(value.session_ttl_minutes ?? 180),
    });
  }, [form, value.bot_username, value.enabled, value.media_inbox_code, value.session_ttl_minutes]);

  const submit = form.handleSubmit((values) => onSubmit(values));
  const enabled = form.watch('enabled');

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <InspectorDraftHint />

        <FormField
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <FormItem className="rounded-2xl border border-slate-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <FormLabel>Telegram ativo</FormLabel>
                  <FormDescription>
                    Liga o recebimento por bot privado no Telegram.
                  </FormDescription>
                </div>
                <FormControl>
                  <Switch
                    aria-label="Ativar Telegram"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={disabled || isPending}
                  />
                </FormControl>
              </div>
            </FormItem>
          )}
        />

        <div className="grid gap-4 md:grid-cols-2">
          <FormField
            control={form.control}
            name="bot_username"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Username do bot</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending || !enabled} placeholder="@EventoVivoBot" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="media_inbox_code"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Inbox code</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending || !enabled} placeholder="BOT2026" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="session_ttl_minutes"
          render={({ field }) => (
            <FormItem>
              <FormLabel>TTL da sessao (min)</FormLabel>
              <FormControl>
                <Input {...field} inputMode="numeric" disabled={disabled || isPending || !enabled} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending || !form.formState.isDirty}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar Telegram'}
          </Button>
        </div>
      </form>
    </Form>
  );
}

function JourneyPublicUploadForm({
  value,
  disabled,
  isPending,
  onSubmit,
}: {
  value: {
    enabled: boolean;
  };
  disabled: boolean;
  isPending: boolean;
  onSubmit: (values: PublicUploadFormValues) => void | Promise<void>;
}) {
  const form = useForm<PublicUploadFormValues>({
    resolver: zodResolver(publicUploadSchema),
    defaultValues: {
      enabled: value.enabled,
    },
  });

  useEffect(() => {
    form.reset({ enabled: value.enabled });
  }, [form, value.enabled]);

  const submit = form.handleSubmit((values) => onSubmit(values));

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <InspectorDraftHint />

        <FormField
          control={form.control}
          name="enabled"
          render={({ field }) => (
            <FormItem className="rounded-2xl border border-slate-200 p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <FormLabel>Link publico ativo</FormLabel>
                  <FormDescription>
                    Liga ou desliga o recebimento pelo link e QR code do evento.
                  </FormDescription>
                </div>
                <FormControl>
                  <Switch
                    aria-label="Ativar link de envio"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    disabled={disabled || isPending}
                  />
                </FormControl>
              </div>
            </FormItem>
          )}
        />

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending || !form.formState.isDirty}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar link de envio'}
          </Button>
        </div>
      </form>
    </Form>
  );
}

function JourneyReadonlyInspector({
  eventId,
}: {
  eventId: number | string;
}) {
  return (
    <Card className="border-dashed border-muted-foreground/30 bg-background/60 shadow-none">
      <CardHeader className="pb-3">
        <CardTitle className="text-base">Edicao guiada ainda nao disponivel</CardTitle>
        <CardDescription>
          Esta etapa ja pode ser consultada aqui, mas a edicao guiada ainda nao entrou nesta parte da tela.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-muted-foreground">
        <p>
          O proximo corte do painel vai cobrir bloqueio de remetentes, destinos secundarios e ajustes mais especificos do fim do fluxo.
        </p>
        <Button type="button" variant="outline" asChild>
          <Link to={`/events/${eventId}`}>Abrir editor completo do evento</Link>
        </Button>
      </CardContent>
    </Card>
  );
}

function JourneyInspectorBody({
  eventId,
  projection,
  selectedNode,
  selectedScenario = null,
  onClearScenario,
  scenarios,
  technicalDetailsOpen,
  templateDraftPreview = null,
}: Omit<JourneyInspectorProps, 'mode' | 'open' | 'onOpenChange'>) {
  const section = resolveInspectorSection(selectedNode?.id ?? null);
  const queryClient = useQueryClient();
  const { toast } = useToast();

  const contentModerationSettingsQuery = useQuery({
    queryKey: ['event-content-moderation-settings', Number(eventId)],
    queryFn: () => getEventContentModerationSettings(eventId),
    enabled: section?.kind === 'content-moderation',
  });

  const mediaIntelligenceSettingsQuery = useQuery({
    queryKey: ['event-media-intelligence-settings', Number(eventId)],
    queryFn: () => getEventMediaIntelligenceSettings(eventId),
    enabled: section?.kind === 'media-intelligence',
  });

  const presetsQuery = useQuery({
    queryKey: ['ia-media-reply-presets', 'event-form'],
    queryFn: () => aiMediaRepliesService.listPresets(),
    enabled: section?.kind === 'media-intelligence',
  });

  const updateMutation = useMutation({
    mutationFn: async (variables: {
      payload: EventJourneyUpdatePayload;
      successTitle: string;
      successDescription: string;
    }) => updateEventJourneyBuilder(eventId, variables.payload),
    onSuccess: async (_data, variables) => {
      await invalidateEventJourneyBuilderQueries(queryClient, eventId);
      toast({
        title: variables.successTitle,
        description: variables.successDescription,
      });
    },
    onError: (error) => {
      const message = error instanceof Error ? error.message : 'Nao foi possivel salvar a jornada.';

      toast({
        title: 'Falha ao salvar a jornada',
        description: message,
        variant: 'destructive',
      });
    },
  });

  const submitJourneyPatch = (payload: EventJourneyUpdatePayload, successTitle: string, successDescription: string) =>
    updateMutation.mutate({
      payload,
      successTitle,
      successDescription,
    });

  const contentModerationSettings = contentModerationSettingsQuery.data
    ? mergeJourneyContentModerationSettings(
      contentModerationSettingsQuery.data as ApiEventContentModerationSettings,
      templateDraftPreview?.payload.content_moderation,
    )
    : null;
  const mediaIntelligenceSettings = mediaIntelligenceSettingsQuery.data
    ? mergeJourneyMediaIntelligenceSettings(
      mediaIntelligenceSettingsQuery.data as ApiEventMediaIntelligenceSettings,
      templateDraftPreview?.payload.media_intelligence,
    )
    : null;
  const presets = presetsQuery.data ?? [];
  const hasTemplateDraft = templateDraftPreview !== null;
  const isLocked = !selectedNode?.data.node.editable
    || selectedNode.data.node.status === 'locked'
    || selectedNode.data.node.status === 'unavailable'
    || hasTemplateDraft;

  let sectionContent: ReactNode = null;

  if (!selectedNode || !section) {
    sectionContent = (
      <Card className="border-dashed border-muted-foreground/30 bg-background/60 shadow-none">
        <CardHeader className="pb-3">
          <CardTitle className="text-base">Nenhuma etapa selecionada</CardTitle>
          <CardDescription>
            Selecione um no no fluxo para abrir o inspector e revisar ou editar essa etapa.
          </CardDescription>
        </CardHeader>
      </Card>
    );
  } else if (section.kind === 'moderation-mode') {
    sectionContent = (
      <JourneyModerationModeForm
        value={projection.settings.moderation_mode ?? projection.event.moderation_mode ?? 'manual'}
        disabled={isLocked}
        isPending={updateMutation.isPending}
        onSubmit={(values) => {
          const payload = buildDirtyPayload(
            projection,
            (draft) => {
              draft.moderation_mode = values.moderation_mode;
            },
            { moderation_mode: true },
          );

          submitJourneyPatch(
            payload,
            'Modo de moderacao atualizado',
            'A jornada foi salva com o novo modo principal de moderacao.',
          );
        }}
      />
    );
  } else if (section.kind === 'whatsapp-direct') {
    sectionContent = (
      <JourneyWhatsAppDirectForm
        value={projection.intake_channels.whatsapp_direct}
        disabled={isLocked}
        isPending={updateMutation.isPending}
        onSubmit={(values) => {
          const payload = buildDirtyPayload(
            projection,
            (draft) => {
              draft.intake_channels.whatsapp_direct = values;
            },
            {
              intake_channels: {
                whatsapp_direct: {
                  enabled: true,
                  media_inbox_code: true,
                  session_ttl_minutes: true,
                },
              },
            },
          );

          submitJourneyPatch(
            payload,
            'WhatsApp privado atualizado',
            'A etapa de entrada por WhatsApp privado foi salva.',
          );
        }}
      />
    );
  } else if (section.kind === 'whatsapp-groups') {
    sectionContent = (
      <JourneyWhatsAppGroupsForm
        eventId={eventId}
        value={{
          enabled: projection.intake_channels.whatsapp_groups.enabled,
          groupCount: projection.intake_channels.whatsapp_groups.groups.length,
          groups: projection.intake_channels.whatsapp_groups.groups,
        }}
        disabled={isLocked}
        isPending={updateMutation.isPending}
        onSubmit={(values) => {
          const payload = buildDirtyPayload(
            projection,
            (draft) => {
              draft.intake_channels.whatsapp_groups.enabled = values.enabled;
            },
            {
              intake_channels: {
                whatsapp_groups: {
                  enabled: true,
                },
              },
            },
          );

          submitJourneyPatch(
            payload,
            'WhatsApp grupos atualizado',
            'A etapa de entrada por grupos foi salva.',
          );
        }}
      />
    );
  } else if (section.kind === 'telegram') {
    sectionContent = (
      <JourneyTelegramForm
        value={projection.intake_channels.telegram}
        disabled={isLocked}
        isPending={updateMutation.isPending}
        onSubmit={(values) => {
          const payload = buildDirtyPayload(
            projection,
            (draft) => {
              draft.intake_channels.telegram = values;
            },
            {
              intake_channels: {
                telegram: {
                  enabled: true,
                  bot_username: true,
                  media_inbox_code: true,
                  session_ttl_minutes: true,
                },
              },
            },
          );

          submitJourneyPatch(
            payload,
            'Telegram atualizado',
            'A etapa de entrada por Telegram foi salva.',
          );
        }}
      />
    );
  } else if (section.kind === 'public-upload') {
    sectionContent = (
      <JourneyPublicUploadForm
        value={projection.intake_channels.public_upload}
        disabled={isLocked}
        isPending={updateMutation.isPending}
        onSubmit={(values) => {
          const payload = buildDirtyPayload(
            projection,
            (draft) => {
              draft.intake_channels.public_upload = values;
            },
            {
              intake_channels: {
                public_upload: {
                  enabled: true,
                },
              },
            },
          );

          submitJourneyPatch(
            payload,
            'Link de envio atualizado',
            'A etapa de entrada por link publico foi salva.',
          );
        }}
      />
    );
  } else if (section.kind === 'content-moderation') {
    if (contentModerationSettingsQuery.isPending) {
      sectionContent = (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando detalhes da analise de risco...
        </div>
      );
    } else if (contentModerationSettingsQuery.isError || !contentModerationSettings) {
      sectionContent = (
        <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
          Nao foi possivel carregar os detalhes da analise de risco.
        </div>
      );
    } else {
      sectionContent = (
        <EventContentModerationSettingsForm
          settings={contentModerationSettings as ApiEventContentModerationSettings}
          eventModerationMode={projection.settings.moderation_mode ?? projection.event.moderation_mode}
          isPending={updateMutation.isPending}
          disabled={isLocked}
          onSubmit={(payload) => {
            submitJourneyPatch(
              buildContentModerationPatchPayload(payload),
              'Analise de risco atualizada',
              'As configuracoes da etapa de risco foram salvas.',
            );
          }}
        />
      );
    }
  } else if (section.kind === 'media-intelligence') {
    if (mediaIntelligenceSettingsQuery.isPending) {
      sectionContent = (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" />
          Carregando detalhes de contexto e resposta automatica...
        </div>
      );
    } else if (mediaIntelligenceSettingsQuery.isError || !mediaIntelligenceSettings) {
      sectionContent = (
        <div className="rounded-2xl border border-dashed border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">
          Nao foi possivel carregar os detalhes de contexto e resposta automatica.
        </div>
      );
    } else {
      sectionContent = (
        <EventMediaIntelligenceSettingsForm
          settings={mediaIntelligenceSettings as ApiEventMediaIntelligenceSettings}
          eventModerationMode={projection.settings.moderation_mode ?? projection.event.moderation_mode}
          presets={presets as MediaReplyPromptPreset[]}
          isPending={updateMutation.isPending}
          disabled={isLocked}
          onSubmit={(payload) => {
            submitJourneyPatch(
              buildMediaIntelligencePatchPayload(payload),
              'Contexto e resposta atualizados',
              'As configuracoes de contexto da midia e resposta automatica foram salvas.',
            );
          }}
        />
      );
    }
  } else {
    sectionContent = (
      <JourneyReadonlyInspector eventId={eventId} />
    );
  }

  return (
    <div className="flex h-full flex-col gap-4">
      {selectedScenario ? (
        <JourneyScenarioInspectorCard
          projection={projection}
          scenario={selectedScenario}
          onClear={onClearScenario}
        />
      ) : null}

      {hasTemplateDraft ? (
        <Card className="border-primary/20 bg-primary/5 shadow-none">
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Modelo em rascunho ativo</CardTitle>
            <CardDescription>
              {templateDraftPreview.template.label} ja alterou a pre-visualizacao desta tela. Salve ou descarte o modelo antes de editar manualmente neste painel.
            </CardDescription>
          </CardHeader>
        </Card>
      ) : null}

      {selectedNode && section ? (
        <Card className="border-white/70 bg-white/90 shadow-sm">
          <CardHeader className="space-y-3 pb-3">
            <div className="flex items-start gap-3">
              <div className="mt-0.5 rounded-2xl border border-primary/20 bg-primary/10 p-2 text-primary">
                <section.icon className="h-4 w-4" />
              </div>
              <div className="space-y-1">
                <CardTitle className="text-base">{section.title}</CardTitle>
                <CardDescription>{section.description}</CardDescription>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Badge variant="secondary">{humanizeJourneyStageLabel(selectedNode.data.stage.id)}</Badge>
              <Badge variant="outline">{selectedNode.data.node.editable ? 'Pode ajustar' : 'Feito pelo sistema'}</Badge>
              <Badge variant="outline">{humanizeJourneyStatusLabel(selectedNode.data.node.status)}</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <p className="leading-6 text-foreground/90">{getJourneyNodeCopy(selectedNode.data.node).summary}</p>

            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
              <p className="font-medium text-foreground">O que isso significa</p>
              <p className="mt-1">{describeJourneyStatus(selectedNode.data.node.status)}</p>
            </div>

            {selectedNode.data.node.warnings.length > 0 ? (
              <div className="rounded-2xl border border-amber-200 bg-amber-50 p-3">
                <p className="text-xs font-medium uppercase tracking-[0.16em] text-amber-800">Pontos de atencao</p>
                <ul className="mt-2 space-y-2 text-sm text-amber-900">
                  {selectedNode.data.node.warnings.map((warning) => (
                    <li key={warning} className="flex gap-2">
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                      <span>{humanizeJourneyText(warning)}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      {sectionContent}

      <InspectorMetaCard
        projection={projection}
        selectedNode={selectedNode}
        scenarios={scenarios}
        technicalDetailsOpen={technicalDetailsOpen}
      />
    </div>
  );
}

export function JourneyInspector(props: JourneyInspectorProps) {
  const {
    mode,
    open = false,
    onOpenChange,
    eventId,
    projection,
    selectedNode,
    selectedScenario,
    onClearScenario,
    scenarios,
    technicalDetailsOpen,
    templateDraftPreview,
  } = props;

  const content = (
    <ScrollArea className="h-full">
      <div className="space-y-4 pr-4">
        <JourneyInspectorBody
          eventId={eventId}
          projection={projection}
          selectedNode={selectedNode}
          selectedScenario={selectedScenario}
          onClearScenario={onClearScenario}
          scenarios={scenarios}
          technicalDetailsOpen={technicalDetailsOpen}
          templateDraftPreview={templateDraftPreview}
        />
      </div>
    </ScrollArea>
  );

  if (mode === 'drawer') {
    return (
      <Drawer open={open} onOpenChange={onOpenChange}>
        <DrawerContent
          data-testid="journey-inspector-drawer"
          className="max-h-[92vh] overflow-hidden"
        >
          <DrawerHeader>
            <DrawerTitle>Etapa selecionada</DrawerTitle>
            <DrawerDescription>
              Revise e ajuste a etapa escolhida sem sair do mapa visual.
            </DrawerDescription>
          </DrawerHeader>
          <div className="px-4 pb-6">{content}</div>
        </DrawerContent>
      </Drawer>
    );
  }

  return (
    <div
      data-testid="journey-inspector-panel"
      className={cn('h-full bg-slate-50/80 p-5')}
    >
      {content}
    </div>
  );
}
