import type {
  MediaOrientation,
  WallMediaItem,
  WallSelectionMode,
  WallSenderRuntimeStats,
  WallRuntimeItem,
  WallSettings,
} from '../types';

export interface WallSelectionPolicy {
  maxEligibleItemsPerSender: number;
  maxReplaysPerItem: number;
  avoidSameSenderIfAlternativeExists: boolean;
  avoidSameDuplicateClusterIfAlternativeExists: boolean;
  senderCooldownMs: number;
  senderWindowLimit: number;
  senderWindowMs: number;
  lowVolumeMaxItems: number;
  mediumVolumeMaxItems: number;
  replayIntervalLowMs: number;
  replayIntervalMediumMs: number;
  replayIntervalHighMs: number;
}

export const DEFAULT_WALL_SELECTION_POLICY: WallSelectionPolicy = {
  maxEligibleItemsPerSender: 3,
  maxReplaysPerItem: 2,
  avoidSameSenderIfAlternativeExists: true,
  avoidSameDuplicateClusterIfAlternativeExists: true,
  senderCooldownMs: 60_000,
  senderWindowLimit: 3,
  senderWindowMs: 10 * 60_000,
  lowVolumeMaxItems: 6,
  mediumVolumeMaxItems: 12,
  replayIntervalLowMs: 8 * 60_000,
  replayIntervalMediumMs: 12 * 60_000,
  replayIntervalHighMs: 20 * 60_000,
};

const WALL_SELECTION_POLICY_PRESETS: Record<WallSelectionMode, WallSelectionPolicy> = {
  balanced: DEFAULT_WALL_SELECTION_POLICY,
  live: {
    ...DEFAULT_WALL_SELECTION_POLICY,
    maxEligibleItemsPerSender: 5,
    maxReplaysPerItem: 3,
    senderCooldownMs: 30_000,
    senderWindowLimit: 4,
  },
  inclusive: {
    ...DEFAULT_WALL_SELECTION_POLICY,
    maxEligibleItemsPerSender: 3,
    maxReplaysPerItem: 1,
    senderCooldownMs: 90_000,
    senderWindowLimit: 2,
  },
  editorial: {
    ...DEFAULT_WALL_SELECTION_POLICY,
    maxEligibleItemsPerSender: 4,
    maxReplaysPerItem: 2,
    senderCooldownMs: 45_000,
  },
  custom: DEFAULT_WALL_SELECTION_POLICY,
};

export type WallReplayVolume = 'low' | 'medium' | 'high';

function normalizeSenderSegment(value?: string | null): string {
  const normalized = (value ?? '')
    .trim()
    .toLowerCase()
    .normalize('NFKD')
    .replace(/\p{M}/gu, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return normalized;
}

function toTimestamp(value?: string | null): number {
  if (!value) {
    return 0;
  }

  const timestamp = Date.parse(value);
  return Number.isFinite(timestamp) ? timestamp : 0;
}

function compareNullableIsoAsc(left?: string | null, right?: string | null): number {
  if (!left && !right) return 0;
  if (!left) return -1;
  if (!right) return 1;
  return toTimestamp(left) - toTimestamp(right);
}

function compareNullableIsoDesc(left?: string | null, right?: string | null): number {
  return compareNullableIsoAsc(right, left);
}

function nowTimestamp(): number {
  return Date.now();
}

function clampInteger(value: number | undefined, fallback: number, min: number, max: number): number {
  if (!Number.isFinite(value)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.trunc(value as number)));
}

export function resolveWallSelectionPolicy(
  settings?: Pick<WallSettings, 'selection_mode' | 'selection_policy'> | null,
): WallSelectionPolicy {
  const preset = WALL_SELECTION_POLICY_PRESETS[settings?.selection_mode ?? 'balanced']
    ?? DEFAULT_WALL_SELECTION_POLICY;
  const policy = settings?.selection_policy;

  if (!policy) {
    return preset;
  }

  return {
    ...preset,
    maxEligibleItemsPerSender: clampInteger(
      policy.max_eligible_items_per_sender,
      preset.maxEligibleItemsPerSender,
      1,
      12,
    ),
    maxReplaysPerItem: clampInteger(
      policy.max_replays_per_item,
      preset.maxReplaysPerItem,
      0,
      6,
    ),
    lowVolumeMaxItems: clampInteger(
      policy.low_volume_max_items,
      preset.lowVolumeMaxItems,
      2,
      20,
    ),
    mediumVolumeMaxItems: clampInteger(
      policy.medium_volume_max_items,
      Math.max(
        clampInteger(policy.low_volume_max_items, preset.lowVolumeMaxItems, 2, 20) + 1,
        preset.mediumVolumeMaxItems,
      ),
      clampInteger(policy.low_volume_max_items, preset.lowVolumeMaxItems, 2, 20) + 1,
      50,
    ),
    replayIntervalLowMs: clampInteger(
      policy.replay_interval_low_minutes,
      Math.round(preset.replayIntervalLowMs / 60_000),
      1,
      60,
    ) * 60_000,
    replayIntervalMediumMs: clampInteger(
      policy.replay_interval_medium_minutes,
      Math.round(preset.replayIntervalMediumMs / 60_000),
      1,
      60,
    ) * 60_000,
    replayIntervalHighMs: clampInteger(
      policy.replay_interval_high_minutes,
      Math.round(preset.replayIntervalHighMs / 60_000),
      1,
      60,
    ) * 60_000,
    avoidSameSenderIfAlternativeExists:
      policy.avoid_same_sender_if_alternative_exists
      ?? preset.avoidSameSenderIfAlternativeExists,
    avoidSameDuplicateClusterIfAlternativeExists:
      policy.avoid_same_duplicate_cluster_if_alternative_exists
      ?? preset.avoidSameDuplicateClusterIfAlternativeExists,
    senderCooldownMs: clampInteger(
      policy.sender_cooldown_seconds,
      Math.round(preset.senderCooldownMs / 1000),
      0,
      300,
    ) * 1000,
    senderWindowLimit: clampInteger(
      policy.sender_window_limit,
      preset.senderWindowLimit,
      1,
      12,
    ),
    senderWindowMs: clampInteger(
      policy.sender_window_minutes,
      Math.round(preset.senderWindowMs / 60_000),
      1,
      30,
    ) * 60_000,
  };
}

function recentTimestampCount(
  timestamps: string[],
  windowMs: number,
  referenceTimestamp = nowTimestamp(),
): number {
  return timestamps.filter((value) => (
    referenceTimestamp - toTimestamp(value) <= windowMs
  )).length;
}

export function resolveWallReplayVolume(
  itemCount: number,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
): WallReplayVolume {
  if (itemCount <= policy.lowVolumeMaxItems) {
    return 'low';
  }

  if (itemCount <= policy.mediumVolumeMaxItems) {
    return 'medium';
  }

  return 'high';
}

export function resolveWallReplayIntervalMs(
  itemCount: number,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
): number {
  const volume = resolveWallReplayVolume(itemCount, policy);

  if (volume === 'low') {
    return policy.replayIntervalLowMs;
  }

  if (volume === 'medium') {
    return policy.replayIntervalMediumMs;
  }

  return policy.replayIntervalHighMs;
}

export function detectOrientation(
  width?: number | null,
  height?: number | null,
): MediaOrientation | null {
  if (!width || !height) {
    return null;
  }

  const ratio = width / height;
  if (ratio > 1.15) return 'horizontal';
  if (ratio < 0.85) return 'vertical';
  return 'squareish';
}

export function deriveWallSenderKey(media: Pick<WallMediaItem, 'id' | 'sender_key' | 'sender_name'>): string {
  const explicitSenderKey = normalizeSenderSegment(media.sender_key);
  if (explicitSenderKey) {
    return explicitSenderKey;
  }

  const senderName = normalizeSenderSegment(media.sender_name);
  if (senderName) {
    return `guest-${senderName}`;
  }

  return `media-${normalizeSenderSegment(media.id) || media.id}`;
}

export function deriveWallDuplicateClusterKey(
  media: Pick<WallMediaItem, 'duplicate_cluster_key'>,
): string | null {
  const normalizedCluster = normalizeSenderSegment(media.duplicate_cluster_key);
  return normalizedCluster || null;
}

export function mediaToRuntimeItem(
  media: WallMediaItem,
  previous?: Partial<WallRuntimeItem> | null,
): WallRuntimeItem {
  const width = previous?.width ?? media.width ?? null;
  const height = previous?.height ?? media.height ?? null;
  const orientation =
    previous?.orientation
    ?? media.orientation
    ?? detectOrientation(width, height);

  return {
    ...media,
    senderKey: deriveWallSenderKey(media),
    duplicateClusterKey: deriveWallDuplicateClusterKey(media),
    assetStatus: previous?.assetStatus ?? 'idle',
    playedAt: previous?.playedAt ?? null,
    playCount: previous?.playCount ?? 0,
    lastError: previous?.lastError ?? null,
    width,
    height,
    orientation,
  };
}

export function isWallItemRenderable(item: WallRuntimeItem): boolean {
  return Boolean(item.url) && item.assetStatus !== 'error';
}

function isSenderBlockedNow(
  senderKey: string,
  senderStats: Record<string, WallSenderRuntimeStats>,
  policy: WallSelectionPolicy,
  referenceTimestamp = nowTimestamp(),
): boolean {
  const stats = senderStats[senderKey];
  if (!stats) {
    return false;
  }

  const lastPlayedAt = toTimestamp(stats.lastPlayedAt);
  const blockedByCooldown =
    lastPlayedAt > 0
    && referenceTimestamp - lastPlayedAt < policy.senderCooldownMs;

  const blockedByWindow =
    recentTimestampCount(stats.recentPlayTimestamps, policy.senderWindowMs, referenceTimestamp)
    >= policy.senderWindowLimit;

  return blockedByCooldown || blockedByWindow;
}

export function isWallItemReplayMature(
  item: WallRuntimeItem,
  itemCount: number,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
  referenceTimestamp = nowTimestamp(),
): boolean {
  if (!item.playedAt || item.playCount === 0) {
    return true;
  }

  const intervalMs = resolveWallReplayIntervalMs(itemCount, policy);
  return referenceTimestamp - toTimestamp(item.playedAt) >= intervalMs;
}

export function hasWallItemReplayBudget(
  item: WallRuntimeItem,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
): boolean {
  const maxDisplaysPerItem = Math.max(1, policy.maxReplaysPerItem + 1);

  if (item.playCount <= 0) {
    return true;
  }

  return item.playCount < maxDisplaysPerItem;
}

function compareItemsWithinSender(left: WallRuntimeItem, right: WallRuntimeItem): number {
  if (left.is_featured !== right.is_featured) {
    return Number(right.is_featured) - Number(left.is_featured);
  }

  const leftUnseen = left.playedAt == null;
  const rightUnseen = right.playedAt == null;
  if (leftUnseen !== rightUnseen) {
    return Number(rightUnseen) - Number(leftUnseen);
  }

  if (left.playCount !== right.playCount) {
    return left.playCount - right.playCount;
  }

  const playedCompare = compareNullableIsoAsc(left.playedAt, right.playedAt);
  if (playedCompare !== 0) {
    return playedCompare;
  }

  const createdCompare = compareNullableIsoDesc(left.created_at, right.created_at);
  if (createdCompare !== 0) {
    return createdCompare;
  }

  return left.id.localeCompare(right.id);
}

function selectItemsWithinSenderWindow(
  items: WallRuntimeItem[],
  policy: WallSelectionPolicy,
): WallRuntimeItem[] {
  return [...items]
    .sort(compareItemsWithinSender)
    .slice(0, Math.max(1, policy.maxEligibleItemsPerSender));
}

export function selectEligibleWallItems(
  items: WallRuntimeItem[],
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
  senderStats: Record<string, WallSenderRuntimeStats> = {},
): WallRuntimeItem[] {
  const renderableItems = items.filter(isWallItemRenderable);
  if (renderableItems.length === 0) {
    return [];
  }

  const hasReadyItems = renderableItems.some((item) => item.assetStatus === 'ready');
  const candidatePool = hasReadyItems
    ? renderableItems.filter((item) => item.assetStatus === 'ready')
    : renderableItems;
  const replayBudgetPool = candidatePool.filter((item) => hasWallItemReplayBudget(item, policy));
  const budgetAwarePool = replayBudgetPool.length > 0 ? replayBudgetPool : candidatePool;
  const unseenItems = budgetAwarePool.filter((item) => item.playCount === 0 || !item.playedAt);
  const matureReplayItems = budgetAwarePool.filter((item) => (
    item.playCount > 0
    && item.playedAt
    && isWallItemReplayMature(item, budgetAwarePool.length, policy)
  ));
  const replayAwarePool = unseenItems.length > 0
    ? unseenItems
    : (matureReplayItems.length > 0 ? matureReplayItems : budgetAwarePool);

  const candidatesBySender = new Map<string, WallRuntimeItem[]>();

  for (const item of replayAwarePool) {
    const senderKey = item.senderKey || deriveWallSenderKey(item);
    const group = candidatesBySender.get(senderKey);
    if (group) {
      group.push(item);
    } else {
      candidatesBySender.set(senderKey, [item]);
    }
  }

  const senderEntries = [...candidatesBySender.entries()].map(([senderKey, senderItems]) => ({
    senderKey,
    senderItems,
    isBlocked: isSenderBlockedNow(senderKey, senderStats, policy),
  }));

  const availableSenders = senderEntries.some((entry) => !entry.isBlocked)
    ? senderEntries.filter((entry) => !entry.isBlocked)
    : senderEntries;

  return availableSenders
    .flatMap((entry) => selectItemsWithinSenderWindow(entry.senderItems, policy));
}

export function selectBestItemWithinSender(
  items: WallRuntimeItem[],
  options?: {
    currentDuplicateClusterKey?: string | null;
    policy?: WallSelectionPolicy;
  },
): WallRuntimeItem | null {
  if (items.length === 0) {
    return null;
  }

  const policy = options?.policy ?? DEFAULT_WALL_SELECTION_POLICY;
  const candidateItems = selectItemsWithinSenderWindow(items, policy);
  const orderedItems = [...candidateItems].sort(compareItemsWithinSender);

  if (!policy.avoidSameDuplicateClusterIfAlternativeExists) {
    return orderedItems[0] ?? null;
  }

  const currentDuplicateClusterKey = options?.currentDuplicateClusterKey ?? null;
  if (!currentDuplicateClusterKey) {
    return orderedItems[0] ?? null;
  }

  const alternativeItem = orderedItems.find(
    (item) => item.duplicateClusterKey !== currentDuplicateClusterKey,
  );

  return alternativeItem ?? orderedItems[0] ?? null;
}

export function pickNextWallItemId(
  items: WallRuntimeItem[],
  currentItemId?: string | null,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
  senderStats: Record<string, WallSenderRuntimeStats> = {},
): string | null {
  const candidatePool = selectEligibleWallItems(items, policy, senderStats);
  if (candidatePool.length === 0) {
    return null;
  }

  const currentItem = currentItemId
    ? candidatePool.find((item) => item.id === currentItemId) ?? null
    : null;
  const currentDuplicateClusterKey = currentItem?.duplicateClusterKey ?? null;

  const candidatesBySender = new Map<string, WallRuntimeItem[]>();

  for (const item of candidatePool) {
    const senderKey = item.senderKey || deriveWallSenderKey(item);
    const group = candidatesBySender.get(senderKey);
    if (group) {
      group.push(item);
    } else {
      candidatesBySender.set(senderKey, [item]);
    }
  }

  const senderCandidates = [...candidatesBySender.entries()]
    .map(([senderKey, senderItems]) => {
      const candidate = selectBestItemWithinSender(senderItems, {
        currentDuplicateClusterKey,
        policy,
      });

      if (!candidate) {
        return null;
      }

      return {
        senderKey,
        candidate,
        totalPlayCount: senderItems.reduce((total, item) => total + item.playCount, 0),
        lastPlayedAt: senderItems
          .map((item) => item.playedAt)
          .filter((value): value is string => Boolean(value))
          .sort((left, right) => toTimestamp(right) - toTimestamp(left))[0] ?? null,
      };
    })
    .filter((value): value is NonNullable<typeof value> => Boolean(value));

  if (senderCandidates.length === 0) {
    return null;
  }

  const currentSenderKey = currentItem?.senderKey ?? null;
  const fairPool = policy.avoidSameSenderIfAlternativeExists && currentSenderKey && senderCandidates.length > 1
    ? senderCandidates.filter((entry) => entry.senderKey !== currentSenderKey)
    : senderCandidates;

  const orderedPool = [...fairPool].sort((left, right) => {
    const leftDifferentCluster = currentDuplicateClusterKey != null
      && left.candidate.duplicateClusterKey !== currentDuplicateClusterKey;
    const rightDifferentCluster = currentDuplicateClusterKey != null
      && right.candidate.duplicateClusterKey !== currentDuplicateClusterKey;
    if (leftDifferentCluster !== rightDifferentCluster) {
      return Number(rightDifferentCluster) - Number(leftDifferentCluster);
    }

    const leftUnseen = left.candidate.playedAt == null;
    const rightUnseen = right.candidate.playedAt == null;
    if (leftUnseen !== rightUnseen) {
      return Number(rightUnseen) - Number(leftUnseen);
    }

    const playedCompare = compareNullableIsoAsc(left.lastPlayedAt, right.lastPlayedAt);
    if (playedCompare !== 0) {
      return playedCompare;
    }

    if (left.totalPlayCount !== right.totalPlayCount) {
      return left.totalPlayCount - right.totalPlayCount;
    }

    if (left.candidate.is_featured !== right.candidate.is_featured) {
      return Number(right.candidate.is_featured) - Number(left.candidate.is_featured);
    }

    const createdCompare = compareNullableIsoDesc(
      left.candidate.created_at,
      right.candidate.created_at,
    );
    if (createdCompare !== 0) {
      return createdCompare;
    }

    return left.senderKey.localeCompare(right.senderKey);
  });

  return orderedPool[0]?.candidate.id ?? null;
}

export function resolveInitialWallItemId(
  items: WallRuntimeItem[],
  preferredCurrentItemId?: string | null,
  policy: WallSelectionPolicy = DEFAULT_WALL_SELECTION_POLICY,
  senderStats: Record<string, WallSenderRuntimeStats> = {},
): string | null {
  if (preferredCurrentItemId) {
    const existingItem = items.find((item) => item.id === preferredCurrentItemId);
    if (existingItem && isWallItemRenderable(existingItem)) {
      return existingItem.id;
    }
  }

  return pickNextWallItemId(items, null, policy, senderStats);
}

export function findWallCurrentIndex(
  items: WallRuntimeItem[],
  currentItemId?: string | null,
): number {
  if (!currentItemId) {
    return 0;
  }

  const index = items.findIndex((item) => item.id === currentItemId);
  return index >= 0 ? index : 0;
}
