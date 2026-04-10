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

  it('forwards operational moderation filters to feed and stats endpoints', async () => {
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
    const getSpy = vi.spyOn(api, 'get').mockResolvedValue({
      total: 2,
      pending: 1,
      approved: 0,
      rejected: 0,
      featured: 0,
      pinned: 0,
    });

    const filters = {
      media_type: 'image' as const,
      duplicates: true,
      ai_review: true,
      status: 'error' as const,
    };

    await moderationService.list(filters, signal);
    await moderationService.listStats(filters, signal);

    expect(getRawSpy).toHaveBeenCalledWith('/media/feed', {
      params: filters,
      signal,
    });

    expect(getSpy).toHaveBeenCalledWith('/media/feed/stats', {
      params: filters,
      signal,
    });
  });

  it('forwards reject reasons in single-item moderation actions', async () => {
    const postSpy = vi.spyOn(api, 'post').mockResolvedValue({} as never);

    await moderationService.reject(77, 'Duplicada');

    expect(postSpy).toHaveBeenCalledWith('/media/77/reject', {
      body: {
        reason: 'Duplicada',
      },
    });
  });

  it('forwards reject reasons in bulk moderation actions', async () => {
    const postSpy = vi.spyOn(api, 'post').mockResolvedValue({} as never);

    await moderationService.bulkReject([10, 11], 'Spam');

    expect(postSpy).toHaveBeenCalledWith('/media/bulk/reject', {
      body: {
        ids: [10, 11],
        reason: 'Spam',
      },
    });
  });

  it('loads the duplicate cluster for a focused moderation item', async () => {
    const getSpy = vi.spyOn(api, 'get').mockResolvedValue([] as never);
    const signal = new AbortController().signal;

    await moderationService.listDuplicateCluster(77, signal);

    expect(getSpy).toHaveBeenCalledWith('/media/77/duplicates', {
      signal,
    });
  });

  it('posts to the dedicated undo endpoint for manual moderation decisions', async () => {
    const postSpy = vi.spyOn(api, 'post').mockResolvedValue({} as never);

    await moderationService.undoDecision(77);

    expect(postSpy).toHaveBeenCalledWith('/media/77/undo-decision');
  });
});
