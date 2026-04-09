import { describe, expect, it } from 'vitest';

import type { ApiEventMediaItem } from '@/lib/api-types';

import { compareModerationMedia, moderationItemMatchesFilters, prependModerationItems, upsertModerationItems } from './feed-utils';
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
});
