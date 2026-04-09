import type { WallRuntimeItem } from '../types';
import {
  DEFAULT_WALL_SELECTION_POLICY,
  hasWallItemReplayBudget,
  isWallItemReplayMature,
  mediaToRuntimeItem,
  pickNextWallItemId,
  resolveWallReplayIntervalMs,
  resolveWallReplayVolume,
  selectBestItemWithinSender,
  selectEligibleWallItems,
} from './selectors';

function makeRuntimeItem(overrides: Partial<WallRuntimeItem> = {}): WallRuntimeItem {
  return {
    id: 'media_1',
    url: 'https://cdn.example.com/media-1.jpg',
    type: 'image',
    sender_name: 'Maria',
    sender_key: 'sender-maria',
    senderKey: 'sender-maria',
    source_type: 'whatsapp',
    caption: 'Legenda',
    duplicate_cluster_key: null,
    duplicateClusterKey: null,
    is_featured: false,
    created_at: '2026-04-01T10:00:00Z',
    assetStatus: 'ready',
    playedAt: null,
    playCount: 0,
    lastError: null,
    orientation: null,
    width: 1200,
    height: 900,
    ...overrides,
  };
}

describe('wall selectors', () => {
  beforeEach(() => {
    vi.useRealTimers();
  });

  it('limits the eligible window per sender and releases backlog as items are consumed', () => {
    const senderBurst = [
      makeRuntimeItem({ id: 'maria_1', created_at: '2026-04-01T10:01:00Z' }),
      makeRuntimeItem({ id: 'maria_2', created_at: '2026-04-01T10:02:00Z' }),
      makeRuntimeItem({ id: 'maria_3', created_at: '2026-04-01T10:03:00Z' }),
      makeRuntimeItem({ id: 'maria_4', created_at: '2026-04-01T10:04:00Z' }),
    ];

    const initialEligibleIds = selectEligibleWallItems(senderBurst, DEFAULT_WALL_SELECTION_POLICY)
      .map((item) => item.id);

    expect(initialEligibleIds).toEqual(['maria_4', 'maria_3', 'maria_2']);

    const afterOnePlay = senderBurst.map((item) => (
      item.id === 'maria_4'
        ? {
            ...item,
            playCount: 1,
            playedAt: '2026-04-01T10:05:00Z',
          }
        : item
    ));

    const refreshedEligibleIds = selectEligibleWallItems(afterOnePlay, DEFAULT_WALL_SELECTION_POLICY)
      .map((item) => item.id);

    expect(refreshedEligibleIds).toEqual(['maria_3', 'maria_2', 'maria_1']);
  });

  it('avoids repeating the same duplicate cluster when another item is available', () => {
    const candidate = selectBestItemWithinSender([
      makeRuntimeItem({
        id: 'cluster_a',
        duplicate_cluster_key: 'dup-a',
        duplicateClusterKey: 'dup-a',
        created_at: '2026-04-01T10:01:00Z',
      }),
      makeRuntimeItem({
        id: 'cluster_b',
        duplicate_cluster_key: 'dup-b',
        duplicateClusterKey: 'dup-b',
        created_at: '2026-04-01T10:00:00Z',
      }),
    ], {
      currentDuplicateClusterKey: 'dup-a',
    });

    expect(candidate?.id).toBe('cluster_b');
  });

  it('respects sender cooldown when another sender can appear', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-01T10:10:00Z'));

    const nextItemId = pickNextWallItemId([
      makeRuntimeItem({
        id: 'maria_1',
        senderKey: 'sender-maria',
        sender_key: 'sender-maria',
      }),
      makeRuntimeItem({
        id: 'joao_1',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
      }),
    ], 'maria_1', DEFAULT_WALL_SELECTION_POLICY, {
      'sender-maria': {
        lastPlayedAt: '2026-04-01T10:09:30Z',
        recentPlayTimestamps: ['2026-04-01T10:09:30Z'],
        totalPlayCount: 1,
      },
    });

    expect(nextItemId).toBe('joao_1');
  });

  it('respects sender window limit when another sender is available', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-01T10:10:00Z'));

    const nextItemId = pickNextWallItemId([
      makeRuntimeItem({
        id: 'maria_1',
        senderKey: 'sender-maria',
        sender_key: 'sender-maria',
        created_at: '2026-04-01T10:05:00Z',
      }),
      makeRuntimeItem({
        id: 'joao_1',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
        created_at: '2026-04-01T10:04:00Z',
      }),
    ], null, DEFAULT_WALL_SELECTION_POLICY, {
      'sender-maria': {
        lastPlayedAt: '2026-04-01T10:08:30Z',
        recentPlayTimestamps: [
          '2026-04-01T10:02:00Z',
          '2026-04-01T10:05:00Z',
          '2026-04-01T10:08:30Z',
        ],
        totalPlayCount: 3,
      },
    });

    expect(nextItemId).toBe('joao_1');
  });

  it('resolves adaptive replay volume and interval defaults', () => {
    expect(resolveWallReplayVolume(3, DEFAULT_WALL_SELECTION_POLICY)).toBe('low');
    expect(resolveWallReplayVolume(8, DEFAULT_WALL_SELECTION_POLICY)).toBe('medium');
    expect(resolveWallReplayVolume(13, DEFAULT_WALL_SELECTION_POLICY)).toBe('high');

    expect(resolveWallReplayIntervalMs(3, DEFAULT_WALL_SELECTION_POLICY)).toBe(8 * 60_000);
    expect(resolveWallReplayIntervalMs(8, DEFAULT_WALL_SELECTION_POLICY)).toBe(12 * 60_000);
    expect(resolveWallReplayIntervalMs(13, DEFAULT_WALL_SELECTION_POLICY)).toBe(20 * 60_000);
  });

  it('changes replay maturity based on queue volume', () => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-01T10:10:00Z'));

    const replayedItem = makeRuntimeItem({
      id: 'replayed_1',
      playCount: 1,
      playedAt: '2026-04-01T10:01:00Z',
    });

    expect(isWallItemReplayMature(replayedItem, 3, DEFAULT_WALL_SELECTION_POLICY)).toBe(true);
    expect(isWallItemReplayMature(replayedItem, 8, DEFAULT_WALL_SELECTION_POLICY)).toBe(false);
    expect(isWallItemReplayMature(replayedItem, 13, DEFAULT_WALL_SELECTION_POLICY)).toBe(false);
  });

  it('uses persisted adaptive replay thresholds when they differ from engine defaults', () => {
    const policy = {
      ...DEFAULT_WALL_SELECTION_POLICY,
      lowVolumeMaxItems: 4,
      mediumVolumeMaxItems: 9,
      replayIntervalLowMs: 4 * 60_000,
      replayIntervalMediumMs: 9 * 60_000,
      replayIntervalHighMs: 18 * 60_000,
    };

    expect(resolveWallReplayVolume(4, policy)).toBe('low');
    expect(resolveWallReplayVolume(5, policy)).toBe('medium');
    expect(resolveWallReplayVolume(10, policy)).toBe('high');

    expect(resolveWallReplayIntervalMs(4, policy)).toBe(4 * 60_000);
    expect(resolveWallReplayIntervalMs(5, policy)).toBe(9 * 60_000);
    expect(resolveWallReplayIntervalMs(10, policy)).toBe(18 * 60_000);
  });

  it('respects the max replay budget per item when alternatives still exist', () => {
    const policy = {
      ...DEFAULT_WALL_SELECTION_POLICY,
      maxReplaysPerItem: 1,
    };

    const eligibleIds = selectEligibleWallItems([
      makeRuntimeItem({
        id: 'spent_item',
        sender_name: 'Maria',
        senderKey: 'sender-maria',
        sender_key: 'sender-maria',
        playCount: 2,
        playedAt: '2026-04-01T10:01:00Z',
      }),
      makeRuntimeItem({
        id: 'fresh_item',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
        playCount: 1,
        playedAt: '2026-04-01T10:02:00Z',
        created_at: '2026-04-01T10:03:00Z',
      }),
    ], policy).map((item) => item.id);

    expect(hasWallItemReplayBudget(makeRuntimeItem({ playCount: 2 }), policy)).toBe(false);
    expect(eligibleIds).toEqual(['fresh_item']);
    expect(pickNextWallItemId([
      makeRuntimeItem({
        id: 'spent_item',
        sender_name: 'Maria',
        senderKey: 'sender-maria',
        sender_key: 'sender-maria',
        playCount: 2,
        playedAt: '2026-04-01T10:01:00Z',
      }),
      makeRuntimeItem({
        id: 'fresh_item',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
        playCount: 1,
        playedAt: '2026-04-01T10:02:00Z',
        created_at: '2026-04-01T10:03:00Z',
      }),
    ], null, policy)).toBe('fresh_item');
  });

  it('falls back to exhausted items only when every item already spent the replay budget', () => {
    const policy = {
      ...DEFAULT_WALL_SELECTION_POLICY,
      maxReplaysPerItem: 1,
    };

    const eligibleIds = selectEligibleWallItems([
      makeRuntimeItem({
        id: 'spent_item_a',
        sender_name: 'Maria',
        senderKey: 'sender-maria',
        sender_key: 'sender-maria',
        playCount: 2,
        playedAt: '2026-04-01T10:01:00Z',
      }),
      makeRuntimeItem({
        id: 'spent_item_b',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
        playCount: 2,
        playedAt: '2026-04-01T10:02:00Z',
        created_at: '2026-04-01T10:03:00Z',
      }),
    ], policy).map((item) => item.id);

    expect(eligibleIds).toHaveLength(2);
    expect(eligibleIds).toEqual(expect.arrayContaining(['spent_item_a', 'spent_item_b']));
  });

  it('hydrates runtime dimensions and orientation directly from the wall payload', () => {
    const runtimeItem = mediaToRuntimeItem({
      id: 'media_vertical',
      url: 'https://cdn.example.com/media-vertical.webp',
      original_url: 'https://cdn.example.com/media-vertical-original.jpg',
      type: 'image',
      sender_name: 'Ana',
      sender_key: 'sender-ana',
      source_type: 'whatsapp',
      caption: 'Vertical',
      duplicate_cluster_key: null,
      is_featured: false,
      width: 1080,
      height: 1920,
      orientation: 'vertical',
      created_at: '2026-04-01T10:00:00Z',
    });

    expect(runtimeItem.width).toBe(1080);
    expect(runtimeItem.height).toBe(1920);
    expect(runtimeItem.orientation).toBe('vertical');
    expect(runtimeItem.original_url).toBe('https://cdn.example.com/media-vertical-original.jpg');
  });

  it('prefers ready items when at least one renderable item is ready', () => {
    const eligibleIds = selectEligibleWallItems([
      makeRuntimeItem({
        id: 'video_idle',
        type: 'video',
        assetStatus: 'idle',
        created_at: '2026-04-01T10:03:00Z',
      }),
      makeRuntimeItem({
        id: 'image_ready',
        type: 'image',
        assetStatus: 'ready',
        created_at: '2026-04-01T10:02:00Z',
      }),
    ]).map((item) => item.id);

    expect(eligibleIds).toEqual(['image_ready']);
  });

  it('falls back to idle items when no renderable item is ready yet', () => {
    const eligibleIds = selectEligibleWallItems([
      makeRuntimeItem({
        id: 'video_idle_a',
        type: 'video',
        assetStatus: 'idle',
        created_at: '2026-04-01T10:03:00Z',
      }),
      makeRuntimeItem({
        id: 'video_idle_b',
        type: 'video',
        assetStatus: 'idle',
        sender_name: 'Joao',
        senderKey: 'sender-joao',
        sender_key: 'sender-joao',
        created_at: '2026-04-01T10:02:00Z',
      }),
    ]).map((item) => item.id);

    expect(eligibleIds).toEqual(expect.arrayContaining(['video_idle_a', 'video_idle_b']));
  });
});
