import { describe, expect, it } from 'vitest';

import type { ApiEventMediaItem } from '@/lib/api-types';

import {
  compareModerationMedia,
  moderationItemMatchesFilters,
  prependModerationItems,
  resolveDuplicateClusterSelection,
  resolveNextPendingModerationItem,
  resolveNextModerationDetailPrefetchItem,
  upsertModerationItems,
} from './feed-utils';
import type { ModerationFeedPage } from './types';

function makeMedia(overrides: Partial<ApiEventMediaItem> = {}): ApiEventMediaItem {
  return {
    id: 1,
    event_id: 10,
    event_title: 'Evento',
    event_slug: 'evento',
    event_status: 'active',
    event_moderation_mode: 'ai',
    media_type: 'image',
    channel: 'upload',
    status: 'approved',
    processing_status: 'processed',
    moderation_status: 'approved',
    publication_status: 'draft',
    sender_name: 'Convidado',
    caption: null,
    thumbnail_url: 'https://example.test/thumb.jpg',
    preview_url: 'https://example.test/preview.jpg',
    thumbnail_source: 'thumb',
    preview_source: 'fast_preview',
    original_url: 'https://example.test/original.jpg',
    created_at: '2026-04-09T12:00:00Z',
    updated_at: '2026-04-09T12:00:10Z',
    published_at: null,
    is_featured: false,
    is_pinned: false,
    sort_order: 0,
    orientation: 'portrait',
    ...overrides,
  };
}

function makeFeedPage(items: ApiEventMediaItem[]): { pages: ModerationFeedPage[]; pageParams: Array<string | null> } {
  return {
    pages: [
      {
        data: items,
        meta: {
          per_page: 24,
          next_cursor: null,
          prev_cursor: null,
          has_more: false,
          request_id: 'req_test',
          stats: null,
        },
      },
    ],
    pageParams: [null],
  };
}

describe('moderation feed local helpers', () => {
  it('prioritizes the effective pending_moderation state in local sorting even when raw moderation_status is already approved', () => {
    const aiPending = makeMedia({
      id: 100,
      status: 'pending_moderation',
      moderation_status: 'approved',
      created_at: '2026-04-09T10:00:00Z',
    });

    const approved = makeMedia({
      id: 101,
      status: 'approved',
      moderation_status: 'approved',
      created_at: '2026-04-09T12:00:00Z',
    });

    const ordered = [approved, aiPending].sort(compareModerationMedia);

    expect(ordered.map((item) => item.id)).toEqual([100, 101]);
  });

  it('matches filters by effective status on the client side', () => {
    const aiPending = makeMedia({
      id: 200,
      status: 'pending_moderation',
      moderation_status: 'approved',
    });

    expect(moderationItemMatchesFilters(aiPending, { status: 'pending_moderation' })).toBe(true);
    expect(moderationItemMatchesFilters(aiPending, { status: 'approved' })).toBe(false);
  });

  it('matches operational filters for media type, duplicates and ai review on the client side', () => {
    const aiReviewDuplicateImage = makeMedia({
      id: 210,
      media_type: 'image',
      duplicate_group_key: 'dup-ai-1',
      is_duplicate_candidate: true,
      safety_status: 'review',
      safety_decision: 'review',
      safety_is_blocking: true,
      context_decision: 'approved',
      context_is_blocking: false,
    });

    const plainVideo = makeMedia({
      id: 211,
      media_type: 'video',
      duplicate_group_key: null,
      is_duplicate_candidate: false,
      safety_status: 'skipped',
      safety_decision: 'skipped',
      safety_is_blocking: false,
      context_decision: 'skipped',
      context_is_blocking: false,
    });

    expect(moderationItemMatchesFilters(aiReviewDuplicateImage, { media_type: 'image' })).toBe(true);
    expect(moderationItemMatchesFilters(aiReviewDuplicateImage, { duplicates: true })).toBe(true);
    expect(moderationItemMatchesFilters(aiReviewDuplicateImage, { ai_review: true })).toBe(true);

    expect(moderationItemMatchesFilters(plainVideo, { media_type: 'video' })).toBe(true);
    expect(moderationItemMatchesFilters(plainVideo, { duplicates: true })).toBe(false);
    expect(moderationItemMatchesFilters(plainVideo, { ai_review: true })).toBe(false);
  });

  it('keeps the freshest copy of an item when an older realtime payload arrives', () => {
    const current = makeFeedPage([
      makeMedia({
        id: 300,
        status: 'approved',
        updated_at: '2026-04-09T12:00:10Z',
      }),
    ]);

    const next = upsertModerationItems(
      current,
      [
        makeMedia({
          id: 300,
          status: 'rejected',
          updated_at: '2026-04-09T12:00:05Z',
        }),
      ],
      {},
      24,
    );

    expect(next?.pages[0]?.data[0]?.status).toBe('approved');
  });

  it('accepts a newer incoming payload when prepending waiting realtime items', () => {
    const current = makeFeedPage([
      makeMedia({
        id: 301,
        status: 'approved',
        updated_at: '2026-04-09T12:00:05Z',
      }),
    ]);

    const next = prependModerationItems(
      current,
      [
        makeMedia({
          id: 301,
          status: 'rejected',
          updated_at: '2026-04-09T12:00:20Z',
        }),
      ],
      {},
      24,
    );

    expect(next?.pages[0]?.data[0]?.status).toBe('rejected');
  });

  it('returns the next loaded item for detail prefetch after the focused media', () => {
    const first = makeMedia({ id: 401 });
    const second = makeMedia({ id: 402 });
    const third = makeMedia({ id: 403 });

    expect(resolveNextModerationDetailPrefetchItem([first, second, third], 402)?.id).toBe(403);
    expect(resolveNextModerationDetailPrefetchItem([first, second, third], 403)).toBeNull();
    expect(resolveNextModerationDetailPrefetchItem([first, second, third], null)).toBeNull();
  });

  it('resolves the next pending moderation item after the focused media and skips handled ids', () => {
    const items = [
      makeMedia({ id: 501, status: 'approved' }),
      makeMedia({ id: 502, status: 'pending_moderation' }),
      makeMedia({ id: 503, status: 'pending_moderation' }),
      makeMedia({ id: 504, status: 'rejected' }),
    ];

    expect(resolveNextPendingModerationItem(items, 502, { excludeIds: [502] })?.id).toBe(503);
    expect(resolveNextPendingModerationItem(items, 503, { excludeIds: [503] })?.id).toBe(502);
  });

  it('returns null when no pending item remains after the handled selection', () => {
    const items = [
      makeMedia({ id: 601, status: 'approved' }),
      makeMedia({ id: 602, status: 'pending_moderation' }),
      makeMedia({ id: 603, status: 'rejected' }),
    ];

    expect(resolveNextPendingModerationItem(items, 602, { excludeIds: [602] })).toBeNull();
  });

  it('returns the duplicate cluster excluding the currently focused item', () => {
    const items = [
      makeMedia({ id: 701, duplicate_group_key: 'dup-1', is_duplicate_candidate: true }),
      makeMedia({ id: 702, duplicate_group_key: 'dup-1', is_duplicate_candidate: true }),
      makeMedia({ id: 703, duplicate_group_key: 'dup-1', is_duplicate_candidate: true }),
      makeMedia({ id: 704, duplicate_group_key: 'dup-2', is_duplicate_candidate: true }),
    ];

    expect(resolveDuplicateClusterSelection(items, 702).map((item) => item.id)).toEqual([701, 703]);
    expect(resolveDuplicateClusterSelection(items, null)).toEqual([]);
  });
});
