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
import { Textarea } from '@/components/ui/textarea';
import type { ApiEventDetail, ApiEventMediaIntelligenceSettings } from '@/lib/api-types';
import type { UpdateEventMediaIntelligenceSettingsPayload } from '../../api';

const mediaIntelligenceSettingsSchema = z.object({
  enabled: z.boolean(),
  provider_key: z.enum(['vllm', 'noop']),
  model_key: z.string().trim().min(1, 'Informe o modelo.').max(160, 'Use ate 160 caracteres.'),
  mode: z.enum(['enrich_only', 'gate']),
  prompt_version: z.string().trim().max(100, 'Use ate 100 caracteres.'),
  approval_prompt: z.string().trim().min(1, 'Informe o prompt principal.').max(5000, 'Use ate 5000 caracteres.'),
  caption_style_prompt: z.string().trim().min(1, 'Informe o prompt de legenda.').max(5000, 'Use ate 5000 caracteres.'),
  response_schema_version: z.string().trim().min(1, 'Informe a versao do schema.').max(100, 'Use ate 100 caracteres.'),
  timeout_ms: z.string().regex(/^\d+$/, 'Informe um inteiro em milissegundos.'),
  fallback_mode: z.enum(['review', 'skip']),
  require_json_output: z.boolean(),
}).superRefine((value, ctx) => {
  if (value.mode === 'gate' && value.fallback_mode !== 'review') {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['fallback_mode'],
      message: 'Modo gate exige fallback review para nunca aprovar por erro tecnico.',
    });
  }
});

type MediaIntelligenceSettingsFormValues = z.infer<typeof mediaIntelligenceSettingsSchema>;

function buildFormValues(settings: ApiEventMediaIntelligenceSettings): MediaIntelligenceSettingsFormValues {
  return {
    enabled: settings.enabled,
    provider_key: settings.provider_key === 'noop' ? 'noop' : 'vllm',
    model_key: settings.model_key,
    mode: settings.mode === 'gate' ? 'gate' : 'enrich_only',
    prompt_version: settings.prompt_version ?? 'foundation-v1',
    approval_prompt: settings.approval_prompt ?? '',
    caption_style_prompt: settings.caption_style_prompt ?? '',
    response_schema_version: settings.response_schema_version ?? 'foundation-v1',
    timeout_ms: String(settings.timeout_ms ?? 12000),
    fallback_mode: settings.fallback_mode === 'skip' ? 'skip' : 'review',
    require_json_output: settings.require_json_output,
  };
}

function toPayload(values: MediaIntelligenceSettingsFormValues): UpdateEventMediaIntelligenceSettingsPayload {
  return {
    enabled: values.enabled,
    provider_key: values.provider_key,
    model_key: values.model_key,
    mode: values.mode,
    prompt_version: values.prompt_version || null,
    approval_prompt: values.approval_prompt,
    caption_style_prompt: values.caption_style_prompt,
    response_schema_version: values.response_schema_version,
    timeout_ms: Number(values.timeout_ms),
    fallback_mode: values.fallback_mode,
    require_json_output: values.require_json_output,
  };
}

interface EventMediaIntelligenceSettingsFormProps {
  settings: ApiEventMediaIntelligenceSettings;
  eventModerationMode: ApiEventDetail['moderation_mode'];
  isPending?: boolean;
  disabled?: boolean;
  onSubmit: (payload: UpdateEventMediaIntelligenceSettingsPayload) => void | Promise<void>;
}

export function EventMediaIntelligenceSettingsForm({
  settings,
  eventModerationMode,
  isPending = false,
  disabled = false,
  onSubmit,
}: EventMediaIntelligenceSettingsFormProps) {
  const form = useForm<MediaIntelligenceSettingsFormValues>({
    resolver: zodResolver(mediaIntelligenceSettingsSchema),
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
              ? 'VLM pode atuar como gate quando o modo interno estiver em gate.'
              : 'As configuracoes ficam salvas, mas o gate so faz efeito quando o evento estiver em modo ai.'}
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
                    <FormLabel>VLM habilitado</FormLabel>
                    <FormDescription>
                      Liga a camada semantica por evento para caption, tags e decisao opcional.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Habilitar MediaIntelligence"
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
            name="require_json_output"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <FormLabel>Forcar JSON estruturado</FormLabel>
                    <FormDescription>
                      Mantem o contrato do dominio estavel e reduz parse fragil no fast lane.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Forcar saida JSON"
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
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                    <SelectItem value="vllm">vLLM</SelectItem>
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
                <FormLabel>Modo</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o modo" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="enrich_only">Enrich only</SelectItem>
                    <SelectItem value="gate">Gate</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  `gate` so deve ser usado quando o produto aceitar VLM como bloqueante.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="fallback_mode"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Fallback</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o fallback" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="review">Review manual</SelectItem>
                    <SelectItem value="skip">Pular caption</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="timeout_ms"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Timeout (ms)</FormLabel>
                <FormControl>
                  <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <FormField
            control={form.control}
            name="model_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Modelo</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="Qwen/Qwen2.5-VL-3B-Instruct" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="prompt_version"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Versao do prompt</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="foundation-v1" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="response_schema_version"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Versao do schema</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="foundation-v1" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <FormField
            control={form.control}
            name="approval_prompt"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Prompt de decisao</FormLabel>
                <FormControl>
                  <Textarea {...field} rows={8} disabled={disabled || isPending} />
                </FormControl>
                <FormDescription>
                  Um unico prompt deve orientar decisao, motivo, caption curta e tags.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="caption_style_prompt"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Prompt de estilo da legenda</FormLabel>
                <FormControl>
                  <Textarea {...field} rows={8} disabled={disabled || isPending} />
                </FormControl>
                <FormDescription>
                  Mantem consistencia editorial das legendas sem separar outra chamada ao VLM.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar VLM'}
          </Button>
        </div>
      </form>
    </Form>
  );
}
