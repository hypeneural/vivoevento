import type { WallHeartbeatPayload } from './types';

type NetworkInformationLike = {
  effectiveType?: string;
  saveData?: boolean;
  downlink?: number;
  rtt?: number;
};

type NavigatorWithMediaHints = Navigator & {
  connection?: NetworkInformationLike;
  mozConnection?: NetworkInformationLike;
  webkitConnection?: NetworkInformationLike;
  deviceMemory?: number;
};

type WallRuntimeProfile = Pick<
  WallHeartbeatPayload,
  | 'hardware_concurrency'
  | 'device_memory_gb'
  | 'network_effective_type'
  | 'network_save_data'
  | 'network_downlink_mbps'
  | 'network_rtt_ms'
  | 'prefers_reduced_motion'
  | 'document_visibility_state'
>;

export function resolveWallRuntimeProfile(): WallRuntimeProfile {
  const navigatorLike = typeof navigator !== 'undefined'
    ? navigator as NavigatorWithMediaHints
    : null;

  const connection = navigatorLike?.connection
    ?? navigatorLike?.mozConnection
    ?? navigatorLike?.webkitConnection;

  return {
    hardware_concurrency: normalizeInteger(navigatorLike?.hardwareConcurrency),
    device_memory_gb: normalizeNumber(navigatorLike?.deviceMemory),
    network_effective_type: normalizeEffectiveType(connection?.effectiveType),
    network_save_data: typeof connection?.saveData === 'boolean' ? connection.saveData : null,
    network_downlink_mbps: normalizeNumber(connection?.downlink),
    network_rtt_ms: normalizeInteger(connection?.rtt),
    prefers_reduced_motion: resolveReducedMotionPreference(),
    document_visibility_state: resolveVisibilityState(),
  };
}

function resolveReducedMotionPreference(): boolean | null {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return null;
  }

  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function resolveVisibilityState(): WallRuntimeProfile['document_visibility_state'] {
  if (typeof document === 'undefined') {
    return null;
  }

  const state = document.visibilityState;

  if (state === 'visible' || state === 'hidden' || state === 'prerender' || state === 'unloaded') {
    return state;
  }

  return null;
}

function normalizeEffectiveType(value?: string): WallRuntimeProfile['network_effective_type'] {
  if (value === 'slow-2g' || value === '2g' || value === '3g' || value === '4g' || value === 'unknown') {
    return value;
  }

  return value ? 'unknown' : null;
}

function normalizeInteger(value?: number): number | null {
  if (!Number.isFinite(value) || (value ?? 0) <= 0) {
    return null;
  }

  return Math.trunc(value as number);
}

function normalizeNumber(value?: number): number | null {
  if (!Number.isFinite(value) || (value ?? 0) <= 0) {
    return null;
  }

  return Math.round((value as number) * 100) / 100;
}
