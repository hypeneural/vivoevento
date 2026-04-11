import Phaser from 'phaser';

import type { PuzzleGameSettings } from '@/modules/play/types';

import { removeTextureKeys } from '../core/cleanup';
import { BasePlayScene } from '../core/BasePlayScene';
import {
  PUZZLE_PROMPT_TEXTURE_KEY,
  PUZZLE_SOURCE_TEXTURE_KEY,
  PUZZLE_UI_ASSETS,
  resolvePuzzleCoverAsset,
} from './config/puzzleAssets';
import { resolvePuzzleGrid } from './config/puzzleConfig';
import {
  buildPuzzleSceneLayout,
  createPuzzleBoardState,
  getRestoredPieceKeys,
} from './domain/PuzzleBoard';
import { PuzzleScore } from './domain/PuzzleScore';
import { PuzzlePieceFactory } from './factories/PuzzlePieceFactory';
import { PuzzleSlotFactory } from './factories/PuzzleSlotFactory';
import { PuzzleAudioSystem } from './systems/PuzzleAudioSystem';
import { PuzzleDragSystem } from './systems/PuzzleDragSystem';
import { PuzzleFeedbackSystem } from './systems/PuzzleFeedbackSystem';
import { PuzzlePlacementSystem } from './systems/PuzzlePlacementSystem';
import { PuzzleVictorySystem } from './systems/PuzzleVictorySystem';
import { PuzzleHudBridge } from './ui/PuzzleHudBridge';

export class PuzzleScene extends BasePlayScene<PuzzleGameSettings> {
  private pieceKeys: string[] = [];

  constructor() {
    super('PuzzleScene');
  }

  preload() {
    this.load.on('progress', (value: number) => {
      this.bridge.progress({ phase: 'loading', progress: value });
    });

    const cover = resolvePuzzleCoverAsset(this.payload.assets);

    if (cover?.url) {
      this.load.image(PUZZLE_SOURCE_TEXTURE_KEY, cover.url);
    }

    this.load.svg(PUZZLE_PROMPT_TEXTURE_KEY, PUZZLE_UI_ASSETS.promptTouch, { width: 64, height: 64 });
    PuzzleAudioSystem.preload(this);
  }

  create() {
    const hud = new PuzzleHudBridge(this.bridge);
    const cover = resolvePuzzleCoverAsset(this.payload.assets);

    if (!cover?.url || !this.textures.exists(PUZZLE_SOURCE_TEXTURE_KEY)) {
      this.add.text(34, 60, 'Nenhuma foto disponivel para montar o puzzle.', {
        color: '#ffffff',
        fontSize: '24px',
      });
      this.bridge.error('Nenhuma foto valida disponivel para montar o puzzle.');
      return;
    }

    const grid = resolvePuzzleGrid(this.payload.settings.gridSize);
    this.pieceKeys = PuzzlePieceFactory.createTextures(this, PUZZLE_SOURCE_TEXTURE_KEY, grid.rows, grid.cols);
    const layout = buildPuzzleSceneLayout(this, this.payload.settings);

    this.renderSceneChrome(layout, this.payload.settings.showReferenceImage ?? true);

    const restoredPieceKeys = getRestoredPieceKeys(this.payload.restore?.moves ?? []);
    const slots = PuzzleSlotFactory.create({
      scene: this,
      grid,
      layout,
      pieceKeys: this.pieceKeys,
    });
    const pieces = PuzzlePieceFactory.create({
      scene: this,
      grid,
      layout,
      slots,
      sessionSeed: this.payload.sessionSeed ?? this.payload.sessionUuid,
      restoredPieceKeys,
    });
    const board = createPuzzleBoardState(grid, layout, slots, pieces);
    const audio = new PuzzleAudioSystem(this);
    const feedback = new PuzzleFeedbackSystem(this);
    const score = new PuzzleScore();
    score.hydrateFromMoves(this.payload.restore?.moves ?? []);
    const placement = new PuzzlePlacementSystem(board, score, feedback, audio, this.payload.settings);
    const victory = new PuzzleVictorySystem(this, board, feedback, audio);
    const dragSystem = new PuzzleDragSystem({
      scene: this,
      board,
      placement,
      feedback,
      audio,
      victory,
      hud,
      getElapsedMs: () => this.elapsedMs(),
      onSolved: () => {
        hud.finish(score.buildResult(this.elapsedMs(), this.payload.settings));
      },
      emitProgress: (snapshot, phase = 'progress') => {
        hud.progress(snapshot, phase);
      },
    });

    dragSystem.bind();

    const initialProgress = score.buildProgress(board.totalPieces, board.placedCount, this.elapsedMs());
    hud.ready(initialProgress);

    this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
      dragSystem.destroy();
      removeTextureKeys(this, this.pieceKeys);
    });
  }

  private renderSceneChrome(
    layout: ReturnType<typeof buildPuzzleSceneLayout>,
    showReferenceImage: boolean,
  ) {
    this.add.text(layout.viewport.hudRect.x, layout.viewport.hudRect.y + 2, 'Puzzle do Evento', {
      color: '#ffffff',
      fontSize: '28px',
      fontStyle: 'bold',
    });
    this.add.text(layout.viewport.hudRect.x, layout.viewport.hudRect.y + 38, 'Arraste as pecas para montar a foto completa.', {
      color: 'rgba(255,255,255,0.75)',
      fontSize: '15px',
      wordWrap: { width: layout.viewport.hudRect.w },
    });

    if (showReferenceImage) {
      this.add.image(layout.viewport.hudRect.x + 50, layout.viewport.hudRect.y + 106, PUZZLE_SOURCE_TEXTURE_KEY)
        .setOrigin(0, 0.5)
        .setDisplaySize(64, 84)
        .setAlpha(0.86);
      this.add.text(layout.viewport.hudRect.x + 126, layout.viewport.hudRect.y + 88, 'Referencia', {
        color: '#ffffff',
        fontSize: '16px',
        fontStyle: 'bold',
      });
      this.add.text(layout.viewport.hudRect.x + 126, layout.viewport.hudRect.y + 110, 'Use a foto guia para encaixar as pecas no quadro.', {
        color: 'rgba(255,255,255,0.66)',
        fontSize: '13px',
        wordWrap: { width: layout.viewport.hudRect.w - 126 },
      });
    }

    this.add.rectangle(
      layout.boardX + layout.boardSize / 2,
      layout.boardY + layout.boardSize / 2,
      layout.boardSize + 18,
      layout.boardSize + 18,
      0xffffff,
      0.05,
    ).setStrokeStyle(1, 0xffffff, 0.06);

    this.add.rectangle(
      layout.trayRect.x + layout.trayRect.w / 2,
      layout.trayRect.y + layout.trayRect.h / 2,
      layout.trayRect.w,
      layout.trayRect.h,
      0x0f172a,
      0.62,
    ).setStrokeStyle(1, 0xffffff, 0.08);

    this.add.text(layout.trayRect.x + 18, layout.trayRect.y - 18, 'Bandeja de pecas', {
      color: 'rgba(255,255,255,0.72)',
      fontSize: '13px',
      fontStyle: 'bold',
    });

    if (this.textures.exists(PUZZLE_PROMPT_TEXTURE_KEY)) {
      this.add.image(layout.trayRect.x + layout.trayRect.w - 30, layout.trayRect.y - 12, PUZZLE_PROMPT_TEXTURE_KEY)
        .setDisplaySize(26, 26)
        .setAlpha(0.68);
    }

    if (showReferenceImage) {
      this.add.image(layout.boardX + layout.boardSize / 2, layout.boardY + layout.boardSize / 2, PUZZLE_SOURCE_TEXTURE_KEY)
        .setDisplaySize(layout.boardSize, layout.boardSize)
        .setAlpha(0.12);
    }
  }
}
