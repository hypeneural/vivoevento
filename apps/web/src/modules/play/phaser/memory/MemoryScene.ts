import Phaser from 'phaser';

import type { MemoryGameSettings } from '@/modules/play/types';

import { BasePlayScene } from '../core/BasePlayScene';
import { seededShuffle } from '../core/shuffle';
import { fitGridCell, resolveSceneViewport } from '../core/viewport';
import { buildMemoryResult } from './memoryResult';

type MemoryCard = {
  container: Phaser.GameObjects.Container;
  cover: Phaser.GameObjects.Rectangle;
  assetId: string;
  matched: boolean;
  flipped: boolean;
};

export class MemoryScene extends BasePlayScene<MemoryGameSettings> {
  private cards: MemoryCard[] = [];
  private opened: MemoryCard[] = [];
  private moves = 0;
  private mistakes = 0;
  private matched = 0;
  private totalPairs = 0;
  private locked = false;

  constructor() {
    super('MemoryScene');
  }

  preload() {
    this.load.on('progress', (value: number) => {
      this.bridge.progress({ phase: 'loading', progress: value });
    });

    this.payload.assets
      .filter((asset) => !!asset.url)
      .forEach((asset, index) => {
        this.load.image(`memory-card-${index}`, asset.url!);
      });
  }

  create() {
    const assets = this.payload.assets.filter((asset) => !!asset.url);
    const selected = assets.slice(0, Math.max(2, this.payload.settings.pairsCount));
    this.totalPairs = selected.length;

    if (selected.length < 2) {
      this.add.text(80, 80, 'Sem fotos suficientes para iniciar o jogo.', {
        color: '#ffffff',
        fontSize: '28px',
      });
      this.bridge.ready();
      return;
    }

    const deck = [...selected, ...selected]
      .map((asset, index) => ({
        localId: `${asset.id}-${index}`,
        assetId: asset.id,
        textureKey: `memory-card-${selected.findIndex((item) => item.id === asset.id)}`,
      }))
    ;
    const orderedDeck = seededShuffle(deck, `${this.payload.sessionSeed ?? this.payload.sessionUuid}:memory`);

    const viewport = resolveSceneViewport(this, {
      hudHeight: 92,
      bottomInset: 30,
      sideInset: 20,
      topInset: 24,
    });
    const columns = deck.length <= 12 ? 3 : 4;
    const rows = Math.ceil(deck.length / columns);
    const gap = deck.length <= 12 ? 14 : 10;
    const cardSize = Math.max(68, Math.min(110, fitGridCell(viewport.boardRect, columns, rows, gap)));
    const totalWidth = columns * cardSize + (columns - 1) * gap;
    const totalHeight = rows * cardSize + (rows - 1) * gap;
    const startX = viewport.boardRect.x + (viewport.boardRect.w - totalWidth) / 2 + cardSize / 2;
    const startY = viewport.boardRect.y + (viewport.boardRect.h - totalHeight) / 2 + cardSize / 2;

    this.add.text(viewport.hudRect.x, viewport.hudRect.y + 2, 'Jogo da Memoria', {
      color: '#ffffff',
      fontSize: '28px',
      fontStyle: 'bold',
    });
    this.add.text(viewport.hudRect.x, viewport.hudRect.y + 40, 'Toque nas cartas e encontre os pares do evento.', {
      color: 'rgba(255,255,255,0.75)',
      fontSize: '15px',
      wordWrap: { width: viewport.hudRect.w },
    });

    orderedDeck.forEach((entry, index) => {
      const col = index % columns;
      const row = Math.floor(index / columns);
      const x = startX + col * (cardSize + gap);
      const y = startY + row * (cardSize + gap);

      const shadow = this.add.rectangle(0, 6, cardSize + 8, cardSize + 8, 0x020617, 0.32);
      const frame = this.add.rectangle(0, 0, cardSize + 4, cardSize + 4, 0xffffff, 0.1);
      const image = this.add.image(0, 0, entry.textureKey).setDisplaySize(cardSize, cardSize);
      const cover = this.add.rectangle(0, 0, cardSize, cardSize, 0x0f172a, 0.92);
      const accent = this.add.rectangle(0, cardSize / 2 - 10, cardSize, 20, 0x22c55e, 0.8);

      const container = this.add.container(x, y, [shadow, frame, image, cover, accent]);
      container.setSize(cardSize, cardSize);
      container.setInteractive(
        new Phaser.Geom.Rectangle(-cardSize / 2, -cardSize / 2, cardSize, cardSize),
        Phaser.Geom.Rectangle.Contains,
      );

      const card: MemoryCard = {
        container,
        cover,
        assetId: entry.assetId,
        matched: false,
        flipped: false,
      };

      container.on('pointerdown', () => this.flipCard(card));
      this.cards.push(card);
    });

    const restored = this.applyRestoreState();

    if (!restored && (this.payload.settings.showPreviewSeconds ?? 0) > 0) {
      this.revealAllForPreview();
    }

    this.emitProgress(restored ? 'progress' : 'ready');
    this.bridge.ready();
  }

  private applyRestoreState() {
    const restoreMoves = this.payload.restore?.moves ?? [];

    if (restoreMoves.length === 0) {
      return false;
    }

    const matchedAssetIds = new Set(
      restoreMoves
        .filter((move) => move.type === 'match')
        .map((move) => String(move.payload?.assetId ?? ''))
        .filter(Boolean),
    );

    this.moves = restoreMoves.filter((move) => move.type === 'match' || move.type === 'mismatch').length;
    this.mistakes = restoreMoves.filter((move) => move.type === 'mismatch').length;

    this.cards.forEach((card) => {
      if (!matchedAssetIds.has(card.assetId)) {
        return;
      }

      card.matched = true;
      card.flipped = true;
      card.cover.setVisible(false);
      card.container.setAlpha(0.9);
    });

    this.matched = matchedAssetIds.size * 2;

    return true;
  }

  private revealAllForPreview() {
    this.cards.forEach((card) => {
      card.cover.setVisible(false);
      card.flipped = true;
    });

    this.time.delayedCall((this.payload.settings.showPreviewSeconds ?? 0) * 1000, () => {
      this.cards.forEach((card) => {
        if (!card.matched) {
          card.cover.setVisible(true);
          card.flipped = false;
        }
      });
    });
  }

  private flipCard(card: MemoryCard) {
    if (this.locked || card.matched || card.flipped) {
      return;
    }

    this.bridge.move({
      moveType: 'flip',
      payload: {
        assetId: card.assetId,
        openedCount: this.opened.length + 1,
      },
    });

    this.tweens.add({
      targets: card.container,
      scaleX: 0.92,
      scaleY: 0.92,
      duration: 70,
      yoyo: true,
    });

    card.cover.setVisible(false);
    card.flipped = true;
    this.opened.push(card);

    if (this.opened.length < 2) {
      return;
    }

    this.moves += 1;
    const [first, second] = this.opened;

    if (first.assetId === second.assetId) {
      first.matched = true;
      second.matched = true;
      this.matched += 2;
      this.opened = [];
      this.bridge.move({
        moveType: 'match',
        payload: {
          assetId: first.assetId,
          moveCount: this.moves,
          matchedPairs: this.matched / 2,
        },
      });
      this.emitProgress(this.matched === this.cards.length ? 'victory' : 'progress');

      if (this.matched === this.cards.length) {
        this.bridge.finish(buildMemoryResult({
          moves: this.moves,
          mistakes: this.mistakes,
          elapsedMs: this.elapsedMs(),
          settings: this.payload.settings,
        }));
      }

      return;
    }

    this.locked = true;
    this.mistakes += 1;
    this.bridge.move({
      moveType: 'mismatch',
      payload: {
        firstAssetId: first.assetId,
        secondAssetId: second.assetId,
        moveCount: this.moves,
        mistakes: this.mistakes,
      },
    });
    this.emitProgress('progress');

    this.time.delayedCall(this.payload.settings.flipBackDelayMs ?? 800, () => {
      first.cover.setVisible(true);
      second.cover.setVisible(true);
      first.flipped = false;
      second.flipped = false;
      this.opened = [];
      this.locked = false;
    });
  }

  private emitProgress(phase: 'ready' | 'progress' | 'victory' = 'progress') {
    const matchedPairs = Math.floor(this.matched / 2);
    const totalPairs = Math.max(1, this.totalPairs);
    const accuracy = this.moves > 0 ? (this.moves - this.mistakes) / this.moves : 1;
    const elapsedSeconds = Math.ceil(this.elapsedMs() / 1000);
    const scorePreview = Math.max(0, 1200 - (elapsedSeconds * 6) - (this.moves * 4) - (this.mistakes * 15));

    this.bridge.progress({
      phase,
      moves: this.moves,
      mistakes: this.mistakes,
      matchedCards: this.matched,
      matchedPairs,
      totalPairs,
      accuracy,
      scorePreview,
      completionRatio: totalPairs > 0 ? matchedPairs / totalPairs : 0,
    });
  }
}
