import { useDeferredValue, useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  CalendarRange,
  ExternalLink,
  FilterX,
  ImageIcon,
  LayoutGrid,
  Layers3,
  List,
  Loader2,
  ScanFace,
  Search,
  SlidersHorizontal,
  Star,
} from 'lucide-react';
import { Link } from 'react-router-dom';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/hooks/use-toast';
import type { ApiEventMediaDetail, ApiEventMediaItem, ApiFaceSearchResponse } from '@/lib/api-types';
import { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/query-client';
import { eventPeopleApi } from '@/modules/event-people/api';
import { EventPeopleFaceOverlay } from '@/modules/event-people/components/EventPeopleFaceOverlay';
import { EventPeopleIdentitySheet } from '@/modules/event-people/components/EventPeopleIdentitySheet';
import { EventPeopleReviewInboxCard } from '@/modules/event-people/components/EventPeopleReviewInboxCard';
import { formatEventPersonReviewStatus } from '@/modules/event-people/labels';
import type {
  EventMediaFacePeople,
  EventPerson,
  EventPersonReviewQueueItem,
  PaginatedApiResponse,
} from '@/modules/event-people/types';
import { eventsService } from '@/modules/events/services/events.service';
import type { EventListItem } from '@/modules/events/types';
import { searchEventFaces } from '@/modules/face-search/api';
import { FaceSearchSearchPanel } from '@/modules/face-search/components/FaceSearchSearchPanel';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { ChannelBadge, MediaStatusBadge } from '@/shared/components/StatusBadges';
import { StatsCard } from '@/shared/components/StatsCard';

import { MediaVirtualFeed } from './components/MediaVirtualFeed';
import { mediaService } from './services/media.service';
import {
  MEDIA_CATALOG_CHANNEL_OPTIONS,
  MEDIA_CATALOG_FACE_INDEX_OPTIONS,
  MEDIA_CATALOG_SORT_OPTIONS,
  MEDIA_CATALOG_STATUS_OPTIONS,
  MEDIA_CATALOG_TYPE_OPTIONS,
  type MediaCatalogChannelFilter,
  type MediaCatalogFaceIndexStatusFilter,
  type MediaCatalogFilters,
  type MediaCatalogMediaTypeFilter,
  type MediaCatalogSortBy,
  type MediaCatalogStatusFilter,
} from './types';

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

type IdentityAction = 'confirm' | 'create' | 'ignore' | 'split' | 'merge' | null;

function formatDateTime(value?: string | null) {
  if (!value) return 'Nao disponivel';

  return new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value));
}

function formatBytes(value?: number | null) {
  if (!value || value <= 0) return 'Nao informado';

  const units = ['B', 'KB', 'MB', 'GB'];
  let size = value;
  let unitIndex = 0;

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex += 1;
  }

  return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function toApiDateTime(value: string) {
  if (!value) return undefined;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? undefined : date.toISOString();
}

function renderMediaSurface(media: ApiEventMediaItem | ApiEventMediaDetail, className: string) {
  const surfaceUrl = media.preview_url || media.thumbnail_url || media.original_url;

  if (!surfaceUrl) {
    return (
      <div className={`flex items-center justify-center bg-muted text-muted-foreground ${className}`}>
        <ImageIcon className="h-10 w-10" />
      </div>
    );
  }

  if (media.media_type === 'video' && media.preview_url) {
    return <video src={media.preview_url} className={className} muted playsInline preload="metadata" />;
  }

  return <img src={surfaceUrl} alt={media.caption || media.event_title || media.sender_name} className={className} loading="lazy" decoding="async" />;
}

function sortReviewItems(items: EventPersonReviewQueueItem[]) {
  return [...items].sort((left, right) => {
    if (right.priority !== left.priority) return right.priority - left.priority;

    const leftSignal = left.last_signal_at ? new Date(left.last_signal_at).getTime() : 0;
    const rightSignal = right.last_signal_at ? new Date(right.last_signal_at).getTime() : 0;

    return rightSignal - leftSignal;
  });
}

function upsertReviewQueueItem(
  response: PaginatedApiResponse<EventPersonReviewQueueItem> | undefined,
  item: EventPersonReviewQueueItem,
) {
  if (!response) return response;

  const nextItems = sortReviewItems([
    ...response.data.filter((entry) => entry.id !== item.id),
    item,
  ]);

  return {
    ...response,
    data: nextItems,
  };
}

function upsertFaceInCollection(current: EventMediaFacePeople[] | undefined, nextFace: EventMediaFacePeople) {
  if (!current) return current;

  return current
    .map((face) => (face.id === nextFace.id ? nextFace : face))
    .sort((left, right) => left.face_index - right.face_index);
}

function MediaCard({ media, onOpen }: { media: ApiEventMediaItem; onOpen: () => void }) {
  return (
    <article className="glass overflow-hidden rounded-2xl border border-border/60 shadow-sm">
      <button type="button" className="block w-full text-left" onClick={onOpen}>
        {renderMediaSurface(media, 'h-40 w-full object-cover')}
      </button>
      <div className="space-y-3 p-4">
        <div className="flex flex-wrap gap-2">
          <MediaStatusBadge status={media.status as never} />
          <ChannelBadge channel={media.channel as never} />
          {media.is_featured ? <Badge variant="secondary">Destaque</Badge> : null}
          {media.is_duplicate_candidate ? <Badge variant="secondary">Duplicada</Badge> : null}
          {media.face_index_status === 'indexed' ? <Badge variant="secondary">Rosto indexado</Badge> : null}
        </div>
        <div className="space-y-1">
          <p className="truncate text-sm font-semibold">{media.event_title || 'Evento sem titulo'}</p>
          <p className="truncate text-xs text-muted-foreground">{media.sender_name}</p>
          <p className="line-clamp-2 text-xs text-muted-foreground">{media.caption || media.original_filename || 'Sem legenda ou nome amigavel.'}</p>
        </div>
        <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
          <span>{formatDateTime(media.created_at)}</span>
          <span>{media.media_type === 'video' ? 'Video' : 'Imagem'}</span>
        </div>
        <div className="flex items-center gap-2">
          <Button type="button" size="sm" className="flex-1" onClick={onOpen}>Detalhes</Button>
          <Button asChild type="button" size="sm" variant="outline" className="flex-1">
            <Link to={`/events/${media.event_id}`}>Evento</Link>
          </Button>
        </div>
      </div>
    </article>
  );
}

function MediaListRow({ media, onOpen }: { media: ApiEventMediaItem; onOpen: () => void }) {
  return (
    <div className="flex flex-col gap-3 border-b border-border/40 p-4 last:border-b-0 md:flex-row md:items-center">
      <button type="button" className="overflow-hidden rounded-xl bg-muted md:w-24" onClick={onOpen}>
        {renderMediaSurface(media, 'h-20 w-full object-cover md:h-16')}
      </button>
      <div className="min-w-0 flex-1 space-y-1">
        <p className="truncate text-sm font-semibold">{media.event_title || 'Evento sem titulo'}</p>
        <p className="truncate text-xs text-muted-foreground">{media.sender_name} - {media.original_filename || 'arquivo sem nome'}</p>
        <p className="line-clamp-1 text-xs text-muted-foreground">{media.caption || 'Sem legenda'}</p>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <MediaStatusBadge status={media.status as never} />
        <ChannelBadge channel={media.channel as never} />
        {media.is_duplicate_candidate ? <Badge variant="secondary">Duplicada</Badge> : null}
        {media.face_index_status === 'indexed' ? <Badge variant="secondary">Face pronta</Badge> : null}
      </div>
      <div className="flex items-center gap-2 text-xs text-muted-foreground md:w-[160px] md:justify-end">
        <span>{formatDateTime(media.created_at)}</span>
      </div>
      <div className="flex items-center gap-2 md:w-[180px] md:justify-end">
        <Button type="button" size="sm" variant="outline" onClick={onOpen}>Detalhes</Button>
        <Button asChild type="button" size="sm">
          <Link to={`/events/${media.event_id}`}>Evento</Link>
        </Button>
      </div>
    </div>
  );
}

export default function MediaPage() {
  const queryClient = useQueryClient();
  const [isUiTransitionPending, startUiTransition] = useTransition();
  const [view, setView] = useState<'grid' | 'list'>('grid');
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [perPage, setPerPage] = useState(36);
  const [search, setSearch] = useState('');
  const [eventFilter, setEventFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState<'all' | MediaCatalogStatusFilter>('all');
  const [channelFilter, setChannelFilter] = useState<'all' | MediaCatalogChannelFilter>('all');
  const [mediaTypeFilter, setMediaTypeFilter] = useState<'all' | MediaCatalogMediaTypeFilter>('all');
  const [faceIndexFilter, setFaceIndexFilter] = useState<'all' | MediaCatalogFaceIndexStatusFilter>('all');
  const [sortBy, setSortBy] = useState<MediaCatalogSortBy>('created_at');
  const [createdFrom, setCreatedFrom] = useState('');
  const [createdTo, setCreatedTo] = useState('');
  const [duplicatesOnly, setDuplicatesOnly] = useState(false);
  const [featuredOnly, setFeaturedOnly] = useState(false);
  const [recognitionReadyOnly, setRecognitionReadyOnly] = useState(false);
  const [faceSearchDialogOpen, setFaceSearchDialogOpen] = useState(false);
  const [faceSearchEventId, setFaceSearchEventId] = useState('');
  const [faceSearchIncludePending, setFaceSearchIncludePending] = useState(true);
  const [faceSearchErrorMessage, setFaceSearchErrorMessage] = useState<string | null>(null);
  const [faceSearchResponse, setFaceSearchResponse] = useState<ApiFaceSearchResponse | null>(null);
  const [selectedMediaId, setSelectedMediaId] = useState<number | null>(null);
  const [selectedFaceId, setSelectedFaceId] = useState<number | null>(null);
  const [identitySheetOpen, setIdentitySheetOpen] = useState(false);
  const [pendingIdentityAction, setPendingIdentityAction] = useState<IdentityAction>(null);

  const deferredSearch = useDeferredValue(search);
  const loadMoreRef = useRef<HTMLDivElement | null>(null);

  const filters = useMemo<MediaCatalogFilters>(() => ({
    per_page: perPage,
    search: deferredSearch || undefined,
    event_id: eventFilter === 'all' ? undefined : Number(eventFilter),
    status: statusFilter === 'all' ? undefined : statusFilter,
    channel: channelFilter === 'all' ? undefined : channelFilter,
    media_type: recognitionReadyOnly ? 'image' : mediaTypeFilter === 'all' ? undefined : mediaTypeFilter,
    featured: featuredOnly ? true : undefined,
    duplicates: duplicatesOnly ? true : undefined,
    face_search_enabled: recognitionReadyOnly ? true : undefined,
    face_index_status: recognitionReadyOnly ? 'indexed' : faceIndexFilter === 'all' ? undefined : faceIndexFilter,
    created_from: toApiDateTime(createdFrom),
    created_to: toApiDateTime(createdTo),
    sort_by: sortBy,
    sort_direction: 'desc',
  }), [channelFilter, createdFrom, createdTo, deferredSearch, duplicatesOnly, eventFilter, faceIndexFilter, featuredOnly, mediaTypeFilter, perPage, recognitionReadyOnly, sortBy, statusFilter]);

  const activeFiltersCount = [
    deferredSearch,
    eventFilter !== 'all',
    statusFilter !== 'all',
    channelFilter !== 'all',
    mediaTypeFilter !== 'all',
    faceIndexFilter !== 'all',
    duplicatesOnly,
    featuredOnly,
    recognitionReadyOnly,
    createdFrom,
    createdTo,
    sortBy !== 'created_at',
  ].filter(Boolean).length;

  const eventsQuery = useQuery({
    queryKey: queryKeys.events.list({ scope: 'media-filter-options' }),
    queryFn: () => eventsService.list({ per_page: 100, sort_by: 'starts_at', sort_direction: 'desc' }),
  });

  const workbenchEventId = useMemo(() => {
    if (eventFilter !== 'all') return eventFilter;

    return eventsQuery.data?.data?.[0]?.id ? String(eventsQuery.data.data[0].id) : '';
  }, [eventFilter, eventsQuery.data?.data]);

  const mediaQuery = useInfiniteQuery({
    queryKey: queryKeys.media.list(filters),
    initialPageParam: 1,
    queryFn: ({ pageParam }) => mediaService.list({ ...filters, page: pageParam }),
    getNextPageParam: (lastPage) => lastPage.meta.page < lastPage.meta.last_page ? lastPage.meta.page + 1 : undefined,
    staleTime: 15_000,
  });

  const detailQuery = useQuery({
    queryKey: queryKeys.media.detail(String(selectedMediaId ?? '')),
    queryFn: () => mediaService.show(selectedMediaId as number),
    enabled: selectedMediaId !== null,
  });

  const reviewQueueFilters = useMemo(() => ({ per_page: 24 }), []);

  const reviewQueueQuery = useQuery({
    queryKey: queryKeys.eventPeople.reviewQueue(workbenchEventId || 'none', reviewQueueFilters),
    queryFn: () => eventPeopleApi.listReviewQueue(workbenchEventId, reviewQueueFilters),
    enabled: workbenchEventId !== '',
    staleTime: 15_000,
  });

  const faceSearchMutation = useMutation({
    mutationFn: ({ eventId, file, includePending }: { eventId: string; file: File; includePending: boolean }) =>
      searchEventFaces(eventId, file, includePending),
    onMutate: () => {
      setFaceSearchErrorMessage(null);
    },
    onSuccess: (response) => {
      setFaceSearchResponse(response);
    },
    onError: (error) => {
      const message = error instanceof ApiError
        ? error.message
        : 'Nao foi possivel buscar essa pessoa agora.';

      setFaceSearchErrorMessage(message);
    },
  });

  function patchReviewQueueCache(eventId: number | string, reviewItem: EventPersonReviewQueueItem | null) {
    if (!reviewItem) return;

    queryClient.setQueriesData<PaginatedApiResponse<EventPersonReviewQueueItem> | undefined>(
      { queryKey: queryKeys.eventPeople.reviewQueues(eventId) },
      (current) => upsertReviewQueueItem(current, reviewItem),
    );
  }

  function patchMediaFacesCache(eventId: number | string, mediaId: number | string, face: EventMediaFacePeople) {
    queryClient.setQueryData<EventMediaFacePeople[] | undefined>(
      queryKeys.eventPeople.mediaFaces(eventId, mediaId),
      (current) => upsertFaceInCollection(current, face),
    );
  }

  function openFaceIdentity(face: EventMediaFacePeople) {
    startUiTransition(() => {
      setSelectedFaceId(face.id);
      setIdentitySheetOpen(true);
    });
  }

  function openReviewItem(item: EventPersonReviewQueueItem) {
    if (!item.face) return;

    startUiTransition(() => {
      if (eventFilter !== String(item.event_id)) {
        setEventFilter(String(item.event_id));
      }

      setSelectedMediaId(item.face.event_media_id);
      setSelectedFaceId(item.face.id);
      setIdentitySheetOpen(true);
    });
  }

  const confirmReviewMutation = useMutation({
    mutationFn: ({ eventId, reviewItemId, payload }: { eventId: number | string; reviewItemId: number; payload: { person_id?: number; person?: { display_name: string; type?: string; side?: string } } }) =>
      eventPeopleApi.confirmReviewItem(eventId, reviewItemId, payload),
    onMutate: ({ payload }) => {
      setPendingIdentityAction(payload.person ? 'create' : 'confirm');
    },
    onSuccess: (response, variables) => {
      patchMediaFacesCache(variables.eventId, response.face.event_media_id, response.face);
      patchReviewQueueCache(variables.eventId, response.review_item);
      void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.peopleLists(variables.eventId) });
      setSelectedFaceId(response.face.id);

      toast({
        title: variables.payload.person ? 'Pessoa criada localmente' : 'Confirmacao salva localmente',
        description: 'A mudanca ja apareceu no painel. O restante continua sendo atualizado na fila.',
      });
    },
    onError: (error) => {
      toast({
        title: 'Falha ao confirmar identidade',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel confirmar essa pessoa agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => {
      setPendingIdentityAction(null);
    },
  });

  const ignoreReviewMutation = useMutation({
    mutationFn: ({ eventId, reviewItemId }: { eventId: number | string; reviewItemId: number }) =>
      eventPeopleApi.ignoreReviewItem(eventId, reviewItemId),
    onMutate: () => {
      setPendingIdentityAction('ignore');
    },
    onSuccess: (response, variables) => {
      patchReviewQueueCache(variables.eventId, response.review_item);
      if (selectedMediaPreview) {
        void queryClient.invalidateQueries({
          queryKey: queryKeys.eventPeople.mediaFaces(selectedMediaPreview.event_id, selectedMediaPreview.id),
        });
      }

      toast({
        title: 'Item tratado localmente',
        description: 'A fila foi atualizada na hora.',
      });
    },
    onError: (error) => {
      toast({
        title: 'Falha ao ignorar item',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel atualizar a inbox agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => {
      setPendingIdentityAction(null);
    },
  });

  const splitReviewMutation = useMutation({
    mutationFn: ({ eventId, reviewItemId }: { eventId: number | string; reviewItemId: number }) =>
      eventPeopleApi.splitReviewItem(eventId, reviewItemId),
    onMutate: () => {
      setPendingIdentityAction('split');
    },
    onSuccess: (response, variables) => {
      patchMediaFacesCache(variables.eventId, response.face.event_media_id, response.face);
      patchReviewQueueCache(variables.eventId, response.review_item);
      setSelectedFaceId(response.face.id);

      toast({
        title: 'Identidade reaberta localmente',
        description: 'O rosto voltou para revisao. O restante continua na fila.',
      });
    },
    onError: (error) => {
      toast({
        title: 'Falha ao reabrir identidade',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel separar esse rosto agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => {
      setPendingIdentityAction(null);
    },
  });

  const mergeReviewMutation = useMutation({
    mutationFn: ({
      eventId,
      reviewItemId,
      sourcePersonId,
      targetPersonId,
    }: {
      eventId: number | string;
      reviewItemId: number;
      sourcePersonId: number;
      targetPersonId: number;
    }) => eventPeopleApi.mergeReviewItem(eventId, reviewItemId, {
      source_person_id: sourcePersonId,
      target_person_id: targetPersonId,
    }),
    onMutate: () => {
      setPendingIdentityAction('merge');
    },
    onSuccess: (response, variables) => {
      patchReviewQueueCache(variables.eventId, response.review_item);
      void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.peopleLists(variables.eventId) });
      void queryClient.invalidateQueries({ queryKey: queryKeys.eventPeople.reviewQueues(variables.eventId) });

      if (selectedMediaPreview) {
        void queryClient.invalidateQueries({
          queryKey: queryKeys.eventPeople.mediaFaces(selectedMediaPreview.event_id, selectedMediaPreview.id),
        });
      }

      toast({
        title: 'Merge aplicado localmente',
        description: 'As pessoas foram consolidadas no banco local. A reconciliacao remota segue em fila.',
      });
    },
    onError: (error) => {
      toast({
        title: 'Falha ao mesclar pessoas',
        description: error instanceof ApiError ? error.message : 'Nao foi possivel concluir o merge agora.',
        variant: 'destructive',
      });
    },
    onSettled: () => {
      setPendingIdentityAction(null);
    },
  });

  const events = (eventsQuery.data?.data ?? []) as EventListItem[];
  const pages = mediaQuery.data?.pages ?? [];
  const items = useMemo(() => pages.flatMap((page) => page.data), [pages]);
  const total = pages[0]?.meta.total ?? 0;
  const stats = pages[0]?.meta.stats ?? EMPTY_STATS;
  const selectedMediaPreview = detailQuery.data ?? items.find((item) => item.id === selectedMediaId) ?? null;
  const selectedFaceSearchEvent = events.find((eventItem) => String(eventItem.id) === faceSearchEventId);
  const workbenchEvent = events.find((eventItem) => String(eventItem.id) === workbenchEventId) ?? null;

  const mediaFacesQuery = useQuery({
    queryKey: queryKeys.eventPeople.mediaFaces(selectedMediaPreview?.event_id ?? 'none', selectedMediaPreview?.id ?? 'none'),
    queryFn: () => eventPeopleApi.listMediaFaces(
      selectedMediaPreview?.event_id ?? '',
      selectedMediaPreview?.id ?? '',
    ),
    enabled: selectedMediaPreview !== null && selectedMediaPreview.media_type === 'image',
    staleTime: 5_000,
  });

  const reviewQueueItems = useMemo(
    () => sortReviewItems((reviewQueueQuery.data?.data ?? []).filter((item) => item.status === 'pending' || item.status === 'conflict')).slice(0, 8),
    [reviewQueueQuery.data?.data],
  );

  const selectedFace = useMemo(
    () => mediaFacesQuery.data?.find((face) => face.id === selectedFaceId) ?? null,
    [mediaFacesQuery.data, selectedFaceId],
  );

  const isIdentityMutationPending = pendingIdentityAction !== null;

  useEffect(() => {
    const node = loadMoreRef.current;
    if (!node || !mediaQuery.hasNextPage || mediaQuery.isFetchingNextPage || mediaQuery.isFetchNextPageError) return;

    const observer = new IntersectionObserver((entries) => {
      if (entries[0]?.isIntersecting) mediaQuery.fetchNextPage();
    }, { rootMargin: '1200px 0px' });

    observer.observe(node);
    return () => observer.disconnect();
  }, [items.length, mediaQuery.fetchNextPage, mediaQuery.hasNextPage, mediaQuery.isFetchNextPageError, mediaQuery.isFetchingNextPage]);

  useEffect(() => {
    if (!faceSearchDialogOpen || faceSearchEventId || events.length === 0) return;

    setFaceSearchEventId(eventFilter !== 'all' ? eventFilter : String(events[0].id));
  }, [eventFilter, events, faceSearchDialogOpen, faceSearchEventId]);

  useEffect(() => {
    if (selectedMediaId !== null) return;

    setSelectedFaceId(null);
    setIdentitySheetOpen(false);
  }, [selectedMediaId]);

  useEffect(() => {
    if (!identitySheetOpen || !selectedFaceId || !mediaFacesQuery.data?.length) return;

    if (mediaFacesQuery.data.some((face) => face.id === selectedFaceId)) return;

    setSelectedFaceId(mediaFacesQuery.data[0]?.id ?? null);
  }, [identitySheetOpen, mediaFacesQuery.data, selectedFaceId]);

  function clearFilters() {
    setSearch('');
    setEventFilter('all');
    setStatusFilter('all');
    setChannelFilter('all');
    setMediaTypeFilter('all');
    setFaceIndexFilter('all');
    setSortBy('created_at');
    setCreatedFrom('');
    setCreatedTo('');
    setDuplicatesOnly(false);
    setFeaturedOnly(false);
    setRecognitionReadyOnly(false);
  }

  function openFaceSearchDialog() {
    setFaceSearchDialogOpen(true);
    setFaceSearchErrorMessage(null);
    setFaceSearchResponse(null);

    if (eventFilter !== 'all') {
      setFaceSearchEventId(eventFilter);
      return;
    }

    if (!faceSearchEventId && events.length > 0) {
      setFaceSearchEventId(String(events[0].id));
    }
  }

  function openMediaDetails(mediaId: number) {
    startUiTransition(() => {
      setSelectedMediaId(mediaId);
      setSelectedFaceId(null);
      setIdentitySheetOpen(false);
    });
  }

  return (
    <>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-6">
        <PageHeader title="Midias" description={`${items.length} de ${total} itens carregados com scroll infinito`} />

        <div className="grid grid-cols-2 gap-3 xl:grid-cols-5">
          <StatsCard title="Total" value={stats.total} icon={Layers3} />
          <StatsCard title="Publicadas" value={stats.published} icon={ImageIcon} />
          <StatsCard title="Pendentes" value={stats.pending} icon={CalendarRange} />
          <StatsCard title="Duplicadas" value={stats.duplicates} icon={Layers3} />
          <StatsCard title="Rosto Indexado" value={stats.face_indexed} icon={ScanFace} />
        </div>

        <EventPeopleReviewInboxCard
          eventName={workbenchEvent?.title ?? null}
          items={reviewQueueItems}
          isLoading={reviewQueueQuery.isLoading}
          isError={reviewQueueQuery.isError}
          isPendingUi={isUiTransitionPending || isIdentityMutationPending}
          onOpenItem={openReviewItem}
        />

        <section className="glass rounded-3xl border border-border/60 p-4 sm:p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="max-w-2xl space-y-2">
              <p className="text-lg font-semibold">Catalogo de midias do evento</p>
              <p className="text-sm text-muted-foreground">Periodo com hora, carregamento progressivo e viewport virtualizado para centenas de fotos.</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" onClick={openFaceSearchDialog}>
                <ScanFace className="h-4 w-4" />
                Buscar pessoa por foto
              </Button>
              <Button type="button" variant={filtersOpen || activeFiltersCount > 0 ? 'secondary' : 'outline'} onClick={() => setFiltersOpen((current) => !current)}>
                <SlidersHorizontal className="h-4 w-4" />
                {filtersOpen ? 'Fechar filtros' : 'Abrir filtros'}
                {activeFiltersCount > 0 ? ` (${activeFiltersCount})` : ''}
              </Button>
              <Button type="button" variant={view === 'grid' ? 'secondary' : 'outline'} size="icon" onClick={() => setView('grid')}><LayoutGrid className="h-4 w-4" /></Button>
              <Button type="button" variant={view === 'list' ? 'secondary' : 'outline'} size="icon" onClick={() => setView('list')}><List className="h-4 w-4" /></Button>
            </div>
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-2">
            {activeFiltersCount > 0 ? <Badge variant="outline">{activeFiltersCount} filtros ativos</Badge> : <Badge variant="outline">Sem filtros extras</Badge>}
            {mediaQuery.isFetching && !mediaQuery.isFetchingNextPage ? <Badge variant="outline">Sincronizando</Badge> : null}
          </div>

          {filtersOpen ? (
            <div className="mt-5 space-y-4 border-t border-border/50 pt-5">
              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
                <div className="relative md:col-span-2 xl:col-span-4">
                  <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input placeholder="Buscar por evento, legenda, remetente ou arquivo" value={search} onChange={(event) => setSearch(event.target.value)} className="pl-9" />
                </div>
                <Select value={eventFilter} onValueChange={setEventFilter}>
                  <SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Evento" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os eventos</SelectItem>
                    {events.map((eventItem) => <SelectItem key={eventItem.id} value={String(eventItem.id)}>{eventItem.title}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as typeof statusFilter)}>
                  <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Status" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os status</SelectItem>
                    {MEDIA_CATALOG_STATUS_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={channelFilter} onValueChange={(value) => setChannelFilter(value as typeof channelFilter)}>
                  <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Canal" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos os canais</SelectItem>
                    {MEDIA_CATALOG_CHANNEL_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={mediaTypeFilter} onValueChange={(value) => setMediaTypeFilter(value as typeof mediaTypeFilter)}>
                  <SelectTrigger className="xl:col-span-1"><SelectValue placeholder="Tipo" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Tudo</SelectItem>
                    {MEDIA_CATALOG_TYPE_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>

              <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
                <Select value={faceIndexFilter} onValueChange={(value) => setFaceIndexFilter(value as typeof faceIndexFilter)}>
                  <SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Status facial" /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Qualquer status facial</SelectItem>
                    {MEDIA_CATALOG_FACE_INDEX_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select value={sortBy} onValueChange={(value) => setSortBy(value as MediaCatalogSortBy)}>
                  <SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Ordenacao" /></SelectTrigger>
                  <SelectContent>
                    {MEDIA_CATALOG_SORT_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                <div className="space-y-1 xl:col-span-3">
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Inicio do periodo</p>
                  <Input type="datetime-local" value={createdFrom} onChange={(event) => setCreatedFrom(event.target.value)} step={60} />
                </div>
                <div className="space-y-1 xl:col-span-3">
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Fim do periodo</p>
                  <Input type="datetime-local" value={createdTo} onChange={(event) => setCreatedTo(event.target.value)} step={60} />
                </div>
                <Select value={String(perPage)} onValueChange={(value) => setPerPage(Number(value))}>
                  <SelectTrigger className="xl:col-span-1"><SelectValue placeholder="Carga" /></SelectTrigger>
                  <SelectContent>{[24, 36, 48, 72].map((option) => <SelectItem key={option} value={String(option)}>{option}/lote</SelectItem>)}</SelectContent>
                </Select>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Button type="button" variant={duplicatesOnly ? 'default' : 'outline'} onClick={() => setDuplicatesOnly((current) => !current)}>Duplicadas</Button>
                <Button type="button" variant={featuredOnly ? 'default' : 'outline'} onClick={() => setFeaturedOnly((current) => !current)}><Star className="h-4 w-4" />Destaque</Button>
                <Button type="button" variant={recognitionReadyOnly ? 'default' : 'outline'} onClick={() => setRecognitionReadyOnly((current) => !current)}><ScanFace className="h-4 w-4" />Prontas para reconhecimento</Button>
                <Button type="button" variant="ghost" onClick={clearFilters}><FilterX className="h-4 w-4" />Limpar filtros</Button>
              </div>
            </div>
          ) : null}
        </section>

        {mediaQuery.isLoading ? <div className="rounded-3xl border border-border/60 bg-background/80 px-4 py-20 text-center text-muted-foreground"><Loader2 className="mx-auto mb-3 h-6 w-6 animate-spin" />Carregando catalogo de midias...</div> : null}
        {mediaQuery.isError ? <div className="rounded-3xl border border-destructive/30 bg-destructive/5 px-4 py-16 text-center text-sm text-destructive">Nao foi possivel carregar a listagem de midias agora.</div> : null}
        {!mediaQuery.isLoading && !mediaQuery.isError && items.length === 0 ? <div className="glass rounded-3xl border border-border/60"><EmptyState icon={ImageIcon} title="Nenhuma midia encontrada" description="Ajuste os filtros ou aguarde novas fotos entrarem no evento." action={<Button variant="outline" onClick={clearFilters}>Limpar filtros</Button>} /></div> : null}

        {!mediaQuery.isLoading && !mediaQuery.isError && items.length > 0 ? (
          <>
            <MediaVirtualFeed items={items} view={view} loadMoreRef={loadMoreRef} renderItem={(item) => view === 'grid' ? <MediaCard key={item.id} media={item} onOpen={() => openMediaDetails(item.id)} /> : <MediaListRow key={item.id} media={item} onOpen={() => openMediaDetails(item.id)} />} />
            <div className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-background/80 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div className="text-sm text-muted-foreground">{items.length} de {total} itens carregados</div>
              <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                {mediaQuery.isFetchingNextPage ? <><Loader2 className="h-4 w-4 animate-spin" />Carregando mais midias...</> : mediaQuery.hasNextPage ? <Badge variant="outline">Role para carregar mais</Badge> : <Badge variant="secondary">Fim da lista</Badge>}
              </div>
            </div>
            {mediaQuery.isFetchNextPageError ? <div className="rounded-2xl border border-destructive/30 bg-destructive/5 px-4 py-4 text-sm text-destructive">Houve falha ao carregar o proximo lote.<Button type="button" variant="outline" size="sm" className="ml-3" onClick={() => mediaQuery.fetchNextPage()}>Tentar novamente</Button></div> : null}
          </>
        ) : null}
      </motion.div>

      <Dialog open={selectedMediaId !== null} onOpenChange={(open) => !open && setSelectedMediaId(null)}>
        <DialogContent className="max-w-5xl">
          <DialogHeader>
            <DialogTitle>{selectedMediaPreview?.event_title || 'Detalhes da midia'}</DialogTitle>
            <DialogDescription>{selectedMediaPreview ? `${selectedMediaPreview.sender_name} - ${formatDateTime(selectedMediaPreview.created_at)}` : 'Visualizando payload real do backend'}</DialogDescription>
          </DialogHeader>
          {!selectedMediaPreview ? (
            <div className="flex h-72 items-center justify-center text-muted-foreground"><Loader2 className="h-6 w-6 animate-spin" /></div>
          ) : (
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
              <div className="space-y-4">
                {selectedMediaPreview.media_type === 'image' ? (
                  <EventPeopleFaceOverlay
                    mediaType={selectedMediaPreview.media_type}
                    surfaceUrl={selectedMediaPreview.preview_url || selectedMediaPreview.thumbnail_url || selectedMediaPreview.original_url}
                    alt={selectedMediaPreview.caption || selectedMediaPreview.event_title || selectedMediaPreview.sender_name || 'Midia selecionada'}
                    faces={mediaFacesQuery.data ?? []}
                    selectedFaceId={selectedFaceId}
                    onSelectFace={openFaceIdentity}
                  />
                ) : (
                  <div className="overflow-hidden rounded-3xl border border-border/60 bg-muted">
                    {renderMediaSurface(selectedMediaPreview, 'max-h-[70vh] w-full object-contain')}
                  </div>
                )}

                {selectedMediaPreview.media_type === 'image' ? (
                  <div className="rounded-3xl border border-border/60 bg-background/80 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="text-sm font-semibold">Rostos detectados</p>
                        <p className="text-xs text-muted-foreground">Clique na marcacao da foto ou escolha um rosto abaixo para revisar quem aparece nela.</p>
                      </div>
                      {isIdentityMutationPending ? <Badge variant="secondary">Atualizando painel</Badge> : null}
                    </div>

                    {mediaFacesQuery.isLoading ? (
                      <div className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="h-4 w-4 animate-spin" />
                        Carregando marcacoes dos rostos...
                      </div>
                    ) : null}

                    {mediaFacesQuery.isError ? (
                      <div className="mt-4 rounded-2xl border border-destructive/30 bg-destructive/5 px-3 py-3 text-sm text-destructive">
                        Nao foi possivel carregar as marcacoes dos rostos desta midia.
                      </div>
                    ) : null}

                    {!mediaFacesQuery.isLoading && !mediaFacesQuery.isError ? (
                      <div className="mt-4 grid gap-3 md:grid-cols-2">
                        {(mediaFacesQuery.data ?? []).map((face) => (
                          <button
                            key={face.id}
                            type="button"
                            className={`rounded-2xl border px-3 py-3 text-left transition ${
                              selectedFaceId === face.id
                                ? 'border-primary bg-primary/10'
                                : 'border-border/50 bg-background hover:border-primary/40 hover:bg-primary/5'
                            }`}
                            onClick={() => openFaceIdentity(face)}
                          >
                            <div className="flex items-center justify-between gap-2">
                              <div>
                                <p className="font-medium">
                                  {face.current_assignment?.person?.display_name || `Rosto #${face.face_index + 1}`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                  {face.current_assignment?.person
                                    ? 'Identidade confirmada'
                                    : face.review_item?.payload.question || 'Quem e esta pessoa?'}
                                </p>
                              </div>
                              <Badge variant={face.review_item?.status === 'conflict' ? 'destructive' : 'outline'}>
                                {formatEventPersonReviewStatus(face.review_item?.status)}
                              </Badge>
                            </div>
                          </button>
                        ))}
                      </div>
                    ) : null}
                  </div>
                ) : null}
              </div>
              <div className="space-y-4">
                <div className="flex flex-wrap gap-2">
                  <MediaStatusBadge status={selectedMediaPreview.status as never} />
                  <ChannelBadge channel={selectedMediaPreview.channel as never} />
                  {selectedMediaPreview.is_duplicate_candidate ? <Badge variant="secondary">Duplicada</Badge> : null}
                  {selectedMediaPreview.event_face_search_enabled ? <Badge variant="secondary">Busca facial ativa</Badge> : null}
                  {selectedMediaPreview.event_allow_public_selfie_search ? <Badge variant="secondary">Selfie liberada</Badge> : null}
                </div>
                <div className="grid gap-3 rounded-3xl border border-border/60 bg-background/80 p-4 text-sm">
                  <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Evento</p><p className="mt-1 font-medium">{selectedMediaPreview.event_title || 'Nao informado'}</p></div>
                  <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Arquivo</p><p className="mt-1 font-medium">{selectedMediaPreview.original_filename || 'Nao informado'}</p></div>
                  <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Legenda</p><p className="mt-1 text-muted-foreground">{selectedMediaPreview.caption || 'Sem legenda'}</p></div>
                  <div className="grid grid-cols-2 gap-3">
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Recebida</p><p className="mt-1 font-medium">{formatDateTime(selectedMediaPreview.created_at)}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Publicada</p><p className="mt-1 font-medium">{formatDateTime(selectedMediaPreview.published_at)}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Dimensoes</p><p className="mt-1 font-medium">{selectedMediaPreview.width && selectedMediaPreview.height ? `${selectedMediaPreview.width} x ${selectedMediaPreview.height}` : 'Nao informado'}</p></div>
                    <div><p className="text-xs uppercase tracking-wide text-muted-foreground">Tamanho</p><p className="mt-1 font-medium">{detailQuery.data ? formatBytes(detailQuery.data.size_bytes) : 'Carregando'}</p></div>
                  </div>
                </div>
                <div className="grid gap-2 rounded-3xl border border-border/60 bg-background/80 p-4 text-sm">
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Pipeline</p>
                  <div className="flex flex-wrap gap-2">
                    {selectedMediaPreview.processing_status ? <Badge variant="outline">proc: {selectedMediaPreview.processing_status}</Badge> : null}
                    {selectedMediaPreview.moderation_status ? <Badge variant="outline">mod: {selectedMediaPreview.moderation_status}</Badge> : null}
                    {selectedMediaPreview.publication_status ? <Badge variant="outline">pub: {selectedMediaPreview.publication_status}</Badge> : null}
                    {selectedMediaPreview.safety_status ? <Badge variant="outline">safety: {selectedMediaPreview.safety_status}</Badge> : null}
                    {selectedMediaPreview.face_index_status ? <Badge variant="outline">face: {selectedMediaPreview.face_index_status}</Badge> : null}
                    {selectedMediaPreview.vlm_status ? <Badge variant="outline">vlm: {selectedMediaPreview.vlm_status}</Badge> : null}
                    {selectedMediaPreview.decision_source ? <Badge variant="outline">decisao: {selectedMediaPreview.decision_source}</Badge> : null}
                  </div>
                  {selectedMediaPreview.duplicate_group_key ? <p className="text-xs text-muted-foreground">Grupo de duplicidade: {selectedMediaPreview.duplicate_group_key}</p> : null}
                </div>
                {detailQuery.data?.processing_runs?.length ? (
                  <div className="rounded-3xl border border-border/60 bg-background/80 p-4">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Runs recentes</p>
                    <div className="mt-3 space-y-2">
                      {detailQuery.data.processing_runs.slice(0, 4).map((run) => (
                        <div key={run.id} className="rounded-2xl border border-border/40 px-3 py-2 text-sm">
                          <p className="font-medium">{run.stage_key || run.run_type}</p>
                          <p className="text-xs text-muted-foreground">{run.status} - {run.provider_key || 'provider n/a'} - {formatDateTime(run.finished_at || run.started_at)}</p>
                        </div>
                      ))}
                    </div>
                  </div>
                ) : null}
                {selectedFace ? (
                  <div className="rounded-3xl border border-primary/20 bg-primary/5 p-4">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Rosto selecionado</p>
                        <p className="mt-1 font-medium">
                          {selectedFace.current_assignment?.person?.display_name || selectedFace.review_item?.payload.question || `Rosto #${selectedFace.face_index + 1}`}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                          {selectedFace.current_assignment?.person
                            ? 'Use o painel lateral para corrigir, reabrir ou consolidar a identidade.'
                          : 'Abra o painel lateral para confirmar a pessoa ou criar um novo cadastro.'}
                        </p>
                      </div>
                      <Button type="button" size="sm" onClick={() => openFaceIdentity(selectedFace)}>
                        {selectedFace.current_assignment?.person ? 'Ajustar identidade' : 'Quem e esta pessoa?'}
                      </Button>
                    </div>
                  </div>
                ) : null}
                <div className="flex flex-wrap gap-2">
                  {selectedMediaPreview.original_url ? <Button asChild><a href={selectedMediaPreview.original_url} target="_blank" rel="noreferrer"><ExternalLink className="h-4 w-4" />Abrir original</a></Button> : null}
                  <Button asChild variant="outline"><Link to={`/events/${selectedMediaPreview.event_id}`}>Abrir evento</Link></Button>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <EventPeopleIdentitySheet
        open={identitySheetOpen}
        onOpenChange={setIdentitySheetOpen}
        eventId={selectedMediaPreview?.event_id ?? (selectedFace?.review_item?.event_id ?? null)}
        face={selectedFace}
        pendingAction={pendingIdentityAction}
        onConfirmExisting={(personId) => {
          const reviewItemId = selectedFace?.review_item?.id;
          const eventId = selectedMediaPreview?.event_id ?? selectedFace?.review_item?.event_id;

          if (!reviewItemId || !eventId) return;

          confirmReviewMutation.mutate({
            eventId,
            reviewItemId,
            payload: { person_id: personId },
          });
        }}
        onCreatePerson={(payload) => {
          const reviewItemId = selectedFace?.review_item?.id;
          const eventId = selectedMediaPreview?.event_id ?? selectedFace?.review_item?.event_id;

          if (!reviewItemId || !eventId) return;

          confirmReviewMutation.mutate({
            eventId,
            reviewItemId,
            payload: { person: payload },
          });
        }}
        onIgnore={() => {
          const reviewItemId = selectedFace?.review_item?.id;
          const eventId = selectedMediaPreview?.event_id ?? selectedFace?.review_item?.event_id;

          if (!reviewItemId || !eventId) return;

          ignoreReviewMutation.mutate({ eventId, reviewItemId });
        }}
        onSplit={() => {
          const reviewItemId = selectedFace?.review_item?.id;
          const eventId = selectedMediaPreview?.event_id ?? selectedFace?.review_item?.event_id;

          if (!reviewItemId || !eventId) return;

          splitReviewMutation.mutate({ eventId, reviewItemId });
        }}
        onMerge={(sourcePersonId, targetPersonId) => {
          const reviewItemId = selectedFace?.review_item?.id;
          const eventId = selectedMediaPreview?.event_id ?? selectedFace?.review_item?.event_id;

          if (!reviewItemId || !eventId) return;

          mergeReviewMutation.mutate({
            eventId,
            reviewItemId,
            sourcePersonId,
            targetPersonId,
          });
        }}
      />

      <Dialog open={faceSearchDialogOpen} onOpenChange={setFaceSearchDialogOpen}>
        <DialogContent className="max-w-5xl">
          <DialogHeader>
            <DialogTitle>Buscar pessoa por foto</DialogTitle>
            <DialogDescription>
              Escolha o evento e envie uma selfie. A busca mostra fotos da galeria que ja estao preparadas para reconhecimento facial.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <p className="text-sm font-medium">Evento da busca</p>
              <Select
                value={faceSearchEventId}
                onValueChange={(value) => {
                  setFaceSearchEventId(value);
                  setFaceSearchResponse(null);
                  setFaceSearchErrorMessage(null);
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione um evento" />
                </SelectTrigger>
                <SelectContent>
                  {events.map((eventItem) => (
                    <SelectItem key={eventItem.id} value={String(eventItem.id)}>
                      {eventItem.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {selectedFaceSearchEvent ? (
                <p className="text-xs text-muted-foreground">
                  Buscando dentro de: {selectedFaceSearchEvent.title}
                </p>
              ) : (
                <p className="text-xs text-muted-foreground">
                  Selecione um evento para liberar o envio da selfie.
                </p>
              )}
            </div>

            <FaceSearchSearchPanel
              title="Encontrar fotos de uma pessoa"
              description="Envie uma selfie nitida para localizar fotos parecidas dentro do evento escolhido."
              submitLabel="Buscar fotos"
              isPending={faceSearchMutation.isPending}
              includePendingEnabled
              includePending={faceSearchIncludePending}
              onIncludePendingChange={setFaceSearchIncludePending}
              disabled={!faceSearchEventId}
              disabledMessage={!faceSearchEventId ? 'Selecione um evento antes de enviar a selfie.' : null}
              requestMeta={faceSearchResponse?.request ?? null}
              results={faceSearchResponse?.results ?? []}
              errorMessage={faceSearchErrorMessage}
              onSubmit={({ file, includePending }) => {
                if (!faceSearchEventId) return;

                faceSearchMutation.mutate({
                  eventId: faceSearchEventId,
                  file,
                  includePending,
                });
              }}
            />
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
