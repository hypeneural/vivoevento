import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';

import { moderationService } from './moderation.service';

describe('moderationService', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('passes AbortSignal through the feed request', async () => {
    const signal = new AbortController().signal;
    const getRawSpy = vi.spyOn(api, 'getRaw').mockResolvedValue({
      success: true,
      data: [],
      meta: {
        per_page: 24,
        next_cursor: null,
        prev_cursor: null,
        has_more: false,
        stats: null,
        request_id: 'req_test',
      },
    });

    await moderationService.list({ per_page: 24 }, signal);

    expect(getRawSpy).toHaveBeenCalledWith('/media/feed', {
      params: { per_page: 24 },
      signal,
    });
  });

  it('loads dedicated moderation stats without coupling to feed pagination params', async () => {
    const getSpy = vi.spyOn(api, 'get').mockResolvedValue({
      total: 10,
      pending: 4,
      approved: 3,
      rejected: 2,
      featured: 1,
      pinned: 1,
    });

    await moderationService.listStats({ status: 'pending_moderation' });

    expect(getSpy).toHaveBeenCalledWith('/media/feed/stats', {
      params: { status: 'pending_moderation' },
      signal: undefined,
    });
  });
});
