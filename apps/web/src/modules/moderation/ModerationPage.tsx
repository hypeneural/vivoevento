import { startTransition, useCallback, useDeferredValue, useEffect, useMemo, useRef, useState } from 'react';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient, type InfiniteData } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { useSearchParams } from 'react-router-dom';
import {
  CheckCheck,
  CheckCircle2,
  Clock3,
  FilterX,
  ImageIcon,
  Loader2,
  Pin,
  Search,
  ShieldBan,
  SlidersHorizontal,
  Sparkles,
  Star,
  Wifi,
  WifiOff,
  XCircle,
} from 'lucide-react';

import { useAuth } from '@/app/providers/AuthProvider';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerContent, DrawerDescription, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useIsMobile } from '@/hooks/use-mobile';
import { useToast } from '@/hooks/use-toast';
import type { ApiEventMediaDetail, ApiEventMediaItem } from '@/lib/api-types';
import { queryKeys } from '@/lib/query-client';
import { resolveSenderBlockExpiration } from '@/lib/sender-blocking';
import { readSenderScopedPrefill } from '@/lib/sender-filters';
import { eventsService } from '@/modules/events/services/events.service';
import type { EventListItem } from '@/modules/events/types';
import { EmptyState } from '@/shared/components/EmptyState';
import { PageHeader } from '@/shared/components/PageHeader';
import { StatsCard } from '@/shared/components/StatsCard';
import { cn } from '@/lib/utils';

import { ModerationBulkActionBar } from './components/ModerationBulkActionBar';
import { type ModerationMediaAction } from './components/ModerationMediaCard';
import { ModerationMediaSurface } from './components/ModerationMediaSurface';
import { ModerationReviewPanel } from './components/ModerationReviewPanel';
import { ModerationVirtualGrid, type ModerationVirtualGridHandle } from './components/ModerationVirtualGrid';
import {
  compareModerationMedia,
  flattenModerationPages,
  mergeModerationItemCollections,
  moderationItemMatchesFilters,
  prependModerationItems,
  removeModerationItems,
  upsertModerationItems,
} from './feed-utils';
import { useModerationRealtime } from './hooks/useModerationRealtime';
import { moderationService } from './services/moderation.service';
import {
  MODERATION_ORIENTATION_OPTIONS,
  MODERATION_PAGE_SIZE_OPTIONS,
  MODERATION_QUICK_FILTERS,
  MODERATION_STATUS_OPTIONS,
  type ModerationFeedPage,
  type ModerationListFilters,
  type ModerationOrientationFilter,
  type ModerationStatusFilter,
} from './types';
import { buildActionMessage, formatDateTime, resolveQuickFilter } from './utils';

type FeedData = InfiniteData<ModerationFeedPage, string | null>;
type ModerationMutationPayload = {
  action: ModerationMediaAction;
  items: ApiEventMediaItem[];
  desiredValue?: boolean;
  mode: 'single' | 'bulk';
};

const EMPTY_STATS = { total: 0, pending: 0, approved: 0, rejected: 0, featured: 0, pinned: 0 };

function isInteractiveTarget(target: EventTarget | null) {
  if (!(target instanceof HTMLElement)) {
    return false;
  }

  return Boolean(
    target.closest('input, textarea, select, [role="combobox"], [contenteditable="true"]'),
  );
}

function buildOptimisticItems(
  payload: ModerationMutationPayload,
  currentItems: ApiEventMediaItem[],
) {
  const nextTimestamp = new Date().toISOString();
  const maxSortOrderByEvent = new Map<number, number>();
  const pinOffsets = new Map<number, number>();

  currentItems.forEach((item) => {
    maxSortOrderByEvent.set(item.event_id, Math.max(maxSortOrderByEvent.get(item.event_id) ?? 0, item.sort_order ?? 0));
  });

  return payload.items.map((item) => {
    if (payload.action === 'approve') {
      return {
        ...item,
        moderation_status: 'approved',
        publication_status: 'published',
        status: 'published',
        updated_at: nextTimestamp,
        published_at: item.published_at ?? nextTimestamp,
      };
    }

    if (payload.action === 'reject') {
      return {
        ...item,
        moderation_status: 'rejected',
        publication_status: 'draft',
        status: 'rejected',
        updated_at: nextTimestamp,
      };
    }

    if (payload.action === 'favorite') {
      return {
        ...item,
        is_featured: payload.desiredValue ?? !item.is_featured,
        updated_at: nextTimestamp,
      };
    }

    const nextPinnedState = payload.desiredValue ?? !item.is_pinned;

    if (!nextPinnedState) {
      return {
        ...item,
        is_pinned: false,
        sort_order: 0,
        updated_at: nextTimestamp,
      };
    }

    const offset = (pinOffsets.get(item.event_id) ?? 0) + 1;
    pinOffsets.set(item.event_id, offset);

    return {
      ...item,
      is_pinned: true,
      sort_order: (maxSortOrderByEvent.get(item.event_id) ?? 0) + offset,
      updated_at: nextTimestamp,
    };
  });
}

export default function ModerationPage() {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isMobile = useIsMobile();
  const { can, meOrganization } = useAuth();
  const [searchParams] = useSearchParams();
  const prefilledScope = useMemo(() => readSenderScopedPrefill(searchParams), [searchParams]);

  const canView = can('media.view') || can('media.moderate');
  const canModerate = can('media.moderate');

  const [perPage, setPerPage] = useState<number>(MODERATION_PAGE_SIZE_OPTIONS[1]);
  const [search, setSearch] = useState(prefilledScope.search);
  const [eventFilter, setEventFilter] = useState(prefilledScope.eventId);
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [orientationFilter, setOrientationFilter] = useState<string>('all');
  const [featuredOnly, setFeaturedOnly] = useState(false);
  const [pinnedOnly, setPinnedOnly] = useState(false);
  const [blockedSenderOnly, setBlockedSenderOnly] = useState(false);
  const [focusedMediaId, setFocusedMediaId] = useState<number | null>(null);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [selectionAnchorId, setSelectionAnchorId] = useState<number | null>(null);
  const [incomingItems, setIncomingItems] = useState<ApiEventMediaItem[]>([]);
  const [mobileReviewOpen, setMobileReviewOpen] = useState(false);
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false);
  const [filtersPanelOpen, setFiltersPanelOpen] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [isNearTop, setIsNearTop] = useState(true);
  const [senderBlockDuration, setSenderBlockDuration] = useState('7d');

  useEffect(() => {
    setSearch(prefilledScope.search);
    setEventFilter(prefilledScope.eventId);
  }, [prefilledScope.eventId, prefilledScope.search]);

  const deferredSearch = useDeferredValue(search);

  const sharedFilters = useMemo<Omit<ModerationListFilters, 'per_page' | 'cursor'>>(() => ({
    search: deferredSearch || undefined,
    event_id: eventFilter === 'all' ? undefined : Number(eventFilter),
    status: statusFilter === 'all' ? undefined : statusFilter as ModerationStatusFilter,
    featured: featuredOnly ? true : undefined,
    pinned: pinnedOnly ? true : undefined,
    sender_blocked: blockedSenderOnly ? true : undefined,
    orientation: orientationFilter === 'all' ? undefined : orientationFilter as ModerationOrientationFilter,
  }), [blockedSenderOnly, deferredSearch, eventFilter, featuredOnly, orientationFilter, pinnedOnly, statusFilter]);

  const feedFilters = useMemo<ModerationListFilters>(() => ({
    ...sharedFilters,
    per_page: perPage,
  }), [perPage, sharedFilters]);

  const feedQueryKey = useMemo(() => queryKeys.media.feed(feedFilters), [feedFilters]);
  const statsQueryKey = useMemo(() => queryKeys.media.feedStats(sharedFilters), [sharedFilters]);

  const eventsQuery = useQuery({
    queryKey: queryKeys.events.list({ scope: 'moderation-options' }),
    queryFn: () => eventsService.list({ per_page: 100, sort_by: 'starts_at', sort_direction: 'desc' }),
    enabled: canView,
  });

  const feedQuery = useInfiniteQuery({
    queryKey: feedQueryKey,
    initialPageParam: null as string | null,
    queryFn: ({ pageParam, signal }) => moderationService.list({ ...feedFilters, cursor: pageParam }, signal),
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
    enabled: canView,
    staleTime: 15_000,
  });

  const statsQuery = useQuery({
    queryKey: statsQueryKey,
    queryFn: ({ signal }) => moderationService.listStats(sharedFilters, signal),
    enabled: canView,
    staleTime: 15_000,
  });

  const events = useMemo(
    () => (eventsQuery.data?.data ?? []) as EventListItem[],
    [eventsQuery.data?.data],
  );
  const media = useMemo(() => flattenModerationPages(feedQuery.data), [feedQuery.data]);
  const selectedSet = useMemo(() => new Set(selectedIds), [selectedIds]);
  const selectedItems = useMemo(() => media.filter((item) => selectedSet.has(item.id)), [media, selectedSet]);
  const focusedMedia = useMemo(() => media.find((item) => item.id === focusedMediaId) ?? null, [focusedMediaId, media]);
  const focusedMediaIndex = useMemo(() => media.findIndex((item) => item.id === focusedMediaId), [focusedMediaId, media]);
  const stats = statsQuery.data ?? EMPTY_STATS;
  const activeQuickFilter = resolveQuickFilter(statusFilter, featuredOnly, pinnedOnly, blockedSenderOnly);
  const loadMoreRef = useRef<HTMLDivElement | null>(null);
  const virtualGridRef = useRef<ModerationVirtualGridHandle | null>(null);

  const focusedMediaDetailQuery = useQuery({
    queryKey: queryKeys.media.detail(String(focusedMediaId ?? '')),
    queryFn: ({ signal }) => moderationService.show(focusedMediaId as number, signal),
    enabled: focusedMediaId !== null,
    staleTime: 15_000,
  });

  const reviewMedia = (focusedMediaDetailQuery.data ?? focusedMedia) as ApiEventMediaDetail | ApiEventMediaItem | null;

  useEffect(() => {
    const handleScroll = () => setIsNearTop(window.scrollY < 140);

    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });

    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    setSelectedIds([]);
    setSelectionAnchorId(null);
    setIncomingItems((current) => current.filter((item) => moderationItemMatchesFilters(item, feedFilters)).sort(compareModerationMedia));
  }, [feedFilters]);

  useEffect(() => {
    if (!media.length) {
      setFocusedMediaId(null);
      return;
    }

    if (!focusedMediaId || !media.some((item) => item.id === focusedMediaId)) {
      setFocusedMediaId(media[0].id);
    }
  }, [focusedMediaId, media]);

  useEffect(() => {
    const node = loadMoreRef.current;

    if (!node || !feedQuery.hasNextPage || feedQuery.isFetching || feedQuery.isFetchingNextPage || feedQuery.isFetchNextPageError) {
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      if (entries[0]?.isIntersecting && !feedQuery.isFetching && !feedQuery.isFetchingNextPage) {
        feedQuery.fetchNextPage();
      }
    }, { rootMargin: '1200px 0px' });

    observer.observe(node);

    return () => observer.disconnect();
  }, [feedQuery, media.length]);

  const clearFilters = useCallback(() => {
    setSearch('');
    setEventFilter('all');
    setStatusFilter('all');
    setOrientationFilter('all');
    setFeaturedOnly(false);
    setPinnedOnly(false);
    setBlockedSenderOnly(false);
    setIncomingItems([]);
    setMobileFiltersOpen(false);
  }, []);

  const toggleFilters = useCallback(() => {
    if (isMobile) {
      setMobileFiltersOpen((current) => !current);
      return;
    }

    setFiltersPanelOpen((current) => !current);
  }, [isMobile]);

  const selectAllLoaded = useCallback(() => {
    setSelectedIds(media.map((item) => item.id));
    setSelectionAnchorId(media[0]?.id ?? null);
  }, [media]);

  const focusMedia = useCallback((itemId: number, options?: { openMobile?: boolean; scroll?: boolean }) => {
    startTransition(() => setFocusedMediaId(itemId));

    if (options?.openMobile && isMobile) {
      setMobileReviewOpen(true);
    }

    if (options?.scroll) {
      const itemIndex = media.findIndex((item) => item.id === itemId);

      window.requestAnimationFrame(() => {
        if (itemIndex >= 0) {
          virtualGridRef.current?.scrollToIndex(itemIndex);
          return;
        }

        document.getElementById(`moderation-media-${itemId}`)?.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
          inline: 'nearest',
        });
      });
    }
  }, [isMobile, media]);

  const focusMediaByOffset = useCallback((offset: -1 | 1) => {
    if (!media.length) {
      return;
    }

    const baseIndex = focusedMediaIndex >= 0
      ? focusedMediaIndex
      : offset > 0
        ? 0
        : media.length - 1;

    const nextIndex = Math.min(Math.max(baseIndex + offset, 0), media.length - 1);
    const nextItem = media[nextIndex];

    if (nextItem) {
      focusMedia(nextItem.id, { scroll: true });
    }
  }, [focusMedia, focusedMediaIndex, media]);

  const updateFeedCache = useCallback((callback: (current: FeedData | undefined) => FeedData | undefined) => {
    queryClient.setQueryData<FeedData>(feedQueryKey, callback);
  }, [feedQueryKey, queryClient]);

  const removeFromFeed = useCallback((ids: number[]) => {
    updateFeedCache((current) => removeModerationItems(current, ids, perPage));
  }, [perPage, updateFeedCache]);

  const upsertFeedItems = useCallback((items: ApiEventMediaItem[]) => {
    updateFeedCache((current) => upsertModerationItems(current, items, feedFilters, perPage));
  }, [feedFilters, perPage, updateFeedCache]);

  const prependIncomingToFeed = useCallback((items: ApiEventMediaItem[]) => {
    updateFeedCache((current) => prependModerationItems(current, items, feedFilters, perPage));
  }, [feedFilters, perPage, updateFeedCache]);

  const queueIncomingItem = useCallback((item: ApiEventMediaItem) => {
    setIncomingItems((current) => (
      mergeModerationItemCollections(current, [item]).sort(compareModerationMedia)
    ));
  }, []);

  const handleRealtimeItem = useCallback((item: ApiEventMediaItem) => {
    void queryClient.invalidateQueries({ queryKey: statsQueryKey });

    const existsInFeed = media.some((existing) => existing.id === item.id);
    const matchesFilter = moderationItemMatchesFilters(item, feedFilters);

    if (existsInFeed) {
      upsertFeedItems([item]);
      return;
    }

    if (!matchesFilter) {
      setIncomingItems((current) => current.filter((existing) => existing.id !== item.id));
      return;
    }

    if (isNearTop && selectedIds.length === 0) {
      prependIncomingToFeed([item]);
      return;
    }

    queueIncomingItem(item);
  }, [feedFilters, isNearTop, media, prependIncomingToFeed, queryClient, queueIncomingItem, selectedIds.length, statsQueryKey, upsertFeedItems]);

  const { connectionStatus: realtimeStatus } = useModerationRealtime({
    enabled: canView && !!meOrganization?.id,
    organizationId: meOrganization?.id,
    onCreated: handleRealtimeItem,
    onUpdated: handleRealtimeItem,
    onDeleted: (payload) => {
      void queryClient.invalidateQueries({ queryKey: statsQueryKey });
      removeFromFeed([payload.id]);
      setIncomingItems((current) => current.filter((item) => item.id !== payload.id));
      setSelectedIds((current) => current.filter((id) => id !== payload.id));
    },
  });

  const mutation = useMutation({
    mutationFn: async (payload: ModerationMutationPayload) => {
      const ids = payload.items.map((item) => item.id);

      if (payload.mode === 'single') {
        const [item] = payload.items;
        const response = payload.action === 'approve'
          ? await moderationService.approve(item.id)
          : payload.action === 'reject'
            ? await moderationService.reject(item.id)
            : payload.action === 'favorite'
              ? await moderationService.updateFavorite(item.id, payload.desiredValue ?? !item.is_featured)
              : await moderationService.updatePinned(item.id, payload.desiredValue ?? !item.is_pinned);

        return { ids, items: [response] };
      }

      const response = payload.action === 'approve'
        ? await moderationService.bulkApprove(ids)
        : payload.action === 'reject'
          ? await moderationService.bulkReject(ids)
          : payload.action === 'favorite'
            ? await moderationService.bulkFavorite(ids, payload.desiredValue ?? true)
            : await moderationService.bulkPinned(ids, payload.desiredValue ?? true);

      return response;
    },
    onMutate: async (payload) => {
      await Promise.all([
        queryClient.cancelQueries({ queryKey: feedQueryKey }),
        queryClient.cancelQueries({ queryKey: statsQueryKey }),
      ]);

      const previousFeed = queryClient.getQueryData<FeedData>(feedQueryKey);
      const optimisticItems = buildOptimisticItems(payload, media);

      upsertFeedItems(optimisticItems);

      return { previousFeed };
    },
    onError: (error: Error, _payload, context) => {
      if (context?.previousFeed) {
        queryClient.setQueryData(feedQueryKey, context.previousFeed);
      }

      toast({
        title: 'Falha ao atualizar a moderacao',
        description: error.message,
        variant: 'destructive',
      });
    },
    onSuccess: async (response, payload) => {
      upsertFeedItems(response.items);
      setIncomingItems((current) => current.filter((item) => !response.ids.includes(item.id)));

      if (payload.mode === 'bulk') {
        setSelectedIds((current) => current.filter((id) => !response.ids.includes(id)));
      }

      await Promise.all([
        queryClient.invalidateQueries({ queryKey: queryKeys.gallery.all(), refetchType: 'inactive' }),
        queryClient.invalidateQueries({ queryKey: queryKeys.events.all(), refetchType: 'inactive' }),
        queryClient.invalidateQueries({ queryKey: statsQueryKey }),
        ...response.ids.map((id) => queryClient.invalidateQueries({ queryKey: queryKeys.media.detail(String(id)) })),
      ]);

      const baseMessage = buildActionMessage(payload.action, payload.items[0]);
      const title = payload.items.length > 1 ? `${response.count} midias atualizadas` : baseMessage.title;
      const description = payload.items.length > 1
        ? 'A selecao foi atualizada sem recarregar a fila completa.'
        : baseMessage.description;

      toast({ title, description });
    },
  });

  const senderBlockMutation = useMutation({
    mutationFn: async (payload: { item: ApiEventMediaItem; shouldBlock: boolean; expiresAt: string | null }) => (
      payload.shouldBlock
        ? moderationService.blockSender(payload.item.id, {
          reason: 'Bloqueado pela moderacao do evento.',
          expires_at: payload.expiresAt,
        })
        : moderationService.unblockSender(payload.item.id)
    ),
    onSuccess: async (item, payload) => {
      upsertFeedItems([item]);
      queryClient.setQueryData(queryKeys.media.detail(String(item.id)), (current: ApiEventMediaDetail | ApiEventMediaItem | undefined) => (
        current ? { ...current, ...item } : current
      ));
      await queryClient.invalidateQueries({ queryKey: queryKeys.media.detail(String(item.id)) });
      await queryClient.invalidateQueries({ queryKey: queryKeys.events.detail(String(item.event_id)) });
      await queryClient.invalidateQueries({ queryKey: statsQueryKey });

      toast({
        title: payload.shouldBlock ? 'Remetente bloqueado' : 'Remetente desbloqueado',
        description: payload.shouldBlock
          ? 'Novas midias deste autor deixam de entrar no fluxo do evento.'
          : 'O remetente voltou a poder enviar novas midias para este evento.',
      });
    },
    onError: (error: Error) => {
      toast({
        title: 'Falha ao atualizar o remetente',
        description: error.message,
        variant: 'destructive',
      });
    },
  });

  const activeFilterTags = useMemo(() => {
    const tags: string[] = [];

    if (deferredSearch) tags.push(`Busca: ${deferredSearch}`);
    if (eventFilter !== 'all') tags.push(events.find((item) => String(item.id) === eventFilter)?.title ?? '');
    if (statusFilter !== 'all') tags.push(MODERATION_STATUS_OPTIONS.find((item) => item.value === statusFilter)?.label ?? '');
    if (orientationFilter !== 'all') tags.push(MODERATION_ORIENTATION_OPTIONS.find((item) => item.value === orientationFilter)?.label ?? '');
    if (featuredOnly) tags.push('Somente favoritas');
    if (pinnedOnly) tags.push('Somente fixadas');
    if (blockedSenderOnly) tags.push('Somente remetentes bloqueados');

    return tags.filter(Boolean);
  }, [blockedSenderOnly, deferredSearch, eventFilter, events, featuredOnly, orientationFilter, pinnedOnly, statusFilter]);

  const applyQuickFilter = (key: typeof MODERATION_QUICK_FILTERS[number]['key']) => {
    if (key === 'all') {
      setStatusFilter('all');
      setFeaturedOnly(false);
      setPinnedOnly(false);
      setBlockedSenderOnly(false);
      return;
    }

    if (key === 'featured') {
      setStatusFilter('all');
      setFeaturedOnly(true);
      setPinnedOnly(false);
      setBlockedSenderOnly(false);
      return;
    }

    if (key === 'pinned') {
      setStatusFilter('all');
      setFeaturedOnly(false);
      setPinnedOnly(true);
      setBlockedSenderOnly(false);
      return;
    }

    if (key === 'blocked_sender') {
      setStatusFilter('all');
      setFeaturedOnly(false);
      setPinnedOnly(false);
      setBlockedSenderOnly(true);
      return;
    }

    setStatusFilter(key);
    setFeaturedOnly(false);
    setPinnedOnly(false);
    setBlockedSenderOnly(false);
  };

  const handleToggleSelection = useCallback((itemId: number, shiftKey: boolean) => {
    const orderedIds = media.map((item) => item.id);

    setSelectedIds((current) => {
      const next = new Set(current);

      if (shiftKey && selectionAnchorId && orderedIds.includes(selectionAnchorId)) {
        const start = orderedIds.indexOf(selectionAnchorId);
        const end = orderedIds.indexOf(itemId);
        const [from, to] = start <= end ? [start, end] : [end, start];

        orderedIds.slice(from, to + 1).forEach((id) => next.add(id));
      } else if (next.has(itemId)) {
        next.delete(itemId);
      } else {
        next.add(itemId);
      }

      return Array.from(next);
    });

    setSelectionAnchorId(itemId);
  }, [media, selectionAnchorId]);

  const runAction = useCallback((payload: ModerationMutationPayload) => {
    mutation.mutate(payload);
  }, [mutation]);

  const runSenderBlockToggle = useCallback((checked: boolean) => {
    if (!focusedMedia) {
      return;
    }

    senderBlockMutation.mutate({
      item: focusedMedia,
      shouldBlock: checked,
      expiresAt: checked ? resolveSenderBlockExpiration(senderBlockDuration) : null,
    });
  }, [focusedMedia, senderBlockDuration, senderBlockMutation]);

  const isItemBusy = useCallback((itemId: number, action?: ModerationMediaAction) => (
    mutation.isPending
    && mutation.variables?.items.some((selected) => selected.id === itemId)
    && (!action || mutation.variables?.action === action)
  ), [mutation.isPending, mutation.variables]);

  const runSingleAction = useCallback((item: ApiEventMediaItem, action: ModerationMediaAction) => {
    runAction({
      action,
      items: [item],
      desiredValue: action === 'favorite'
        ? !item.is_featured
        : action === 'pin'
          ? !item.is_pinned
          : undefined,
      mode: 'single',
    });
  }, [runAction]);

  const runActionForCurrentTarget = useCallback((action: ModerationMediaAction) => {
    if (mutation.isPending) {
      return;
    }

    const targetItems = selectedItems.length > 0
      ? selectedItems
      : focusedMedia
        ? [focusedMedia]
        : [];

    if (!targetItems.length) {
      return;
    }

    const isBulk = targetItems.length > 1;
    const desiredValue = action === 'favorite'
      ? !(isBulk ? targetItems.every((item) => item.is_featured) : targetItems[0].is_featured)
      : action === 'pin'
        ? !(isBulk ? targetItems.every((item) => item.is_pinned) : targetItems[0].is_pinned)
        : undefined;

    runAction({
      action,
      items: targetItems,
      desiredValue,
      mode: isBulk ? 'bulk' : 'single',
    });
  }, [focusedMedia, mutation.isPending, runAction, selectedItems]);

  const allSelectedFeatured = selectedItems.length > 0 && selectedItems.every((item) => item.is_featured);
  const allSelectedPinned = selectedItems.length > 0 && selectedItems.every((item) => item.is_pinned);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (isInteractiveTarget(event.target)) {
        return;
      }

      if (mobileFiltersOpen && event.key !== 'Escape') {
        return;
      }

      const normalizedKey = event.key.toLowerCase();

      if ((event.metaKey || event.ctrlKey) && normalizedKey === 'a') {
        event.preventDefault();
        selectAllLoaded();
        return;
      }

      if (normalizedKey === 'escape') {
        if (previewOpen) {
          setPreviewOpen(false);
          return;
        }

        if (mobileReviewOpen) {
          setMobileReviewOpen(false);
          return;
        }

        if (mobileFiltersOpen) {
          setMobileFiltersOpen(false);
          return;
        }

        if (selectedIds.length > 0) {
          setSelectedIds([]);
          return;
        }
      }

      if (!focusedMedia && !selectedItems.length) {
        return;
      }

      if (normalizedKey === 'j' || event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        focusMediaByOffset(1);
        return;
      }

      if (normalizedKey === 'k' || event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        focusMediaByOffset(-1);
        return;
      }

      if (normalizedKey === 'enter' && focusedMedia) {
        event.preventDefault();
        setPreviewOpen(true);
        return;
      }

      if (normalizedKey === 'x' && focusedMedia) {
        event.preventDefault();
        handleToggleSelection(focusedMedia.id, event.shiftKey);
        return;
      }

      if (normalizedKey === 'a') {
        event.preventDefault();
        runActionForCurrentTarget('approve');
        return;
      }

      if (normalizedKey === 'r') {
        event.preventDefault();
        runActionForCurrentTarget('reject');
        return;
      }

      if (normalizedKey === 'f') {
        event.preventDefault();
        runActionForCurrentTarget('favorite');
        return;
      }

      if (normalizedKey === 'p') {
        event.preventDefault();
        runActionForCurrentTarget('pin');
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [
    focusMediaByOffset,
    focusedMedia,
    handleToggleSelection,
    mobileFiltersOpen,
    mobileReviewOpen,
    previewOpen,
    runActionForCurrentTarget,
    selectAllLoaded,
    selectedIds.length,
    selectedItems.length,
  ]);

  if (!canView) {
    return <div className="glass rounded-[28px] border border-border/60"><EmptyState title="Acesso indisponivel" description="Sua sessao nao possui permissao para visualizar a central de moderacao." /></div>;
  }

  return (
    <>
      <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-5">
        <PageHeader title="Moderacao" description="Fila viva para revisar, destacar e fixar sem interromper o ritmo da operacao." />

        <section className="overflow-hidden rounded-[30px] border border-border/60 bg-[radial-gradient(circle_at_top_left,_rgba(56,189,248,0.18),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(251,191,36,0.18),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.92),_rgba(248,250,252,0.86))] p-4 shadow-sm dark:bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.22),_transparent_34%),radial-gradient(circle_at_bottom_right,_rgba(245,158,11,0.18),_transparent_28%),linear-gradient(180deg,_rgba(15,23,42,0.96),_rgba(15,23,42,0.82))] sm:p-5">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div className="max-w-2xl space-y-3">
              <Badge variant="outline" className="border-primary/25 bg-primary/10 text-primary"><Sparkles className="h-3.5 w-3.5" />Fila operacional</Badge>
              <div className="space-y-2">
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Novas fotos entram sem resetar a pagina inteira e a moderacao segue no mesmo fluxo.</h2>
                <p className="text-sm leading-6 text-muted-foreground sm:text-base">O feed carrega sob demanda, mantem a ordem do filtro, destaca bordas por status e segura novas midias ate voce decidir inseri-las.</p>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3 rounded-[28px] border border-white/50 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5">
              <div><p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Em revisao</p><p className="mt-1 text-2xl font-semibold tabular-nums">{stats.pending}</p></div>
              <div><p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">No topo</p><p className="mt-1 text-2xl font-semibold tabular-nums">{stats.pinned}</p></div>
              <div><p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Favoritas</p><p className="mt-1 text-2xl font-semibold tabular-nums">{stats.featured}</p></div>
              <div className="space-y-1"><p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Conexao</p><div className="inline-flex items-center gap-2 text-sm font-medium">{realtimeStatus === 'connected' ? <Wifi className="h-4 w-4 text-emerald-500" /> : <WifiOff className="h-4 w-4 text-muted-foreground" />}{realtimeStatus === 'connected' ? 'Ao vivo' : 'Reconectando'}</div></div>
            </div>
          </div>
          <div className="mt-5 flex flex-wrap gap-2">{activeFilterTags.length ? activeFilterTags.map((tag) => <Badge key={tag} variant="secondary" className="rounded-full px-3 py-1">{tag}</Badge>) : <Badge variant="secondary" className="rounded-full px-3 py-1">Sem filtros extras</Badge>}</div>
        </section>

        <div className="grid grid-cols-2 gap-3 xl:grid-cols-5">
          <StatsCard title="Pendentes" value={stats.pending} icon={Clock3} />
          <StatsCard title="Aprovadas" value={stats.approved} icon={CheckCircle2} iconColor="text-emerald-500" iconBg="bg-emerald-500/10" />
          <StatsCard title="Reprovadas" value={stats.rejected} icon={XCircle} iconColor="text-rose-500" iconBg="bg-rose-500/10" />
          <StatsCard title="Favoritas" value={stats.featured} icon={Star} iconColor="text-amber-500" iconBg="bg-amber-500/10" />
          <StatsCard title="Fixadas" value={stats.pinned} icon={Pin} iconColor="text-sky-500" iconBg="bg-sky-500/10" />
        </div>

        {incomingItems.length > 0 ? (
          <div className="sticky top-4 z-30 rounded-[24px] border border-primary/20 bg-primary/10 px-4 py-3 shadow-sm backdrop-blur">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div><p className="text-sm font-semibold">{incomingItems.length} novas midias chegaram</p><p className="text-sm text-muted-foreground">Elas ficaram em espera para nao baguncar a fila enquanto voce modera.</p></div>
              <div className="flex gap-2"><Button className="rounded-full" onClick={() => { prependIncomingToFeed(incomingItems); setIncomingItems([]); }}>Inserir no topo</Button><Button variant="ghost" className="rounded-full" onClick={() => setIncomingItems([])}>Dispensar aviso</Button></div>
            </div>
          </div>
        ) : null}

        <section className="glass rounded-[28px] border border-border/60 p-4 sm:p-5">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-2 text-sm font-semibold"><SlidersHorizontal className="h-4 w-4 text-primary" />Filtros de moderacao</div>
            <div className="flex flex-wrap items-center gap-2">
              {!isMobile && activeFilterTags.length ? <Badge variant="outline">{activeFilterTags.length} filtros ativos</Badge> : null}
              <Button type="button" variant="outline" className="rounded-full" onClick={toggleFilters}>
                <SlidersHorizontal className="h-4 w-4" />
                {isMobile
                  ? mobileFiltersOpen ? 'Fechar filtros' : 'Abrir filtros'
                  : filtersPanelOpen ? 'Ocultar filtros' : 'Abrir filtros'}
              </Button>
            </div>
          </div>
          <div className="mt-4 space-y-3 md:hidden">
            <div className="relative"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input placeholder="Buscar por evento, legenda ou remetente" value={search} onChange={(event) => setSearch(event.target.value)} className="pl-9" /></div>
            <div className="flex flex-wrap items-center gap-2">
              <Button type="button" variant="outline" className="rounded-full" onClick={selectAllLoaded}><CheckCheck className="h-4 w-4" />Selecionar carregadas</Button>
              <Button type="button" variant="ghost" className="rounded-full" onClick={clearFilters}><FilterX className="h-4 w-4" />Limpar</Button>
            </div>
          </div>
          {filtersPanelOpen ? (
            <>
              <div className="mt-4 hidden flex-wrap gap-2 md:flex">{MODERATION_QUICK_FILTERS.map((filter) => <Button key={filter.key} type="button" size="sm" variant={activeQuickFilter === filter.key ? 'default' : 'outline'} className={cn('rounded-full px-4', activeQuickFilter === filter.key ? 'shadow-sm' : 'bg-background/60')} onClick={() => applyQuickFilter(filter.key)}>{filter.label}</Button>)}</div>
              <div className="mt-4 hidden gap-3 md:grid md:grid-cols-2 xl:grid-cols-12">
                <div className="relative xl:col-span-4"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input placeholder="Buscar por evento, legenda ou remetente" value={search} onChange={(event) => setSearch(event.target.value)} className="pl-9" /></div>
                <Select value={eventFilter} onValueChange={setEventFilter}><SelectTrigger className="xl:col-span-3"><SelectValue placeholder="Evento" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os eventos</SelectItem>{events.map((eventItem) => <SelectItem key={eventItem.id} value={String(eventItem.id)}>{eventItem.title}</SelectItem>)}</SelectContent></Select>
                <Select value={statusFilter} onValueChange={setStatusFilter}><SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Status" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os status</SelectItem>{MODERATION_STATUS_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select>
                <Select value={orientationFilter} onValueChange={setOrientationFilter}><SelectTrigger className="xl:col-span-2"><SelectValue placeholder="Formato" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os formatos</SelectItem>{MODERATION_ORIENTATION_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select>
                <Button type="button" variant={featuredOnly ? 'default' : 'outline'} className="xl:col-span-1" onClick={() => { setFeaturedOnly((current) => !current); setPinnedOnly(false); setBlockedSenderOnly(false); }}><Star className={cn('h-4 w-4', featuredOnly && 'fill-current')} />Favoritas</Button>
                <Button type="button" variant={pinnedOnly ? 'default' : 'outline'} className="md:col-span-2 xl:col-span-1" onClick={() => { setPinnedOnly((current) => !current); setFeaturedOnly(false); setBlockedSenderOnly(false); }}><Pin className="h-4 w-4" />Fixadas</Button>
                <Button type="button" variant={blockedSenderOnly ? 'default' : 'outline'} className="md:col-span-2 xl:col-span-2" onClick={() => { setBlockedSenderOnly((current) => !current); setFeaturedOnly(false); setPinnedOnly(false); setStatusFilter('all'); }}><ShieldBan className="h-4 w-4" />Bloqueados</Button>
              </div>
            </>
          ) : null}
          <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
            <div className="flex flex-wrap items-center gap-2"><span>{stats.total} itens no recorte atual</span><Badge variant="outline">{media.length} carregadas</Badge>{!isMobile ? <Badge variant="outline">J/K navega - A/R/F/P age - X marca</Badge> : null}</div>
            <div className="flex flex-wrap items-center gap-2">
              {isMobile ? (
                <>
                  <Badge variant="outline">{perPage} por bloco</Badge>
                  <Button type="button" variant="outline" size="sm" className="rounded-full" onClick={toggleFilters}><SlidersHorizontal className="h-4 w-4" />Ajustar</Button>
                </>
              ) : (
                <>
                  <Select value={String(perPage)} onValueChange={(value) => setPerPage(Number(value))}><SelectTrigger className="h-9 w-[132px] rounded-full bg-background/70 text-xs"><SelectValue placeholder="Densidade" /></SelectTrigger><SelectContent>{MODERATION_PAGE_SIZE_OPTIONS.map((option) => <SelectItem key={option} value={String(option)}>{option} por bloco</SelectItem>)}</SelectContent></Select>
                  <Button type="button" variant="outline" size="sm" className="rounded-full" onClick={selectAllLoaded}><CheckCheck className="h-4 w-4" />Selecionar carregadas</Button>
                  <Button type="button" variant="ghost" size="sm" onClick={clearFilters}><FilterX className="h-4 w-4" />Limpar filtros</Button>
                </>
              )}
            </div>
          </div>
        </section>

        {feedQuery.isLoading && media.length === 0 ? <div className="rounded-[28px] border border-border/60 bg-background/70 px-4 py-20 text-center text-muted-foreground"><Loader2 className="mx-auto h-6 w-6 animate-spin" />Carregando fila...</div> : null}
        {feedQuery.isError ? <div className="rounded-[28px] border border-destructive/30 bg-destructive/5 px-4 py-16 text-center text-sm text-destructive">Nao foi possivel carregar a fila de moderacao agora.</div> : null}
        {!feedQuery.isLoading && !feedQuery.isError && media.length === 0 ? <div className="glass rounded-[28px] border border-border/60"><EmptyState icon={ImageIcon} title="Nenhuma midia encontrada" description="Ajuste os filtros ou aguarde novas fotos chegarem para a fila de moderacao." action={<Button variant="outline" onClick={clearFilters}>Limpar filtros</Button>} /></div> : null}

        {media.length > 0 ? (
          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
            <section className="glass overflow-hidden rounded-[28px] border border-border/60">
              <div className="border-b border-border/60 px-4 py-4 sm:px-5"><div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold">Feed infinito de moderacao</p><p className="text-sm text-muted-foreground">Os cards seguem a ordem do filtro sem refazer a pagina inteira.</p><p className="mt-1 hidden text-xs text-muted-foreground md:block">Atalhos ativos: J/K navega, A aprova, R reprova, F favorita, P fixa, X marca e Enter amplia.</p></div>{feedQuery.isFetching && !feedQuery.isFetchingNextPage ? <div className="inline-flex items-center gap-2 text-sm text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" />Sincronizando</div> : null}</div></div>
              <ModerationVirtualGrid
                ref={virtualGridRef}
                items={media}
                focusedMediaId={focusedMediaId}
                selectedSet={selectedSet}
                canModerate={canModerate}
                loadMoreRef={loadMoreRef}
                isBusy={isItemBusy}
                onOpen={(itemId) => focusMedia(itemId, { openMobile: true })}
                onToggleChecked={(itemId, event) => {
                  event.stopPropagation();
                  focusMedia(itemId);
                  handleToggleSelection(itemId, event.shiftKey);
                }}
                onAction={runSingleAction}
              />
              {feedQuery.isFetchingNextPage ? <div className="border-t border-border/60 px-4 py-4 text-center text-sm text-muted-foreground"><Loader2 className="mx-auto mb-2 h-4 w-4 animate-spin" />Buscando mais fotos...</div> : feedQuery.isFetchNextPageError ? <div className="border-t border-border/60 px-4 py-4 text-center text-sm text-destructive"><p>Falha ao carregar o proximo bloco da fila.</p><Button type="button" size="sm" variant="outline" className="mt-3 rounded-full" onClick={() => feedQuery.fetchNextPage()}>Tentar novamente</Button></div> : !feedQuery.hasNextPage ? <div className="border-t border-border/60 px-4 py-4 text-center text-sm text-muted-foreground">Voce chegou ao fim do que foi carregado ate agora.</div> : null}
            </section>
            <aside className="hidden xl:block"><div className="sticky top-20"><ModerationReviewPanel media={reviewMedia} canModerate={canModerate} isBusy={(action) => mutation.isPending && !!focusedMedia && mutation.variables?.items.some((item) => item.id === focusedMedia.id) && (!action || mutation.variables?.action === action)} onAction={(action) => focusedMedia && runAction({ action, items: [focusedMedia], desiredValue: action === 'favorite' ? !focusedMedia.is_featured : action === 'pin' ? !focusedMedia.is_pinned : undefined, mode: 'single' })} onOpenPreview={() => setPreviewOpen(true)} canGoPrevious={focusedMediaIndex > 0} canGoNext={focusedMediaIndex >= 0 && focusedMediaIndex < media.length - 1} onPrevious={() => focusMediaByOffset(-1)} onNext={() => focusMediaByOffset(1)} senderBlockBusy={senderBlockMutation.isPending && !!focusedMedia && senderBlockMutation.variables?.item.id === focusedMedia.id} senderBlockDuration={senderBlockDuration} onSenderBlockDurationChange={setSenderBlockDuration} onSenderBlockToggle={runSenderBlockToggle} /></div></aside>
          </div>
        ) : null}
      </motion.div>

      <ModerationBulkActionBar selectedCount={selectedItems.length} canModerate={canModerate} isBusy={mutation.isPending} favoriteLabel={allSelectedFeatured ? 'Desfavoritar' : 'Favoritar'} pinLabel={allSelectedPinned ? 'Desafixar' : 'Fixar'} onClear={() => { setSelectedIds([]); setSelectionAnchorId(null); }} onAction={(action) => { if (!selectedItems.length) return; runAction({ action, items: selectedItems, desiredValue: action === 'favorite' ? !allSelectedFeatured : action === 'pin' ? !allSelectedPinned : undefined, mode: 'bulk' }); }} />

      <Drawer open={mobileReviewOpen} onOpenChange={setMobileReviewOpen}><DrawerContent className="max-h-[92vh] rounded-t-[28px]"><DrawerHeader className="border-b border-border/60 px-5 pb-4 text-left"><DrawerTitle>Revisao rapida</DrawerTitle><DrawerDescription>Confira a midia atual e decida sem sair do feed.</DrawerDescription></DrawerHeader><div className="overflow-y-auto px-4 py-4"><ModerationReviewPanel media={reviewMedia} canModerate={canModerate} isBusy={(action) => mutation.isPending && !!focusedMedia && mutation.variables?.items.some((item) => item.id === focusedMedia.id) && (!action || mutation.variables?.action === action)} onAction={(action) => focusedMedia && runAction({ action, items: [focusedMedia], desiredValue: action === 'favorite' ? !focusedMedia.is_featured : action === 'pin' ? !focusedMedia.is_pinned : undefined, mode: 'single' })} onOpenPreview={() => setPreviewOpen(true)} canGoPrevious={focusedMediaIndex > 0} canGoNext={focusedMediaIndex >= 0 && focusedMediaIndex < media.length - 1} onPrevious={() => focusMediaByOffset(-1)} onNext={() => focusMediaByOffset(1)} senderBlockBusy={senderBlockMutation.isPending && !!focusedMedia && senderBlockMutation.variables?.item.id === focusedMedia.id} senderBlockDuration={senderBlockDuration} onSenderBlockDurationChange={setSenderBlockDuration} onSenderBlockToggle={runSenderBlockToggle} /></div></DrawerContent></Drawer>

      <Drawer open={mobileFiltersOpen} onOpenChange={setMobileFiltersOpen}><DrawerContent className="max-h-[92vh] rounded-t-[28px]"><DrawerHeader className="border-b border-border/60 px-5 pb-4 text-left"><DrawerTitle>Filtros avancados</DrawerTitle><DrawerDescription>Ajuste o recorte da fila sem perder o contexto da moderacao.</DrawerDescription></DrawerHeader><div className="grid gap-3 overflow-y-auto px-4 py-4"><Select value={eventFilter} onValueChange={setEventFilter}><SelectTrigger><SelectValue placeholder="Evento" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os eventos</SelectItem>{events.map((eventItem) => <SelectItem key={eventItem.id} value={String(eventItem.id)}>{eventItem.title}</SelectItem>)}</SelectContent></Select><Select value={statusFilter} onValueChange={setStatusFilter}><SelectTrigger><SelectValue placeholder="Status" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os status</SelectItem>{MODERATION_STATUS_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select><Select value={orientationFilter} onValueChange={setOrientationFilter}><SelectTrigger><SelectValue placeholder="Formato" /></SelectTrigger><SelectContent><SelectItem value="all">Todos os formatos</SelectItem>{MODERATION_ORIENTATION_OPTIONS.map((option) => <SelectItem key={option.value} value={option.value}>{option.label}</SelectItem>)}</SelectContent></Select><Select value={String(perPage)} onValueChange={(value) => setPerPage(Number(value))}><SelectTrigger><SelectValue placeholder="Densidade" /></SelectTrigger><SelectContent>{MODERATION_PAGE_SIZE_OPTIONS.map((option) => <SelectItem key={option} value={String(option)}>{option} por bloco</SelectItem>)}</SelectContent></Select><div className="grid grid-cols-2 gap-2"><Button type="button" variant={featuredOnly ? 'default' : 'outline'} className="rounded-2xl" onClick={() => { setFeaturedOnly((current) => !current); setPinnedOnly(false); setBlockedSenderOnly(false); }}><Star className={cn('h-4 w-4', featuredOnly && 'fill-current')} />Favoritas</Button><Button type="button" variant={pinnedOnly ? 'default' : 'outline'} className="rounded-2xl" onClick={() => { setPinnedOnly((current) => !current); setFeaturedOnly(false); setBlockedSenderOnly(false); }}><Pin className="h-4 w-4" />Fixadas</Button><Button type="button" variant={blockedSenderOnly ? 'default' : 'outline'} className="col-span-2 rounded-2xl" onClick={() => { setBlockedSenderOnly((current) => !current); setFeaturedOnly(false); setPinnedOnly(false); setStatusFilter('all'); }}><ShieldBan className="h-4 w-4" />Remetentes bloqueados</Button></div><div className="flex flex-wrap gap-2 pt-2"><Button type="button" className="rounded-full" onClick={() => setMobileFiltersOpen(false)}>Aplicar</Button><Button type="button" variant="outline" className="rounded-full" onClick={clearFilters}>Limpar filtros</Button></div></div></DrawerContent></Drawer>

      <Dialog open={previewOpen} onOpenChange={setPreviewOpen}><DialogContent className="max-w-5xl"><DialogHeader><DialogTitle>{focusedMedia?.event_title || 'Preview da midia'}</DialogTitle><DialogDescription>{focusedMedia ? `${focusedMedia.sender_name} - ${formatDateTime(focusedMedia.created_at)}` : 'Visualizacao ampliada'}</DialogDescription></DialogHeader>{focusedMedia ? <div className="overflow-hidden rounded-[24px] border border-border/60 bg-muted"><ModerationMediaSurface media={focusedMedia} variant="preview" className="max-h-[70vh] min-h-[20rem] w-full" mediaClassName="max-h-[70vh] w-full object-contain" fit="contain" videoPreload="auto" /></div> : <div className="flex h-80 items-center justify-center rounded-[24px] border border-dashed border-border/60 bg-muted/30 text-muted-foreground"><ImageIcon className="h-10 w-10" /></div>}</DialogContent></Dialog>
    </>
  );
}
