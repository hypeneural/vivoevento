import { describe, expect, it, vi, beforeEach } from 'vitest';

import { api } from '@/lib/api';
import { mediaService } from './media.service';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

describe('mediaService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ data: [], meta: { page: 1, last_page: 1, total: 0, stats: {} } });
  });

  it('serializes boolean catalog filters as 1 or 0 for Laravel validation', async () => {
    await mediaService.list({
      face_search_enabled: true,
      featured: false,
      duplicates: true,
      face_index_status: 'indexed',
    });

    expect(api.get).toHaveBeenCalledWith('/media', {
      params: expect.objectContaining({
        face_search_enabled: 1,
        featured: 0,
        duplicates: 1,
        face_index_status: 'indexed',
      }),
    });
  });
});
