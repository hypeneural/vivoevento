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

const qualityFilterValues = ['AUTO', 'LOW', 'MEDIUM', 'HIGH', 'NONE'] as const;
const awsIndexProfileValues = [
  'selfie_friendly_event',
  'social_gallery_event',
  'corporate_stage_event',
] as const;

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
  recognition_enabled: z.boolean(),
  search_backend_key: z.enum(['local_pgvector', 'aws_rekognition']),
  fallback_backend_key: z.enum(['none', 'local_pgvector', 'aws_rekognition']),
  routing_policy: z.enum([
    'local_only',
    'aws_primary_local_fallback',
    'aws_primary_local_shadow',
    'local_primary_aws_on_error',
  ]),
  shadow_mode_percentage: z.string().regex(/^\d+$/, 'Informe um numero inteiro valido.'),
  aws_region: z.string().trim().min(1, 'Informe a regiao AWS.').max(40, 'Use ate 40 caracteres.'),
  aws_search_mode: z.enum(['faces', 'users']),
  aws_index_quality_filter: z.enum(qualityFilterValues),
  aws_search_faces_quality_filter: z.enum(qualityFilterValues),
  aws_search_users_quality_filter: z.enum(qualityFilterValues),
  aws_search_face_match_threshold: z.string().regex(/^\d+(\.\d+)?$/, 'Use um valor numerico entre 0 e 100.'),
  aws_search_user_match_threshold: z.string().regex(/^\d+(\.\d+)?$/, 'Use um valor numerico entre 0 e 100.'),
  aws_associate_user_match_threshold: z.string().regex(/^\d+(\.\d+)?$/, 'Use um valor numerico entre 0 e 100.'),
  aws_max_faces_per_image: z.string().regex(/^\d+$/, 'Informe um numero inteiro valido.'),
  aws_index_profile_key: z.enum(awsIndexProfileValues),
  aws_detection_attribute_face_occluded: z.boolean(),
  delete_remote_vectors_on_event_close: z.boolean(),
}).superRefine((value, ctx) => {
  const minFaceSize = Number(value.min_face_size_px);
  const shadowModePercentage = Number(value.shadow_mode_percentage);
  const awsFaceThreshold = Number(value.aws_search_face_match_threshold);
  const awsUserThreshold = Number(value.aws_search_user_match_threshold);
  const awsAssociateThreshold = Number(value.aws_associate_user_match_threshold);
  const awsMaxFacesPerImage = Number(value.aws_max_faces_per_image);

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

  if (!Number.isNaN(shadowModePercentage) && (shadowModePercentage < 0 || shadowModePercentage > 100)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['shadow_mode_percentage'],
      message: 'Use um valor entre 0 e 100.',
    });
  }

  if (value.search_backend_key === 'aws_rekognition' && !value.recognition_enabled) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['recognition_enabled'],
      message: 'O backend AWS exige recognition_enabled=true.',
    });
  }

  if (value.search_backend_key === 'aws_rekognition' && value.aws_region.trim() === '') {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_region'],
      message: 'Informe a regiao AWS quando o backend for aws_rekognition.',
    });
  }

  if (!Number.isNaN(awsFaceThreshold) && (awsFaceThreshold < 0 || awsFaceThreshold > 100)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_search_face_match_threshold'],
      message: 'Use um valor entre 0 e 100.',
    });
  }

  if (!Number.isNaN(awsUserThreshold) && (awsUserThreshold < 0 || awsUserThreshold > 100)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_search_user_match_threshold'],
      message: 'Use um valor entre 0 e 100.',
    });
  }

  if (!Number.isNaN(awsAssociateThreshold) && (awsAssociateThreshold < 0 || awsAssociateThreshold > 100)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_associate_user_match_threshold'],
      message: 'Use um valor entre 0 e 100.',
    });
  }

  if (!Number.isNaN(awsMaxFacesPerImage) && (awsMaxFacesPerImage < 1 || awsMaxFacesPerImage > 100)) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_max_faces_per_image'],
      message: 'Use um valor entre 1 e 100.',
    });
  }
});

type FaceSearchSettingsFormValues = z.infer<typeof faceSearchSettingsSchema>;

function toFormValue(value: number) {
  if (Number.isInteger(value)) {
    return String(value);
  }

  return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

function buildFallbackBackendValue(settings: ApiEventFaceSearchSettings): FaceSearchSettingsFormValues['fallback_backend_key'] {
  return settings.fallback_backend_key === 'aws_rekognition'
    ? 'aws_rekognition'
    : settings.fallback_backend_key === 'local_pgvector'
      ? 'local_pgvector'
      : 'none';
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
    recognition_enabled: settings.recognition_enabled,
    search_backend_key: settings.search_backend_key === 'aws_rekognition' ? 'aws_rekognition' : 'local_pgvector',
    fallback_backend_key: buildFallbackBackendValue(settings),
    routing_policy: settings.routing_policy === 'aws_primary_local_fallback'
      || settings.routing_policy === 'aws_primary_local_shadow'
      || settings.routing_policy === 'local_primary_aws_on_error'
      ? settings.routing_policy
      : 'local_only',
    shadow_mode_percentage: String(settings.shadow_mode_percentage),
    aws_region: settings.aws_region,
    aws_search_mode: settings.aws_search_mode === 'users' ? 'users' : 'faces',
    aws_index_quality_filter: qualityFilterValues.includes(settings.aws_index_quality_filter as typeof qualityFilterValues[number])
      ? (settings.aws_index_quality_filter as typeof qualityFilterValues[number])
      : 'AUTO',
    aws_search_faces_quality_filter: qualityFilterValues.includes(settings.aws_search_faces_quality_filter as typeof qualityFilterValues[number])
      ? (settings.aws_search_faces_quality_filter as typeof qualityFilterValues[number])
      : 'NONE',
    aws_search_users_quality_filter: qualityFilterValues.includes(settings.aws_search_users_quality_filter as typeof qualityFilterValues[number])
      ? (settings.aws_search_users_quality_filter as typeof qualityFilterValues[number])
      : 'NONE',
    aws_search_face_match_threshold: toFormValue(settings.aws_search_face_match_threshold),
    aws_search_user_match_threshold: toFormValue(settings.aws_search_user_match_threshold),
    aws_associate_user_match_threshold: toFormValue(settings.aws_associate_user_match_threshold),
    aws_max_faces_per_image: String(settings.aws_max_faces_per_image),
    aws_index_profile_key: awsIndexProfileValues.includes(settings.aws_index_profile_key as typeof awsIndexProfileValues[number])
      ? (settings.aws_index_profile_key as typeof awsIndexProfileValues[number])
      : 'social_gallery_event',
    aws_detection_attribute_face_occluded: settings.aws_detection_attributes_json.includes('FACE_OCCLUDED'),
    delete_remote_vectors_on_event_close: settings.delete_remote_vectors_on_event_close,
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
    recognition_enabled: values.recognition_enabled,
    search_backend_key: values.search_backend_key,
    fallback_backend_key: values.fallback_backend_key === 'none' ? null : values.fallback_backend_key,
    routing_policy: values.routing_policy,
    shadow_mode_percentage: Number(values.shadow_mode_percentage),
    aws_region: values.aws_region.trim(),
    aws_search_mode: values.aws_search_mode,
    aws_index_quality_filter: values.aws_index_quality_filter,
    aws_search_faces_quality_filter: values.aws_search_faces_quality_filter,
    aws_search_users_quality_filter: values.aws_search_users_quality_filter,
    aws_search_face_match_threshold: Number(values.aws_search_face_match_threshold),
    aws_search_user_match_threshold: Number(values.aws_search_user_match_threshold),
    aws_associate_user_match_threshold: Number(values.aws_associate_user_match_threshold),
    aws_max_faces_per_image: Number(values.aws_max_faces_per_image),
    aws_index_profile_key: values.aws_index_profile_key,
    aws_detection_attributes_json: values.aws_detection_attribute_face_occluded
      ? ['DEFAULT', 'FACE_OCCLUDED']
      : ['DEFAULT'],
    delete_remote_vectors_on_event_close: values.delete_remote_vectors_on_event_close,
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
  const recognitionEnabled = form.watch('recognition_enabled');
  const searchBackendKey = form.watch('search_backend_key');
  const awsBackendSelected = searchBackendKey === 'aws_rekognition';

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

        <div className="grid gap-4 md:grid-cols-3">
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

          <FormField
            control={form.control}
            name="recognition_enabled"
            render={({ field }) => (
              <FormItem className="rounded-2xl border border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <FormLabel>Reconhecimento gerenciado</FormLabel>
                    <FormDescription>
                      Permite usar backend gerenciado por evento quando o roteamento apontar para AWS.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Habilitar reconhecimento gerenciado"
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

        <div className="rounded-2xl border border-slate-200 p-4">
          <h3 className="text-sm font-semibold">Roteamento de backend</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Separa lane local `pgvector` do backend gerenciado e define fallback por evento.
          </p>
          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FormField
              control={form.control}
              name="search_backend_key"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Backend principal</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o backend" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="local_pgvector">local_pgvector</SelectItem>
                      <SelectItem value="aws_rekognition">aws_rekognition</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="fallback_backend_key"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Backend de fallback</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o fallback" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="none">Nenhum</SelectItem>
                      <SelectItem value="local_pgvector">local_pgvector</SelectItem>
                      <SelectItem value="aws_rekognition">aws_rekognition</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="routing_policy"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Politica de rota</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione a politica" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="local_only">local_only</SelectItem>
                      <SelectItem value="aws_primary_local_fallback">aws_primary_local_fallback</SelectItem>
                      <SelectItem value="aws_primary_local_shadow">aws_primary_local_shadow</SelectItem>
                      <SelectItem value="local_primary_aws_on_error">local_primary_aws_on_error</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="shadow_mode_percentage"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Shadow mode (%)</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                  </FormControl>
                  <FormDescription>
                    Use `0` no MVP e aumente so depois do rollout controlado.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
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

        <div className="rounded-2xl border border-slate-200 p-4">
          <h3 className="text-sm font-semibold">AWS Rekognition</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Configura a collection do evento, thresholds nativos da AWS e a politica de indexacao do backend gerenciado.
          </p>

          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FormField
              control={form.control}
              name="aws_region"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Regiao AWS</FormLabel>
                  <FormControl>
                    <Input {...field} disabled={disabled || isPending} placeholder="eu-central-1" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_search_mode"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Modo de busca AWS</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o modo" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="faces">faces</SelectItem>
                      <SelectItem value="users">users</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    `users` so deve entrar depois da fase 2 com vetores consolidados.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_index_profile_key"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Perfil de indexacao</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o perfil" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="selfie_friendly_event">selfie_friendly_event</SelectItem>
                      <SelectItem value="social_gallery_event">social_gallery_event</SelectItem>
                      <SelectItem value="corporate_stage_event">corporate_stage_event</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_max_faces_per_image"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Max faces por imagem</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <FormField
              control={form.control}
              name="aws_index_quality_filter"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Quality filter no index</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o filtro" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {qualityFilterValues.map((value) => (
                        <SelectItem key={value} value={value}>{value}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_search_faces_quality_filter"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Quality filter em SearchFacesByImage</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o filtro" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {qualityFilterValues.map((value) => (
                        <SelectItem key={value} value={value}>{value}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_search_users_quality_filter"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Quality filter em SearchUsersByImage</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o filtro" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {qualityFilterValues.map((value) => (
                        <SelectItem key={value} value={value}>{value}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FormField
              control={form.control}
              name="aws_search_face_match_threshold"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Threshold faces</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_search_user_match_threshold"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Threshold users</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="aws_associate_user_match_threshold"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Threshold de associacao</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
              <p className="font-medium text-foreground">Semantica separada</p>
              <p className="mt-1">
                Esses thresholds sao `0-100` da AWS e nao equivalem ao `search_threshold` local do `pgvector`.
              </p>
            </div>
          </div>

          <div className="mt-4 grid gap-4 md:grid-cols-2">
            <FormField
              control={form.control}
              name="aws_detection_attribute_face_occluded"
              render={({ field }) => (
                <FormItem className="rounded-2xl border border-slate-200 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <FormLabel>DetectionAttributes: FACE_OCCLUDED</FormLabel>
                      <FormDescription>
                        O MVP sempre envia `DEFAULT`; esta flag adiciona `FACE_OCCLUDED` sem usar `ALL`.
                      </FormDescription>
                    </div>
                    <FormControl>
                      <Switch
                        aria-label="Ativar FACE_OCCLUDED"
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
              name="delete_remote_vectors_on_event_close"
              render={({ field }) => (
                <FormItem className="rounded-2xl border border-slate-200 p-4">
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <FormLabel>Limpar collection no encerramento</FormLabel>
                      <FormDescription>
                        Remove vetores remotos quando o evento for encerrado e a politica operacional exigir cleanup.
                      </FormDescription>
                    </div>
                    <FormControl>
                      <Switch
                        aria-label="Apagar vetores remotos no encerramento"
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

          {(settings.aws_collection_id || settings.aws_collection_arn || settings.aws_face_model_version) ? (
            <div className="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
              <p className="font-semibold">Collection provisionada</p>
              <div className="mt-2 grid gap-2 md:grid-cols-3">
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">Collection ID</p>
                  <p className="break-all font-medium">{settings.aws_collection_id ?? 'n/a'}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">Face model</p>
                  <p className="font-medium">{settings.aws_face_model_version ?? 'n/a'}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">Region</p>
                  <p className="font-medium">{settings.aws_region}</p>
                </div>
              </div>
              {settings.aws_collection_arn ? (
                <p className="mt-3 break-all text-xs text-emerald-800">{settings.aws_collection_arn}</p>
              ) : null}
            </div>
          ) : null}

          {awsBackendSelected && !recognitionEnabled ? (
            <div className="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
              O backend `aws_rekognition` so pode ser salvo com `recognition_enabled=true`.
            </div>
          ) : null}
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
