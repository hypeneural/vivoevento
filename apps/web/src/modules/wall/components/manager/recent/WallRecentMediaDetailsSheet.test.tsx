import { render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { WallRecentMediaDetailsSheet } from './WallRecentMediaDetailsSheet';

const useIsMobileMock = vi.fn();

vi.mock('@/hooks/use-mobile', () => ({
  useIsMobile: () => useIsMobileMock(),
}));

const recentItem = {
  id: 'recent-carla',
  previewUrl: 'https://cdn.example.com/recent-carla.jpg',
  senderName: 'Carla',
  senderKey: 'whatsapp:5511999990001',
  source: 'whatsapp',
  createdAt: '2026-04-02T20:58:00Z',
  approvedAt: '2026-04-02T20:58:20Z',
  displayedAt: null,
  status: 'queued' as const,
  isFeatured: true,
  isReplay: false,
};

describe('WallRecentMediaDetailsSheet', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('usa sheet lateral no desktop', async () => {
    useIsMobileMock.mockReturnValue(false);

    render(
      <WallRecentMediaDetailsSheet
        open
        item={recentItem}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-recent-media-details-sheet')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da midia recente/i)).toBeInTheDocument();
    expect(screen.getByText(/Carla/i)).toBeInTheDocument();
  });

  it('usa drawer no mobile', async () => {
    useIsMobileMock.mockReturnValue(true);

    render(
      <WallRecentMediaDetailsSheet
        open
        item={recentItem}
        onOpenChange={vi.fn()}
      />,
    );

    expect(await screen.findByTestId('wall-recent-media-details-drawer')).toBeInTheDocument();
    expect(screen.getByText(/Detalhes da midia recente/i)).toBeInTheDocument();
  });
});
