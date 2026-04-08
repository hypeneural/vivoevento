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
import type { ApiEventDetail, ApiEventFaceSearchSettings } from '@/lib/api-types';
import type { UpdateEventFaceSearchSettingsPayload } from '../../api';

const faceSearchSettingsSchema = z.object({
  enabled: z.boolean(),
  provider_key: z.enum(['noop', 'compreface']),
  embedding_model_key: z.string().trim().min(1, 'Informe o modelo de embedding.').max(120, 'Use ate 120 caracteres.'),
  vector_store_key: z.enum(['pgvector']),
  search_strategy: z.enum(['exact', 'ann']),
  min_face_size_px: z.string().regex(/^\d+$/, 'Informe um numero inteiro valido.'),
  min_quality_score: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
  search_threshold: z.string().regex(/^(0(\.\d+)?|1(\.0+)?)$/, 'Use um valor entre 0 e 1.'),
  top_k: z.string().regex(/^\d+$/, 'Informe um numero inteiro valido.'),
  allow_public_selfie_search: z.boolean(),
  selfie_retention_hours: z.string().regex(/^\d+$/, 'Informe um numero inteiro valido.'),
}).superRefine((value, ctx) => {
  const minFaceSize = Number(value.min_face_size_px);
  if (!Number.isNaN(minFaceSize) && (minFaceSize < 16 || minFaceSize > 1024)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['min_face_size_px'],
      message: 'Use um valor entre 16 e 1024 px.',
    });
  }

  if (value.allow_public_selfie_search && !value.enabled) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['allow_public_selfie_search'],
      message: 'A busca publica por selfie exige FaceSearch habilitado.',
    });
  }
});

type FaceSearchSettingsFormValues = z.infer<typeof faceSearchSettingsSchema>;

function toFormValue(value: number) {
  return value.toFixed(value >= 1 ? 0 : 2).replace(/0+$/, '').replace(/\.$/, '');
}

function buildFormValues(settings: ApiEventFaceSearchSettings): FaceSearchSettingsFormValues {
  return {
    enabled: settings.enabled,
    provider_key: settings.provider_key === 'compreface' ? 'compreface' : 'noop',
    embedding_model_key: settings.embedding_model_key,
    vector_store_key: 'pgvector',
    search_strategy: settings.search_strategy === 'ann' ? 'ann' : 'exact',
    min_face_size_px: String(settings.min_face_size_px),
    min_quality_score: toFormValue(settings.min_quality_score),
    search_threshold: toFormValue(settings.search_threshold),
    top_k: String(settings.top_k),
    allow_public_selfie_search: settings.allow_public_selfie_search,
    selfie_retention_hours: String(settings.selfie_retention_hours),
  };
}

function toPayload(values: FaceSearchSettingsFormValues): UpdateEventFaceSearchSettingsPayload {
  return {
    enabled: values.enabled,
    provider_key: values.provider_key,
    embedding_model_key: values.embedding_model_key,
    vector_store_key: values.vector_store_key,
    search_strategy: values.search_strategy,
    min_face_size_px: Number(values.min_face_size_px),
    min_quality_score: Number(values.min_quality_score),
    search_threshold: Number(values.search_threshold),
    top_k: Number(values.top_k),
    allow_public_selfie_search: values.allow_public_selfie_search,
    selfie_retention_hours: Number(values.selfie_retention_hours),
  };
}

interface EventFaceSearchSettingsFormProps {
  settings: ApiEventFaceSearchSettings;
  eventModerationMode: ApiEventDetail['moderation_mode'];
  isPending?: boolean;
  disabled?: boolean;
  onSubmit: (payload: UpdateEventFaceSearchSettingsPayload) => void | Promise<void>;
}

export function EventFaceSearchSettingsForm({
  settings,
  eventModerationMode,
  isPending = false,
  disabled = false,
  onSubmit,
}: EventFaceSearchSettingsFormProps) {
  const form = useForm<FaceSearchSettingsFormValues>({
    resolver: zodResolver(faceSearchSettingsSchema),
    defaultValues: buildFormValues(settings),
  });

  useEffect(() => {
    form.reset(buildFormValues(settings));
  }, [form, settings]);

  const faceSearchEnabled = form.watch('enabled');

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
            `FaceSearch` continua fora do gate de moderacao e roda como enrichment no heavy lane.
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
                    <FormLabel>FaceSearch habilitado</FormLabel>
                    <FormDescription>
                      Liga a indexacao por face sem bloquear publish nem moderacao.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Habilitar FaceSearch"
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
            name="allow_public_selfie_search"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <FormLabel>Busca publica por selfie</FormLabel>
                    <FormDescription>
                      Controla a experiencia de "encontre minhas fotos" para convidados.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Permitir busca publica por selfie"
                      checked={field.value}
                      onCheckedChange={field.onChange}
                      disabled={!faceSearchEnabled || disabled || isPending}
                    />
                  </FormControl>
                </div>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 md:grid-cols-4">
          <FormField
            control={form.control}
            name="provider_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Provider atual</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o provider" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="noop">Noop</SelectItem>
                    <SelectItem value="compreface">CompreFace</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  CompreFace usa deteccao facial com `calculator` para embeddings.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="vector_store_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Vector store</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o vector store" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="pgvector">pgvector</SelectItem>
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="search_strategy"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Estrategia</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione a estrategia" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="exact">Exact</SelectItem>
                    <SelectItem value="ann">ANN</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  Use ANN apenas apos benchmark por evento.
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="embedding_model_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Modelo de embedding</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="face-embedding-foundation-v1" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <div className="rounded-2xl border border-slate-200 p-4">
            <h3 className="text-sm font-semibold">Qualidade minima para indexacao</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Define quando uma face entra no indice vetorial do evento.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FormField
                control={form.control}
                name="min_face_size_px"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Tamanho minimo da face (px)</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      Default homologado atual: `24 px`. Abaixo disso o recall melhora, mas o ruído tende a subir.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="min_quality_score"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Quality score minimo</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 p-4">
            <h3 className="text-sm font-semibold">Busca e retention</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Controla recall inicial e a janela de descarte da selfie temporaria.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FormField
                control={form.control}
                name="search_threshold"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Threshold de busca</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      Distancia maxima inicial usada no recall do evento.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="top_k"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Top K</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="selfie_retention_hours"
                render={({ field }) => (
                  <FormItem className="md:col-span-2">
                    <FormLabel>Retencao da selfie (horas)</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="numeric" disabled={!faceSearchEnabled || disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      Tempo maximo para selfies temporarias quando a busca publica estiver aberta.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          </div>
        </div>

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar FaceSearch'}
          </Button>
        </div>
      </form>
    </Form>
  );
}
