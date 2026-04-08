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
import type { MediaReplyPromptPreset } from '@/modules/ai/types';
import type { UpdateEventMediaIntelligenceSettingsPayload } from '../../api';

const mediaIntelligenceSettingsSchema = z.object({
  enabled: z.boolean(),
  provider_key: z.enum(['vllm', 'openrouter', 'noop']),
  model_key: z.string().trim().min(1, 'Informe o modelo.').max(160, 'Use ate 160 caracteres.'),
  mode: z.enum(['enrich_only', 'gate']),
  prompt_version: z.string().trim().max(100, 'Use ate 100 caracteres.'),
  approval_prompt: z.string().trim().min(1, 'Informe o prompt principal.').max(5000, 'Use ate 5000 caracteres.'),
  caption_style_prompt: z.string().trim().min(1, 'Informe o prompt de legenda.').max(5000, 'Use ate 5000 caracteres.'),
  response_schema_version: z.string().trim().min(1, 'Informe a versao do schema.').max(100, 'Use ate 100 caracteres.'),
  timeout_ms: z.string().regex(/^\d+$/, 'Informe um inteiro em milissegundos.'),
  fallback_mode: z.enum(['review', 'skip']),
  require_json_output: z.boolean(),
  reply_text_mode: z.enum(['disabled', 'ai', 'fixed_random']),
  reply_prompt_override: z.string().trim().max(5000, 'Use ate 5000 caracteres.'),
  reply_fixed_templates_text: z.string().max(10000, 'Use ate 10000 caracteres.'),
  reply_prompt_preset_id: z.string(),
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

function templatesToTextarea(templates: string[]): string {
  return templates.join('\n');
}

function textareaToTemplates(value: string): string[] {
  return value
    .split(/\r?\n/u)
    .map((item) => item.trim())
    .filter((item) => item.length > 0);
}

function buildFormValues(settings: ApiEventMediaIntelligenceSettings): MediaIntelligenceSettingsFormValues {
  return {
    enabled: settings.enabled,
    provider_key: settings.provider_key === 'noop'
      ? 'noop'
      : settings.provider_key === 'openrouter'
        ? 'openrouter'
        : 'vllm',
    model_key: settings.model_key,
    mode: settings.mode === 'gate' ? 'gate' : 'enrich_only',
    prompt_version: settings.prompt_version ?? 'foundation-v1',
    approval_prompt: settings.approval_prompt ?? '',
    caption_style_prompt: settings.caption_style_prompt ?? '',
    response_schema_version: settings.response_schema_version ?? 'foundation-v1',
    timeout_ms: String(settings.timeout_ms ?? 12000),
    fallback_mode: settings.fallback_mode === 'skip' ? 'skip' : 'review',
    require_json_output: settings.require_json_output,
    reply_text_mode: settings.reply_text_mode === 'fixed_random'
      ? 'fixed_random'
      : settings.reply_text_mode === 'disabled'
        ? 'disabled'
        : 'ai',
    reply_prompt_override: settings.reply_prompt_override ?? '',
    reply_fixed_templates_text: templatesToTextarea(settings.reply_fixed_templates ?? []),
    reply_prompt_preset_id: settings.reply_prompt_preset_id ? String(settings.reply_prompt_preset_id) : 'none',
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
    reply_text_mode: values.reply_text_mode,
    reply_prompt_override: values.reply_text_mode === 'ai' ? values.reply_prompt_override || null : null,
    reply_fixed_templates: values.reply_text_mode === 'fixed_random'
      ? textareaToTemplates(values.reply_fixed_templates_text)
      : [],
    reply_prompt_preset_id: values.reply_text_mode === 'ai' && values.reply_prompt_preset_id !== 'none'
      ? Number(values.reply_prompt_preset_id)
      : null,
  };
}

interface EventMediaIntelligenceSettingsFormProps {
  settings: ApiEventMediaIntelligenceSettings;
  eventModerationMode: ApiEventDetail['moderation_mode'];
  presets?: MediaReplyPromptPreset[];
  isPending?: boolean;
  disabled?: boolean;
  onSubmit: (payload: UpdateEventMediaIntelligenceSettingsPayload) => void | Promise<void>;
}

export function EventMediaIntelligenceSettingsForm({
  settings,
  eventModerationMode,
  presets = [],
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
  const automaticReplyMode = form.watch('reply_text_mode');

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

        <div className="grid gap-4 md:grid-cols-2">
          <FormField
            control={form.control}
            name="reply_text_mode"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <div className="space-y-3">
                  <div>
                    <FormLabel>Tipo de resposta automatica</FormLabel>
                    <FormDescription>
                      Escolha se a midia aprovada nao responde, responde por IA ou usa um texto fixo aleatorio.
                    </FormDescription>
                  </div>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger aria-label="Tipo de resposta automatica">
                        <SelectValue placeholder="Selecione o tipo de resposta" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="disabled">Sem resposta automatica</SelectItem>
                      <SelectItem value="ai">Resposta automatica por IA</SelectItem>
                      <SelectItem value="fixed_random">Texto fixo aleatorio</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <FormMessage />
              </FormItem>
            )}
          />

          <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
            <p className="font-medium text-foreground">Contrato da resposta automatica</p>
            <p className="mt-1">
              A resposta deve ser curta, em portugues do Brasil, com 1 ou 2 emojis coerentes com a cena e sem inventar contexto visual.
            </p>
            <p className="mt-1">
              Quando o texto de instrucao do evento estiver vazio, o sistema usa a instrucao padrao configurada na area de IA.
            </p>
            <p className="mt-1">
              No modo de texto fixo, o evento pode sobrescrever a lista padrao com um texto por linha.
            </p>
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <FormField
            control={form.control}
            name="provider_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Provedor</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o provider" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="vllm">vLLM</SelectItem>
                    <SelectItem value="openrouter">OpenRouter</SelectItem>
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
                    <SelectItem value="enrich_only">Apenas enriquecer</SelectItem>
                    <SelectItem value="gate">Bloquear</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  O modo de bloqueio so deve ser usado quando o produto aceitar VLM como decisao bloqueante.
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

        {automaticReplyMode === 'ai' ? (
          <div className="space-y-4">
            <FormField
              control={form.control}
              name="reply_prompt_preset_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Preset do evento</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger aria-label="Preset do evento">
                        <SelectValue placeholder="Selecione um preset opcional" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="none">Sem preset</SelectItem>
                      {presets.map((preset) => (
                        <SelectItem key={preset.id} value={String(preset.id)}>
                          {preset.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    O preset adiciona um estilo base para a resposta automatica antes do texto de instrucao do evento.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="reply_prompt_override"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Texto de instrucao do evento</FormLabel>
                  <FormControl>
                    <Textarea
                      {...field}
                      rows={6}
                      disabled={disabled || isPending}
                      placeholder="Gere uma resposta bem curta, calorosa e baseada no que aparece na foto. Use 1 ou 2 emojis coerentes com a cena. Se {nome_do_evento} ajudar naturalmente, voce pode usar esse contexto. Se estiver incerto, retorne vazio."
                    />
                  </FormControl>
                  <FormDescription>
                    Instrucao opcional. Quando vazio, o evento herda a instrucao padrao configurada na area de IA.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        ) : null}

        {automaticReplyMode === 'fixed_random' ? (
          <FormField
            control={form.control}
            name="reply_fixed_templates_text"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Textos fixos do evento</FormLabel>
                <FormControl>
                  <Textarea
                    {...field}
                    rows={6}
                    disabled={disabled || isPending}
                    placeholder={'Memorias que fazem o coracao sorrir!\nMomento de risadas e lembrancas!'}
                  />
                </FormControl>
                <FormDescription>
                  Um texto por linha. Quando vazio, o evento usa os textos fixos padrao da area de IA.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        ) : null}

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
