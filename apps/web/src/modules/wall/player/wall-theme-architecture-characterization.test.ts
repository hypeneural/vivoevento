import fs from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

describe('wall theme architecture characterization', () => {
  it('does not yet expose puzzle or theme_config in the shared contract, backend enum, or manager options', () => {
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

    expect(sharedTypesSource).not.toContain("'puzzle'");
    expect(sharedTypesSource).not.toContain('theme_config');
    expect(backendEnumSource).not.toContain("case Puzzle = 'puzzle'");
    expect(managerConfigSource).not.toContain("value: 'puzzle'");
    expect(settingsRequestSource).not.toContain('theme_config');
  });

  it('still hardcodes 3 slots for multi-item layouts, imports framer-motion, and does not expose a formal theme-level motion system', () => {
    const layoutRendererSource = fs.readFileSync(
      path.resolve(__dirname, 'components/LayoutRenderer.tsx'),
      'utf8',
    );
    const playerRootSource = fs.readFileSync(
      path.resolve(__dirname, 'components/WallPlayerRoot.tsx'),
      'utf8',
    );

    expect(layoutRendererSource).toContain('const MULTI_ITEM_SLOT_COUNT = 3');
    expect(layoutRendererSource).toContain('AnimatePresence');
    expect(layoutRendererSource).toContain("from 'framer-motion'");
    expect(layoutRendererSource).not.toContain("from 'motion/react'");
    expect(layoutRendererSource).not.toContain('MotionConfig');
    expect(layoutRendererSource).not.toContain('LayoutGroup');
    expect(layoutRendererSource).not.toContain('layoutId');

    expect(playerRootSource).not.toContain('MotionConfig');
    expect(playerRootSource).not.toContain('useReducedMotion');
    expect(playerRootSource).not.toContain('LayoutGroup');
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

  it('already uses ResizeObserver in the manager preview and placeholderData in wall queries, but not prefetchQuery in the manager flow', () => {
    const previewSource = fs.readFileSync(
      path.resolve(__dirname, '../components/manager/stage/WallPreviewCanvas.tsx'),
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
    expect(queryOptionsSource).toContain('placeholderData: previousData');
    expect(queryOptionsSource).toContain('placeholderData: previousLiveSnapshot');
    expect(managerSource).not.toContain('prefetchQuery');
  });

  it('uses img.decode() only in proactive next-item preload, not in the generic wall asset probe pipeline', () => {
    const preloadSource = fs.readFileSync(
      path.resolve(__dirname, 'engine/preload.ts'),
      'utf8',
    );
    const cacheSource = fs.readFileSync(
      path.resolve(__dirname, 'engine/cache.ts'),
      'utf8',
    );

    expect(preloadSource).toContain('img.decode()');
    expect(cacheSource).not.toContain('.decode(');
    expect(cacheSource).toContain('image.onload');
  });
});
