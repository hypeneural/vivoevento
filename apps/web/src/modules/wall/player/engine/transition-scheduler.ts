import type {
  WallRuntimeItem,
  WallSettings,
  WallTransition,
  WallTransitionMode,
} from '../types';
import { getWallLayoutDefinition } from '../themes/registry';
import {
  DEFAULT_RANDOM_WALL_TRANSITION_POOL,
  getWallTransitionDefinition,
  sanitizeWallTransitionPool,
} from './transition-registry';

export const DEFAULT_RANDOM_TRANSITION_POOL: WallTransition[] = [...DEFAULT_RANDOM_WALL_TRANSITION_POOL];

interface ResolveWallRuntimeTransitionEffectInput {
  code: string;
  eventId?: string | number | null;
  settings?: WallSettings | null;
  currentItem?: WallRuntimeItem | null;
  lastTransitionEffect?: WallTransition | null;
  transitionAdvanceCount?: number;
}

function hashSeed(seed: string): number {
  let hash = 2166136261;

  for (let index = 0; index < seed.length; index += 1) {
    hash ^= seed.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }

  return hash >>> 0;
}

function shouldUseRandomTransitionMode(settings?: WallSettings | null): boolean {
  if (!settings || settings.transition_mode !== 'random') {
    return false;
  }

  return getWallLayoutDefinition(settings.layout).kind === 'single';
}

export function resolveWallRuntimeTransitionMode(
  settings?: WallSettings | null,
): WallTransitionMode {
  return shouldUseRandomTransitionMode(settings) ? 'random' : 'fixed';
}

function sanitizeTransitionEffect(effect?: WallTransition | null): WallTransition {
  return getWallTransitionDefinition(effect ?? 'fade').id;
}

export function resolveWallRuntimeTransitionEffect({
  code,
  eventId = null,
  settings,
  currentItem,
  lastTransitionEffect = null,
  transitionAdvanceCount = 0,
}: ResolveWallRuntimeTransitionEffectInput): WallTransition {
  const baseEffect = sanitizeTransitionEffect(settings?.transition_effect ?? 'fade');

  if (!settings || !currentItem || resolveWallRuntimeTransitionMode(settings) !== 'random') {
    return baseEffect;
  }

  const filteredPool = sanitizeWallTransitionPool(settings.transition_pool)
    .filter((effect) => effect !== 'none');
  const safePool = filteredPool.length > 0
    ? filteredPool
    : DEFAULT_RANDOM_TRANSITION_POOL.filter((effect) => effect !== 'none');
  const normalizedLastEffect = lastTransitionEffect ? sanitizeTransitionEffect(lastTransitionEffect) : null;
  const candidates = normalizedLastEffect && safePool.length > 1
    ? safePool.filter((effect) => effect !== normalizedLastEffect)
    : safePool;
  const activePool = candidates.length > 0 ? candidates : safePool;

  if (activePool.length === 0) {
    return baseEffect;
  }

  const seed = [
    eventId ?? code,
    settings.layout,
    currentItem.id,
    transitionAdvanceCount,
  ].join(':');
  const selectedIndex = hashSeed(seed) % activePool.length;

  return activePool[selectedIndex] ?? baseEffect;
}
