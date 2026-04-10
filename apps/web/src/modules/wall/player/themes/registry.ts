import { createElement } from 'react';
import type { ComponentType } from 'react';

import type { MediaSurfaceVideoControlProps } from '../components/MediaSurface';
import CarouselLayout from '../layouts/CarouselLayout';
import CinematicLayout from '../layouts/CinematicLayout';
import FullscreenLayout from '../layouts/FullscreenLayout';
import GalleryLayout from '../layouts/GalleryLayout';
import GridLayout from '../layouts/GridLayout';
import KenBurnsLayout from '../layouts/KenBurnsLayout';
import MosaicLayout from '../layouts/MosaicLayout';
import PolaroidLayout from '../layouts/PolaroidLayout';
import SpotlightLayout from '../layouts/SpotlightLayout';
import SplitLayout from '../layouts/SplitLayout';
import type { WallLayout, WallLayoutKind, WallRuntimeItem, WallSettings } from '../types';
import { getWallLayoutMotionTokens, type WallMotionTokens } from './motion';

export interface WallLayoutCapabilities {
  supportsVideoPlayback: boolean;
  supportsVideoPosterOnly: boolean;
  supportsMultiVideo: boolean;
  maxSimultaneousVideos: number;
  fallbackVideoLayout?: Exclude<WallLayout, 'auto'>;
  supportsSideThumbnails: boolean;
  supportsFloatingCaption: boolean;
  supportsRealtimeBurst: boolean;
  supportsThemeConfig: boolean;
}

export interface WallLayoutRendererProps {
  media: WallRuntimeItem;
  settings: WallSettings;
  reducedMotion?: boolean;
  videoControl?: MediaSurfaceVideoControlProps | null;
  slots?: (WallRuntimeItem | null)[];
  activeSlot?: number;
}

export interface WallLayoutDefinition {
  id: WallLayout;
  label: string;
  kind: WallLayoutKind;
  renderer: ComponentType<WallLayoutRendererProps>;
  capabilities: WallLayoutCapabilities;
  motion: WallMotionTokens;
  version: string;
}

const WALL_LAYOUT_VERSION = '2026-04-10';

const singleLayoutCapabilities = (
  overrides: Partial<WallLayoutCapabilities> = {},
): WallLayoutCapabilities => ({
  supportsVideoPlayback: true,
  supportsVideoPosterOnly: false,
  supportsMultiVideo: false,
  maxSimultaneousVideos: 1,
  fallbackVideoLayout: undefined,
  supportsSideThumbnails: true,
  supportsFloatingCaption: false,
  supportsRealtimeBurst: false,
  supportsThemeConfig: false,
  ...overrides,
});

const boardLayoutCapabilities = (
  overrides: Partial<WallLayoutCapabilities> = {},
): WallLayoutCapabilities => ({
  supportsVideoPlayback: false,
  supportsVideoPosterOnly: true,
  supportsMultiVideo: false,
  maxSimultaneousVideos: 0,
  fallbackVideoLayout: 'fullscreen',
  supportsSideThumbnails: false,
  supportsFloatingCaption: false,
  supportsRealtimeBurst: true,
  supportsThemeConfig: false,
  ...overrides,
});

type SingleLayoutComponent = ComponentType<{
  media: WallRuntimeItem;
  videoControl?: MediaSurfaceVideoControlProps | null;
}>;

function withSingleRenderer(
  Component: SingleLayoutComponent,
): ComponentType<WallLayoutRendererProps> {
  return function SingleLayoutRenderer({ media, videoControl }) {
    return createElement(Component, {
      media,
      videoControl,
    });
  };
}

function withKenBurnsRenderer(
  Component: ComponentType<{
    media: WallRuntimeItem;
    intervalMs?: number;
    reducedMotion?: boolean;
    videoControl?: MediaSurfaceVideoControlProps | null;
  }>,
): ComponentType<WallLayoutRendererProps> {
  return function KenBurnsRenderer({
    media,
    settings,
    reducedMotion = false,
    videoControl,
  }) {
    return createElement(Component, {
      media,
      intervalMs: settings.interval_ms,
      reducedMotion,
      videoControl,
    });
  };
}

function withBoardRenderer(
  Component: ComponentType<{
    items: (WallRuntimeItem | null)[];
    activeSlot?: number;
  }>,
): ComponentType<WallLayoutRendererProps> {
  return function BoardLayoutRenderer({ slots = [], activeSlot = 0 }) {
    return createElement(Component, {
      items: slots,
      activeSlot,
    });
  };
}

const PuzzleLayoutFallback = withSingleRenderer(FullscreenLayout);

function defineLayout(
  id: WallLayout,
  label: string,
  kind: WallLayoutKind,
  renderer: ComponentType<WallLayoutRendererProps>,
  capabilities: WallLayoutCapabilities,
): WallLayoutDefinition {
  return {
    id,
    label,
    kind,
    renderer,
    capabilities,
    motion: getWallLayoutMotionTokens(id),
    version: WALL_LAYOUT_VERSION,
  };
}

export const wallLayoutRegistry: Record<WallLayout, WallLayoutDefinition> = {
  auto: defineLayout(
    'auto',
    'Automatico',
    'single',
    withSingleRenderer(FullscreenLayout),
    singleLayoutCapabilities(),
  ),
  fullscreen: defineLayout(
    'fullscreen',
    'Tela cheia',
    'single',
    withSingleRenderer(FullscreenLayout),
    singleLayoutCapabilities({
      supportsFloatingCaption: true,
    }),
  ),
  polaroid: defineLayout(
    'polaroid',
    'Polaroid',
    'single',
    withSingleRenderer(PolaroidLayout),
    singleLayoutCapabilities(),
  ),
  split: defineLayout(
    'split',
    'Dividido',
    'single',
    withSingleRenderer(SplitLayout),
    singleLayoutCapabilities(),
  ),
  cinematic: defineLayout(
    'cinematic',
    'Cinematografico',
    'single',
    withSingleRenderer(CinematicLayout),
    singleLayoutCapabilities({
      supportsFloatingCaption: true,
    }),
  ),
  kenburns: defineLayout(
    'kenburns',
    'Ken Burns',
    'single',
    withKenBurnsRenderer(KenBurnsLayout),
    singleLayoutCapabilities(),
  ),
  spotlight: defineLayout(
    'spotlight',
    'Holofote',
    'single',
    withSingleRenderer(SpotlightLayout),
    singleLayoutCapabilities(),
  ),
  gallery: defineLayout(
    'gallery',
    'Galeria de arte',
    'single',
    withSingleRenderer(GalleryLayout),
    singleLayoutCapabilities(),
  ),
  carousel: defineLayout(
    'carousel',
    'Carrossel',
    'board',
    withBoardRenderer(CarouselLayout),
    boardLayoutCapabilities(),
  ),
  mosaic: defineLayout(
    'mosaic',
    'Mosaico',
    'board',
    withBoardRenderer(MosaicLayout),
    boardLayoutCapabilities(),
  ),
  grid: defineLayout(
    'grid',
    'Grade',
    'board',
    withBoardRenderer(GridLayout),
    boardLayoutCapabilities(),
  ),
  puzzle: defineLayout(
    'puzzle',
    'Quebra Cabeca',
    'board',
    PuzzleLayoutFallback,
    boardLayoutCapabilities({
      supportsVideoPlayback: false,
      supportsVideoPosterOnly: false,
      supportsMultiVideo: false,
      maxSimultaneousVideos: 0,
      fallbackVideoLayout: 'cinematic',
      supportsThemeConfig: true,
    }),
  ),
};

export function getWallLayoutDefinition(layout: WallLayout): WallLayoutDefinition {
  return wallLayoutRegistry[layout] ?? wallLayoutRegistry.fullscreen;
}

export function isBoardThemeLayout(layout: WallLayout): boolean {
  return getWallLayoutDefinition(layout).kind === 'board';
}
