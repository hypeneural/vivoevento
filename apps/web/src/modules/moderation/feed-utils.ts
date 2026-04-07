import type { InfiniteData } from '@tanstack/react-query';

import type { ApiEventMediaItem } from '@/lib/api-types';

import type { ModerationFeedPage, ModerationListFilters } from './types';

type ModerationFeedData = InfiniteData<ModerationFeedPage, string | null>;

function mediaDateValue(media: ApiEventMediaItem) {
  return media.created_at ? new Date(media.created_at).getTime() : 0;
}

function pendingPriority(media: ApiEventMediaItem) {
  return media.status === 'pending_moderation' ? 1 : 0;
}

export function compareModerationMedia(a: ApiEventMediaItem, b: ApiEventMediaItem) {
  const pinnedDiff = (b.sort_order ?? 0) - (a.sort_order ?? 0);
  if (pinnedDiff !== 0) return pinnedDiff;

  const pendingDiff = pendingPriority(b) - pendingPriority(a);
  if (pendingDiff !== 0) return pendingDiff;

  const createdDiff = mediaDateValue(b) - mediaDateValue(a);
  if (createdDiff !== 0) return createdDiff;

  return b.id - a.id;
}

export function flattenModerationPages(data?: ModerationFeedData | null) {
  if (!data) return [] as ApiEventMediaItem[];

  return data.pages.flatMap((page) => page.data);
}

export function moderationItemMatchesFilters(media: ApiEventMediaItem, filters: ModerationListFilters) {
  if (filters.event_id && media.event_id !== filters.event_id) {
    return false;
  }

  if (filters.status && media.status !== filters.status) {
    return false;
  }

  if (typeof filters.featured === 'boolean' && media.is_featured !== filters.featured) {
    return false;
  }

  if (typeof filters.pinned === 'boolean' && !!media.is_pinned !== filters.pinned) {
    return false;
  }

  if (typeof filters.sender_blocked === 'boolean' && !!media.sender_blocked !== filters.sender_blocked) {
    return false;
  }

  if (filters.orientation && media.orientation !== filters.orientation) {
    return false;
  }

  if (filters.search) {
    const search = filters.search.trim().toLowerCase();
    const haystack = [
      media.event_title,
      media.sender_name,
      media.sender_phone,
      media.sender_lid,
      media.sender_external_id,
      media.caption,
      media.event_slug,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    if (!haystack.includes(search)) {
      return false;
    }
  }

  return true;
}

function uniqueById(items: ApiEventMediaItem[]) {
  const map = new Map<number, ApiEventMediaItem>();

  items.forEach((item) => {
    map.set(item.id, item);
  });

  return Array.from(map.values());
}

function rebuildPages(
  base: ModerationFeedData,
  items: ApiEventMediaItem[],
  perPage: number,
) {
  const chunkedPages = base.pages.map((page, index) => {
    const pageItems = items.slice(index * perPage, (index + 1) * perPage);

    return {
      ...page,
      data: pageItems,
    };
  });

  const expectedPageCount = Math.max(chunkedPages.length, Math.ceil(items.length / perPage));

  while (chunkedPages.length < expectedPageCount) {
    const previousPage = chunkedPages[chunkedPages.length - 1] ?? base.pages[base.pages.length - 1];
    const start = chunkedPages.length * perPage;
    const end = start + perPage;

    chunkedPages.push({
      ...previousPage,
      data: items.slice(start, end),
    });
  }

  return {
    ...base,
    pages: chunkedPages.filter((page, index) => index === 0 || page.data.length > 0),
  } satisfies ModerationFeedData;
}

export function upsertModerationItems(
  current: ModerationFeedData | undefined,
  items: ApiEventMediaItem[],
  filters: ModerationListFilters,
  perPage: number,
) {
  if (!current) return current;

  const currentItems = flattenModerationPages(current);
  const incomingById = new Map(items.map((item) => [item.id, item] as const));

  const nextItems = uniqueById([
    ...currentItems
      .map((item) => incomingById.get(item.id) ?? item)
      .filter((item) => !incomingById.has(item.id) || moderationItemMatchesFilters(item, filters)),
    ...items.filter((item) => !currentItems.some((currentItem) => currentItem.id === item.id)),
  ])
    .filter((item) => moderationItemMatchesFilters(item, filters))
    .sort(compareModerationMedia);

  return rebuildPages(current, nextItems, perPage);
}

export function prependModerationItems(
  current: ModerationFeedData | undefined,
  items: ApiEventMediaItem[],
  filters: ModerationListFilters,
  perPage: number,
) {
  if (!current) return current;

  const merged = uniqueById([
    ...items.filter((item) => moderationItemMatchesFilters(item, filters)),
    ...flattenModerationPages(current),
  ]).sort(compareModerationMedia);

  return rebuildPages(current, merged, perPage);
}

export function removeModerationItems(
  current: ModerationFeedData | undefined,
  ids: number[],
  perPage: number,
) {
  if (!current) return current;

  const idSet = new Set(ids);
  const nextItems = flattenModerationPages(current)
    .filter((item) => !idSet.has(item.id))
    .sort(compareModerationMedia);

  return rebuildPages(current, nextItems, perPage);
}
