import { zodResolver } from '@hookform/resolvers/zod';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { ApiEventContentModerationSettings, ApiEventDetail } from '@/lib/api-types';
import type { UpdateEventContentModerationSettingsPayload } from '../../api';

const safetySettingsSchema = z.object({
  enabled: z.boolean(),
  provider_key: z.enum(['openai', 'noop']),
  mode: z.enum(['enforced', 'observe_only']),
  threshold_version: z.string().trim().max(100, 'Use ate 100 caracteres.'),
  fallback_mode: z.enum(['review', 'block']),
  hard_block_thresholds: z.object({
    nudity: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
    violence: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
    self_harm: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
  }),
  review_thresholds: z.object({
    nudity: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
    violence: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
    self_harm: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
  }),
});

type SafetySettingsFormValues = z.infer<typeof safetySettingsSchema>;

type ThresholdKey = keyof ApiEventContentModerationSettings['hard_block_thresholds'];

const THRESHOLD_FIELDS: Array<{ key: ThresholdKey; label: string }> = [
  { key: 'nudity', label: 'Nudez' },
  { key: 'violence', label: 'Violencia' },
  { key: 'self_harm', label: 'Autoagressao' },
];

function toFormValue(value: number) {
  return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function buildFormValues(settings: ApiEventContentModerationSettings): SafetySettingsFormValues {
  return {
    enabled: settings.enabled,
    provider_key: settings.provider_key === 'noop' ? 'noop' : 'openai',
    mode: settings.mode === 'observe_only' ? 'observe_only' : 'enforced',
    threshold_version: settings.threshold_version ?? 'foundation-v1',
    fallback_mode: settings.fallback_mode === 'block' ? 'block' : 'review',
    hard_block_thresholds: {
      nudity: toFormValue(settings.hard_block_thresholds.nudity),
      violence: toFormValue(settings.hard_block_thresholds.violence),
      self_harm: toFormValue(settings.hard_block_thresholds.self_harm),
    },
    review_thresholds: {
      nudity: toFormValue(settings.review_thresholds.nudity),
      violence: toFormValue(settings.review_thresholds.violence),
      self_harm: toFormValue(settings.review_thresholds.self_harm),
    },
  };
}

function toPayload(values: SafetySettingsFormValues): UpdateEventContentModerationSettingsPayload {
  return {
    enabled: values.enabled,
    provider_key: values.provider_key,
    mode: values.mode,
    threshold_version: values.threshold_version || null,
    fallback_mode: values.fallback_mode,
    hard_block_thresholds: {
      nudity: Number(values.hard_block_thresholds.nudity),
      violence: Number(values.hard_block_thresholds.violence),
      self_harm: Number(values.hard_block_thresholds.self_harm),
    },
    review_thresholds: {
      nudity: Number(values.review_thresholds.nudity),
      violence: Number(values.review_thresholds.violence),
      self_harm: Number(values.review_thresholds.self_harm),
    },
  };
}

interface EventContentModerationSettingsFormProps {
  settings: ApiEventContentModerationSettings;
  eventModerationMode: ApiEventDetail['moderation_mode'];
  isPending?: boolean;
  disabled?: boolean;
  onSubmit: (payload: UpdateEventContentModerationSettingsPayload) => void | Promise<void>;
}

export function EventContentModerationSettingsForm({
  settings,
  eventModerationMode,
  isPending = false,
  disabled = false,
  onSubmit,
}: EventContentModerationSettingsFormProps) {
  const form = useForm<SafetySettingsFormValues>({
    resolver: zodResolver(safetySettingsSchema),
    defaultValues: buildFormValues(settings),
  });

  useEffect(() => {
    form.reset(buildFormValues(settings));
  }, [form, settings]);

  const submit = form.handleSubmit((values) => onSubmit(toPayload(values)));

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
          <p>
            Modo atual do evento:
            {' '}
            <span className="font-medium text-foreground">{eventModerationMode}</span>
          </p>
          <p className="mt-1">
            {eventModerationMode === 'ai'
              ? 'Safety atua como gate no fast lane.'
              : 'As configuracoes ficam salvas, mas so viram gate quando o evento estiver em modo ai.'}
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <FormField
            control={form.control}
            name="enabled"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <FormLabel>Safety habilitado</FormLabel>
                    <FormDescription>
                      Liga a camada de safety por evento sem misturar com `FaceSearch`.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Habilitar safety"
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

          <FormField
            control={form.control}
            name="fallback_mode"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <FormLabel>Fallback em falha</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o fallback" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="review">Mandar para review</SelectItem>
                    <SelectItem value="block">Bloquear por falha</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  Nunca transforma falha tecnica em aprovacao automatica.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <FormField
            control={form.control}
            name="provider_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Provider</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o provider" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="openai">OpenAI</SelectItem>
                    <SelectItem value="noop">Noop</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="mode"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Modo interno</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o modo" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="enforced">Enforced</SelectItem>
                    <SelectItem value="observe_only">Observe only</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="threshold_version"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Versao dos thresholds</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="foundation-v1" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <div className="rounded-2xl border border-slate-200 p-4">
            <h3 className="text-sm font-semibold">Thresholds de review</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Quando ultrapassados, a midia fica pendente para operador.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {THRESHOLD_FIELDS.map(({ key, label }) => (
                <FormField
                  key={`review-${key}`}
                  control={form.control}
                  name={`review_thresholds.${key}`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{label}</FormLabel>
                      <FormControl>
                        <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ))}
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 p-4">
            <h3 className="text-sm font-semibold">Thresholds de block</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Quando ultrapassados, a midia termina bloqueada pelo safety.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {THRESHOLD_FIELDS.map(({ key, label }) => (
                <FormField
                  key={`block-${key}`}
                  control={form.control}
                  name={`hard_block_thresholds.${key}`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{label}</FormLabel>
                      <FormControl>
                        <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ))}
            </div>
          </div>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar safety'}
          </Button>
        </div>
      </form>
    </Form>
  );
}
