import { zodResolver } from '@hookform/resolvers/zod';
import { Save } from 'lucide-react';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
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
      message: 'A busca publica por selfie exige o reconhecimento facial habilitado.',
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
      message: 'Para usar a AWS como busca principal, ligue a opcao "Usar busca principal da AWS".',
    });
  }

  if (value.search_backend_key === 'aws_rekognition' && value.aws_region.trim() === '') {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ['aws_region'],
      message: 'Informe a regiao da AWS quando a busca principal usar esse motor.',
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
  const moderationModeLabel = eventModerationMode === 'ai'
    ? 'IA'
    : eventModerationMode === 'manual'
      ? 'Manual'
      : 'Sem moderacao';

  const submit = form.handleSubmit((values) => onSubmit(toPayload(values)));

  return (
    <Form {...form}>
      <form onSubmit={submit} className="space-y-5">
        <div className="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
          <p>
            Modo atual do evento:
            {' '}
            <span className="font-medium text-foreground">{moderationModeLabel}</span>
          </p>
          <p className="mt-1">
            Esta busca funciona separada da aprovacao das fotos. Ligar ou desligar aqui nao muda sozinho a moderacao do evento.
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
                    <FormLabel>Reconhecimento facial ativo</FormLabel>
                    <FormDescription>
                      Liga a preparacao das fotos para a busca de pessoas.
                    </FormDescription>
                  </div>
                  <FormControl>
                    <Switch
                      aria-label="Ativar reconhecimento facial"
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
                    <FormLabel>Liberar busca para convidados</FormLabel>
                    <FormDescription>
                      Mostra o caminho "Encontrar minhas fotos" para convidados.
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
                    <FormLabel>Usar busca principal da AWS</FormLabel>
                    <FormDescription>
                      Ativa a estrutura completa da AWS para este evento.
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

        <Accordion type="single" collapsible className="rounded-2xl border border-slate-200 px-4">
          <AccordionItem value="advanced-settings" className="border-b-0">
            <AccordionTrigger className="py-3 text-sm font-medium hover:no-underline">
              Configuracao avancada e integracao AWS
            </AccordionTrigger>
            <AccordionContent className="space-y-5 pb-4 pt-1">
        <div className="grid gap-4 md:grid-cols-4">
          <FormField
            control={form.control}
            name="provider_key"
            render={({ field }) => (
              <FormItem>
                <FormLabel>Motor local</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o motor local" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="noop">Sem busca local (noop)</SelectItem>
                    <SelectItem value="compreface">CompreFace</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  O CompreFace e a opcao local usada quando o evento precisa de comparacao fora da AWS.
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
                <FormLabel>Base vetorial local</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione a base vetorial" />
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
                <FormLabel>Tipo de busca local</FormLabel>
                <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o tipo de busca" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="exact">Busca precisa</SelectItem>
                    <SelectItem value="ann">Busca acelerada</SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  Use a busca acelerada so depois de medir o evento em ambiente real.
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
                <FormLabel>Modelo vetorial local</FormLabel>
                <FormControl>
                  <Input {...field} disabled={disabled || isPending} placeholder="face-embedding-foundation-v1" />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <div className="rounded-2xl border border-slate-200 p-4">
          <h3 className="text-sm font-semibold">Caminho principal da busca</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Define se o evento usa busca local, AWS ou comparacao silenciosa entre os dois motores.
          </p>
          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FormField
              control={form.control}
              name="search_backend_key"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Motor principal</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o motor principal" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="local_pgvector">Busca local</SelectItem>
                      <SelectItem value="aws_rekognition">AWS Rekognition</SelectItem>
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
                  <FormLabel>Reserva em caso de falha</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione a reserva" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="none">Nenhum</SelectItem>
                      <SelectItem value="local_pgvector">Busca local</SelectItem>
                      <SelectItem value="aws_rekognition">AWS Rekognition</SelectItem>
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
                  <FormLabel>Comportamento entre motores</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o comportamento" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="local_only">Somente local</SelectItem>
                      <SelectItem value="aws_primary_local_fallback">AWS principal com reserva local</SelectItem>
                      <SelectItem value="aws_primary_local_shadow">AWS principal com comparacao silenciosa</SelectItem>
                      <SelectItem value="local_primary_aws_on_error">Local principal com AWS em erro</SelectItem>
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
                  <FormLabel>Comparacao silenciosa (%)</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                  </FormControl>
                  <FormDescription>
                    Use `0` enquanto o evento ainda nao estiver em observacao controlada.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>
        </div>

        <div className="grid gap-4 xl:grid-cols-2">
          <div className="rounded-2xl border border-slate-200 p-4">
            <h3 className="text-sm font-semibold">Qualidade minima para preparar fotos</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Define quando uma foto ja pode entrar na busca do evento.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FormField
                control={form.control}
                name="min_face_size_px"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Tamanho minimo do rosto (px)</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="numeric" disabled={disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      O valor homologado atual e `24 px`. Abaixo disso entram mais rostos, mas tambem sobe o ruido.
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
                    <FormLabel>Qualidade minima</FormLabel>
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
            <h3 className="text-sm font-semibold">Busca e descarte</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Controla a sensibilidade inicial da busca e por quanto tempo a selfie temporaria fica guardada.
            </p>
            <div className="mt-4 grid gap-4 md:grid-cols-2">
              <FormField
                control={form.control}
                name="search_threshold"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Sensibilidade da busca</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      Quanto menor, mais resultados entram; quanto maior, mais rigida fica a busca.
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
                    <FormLabel>Quantidade maxima de resultados</FormLabel>
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
                    <FormLabel>Tempo para descartar selfie temporaria (horas)</FormLabel>
                    <FormControl>
                      <Input {...field} inputMode="numeric" disabled={!faceSearchEnabled || disabled || isPending} />
                    </FormControl>
                    <FormDescription>
                      Esse prazo vale quando a busca para convidados estiver liberada.
                    </FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-slate-200 p-4">
          <h3 className="text-sm font-semibold">Ajustes tecnicos da AWS</h3>
          <p className="mt-1 text-sm text-muted-foreground">
            Normalmente a equipe nao precisa mexer aqui no dia a dia. Use este bloco apenas para calibracao e rollout.
          </p>

          <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FormField
              control={form.control}
              name="aws_region"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Regiao da AWS</FormLabel>
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
                  <FormLabel>Modo principal da AWS</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o modo principal" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="faces">Fotos individuais</SelectItem>
                      <SelectItem value="users">Pessoas agrupadas</SelectItem>
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    Pessoas agrupadas so devem entrar depois da validacao tecnica completa do evento.
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
                  <FormLabel>Perfil de preparacao</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange} disabled={disabled || isPending}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Selecione o perfil de preparacao" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="selfie_friendly_event">Evento com foco em selfie</SelectItem>
                      <SelectItem value="social_gallery_event">Galeria social</SelectItem>
                      <SelectItem value="corporate_stage_event">Palco corporativo</SelectItem>
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
                  <FormLabel>Maximo de rostos por foto</FormLabel>
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
                  <FormLabel>Filtro de qualidade na preparacao</FormLabel>
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
                  <FormLabel>Filtro de qualidade na busca por foto</FormLabel>
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
                  <FormLabel>Filtro de qualidade na busca por pessoa</FormLabel>
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
                  <FormLabel>Confianca minima em fotos</FormLabel>
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
                  <FormLabel>Confianca minima em pessoas</FormLabel>
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
                  <FormLabel>Confianca minima para agrupar fotos</FormLabel>
                  <FormControl>
                    <Input {...field} inputMode="decimal" disabled={disabled || isPending} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-muted-foreground">
              <p className="font-medium text-foreground">Atencao sobre as comparacoes</p>
              <p className="mt-1">
                Esses numeros sao da AWS e nao equivalem a sensibilidade da busca local.
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
                      <FormLabel>Detectar rosto parcialmente encoberto</FormLabel>
                      <FormDescription>
                        Mantem a leitura padrao e adiciona a checagem de rosto parcialmente coberto.
                      </FormDescription>
                    </div>
                    <FormControl>
                      <Switch
                        aria-label="Detectar rosto parcialmente encoberto"
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
                      <FormLabel>Apagar estrutura AWS ao encerrar</FormLabel>
                      <FormDescription>
                        Remove a estrutura remota quando o evento for encerrado e a politica operacional pedir limpeza.
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
              <p className="font-semibold">Estrutura AWS pronta</p>
              <div className="mt-2 grid gap-2 md:grid-cols-3">
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">ID da estrutura</p>
                  <p className="break-all font-medium">{settings.aws_collection_id ?? 'n/a'}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">Modelo facial</p>
                  <p className="font-medium">{settings.aws_face_model_version ?? 'n/a'}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-wide text-emerald-700">Regiao</p>
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
              A busca principal na AWS so pode ser salva quando a opcao "Usar busca principal da AWS" estiver ligada.
            </div>
          ) : null}
        </div>
            </AccordionContent>
          </AccordionItem>
        </Accordion>

        <div className="flex justify-end">
          <Button type="submit" disabled={disabled || isPending}>
            <Save className="mr-1.5 h-4 w-4" />
            {isPending ? 'Salvando...' : 'Salvar reconhecimento facial'}
          </Button>
        </div>
      </form>
    </Form>
  );
}
