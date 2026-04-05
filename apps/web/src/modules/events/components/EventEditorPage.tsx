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
  Monitor,
  Save,
  ShieldCheck,
  Trash2,
  UploadCloud,
  UserCheck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { z } from 'zod';

import { useAuth } from '@/app/providers/AuthProvider';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/hooks/use-toast';
import { queryKeys } from '@/lib/query-client';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { EventStatusBadge } from '@/shared/components/StatusBadges';

import { eventsService } from '../services/events.service';
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
  retention_days: z.string(),
  face_search: z.object({
    enabled: z.boolean(),
    allow_public_selfie_search: z.boolean(),
    selfie_retention_hours: z.string().regex(/^\d+$/, 'Informe um numero de horas valido.'),
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
});

type EventFormValues = z.infer<typeof eventFormSchema>;
type EventEditorMode = 'create' | 'edit';
type BrandingPreviewState = Partial<Record<EventBrandingAssetKind, string | null>>;

const moduleItems = [
  { key: 'live' as const, label: 'Live Gallery', icon: ImageIcon, description: 'Galeria colaborativa para receber e organizar as fotos do evento.' },
  { key: 'wall' as const, label: 'Wall', icon: Monitor, description: 'Exibicao em telao com slideshow e curadoria em tempo real.' },
  { key: 'play' as const, label: 'Play', icon: Gamepad2, description: 'Mecanicas interativas e jogos para ativar o publico.' },
  { key: 'hub' as const, label: 'Hub', icon: Globe, description: 'Pagina publica centralizando links, acessos e conteudos do evento.' },
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

function buildDefaultValues(): EventFormValues {
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
    modules: {
      live: true,
      wall: false,
      play: false,
      hub: true,
    },
  };
}

function buildFormValues(event: EventDetailItem): EventFormValues {
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
    modules: {
      live: event.enabled_modules.includes('live'),
      wall: event.enabled_modules.includes('wall'),
      play: event.enabled_modules.includes('play'),
      hub: event.enabled_modules.includes('hub'),
    },
  };
}

function buildPayload(values: EventFormValues, organizationId?: number): EventFormPayload {
  return {
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
      retention_days: Number(values.retention_days),
    },
    face_search: {
      enabled: values.face_search.enabled,
      allow_public_selfie_search: values.face_search.allow_public_selfie_search,
      selfie_retention_hours: Number(values.face_search.selfie_retention_hours),
    },
  };
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

      const payload = buildPayload(values, meOrganization.id);

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

      navigate(`/events/${payload.id}`);
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
                          {EVENT_RETENTION_OPTIONS.map((option) => (
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
