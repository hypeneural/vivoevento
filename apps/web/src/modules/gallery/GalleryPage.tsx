import { useDeferredValue, useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { Eye, EyeOff, ExternalLink, FilterX, ImageIcon, LayoutGrid, Loader2, Pin, Search, Star } from 'lucide-react';
import { Link } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventMediaDetail, ApiEventMediaItem } from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';
import { eventsService } from '@/modules/events/services/events.service';
import type { EventListItem } from '@/modules/events/types';
import { MEDIA_CATALOG_CHANNEL_OPTIONS, MEDIA_CATALOG_TYPE_OPTIONS } from '@/modules/media/types';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { ChannelBadge } from '@/shared/components/StatusBadges';
import { StatsCard } from '@/shared/components/StatsCard';
import { usePermissions } from '@/shared/hooks/usePermissions';

import { galleryService } from './services/gallery.service';
import { GALLERY_ORIENTATION_OPTIONS, GALLERY_PUBLICATION_OPTIONS, GALLERY_SORT_OPTIONS, type GalleryCatalogFilters } from './types';

const EMPTY_STATS = {
  total: 0,
  images: 0,
  videos: 0,
  pending: 0,
  published: 0,
  featured: 0,
  pinned: 0,
  duplicates: 0,
  face_indexed: 0,
};

const PUB_LABELS: Record<string, string> = {
  published: 'Publicada',
  hidden: 'Oculta',
  draft: 'Pronta',
  deleted: 'Removida',
};

const PUB_CLASS: Record<string, string> = {
  published: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  hidden: 'border-amber-200 bg-amber-50 text-amber-700',
  draft: 'border-sky-200 bg-sky-50 text-sky-700',
  deleted: 'border-rose-200 bg-rose-50 text-rose-700',
};

type GalleryMutation =
  | { type: 'featured'; mediaId: number; desiredValue: boolean }
  | { type: 'pinned'; mediaId: number; desiredValue: boolean }
  | { type: 'publish'; eventId: number; mediaId: number }
  | { type: 'hide'; eventId: number; mediaId: number };

function fmt(value?: string | null) {
  if (!value) return 'Nao disponivel';
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
}

function publicationKey(value?: string | null) {
  return value && value in PUB_LABELS ? value : 'draft';
}

function PublicationBadge({ value }: { value?: string | null }) {
  const key = publicationKey(value);
  return <Badge variant="outline" className={PUB_CLASS[key]}>{PUB_LABELS[key]}</Badge>;
}

function MediaSurface({
  media,
  className,
}: {
  media: ApiEventMediaItem | ApiEventMediaDetail;
  className: string;
}) {
  const src = media.preview_url || media.thumbnail_url || media.original_url;

  if (!src) {
    return (
      <div className={`flex items-center justify-center bg-muted text-muted-foreground ${className}`}>
        <ImageIcon className="h-8 w-8" />
      </div>
    );
  }

  if (media.media_type === 'video' && media.preview_url) {
    return <video src={media.preview_url} className={className} muted playsInline preload="metadata" />;
  }

  return <img src={src} alt={media.caption || media.sender_name} className={className} loading="lazy" decoding="async" />;
}

export default function GalleryPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const { can } = usePermissions();
  const canView = can('gallery.view') || can('gallery.manage');
  const canManageGallery = can('gallery.manage');
  const canFeature = can('media.moderate');

  const [mode, setMode] = useState<'cards' | 'preview'>('cards');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(24);
  const [search, setSearch] = useState('');
  const [eventId, setEventId] = useState('all');
  const [publicationStatus, setPublicationStatus] = useState('all');
  const [channel, setChannel] = useState('all');
  const [mediaType, setMediaType] = useState('all');
  const [orientation, setOrientation] = useState('all');
  const [sortBy, setSortBy] = useState<'sort_order' | 'published_at' | 'created_at'>('sort_order');
  const [featuredOnly, setFeaturedOnly] = useState(false);
  const [pinnedOnly, setPinnedOnly] = useState(false);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const deferredSearch = useDeferredValue(search);

  const filters = useMemo<GalleryCatalogFilters>(() => ({
    page,
    per_page: perPage,
    search: deferredSearch || undefined,
    event_id: eventId === 'all' ? undefined : Number(eventId),
    publication_status: publicationStatus === 'all' ? undefined : publicationStatus as any,
    channel: channel === 'all' ? undefined : channel as any,
    media_type: mediaType === 'all' ? undefined : mediaType as any,
    orientation: orientation === 'all' ? undefined : orientation as any,
    featured: featuredOnly ? true : undefined,
    pinned: pinnedOnly ? true : undefined,
    sort_by: sortBy,
    sort_direction: 'desc',
  }), [channel, deferredSearch, eventId, featuredOnly, mediaType, orientation, page, perPage, pinnedOnly, publicationStatus, sortBy]);

  useEffect(() => {
    setPage(1);
  }, [channel, deferredSearch, eventId, featuredOnly, mediaType, orientation, pinnedOnly, publicationStatus, sortBy]);

  const eventsQuery = useQuery({
    queryKey: queryKeys.events.list({ scope: 'gallery-options' }),
    queryFn: () => eventsService.list({ per_page: 100, sort_by: 'starts_at', sort_direction: 'desc' }),
    enabled: canView,
  });

  const galleryQuery = useQuery({
    queryKey: queryKeys.gallery.list(filters),
    queryFn: () => galleryService.list(filters),
    enabled: canView,
  });

  const detailQuery = useQuery({
    queryKey: queryKeys.gallery.detail(String(selectedId ?? '')),
    queryFn: () => galleryService.show(selectedId as number),
    enabled: selectedId !== null,
  });

  const actionMutation = useMutation({
    mutationFn: (payload: GalleryMutation) => {
      switch (payload.type) {
        case 'featured':
          return galleryService.updateFavorite(payload.mediaId, payload.desiredValue);
        case 'pinned':
          return galleryService.updatePinned(payload.mediaId, payload.desiredValue);
        case 'publish':
          return galleryService.publish(payload.eventId, payload.mediaId);
        case 'hide':
          return galleryService.hide(payload.eventId, payload.mediaId);
      }
    },
    onSuccess: async (_data, payload) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.gallery.all() }),
        queryClient.invalidateQueries({ queryKey: queryKeys.media.all(), refetchType: 'inactive' }),
        queryClient.invalidateQueries({ queryKey: queryKeys.events.all(), refetchType: 'inactive' }),
      ]);

      const titles: Record<GalleryMutation['type'], string> = {
        featured: payload.desiredValue ? 'Midia destacada' : 'Destaque removido',
        pinned: payload.desiredValue ? 'Midia fixada' : 'Midia desafixada',
        publish: 'Midia publicada',
        hide: 'Midia ocultada',
      };

      toast({ title: titles[payload.type] });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar a galeria',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  if (!canView) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-muted-foreground">
        Seu perfil nao possui acesso a galeria.
      </div>
    );
  }

  const events = (eventsQuery.data?.data ?? []) as EventListItem[];
  const items = galleryQuery.data?.data ?? [];
  const meta = galleryQuery.data?.meta;
  const stats = meta?.stats ?? EMPTY_STATS;
  const selected = (detailQuery.data ?? items.find((item) => item.id === selectedId)) as ApiEventMediaItem | ApiEventMediaDetail | null;
  const previewItems = items.filter((item) => item.publication_status === 'published');
  const hasFilters = Boolean(
    deferredSearch
    || eventId !== 'all'
    || publicationStatus !== 'all'
    || channel !== 'all'
    || mediaType !== 'all'
    || orientation !== 'all'
    || featuredOnly
    || pinnedOnly
    || sortBy !== 'sort_order',
  );

  function clearFilters() {
    setSearch('');
    setEventId('all');
    setPublicationStatus('all');
    setChannel('all');
    setMediaType('all');
    setOrientation('all');
    setSortBy('sort_order');
    setFeaturedOnly(false);
    setPinnedOnly(false);
    setPage(1);
  }

  function isBusy(mediaId: number) {
    return actionMutation.isPending && actionMutation.variables?.mediaId === mediaId;
  }

  function renderVisibilityButton(item: ApiEventMediaItem | ApiEventMediaDetail, compact = false) {
    if (!canManageGallery || item.publication_status === 'deleted') {
      return null;
    }

    const isPublished = item.publication_status === 'published';

    return (
      <Button
        type="button"
        size={compact ? 'sm' : 'default'}
        variant={isPublished ? 'outline' : 'default'}
        onClick={() => actionMutation.mutate(
          isPublished
            ? { type: 'hide', eventId: item.event_id, mediaId: item.id }
            : { type: 'publish', eventId: item.event_id, mediaId: item.id },
        )}
        disabled={isBusy(item.id)}
      >
        {isPublished ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        {isPublished ? 'Ocultar' : 'Publicar'}
      </Button>
    );
  }

  return (
    <>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
        <PageHeader
          title="Galeria"
          description={`${meta?.total ?? 0} midias aprovadas vindas da API real`}
          actions={(
            <div className="flex gap-2">
              <Button size="sm" variant={mode === 'cards' ? 'default' : 'outline'} onClick={() => setMode('cards')}>
                <LayoutGrid className="h-4 w-4" />
                Curadoria
              </Button>
              <Button size="sm" variant={mode === 'preview' ? 'default' : 'outline'} onClick={() => setMode('preview')}>
                <Eye className="h-4 w-4" />
                Preview
              </Button>
            </div>
          )}
        />

        <section className="overflow-hidden rounded-[30px] border border-border/60 bg-[radial-gradient(circle_at_top_left,_rgba(34,197,94,0.18),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(59,130,246,0.18),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.95),_rgba(248,250,252,0.9))] p-5 shadow-sm dark:bg-[radial-gradient(circle_at_top_left,_rgba(22,163,74,0.2),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(37,99,235,0.18),_transparent_28%),linear-gradient(180deg,_rgba(15,23,42,0.96),_rgba(15,23,42,0.84))]">
          <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div className="max-w-2xl space-y-3">
              <Badge variant="outline" className="border-primary/25 bg-primary/10 text-primary">Curadoria real</Badge>
              <div className="space-y-2">
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">A pagina reflete a ordem publica da galeria e agora tambem publica ou oculta direto daqui.</h2>
                <p className="text-sm leading-6 text-muted-foreground">O recorte usa o modulo Gallery no backend, focado em midias aprovadas e no estado real de exibicao publica.</p>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 rounded-[26px] border border-white/50 bg-white/70 p-4 backdrop-blur dark:border-white/10 dark:bg-white/5">
              <div><p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Recorte</p><p className="mt-1 text-2xl font-semibold">{stats.total}</p></div>
              <div><p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Publicadas</p><p className="mt-1 text-2xl font-semibold">{stats.published}</p></div>
              <div><p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Destaques</p><p className="mt-1 text-2xl font-semibold">{stats.featured}</p></div>
              <div><p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Fixadas</p><p className="mt-1 text-2xl font-semibold">{stats.pinned}</p></div>
            </div>
          </div>
        </section>

        <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
          <StatsCard title="Midias" value={stats.total} icon={ImageIcon} />
          <StatsCard title="Publicadas" value={stats.published} icon={Eye} />
          <StatsCard title="Destaques" value={stats.featured} icon={Star} />
          <StatsCard title="Fixadas" value={stats.pinned} icon={Pin} />
        </div>

        <section className="glass rounded-[28px] border border-border/60 p-4 sm:p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <p className="text-lg font-semibold">Filtros</p>
              <p className="text-sm text-muted-foreground">A listagem ja vem do backend com ordenacao de galeria.</p>
            </div>
            <div className="flex items-center gap-2">
              {hasFilters ? <Badge variant="outline">Filtros ativos</Badge> : null}
              {galleryQuery.isFetching ? <Badge variant="outline">Sincronizando</Badge> : null}
            </div>
          </div>

          <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
            <div className="relative md:col-span-2 xl:col-span-4">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input placeholder="Buscar por evento, legenda ou remetente" value={search} onChange={(event) => setSearch(event.target.value)} className="pl-9" />
            </div>

            <Select value={eventId} onValueChange={setEventId}>
              <SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Evento" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os eventos</SelectItem>
                {events.map((event) => <SelectItem key={event.id} value={String(event.id)}>{event.title}</SelectItem>)}
              </SelectContent>
            </Select>

            <Select value={publicationStatus} onValueChange={setPublicationStatus}>
              <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Estado" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Qualquer estado</SelectItem>
                {GALLERY_PUBLICATION_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
              </SelectContent>
            </Select>

            <Select value={channel} onValueChange={setChannel}>
              <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Canal" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os canais</SelectItem>
                {MEDIA_CATALOG_CHANNEL_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
              </SelectContent>
            </Select>

            <Select value={mediaType} onValueChange={setMediaType}>
              <SelectTrigger className="xl:col-span-1"><SelectValue placeholder="Tipo" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tudo</SelectItem>
                {MEDIA_CATALOG_TYPE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-12">
            <Select value={orientation} onValueChange={setOrientation}>
              <SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Formato" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos os formatos</SelectItem>
                {GALLERY_ORIENTATION_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
              </SelectContent>
            </Select>

            <Select value={sortBy} onValueChange={(value) => setSortBy(value as any)}>
              <SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Ordenacao" /></SelectTrigger>
              <SelectContent>
                {GALLERY_SORT_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
              </SelectContent>
            </Select>

            <Select value={String(perPage)} onValueChange={(value) => setPerPage(Number(value))}>
              <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Densidade" /></SelectTrigger>
              <SelectContent>
                {[12, 24, 36, 48].map((option) => <SelectItem key={option} value={String(option)}>{option}/pag</SelectItem>)}
              </SelectContent>
            </Select>

            <div className="flex gap-2 xl:col-span-4 xl:justify-end">
              <Button type="button" variant={featuredOnly ? 'default' : 'outline'} onClick={() => setFeaturedOnly((current) => !current)}>
                <Star className={`h-4 w-4 ${featuredOnly ? 'fill-current' : ''}`} />
                Destaque
              </Button>
              <Button type="button" variant={pinnedOnly ? 'default' : 'outline'} onClick={() => setPinnedOnly((current) => !current)}>
                <Pin className="h-4 w-4" />
                Fixadas
              </Button>
              <Button type="button" variant="ghost" onClick={clearFilters}>
                <FilterX className="h-4 w-4" />
                Limpar
              </Button>
            </div>
          </div>
        </section>

        {galleryQuery.isLoading ? (
          <div className="rounded-[28px] border border-border/60 bg-background/80 px-4 py-20 text-center text-muted-foreground">
            <Loader2 className="mx-auto mb-3 h-6 w-6 animate-spin" />
            Carregando galeria...
          </div>
        ) : null}

        {galleryQuery.isError ? (
          <div className="rounded-[28px] border border-destructive/30 bg-destructive/5 px-4 py-16 text-center text-sm text-destructive">
            Nao foi possivel carregar a galeria agora.
          </div>
        ) : null}

        {!galleryQuery.isLoading && !galleryQuery.isError && items.length === 0 ? (
          <div className="glass rounded-[28px] border border-border/60">
            <EmptyState
              icon={ImageIcon}
              title="Nenhuma midia encontrada"
              description="Ajuste os filtros ou aguarde novas fotos aprovadas para a galeria."
              action={<Button variant="outline" onClick={clearFilters}>Limpar filtros</Button>}
            />
          </div>
        ) : null}

        {!galleryQuery.isLoading && !galleryQuery.isError && items.length > 0 ? (
          <>
            {mode === 'preview' ? (
              <section className="overflow-hidden rounded-[30px] border border-slate-200 bg-[radial-gradient(circle_at_top,_rgba(34,197,94,0.18),_transparent_35%),linear-gradient(180deg,_#020617_0%,_#0f172a_100%)] px-4 py-6 text-white sm:px-6">
                <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                  <div>
                    <p className="text-xs uppercase tracking-[0.28em] text-white/55">Preview publico</p>
                    <h2 className="text-2xl font-semibold">Apenas o que esta publicado no recorte atual.</h2>
                  </div>
                  <Badge variant="outline" className="w-fit border-white/20 bg-white/10 text-white">{previewItems.length} item(ns)</Badge>
                </div>

                {previewItems.length === 0 ? (
                  <div className="rounded-[24px] border border-white/10 bg-white/5 px-4 py-14 text-center text-sm text-white/70">
                    Nenhuma midia publicada neste recorte.
                  </div>
                ) : (
                  <div className="columns-2 gap-3 space-y-3 md:columns-3 lg:columns-4">
                    {previewItems.map((item) => (
                      <button key={item.id} type="button" className="block w-full break-inside-avoid overflow-hidden rounded-[22px] border border-white/10 bg-white/5 text-left" onClick={() => setSelectedId(item.id)}>
                        <MediaSurface media={item} className="w-full object-cover" />
                      </button>
                    ))}
                  </div>
                )}
              </section>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                {items.map((item) => (
                  <article key={item.id} className="glass overflow-hidden rounded-[26px] border border-border/60 shadow-sm">
                    <button type="button" className="block w-full text-left" onClick={() => setSelectedId(item.id)}>
                      <MediaSurface media={item} className="h-56 w-full object-cover" />
                    </button>

                    <div className="space-y-4 p-4">
                      <div className="flex flex-wrap gap-2">
                        <PublicationBadge value={item.publication_status} />
                        <ChannelBadge channel={item.channel as any} />
                        {item.is_featured ? <Badge variant="secondary">Destaque</Badge> : null}
                        {item.is_pinned ? <Badge variant="secondary">Fixada</Badge> : null}
                      </div>

                      <div className="space-y-1">
                        <p className="truncate text-base font-semibold">{item.event_title || 'Evento sem titulo'}</p>
                        <p className="truncate text-sm text-muted-foreground">{item.sender_name}</p>
                        <p className="line-clamp-2 text-sm text-muted-foreground">{item.caption || item.original_filename || 'Sem legenda ou nome amigavel.'}</p>
                      </div>

                      <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{fmt(item.published_at)}</span>
                        <span>{item.sort_order && item.sort_order > 0 ? `#${item.sort_order}` : 'Livre'}</span>
                      </div>

                      <div className="flex gap-2">
                        {canFeature ? (
                          <>
                            <Button type="button" size="sm" variant={item.is_featured ? 'default' : 'outline'} onClick={() => actionMutation.mutate({ type: 'featured', mediaId: item.id, desiredValue: !item.is_featured })} disabled={isBusy(item.id)}>
                              <Star className={`h-4 w-4 ${item.is_featured ? 'fill-current' : ''}`} />
                            </Button>
                            <Button type="button" size="sm" variant={item.is_pinned ? 'default' : 'outline'} onClick={() => actionMutation.mutate({ type: 'pinned', mediaId: item.id, desiredValue: !item.is_pinned })} disabled={isBusy(item.id)}>
                              <Pin className="h-4 w-4" />
                            </Button>
                          </>
                        ) : null}

                        <Button type="button" size="sm" className="flex-1" onClick={() => setSelectedId(item.id)}>
                          Detalhes
                        </Button>
                        <Button asChild type="button" size="sm" variant="outline" className="flex-1">
                          <Link to={`/events/${item.event_id}`}>Evento</Link>
                        </Button>
                      </div>

                      {renderVisibilityButton(item, true)}
                    </div>
                  </article>
                ))}
              </div>
            )}

            <div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-background/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="text-sm text-muted-foreground">Pagina {meta?.page ?? 1} de {meta?.last_page ?? 1} • {meta?.total ?? 0} itens</div>
              <div className="flex gap-2">
                <Button type="button" variant="outline" disabled={!meta || meta.page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}>Anterior</Button>
                <Button type="button" variant="outline" disabled={!meta || meta.page >= meta.last_page} onClick={() => setPage((current) => current + 1)}>Proxima</Button>
              </div>
            </div>
          </>
        ) : null}
      </motion.div>

      <Dialog open={selectedId !== null} onOpenChange={(open) => !open && setSelectedId(null)}>
        <DialogContent className="max-w-5xl">
          <DialogHeader>
            <DialogTitle>{selected?.event_title || 'Detalhes da midia'}</DialogTitle>
            <DialogDescription>{selected ? `${selected.sender_name} • ${fmt(selected.created_at)}` : 'Carregando midia selecionada'}</DialogDescription>
          </DialogHeader>

          {!selected ? (
            <div className="flex h-72 items-center justify-center text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin" />
            </div>
          ) : (
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
              <div className="overflow-hidden rounded-[28px] border border-border/60 bg-muted">
                <MediaSurface media={selected} className="max-h-[70vh] w-full object-contain" />
              </div>

              <div className="space-y-4">
                <div className="flex flex-wrap gap-2">
                  <PublicationBadge value={selected.publication_status} />
                  <ChannelBadge channel={selected.channel as any} />
                  {selected.is_featured ? <Badge variant="secondary">Destaque</Badge> : null}
                  {selected.is_pinned ? <Badge variant="secondary">Fixada</Badge> : null}
                </div>

                <div className="grid gap-3 rounded-[28px] border border-border/60 bg-background/80 p-4 text-sm">
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Situacao</p>
                    <p className="mt-1 font-medium">
                      {selected.publication_status === 'published'
                        ? 'Visivel na galeria publica'
                        : selected.publication_status === 'hidden'
                          ? 'Oculta da galeria publica'
                          : 'Pronta para galeria'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Legenda</p>
                    <p className="mt-1 text-muted-foreground">{selected.caption || 'Sem legenda'}</p>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Upload</p><p className="mt-1 font-medium">{fmt(selected.created_at)}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Publicacao</p><p className="mt-1 font-medium">{fmt(selected.published_at)}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Posicao</p><p className="mt-1 font-medium">{selected.sort_order && selected.sort_order > 0 ? `#${selected.sort_order}` : 'Livre'}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Dimensoes</p><p className="mt-1 font-medium">{selected.width && selected.height ? `${selected.width} x ${selected.height}` : 'Nao informado'}</p></div>
                  </div>
                </div>

                <div className="flex flex-wrap gap-2">
                  {canFeature ? (
                    <>
                      <Button type="button" variant={selected.is_featured ? 'default' : 'outline'} onClick={() => actionMutation.mutate({ type: 'featured', mediaId: selected.id, desiredValue: !selected.is_featured })}>
                        <Star className={`h-4 w-4 ${selected.is_featured ? 'fill-current' : ''}`} />
                        {selected.is_featured ? 'Remover destaque' : 'Destacar'}
                      </Button>
                      <Button type="button" variant={selected.is_pinned ? 'default' : 'outline'} onClick={() => actionMutation.mutate({ type: 'pinned', mediaId: selected.id, desiredValue: !selected.is_pinned })}>
                        <Pin className="h-4 w-4" />
                        {selected.is_pinned ? 'Desafixar' : 'Fixar'}
                      </Button>
                    </>
                  ) : null}

                  {renderVisibilityButton(selected)}
                </div>

                <div className="flex flex-wrap gap-2">
                  {selected.original_url ? (
                    <Button asChild>
                      <a href={selected.original_url} target="_blank" rel="noreferrer">
                        <ExternalLink className="h-4 w-4" />
                        Abrir original
                      </a>
                    </Button>
                  ) : null}
                  <Button asChild variant="outline">
                    <Link to={`/events/${selected.event_id}`}>Abrir evento</Link>
                  </Button>
                  {selected.event_slug ? (
                    <Button asChild variant="outline">
                      <a href={`/e/${selected.event_slug}/gallery`} target="_blank" rel="noreferrer">
                        <Eye className="h-4 w-4" />
                        Ver publica
                      </a>
                    </Button>
                  ) : null}
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </>
  );
}
