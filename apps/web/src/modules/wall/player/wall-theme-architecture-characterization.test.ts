import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('wall theme architecture characterization', () => {
  it('exposes the puzzle contract while keeping the static manager fallback gated and a synthetic puzzle fallback for persisted drafts', () => {
    const sharedTypesSource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../packages/shared-types/src/wall.ts'),
      'utf8',
    );
    const backendEnumSource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../apps/api/app/Modules/Wall/Enums/WallLayout.php'),
      'utf8',
    );
    const managerConfigSource = fs.readFileSync(
      path.resolve(__dirname, '../manager-config.ts'),
      'utf8',
    );
    const settingsRequestSource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../apps/api/app/Modules/Wall/Http/Requests/UpdateWallSettingsRequest.php'),
      'utf8',
    );
    const staticFallbackLayoutBlock = managerConfigSource.match(/export const fallbackOptions:[\s\S]*?layouts:\s*\[(.*?)\],\s*transitions:/s)?.[1] ?? '';

    expect(sharedTypesSource).toContain("'puzzle'");
    expect(sharedTypesSource).toContain('theme_config');
    expect(backendEnumSource).toContain("case Puzzle = 'puzzle'");
    expect(backendEnumSource).toContain('supports_theme_config');
    expect(managerConfigSource).toContain('export const PUZZLE_LAYOUT_FALLBACK_OPTION');
    expect(managerConfigSource).toContain('resolveManagerWallLayoutOption');
    expect(staticFallbackLayoutBlock).not.toContain("value: 'puzzle'");
    expect(settingsRequestSource).toContain('theme_config');
  });

  it('still hardcodes 3 slots for board layouts, but now uses a formal registry, board subsystem and theme-level motion contract', () => {
    const layoutRendererSource = fs.readFileSync(
      path.resolve(__dirname, 'components/LayoutRenderer.tsx'),
      'utf8',
    );
    const playerRootSource = fs.readFileSync(
      path.resolve(__dirname, 'components/WallPlayerRoot.tsx'),
      'utf8',
    );
    const registrySource = fs.readFileSync(
      path.resolve(__dirname, 'themes/registry.ts'),
      'utf8',
    );
    const motionSource = fs.readFileSync(
      path.resolve(__dirname, 'themes/motion.ts'),
      'utf8',
    );

    expect(layoutRendererSource).toContain('const MULTI_ITEM_SLOT_COUNT = 3');
    expect(layoutRendererSource).toContain('AnimatePresence');
    expect(layoutRendererSource).toContain("from 'framer-motion'");
    expect(layoutRendererSource).toContain('getWallLayoutDefinition');
    expect(layoutRendererSource).toContain('resolveLayoutTransition');
    expect(layoutRendererSource).toContain('useWallBoard');
    expect(layoutRendererSource).toContain('createBoardInstanceKey');
    expect(layoutRendererSource).not.toContain('useMultiSlot');
    expect(layoutRendererSource).not.toContain('function renderSingleLayout');
    expect(layoutRendererSource).not.toContain('function renderMultiLayout');

    expect(playerRootSource).toContain('MotionConfig');
    expect(playerRootSource).toContain('resolveWallMotionConfig');
    expect(playerRootSource).not.toContain("from 'motion/react'");

    expect(registrySource).toContain('interface WallLayoutDefinition');
    expect(registrySource).toContain("puzzle: defineLayout(");
    expect(registrySource).toContain("'board',");
    expect(registrySource).toContain('supportsThemeConfig: true');

    expect(motionSource).toContain('interface WallMotionTokens');
    expect(motionSource).toContain('visualDuration');
    expect(motionSource).toContain('reducedMotion');
  });

  it('still handles realtime through React hook state rather than a useSyncExternalStore bridge', () => {
    const realtimeSource = fs.readFileSync(
      path.resolve(__dirname, 'hooks/useWallRealtime.ts'),
      'utf8',
    );
    const playerSource = fs.readFileSync(
      path.resolve(__dirname, 'hooks/useWallPlayer.ts'),
      'utf8',
    );

    expect(realtimeSource).toContain("useState<WallConnectionStatus>('idle')");
    expect(realtimeSource).toContain('useEffect');
    expect(realtimeSource).not.toContain('useSyncExternalStore');

    expect(playerSource).not.toContain('startTransition');
    expect(playerSource).not.toContain('useTransition');
  });

  it('uses ResizeObserver in preview and live stage geometry, plus placeholderData and prefetchQuery in the manager flow', () => {
    const previewSource = fs.readFileSync(
      path.resolve(__dirname, '../components/manager/stage/WallPreviewCanvas.tsx'),
      'utf8',
    );
    const stageGeometrySource = fs.readFileSync(
      path.resolve(__dirname, 'hooks/useStageGeometry.ts'),
      'utf8',
    );
    const queryOptionsSource = fs.readFileSync(
      path.resolve(__dirname, '../wall-query-options.ts'),
      'utf8',
    );
    const managerSource = fs.readFileSync(
      path.resolve(__dirname, '../pages/EventWallManagerPage.tsx'),
      'utf8',
    );

    expect(previewSource).toContain('new ResizeObserver');
    expect(stageGeometrySource).toContain('new ResizeObserver');
    expect(queryOptionsSource).toContain('placeholderData: previousData');
    expect(queryOptionsSource).toContain('placeholderData: previousLiveSnapshot');
    expect(queryOptionsSource).toContain('simulation');
    expect(managerSource).toContain('prefetchQuery');
  });

  it('uses decode-based readiness both in proactive preload and in the generic wall asset probe pipeline, with a bounded warm window', () => {
    const preloadSource = fs.readFileSync(
      path.resolve(__dirname, 'engine/preload.ts'),
      'utf8',
    );
    const cacheSource = fs.readFileSync(
      path.resolve(__dirname, 'engine/cache.ts'),
      'utf8',
    );
    const readinessSource = fs.readFileSync(
      path.resolve(__dirname, 'engine/readiness.ts'),
      'utf8',
    );
    const engineSource = fs.readFileSync(
      path.resolve(__dirname, 'hooks/useWallEngine.ts'),
      'utf8',
    );

    expect(preloadSource).toContain('img.decode()');
    expect(cacheSource).toContain('loadWallImageReadiness');
    expect(readinessSource).toContain('await image.decode()');
    expect(engineSource).toContain('resolveWallPreloadPlan');
    expect(engineSource).toContain('runtimeBudget.maxConcurrentDecode');
  });

  it('exposes capability metadata and gates puzzle-incompatible manager controls', () => {
    const managerConfigSource = fs.readFileSync(
      path.resolve(__dirname, '../manager-config.ts'),
      'utf8',
    );
    const appearanceTabSource = fs.readFileSync(
      path.resolve(__dirname, '../components/manager/inspector/WallAppearanceTab.tsx'),
      'utf8',
    );
    const optionsControllerSource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../apps/api/app/Modules/Wall/Http/Controllers/EventWallController.php'),
      'utf8',
    );

    expect(managerConfigSource).toContain('WALL_VIDEO_MULTI_LAYOUT_OPTIONS');
    expect(managerConfigSource).toContain('supports_multi_video');
    expect(managerConfigSource).toContain('max_simultaneous_videos');
    expect(managerConfigSource).toContain('supports_theme_config');
    expect(managerConfigSource).not.toContain('posterOnlyMode');

    expect(appearanceTabSource).toContain('resolveManagerWallLayoutOption');
    expect(appearanceTabSource).toContain('layoutCapabilities');
    expect(appearanceTabSource).toContain('supportsThemeConfig');
    expect(appearanceTabSource).toContain('wall-video-multi-layout-locked');
    expect(appearanceTabSource).toContain('wall-side-thumbnails-switch');

    expect(optionsControllerSource).toContain("'layouts' => collect(WallLayout::enabledCases())->map");
    expect(optionsControllerSource).toContain('capabilities');
    expect(optionsControllerSource).toContain('defaults');
  });

  it('keeps the puzzle execution plan aligned with the final video policy during implementation', () => {
    const executionPlanSource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../docs/architecture/wall-puzzle-theme-execution-plan-2026-04-09.md'),
      'utf8',
    );
    const videoPolicySource = fs.readFileSync(
      path.resolve(__dirname, '../../../../../../docs/architecture/wall-puzzle-video-policy-and-theme-capabilities-2026-04-10.md'),
      'utf8',
    );

    expect(executionPlanSource).toContain('docs/architecture/wall-puzzle-video-policy-and-theme-capabilities-2026-04-10.md');
    expect(executionPlanSource).toContain('fallback para video existe');
    expect(executionPlanSource).toContain('video dentro do `puzzle`');
    expect(executionPlanSource).toContain('capabilities incompativeis ficam bloqueadas');

    expect(videoPolicySource).toContain('`video no puzzle = fallback single-item`');
    expect(videoPolicySource).toContain('`maxSimultaneousVideos default = 1`');
    expect(videoPolicySource).toContain('`multi-video no puzzle = fora da v1`');
  });
});
