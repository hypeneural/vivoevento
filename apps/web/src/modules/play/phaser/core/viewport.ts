import type Phaser from 'phaser';

export type SceneViewport = {
  width: number;
  height: number;
  isPortrait: boolean;
  safeTop: number;
  safeBottom: number;
  safeX: number;
  hudRect: { x: number; y: number; w: number; h: number };
  boardRect: { x: number; y: number; w: number; h: number };
};

export function resolveSceneViewport(
  scene: Phaser.Scene,
  options?: {
    hudHeight?: number;
    bottomInset?: number;
    sideInset?: number;
    topInset?: number;
  },
): SceneViewport {
  const width = scene.scale.width;
  const height = scene.scale.height;
  const safeX = options?.sideInset ?? 22;
  const safeTop = options?.topInset ?? 24;
  const safeBottom = options?.bottomInset ?? 28;
  const hudHeight = options?.hudHeight ?? 92;

  return {
    width,
    height,
    isPortrait: height >= width,
    safeTop,
    safeBottom,
    safeX,
    hudRect: {
      x: safeX,
      y: safeTop,
      w: width - safeX * 2,
      h: hudHeight,
    },
    boardRect: {
      x: safeX,
      y: safeTop + hudHeight + 12,
      w: width - safeX * 2,
      h: height - safeTop - safeBottom - hudHeight - 12,
    },
  };
}

export function fitGridCell(rect: SceneViewport['boardRect'], columns: number, rows: number, gap: number) {
  return Math.floor(Math.min(
    (rect.w - gap * (columns - 1)) / columns,
    (rect.h - gap * (rows - 1)) / rows,
  ));
}
