import { useEffect, useState, type RefObject } from 'react';

import type { WallSettings } from '../types';

const DEFAULT_STAGE_WIDTH = 1365;
const DEFAULT_STAGE_HEIGHT = 768;
const BASE_SAFE_INSET = 24;
const SIDE_BRANDING_INSET = 96;
const QR_PANEL_INSET = 140;
const BOTTOM_OVERLAY_INSET = 96;
const FLOATING_CAPTION_INSET = 156;
const STANDARD_STAGE_WIDTH_THRESHOLD = 1180;
const STANDARD_STAGE_HEIGHT_THRESHOLD = 700;
const STANDARD_USABLE_WIDTH_THRESHOLD = 1080;
const STANDARD_USABLE_HEIGHT_THRESHOLD = 620;
const SAFE_AREA_PRESSURE_THRESHOLD = 0.16;

export interface WallStageInsets {
  top: number;
  right: number;
  bottom: number;
  left: number;
}

export interface WallStageGeometry {
  width: number;
  height: number;
  usableWidth: number;
  usableHeight: number;
  safeInsets: WallStageInsets;
  safeAreaCoverageRatio: number;
  effectivePreset: NonNullable<WallSettings['theme_config']['preset']>;
  downgraded: boolean;
  downgradeReason: 'small_stage' | 'safe_area_pressure' | null;
}

interface ResolveWallStageGeometryInput {
  width?: number;
  height?: number;
  showQr?: boolean;
  showBranding?: boolean;
  showSenderCredit?: boolean;
  showFloatingCaption?: boolean;
  preferredPreset?: WallSettings['theme_config']['preset'];
}

interface UseStageGeometryOptions extends ResolveWallStageGeometryInput {
  enabled?: boolean;
}

function resolveOverlayFootprintArea({
  width,
  height,
  showQr = false,
  showBranding = false,
  showSenderCredit = false,
  showFloatingCaption = false,
}: Required<Pick<ResolveWallStageGeometryInput, 'width' | 'height'>> & Omit<ResolveWallStageGeometryInput, 'width' | 'height' | 'preferredPreset'>) {
  const totalArea = Math.max(width * height, 1);
  const qrArea = showQr ? 296 * 168 : 0;
  const brandingArea = showBranding ? 150 * 84 : 0;
  const senderArea = showSenderCredit ? 240 * 92 : 0;
  const captionWidth = Math.min(width * 0.62, 880);
  const captionArea = showFloatingCaption ? captionWidth * 146 : 0;

  return (qrArea + brandingArea + senderArea + captionArea) / totalArea;
}

export function resolveWallStageInsets({
  showQr = false,
  showBranding = false,
  showSenderCredit = false,
  showFloatingCaption = false,
}: Omit<ResolveWallStageGeometryInput, 'width' | 'height' | 'preferredPreset'> = {}): WallStageInsets {
  return {
    top: BASE_SAFE_INSET,
    right: BASE_SAFE_INSET + (showQr ? QR_PANEL_INSET : 0),
    bottom:
      BASE_SAFE_INSET
      + (showQr || showBranding || showSenderCredit ? BOTTOM_OVERLAY_INSET : 0)
      + (showFloatingCaption ? FLOATING_CAPTION_INSET : 0),
    left: BASE_SAFE_INSET + (showBranding || showSenderCredit ? SIDE_BRANDING_INSET : 0),
  };
}

export function resolveStageAwarePuzzlePreset(
  preferredPreset: WallSettings['theme_config']['preset'] | undefined,
  geometry: Pick<WallStageGeometry, 'width' | 'height' | 'usableWidth' | 'usableHeight' | 'safeAreaCoverageRatio'>,
): Pick<WallStageGeometry, 'effectivePreset' | 'downgraded' | 'downgradeReason'> {
  if (preferredPreset === 'compact') {
    return {
      effectivePreset: 'compact',
      downgraded: false,
      downgradeReason: null,
    };
  }

  const widthConstrained =
    geometry.width < STANDARD_STAGE_WIDTH_THRESHOLD
    || geometry.usableWidth < STANDARD_USABLE_WIDTH_THRESHOLD;
  const heightConstrained =
    geometry.height < STANDARD_STAGE_HEIGHT_THRESHOLD
    || geometry.usableHeight < STANDARD_USABLE_HEIGHT_THRESHOLD;

  if (widthConstrained || heightConstrained) {
    return {
      effectivePreset: 'compact',
      downgraded: true,
      downgradeReason: 'small_stage',
    };
  }

  if (geometry.safeAreaCoverageRatio >= SAFE_AREA_PRESSURE_THRESHOLD) {
    return {
      effectivePreset: 'compact',
      downgraded: true,
      downgradeReason: 'safe_area_pressure',
    };
  }

  return {
    effectivePreset: 'standard',
    downgraded: false,
    downgradeReason: null,
  };
}

export function resolveWallStageGeometry({
  width = DEFAULT_STAGE_WIDTH,
  height = DEFAULT_STAGE_HEIGHT,
  showQr = false,
  showBranding = false,
  showSenderCredit = false,
  showFloatingCaption = false,
  preferredPreset = 'standard',
}: ResolveWallStageGeometryInput = {}): WallStageGeometry {
  const safeInsets = resolveWallStageInsets({
    showQr,
    showBranding,
    showSenderCredit,
    showFloatingCaption,
  });
  const usableWidth = Math.max(width - safeInsets.left - safeInsets.right, 320);
  const usableHeight = Math.max(height - safeInsets.top - safeInsets.bottom, 240);
  const safeAreaCoverageRatio = resolveOverlayFootprintArea({
    width,
    height,
    showQr,
    showBranding,
    showSenderCredit,
    showFloatingCaption,
  });
  const preset = resolveStageAwarePuzzlePreset(preferredPreset, {
    width,
    height,
    usableWidth,
    usableHeight,
    safeAreaCoverageRatio,
  });

  return {
    width,
    height,
    usableWidth,
    usableHeight,
    safeInsets,
    safeAreaCoverageRatio,
    ...preset,
  };
}

export function applyStageGeometryToWallSettings(
  settings: WallSettings,
  geometry: WallStageGeometry | null,
): WallSettings {
  if (!geometry || settings.layout !== 'puzzle') {
    return settings;
  }

  const currentPreset = settings.theme_config?.preset ?? 'standard';

  if (currentPreset === geometry.effectivePreset) {
    return settings;
  }

  return {
    ...settings,
    theme_config: {
      ...settings.theme_config,
      preset: geometry.effectivePreset,
    },
  };
}

export function useStageGeometry(
  stageRef: RefObject<HTMLElement | null>,
  {
    enabled = true,
    width = DEFAULT_STAGE_WIDTH,
    height = DEFAULT_STAGE_HEIGHT,
    showQr = false,
    showBranding = false,
    showSenderCredit = false,
    showFloatingCaption = false,
    preferredPreset = 'standard',
  }: UseStageGeometryOptions = {},
) {
  const [size, setSize] = useState(() => ({
    width,
    height,
  }));

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const element = stageRef.current;

    if (!element) {
      setSize((current) => (
        current.width === width && current.height === height
          ? current
          : { width, height }
      ));
      return;
    }

    const updateSize = () => {
      const rect = element.getBoundingClientRect();
      const nextWidth = rect.width > 0 ? rect.width : width;
      const nextHeight = rect.height > 0 ? rect.height : height;

      setSize((current) => (
        current.width === nextWidth && current.height === nextHeight
          ? current
          : { width: nextWidth, height: nextHeight }
      ));
    };

    updateSize();

    const observer = new ResizeObserver(() => {
      updateSize();
    });

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [enabled, height, stageRef, width]);

  return resolveWallStageGeometry({
    width: size.width,
    height: size.height,
    showQr,
    showBranding,
    showSenderCredit,
    showFloatingCaption,
    preferredPreset,
  });
}

export default useStageGeometry;
