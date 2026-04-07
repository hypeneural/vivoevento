import { useEffect, useRef } from "react";
import { prepareGameAssets } from "./gameAssetPipeline";

type GameMode = "puzzle" | "memory";

type PhaserGamesShowcaseProps = {
  mode: GameMode;
  className?: string;
  puzzleImageSrc: string;
  memoryImageSrcs: string[];
};

type PhaserNamespace = typeof import("phaser");

type ShowcaseController = {
  setMode: (mode: GameMode) => void;
  destroy: () => void;
};

type PreparedAssets = Awaited<ReturnType<typeof prepareGameAssets>>;

type PuzzlePiece = {
  correctIndex: number;
  currentIndex: number;
  container: InstanceType<PhaserNamespace["GameObjects"]["Container"]>;
  outline: InstanceType<PhaserNamespace["GameObjects"]["Rectangle"]>;
};

type MemoryCard = {
  pairId: number;
  box: InstanceType<PhaserNamespace["GameObjects"]["Container"]>;
  back: InstanceType<PhaserNamespace["GameObjects"]["Rectangle"]>;
  face: InstanceType<PhaserNamespace["GameObjects"]["Container"]>;
  outline: InstanceType<PhaserNamespace["GameObjects"]["Rectangle"]>;
  faceUp: boolean;
  matched: boolean;
};

let phaserPromise: Promise<PhaserNamespace> | null = null;

async function loadPhaser() {
  if (!phaserPromise) {
    phaserPromise = import("phaser").then((phaserModule) => (phaserModule.default ?? phaserModule) as PhaserNamespace);
  }

  return phaserPromise;
}

function createTextureKey(prefix: string, assetKey: string, index: number) {
  return `${prefix}-${assetKey}-${index}`;
}

function createAssetKey(puzzleImageSrc: string, memoryImageSrcs: string[]) {
  return `${puzzleImageSrc}|${memoryImageSrcs.join("|")}`.replace(/[^a-z0-9]+/gi, "-").slice(0, 80);
}

async function createGame(
  target: HTMLDivElement,
  initialMode: GameMode,
  puzzleImageSrc: string,
  memoryImageSrcs: string[]
): Promise<ShowcaseController> {
  const Phaser = await loadPhaser();
  const preparedAssets = await prepareGameAssets(puzzleImageSrc, memoryImageSrcs);
  const assetKey = createAssetKey(puzzleImageSrc, memoryImageSrcs);

  class ShowcaseScene extends Phaser.Scene {
    private currentMode: GameMode;
    private readonly assetBundle: PreparedAssets;
    private readonly textureKeyPrefix: string;

    constructor(mode: GameMode, assets: PreparedAssets, keyPrefix: string) {
      super("ShowcaseScene");
      this.currentMode = mode;
      this.assetBundle = assets;
      this.textureKeyPrefix = keyPrefix;
    }

    preload() {
      const coverKey = `${this.textureKeyPrefix}-cover`;

      if (!this.textures.exists(coverKey)) {
        this.load.image(coverKey, this.assetBundle.cover);
      }

      this.assetBundle.puzzleTiles.forEach((tile, index) => {
        const textureKey = createTextureKey("puzzle", this.textureKeyPrefix, index);

        if (!this.textures.exists(textureKey)) {
          this.load.image(textureKey, tile);
        }
      });

      this.assetBundle.memoryFaces.forEach((face, index) => {
        const textureKey = createTextureKey("memory", this.textureKeyPrefix, index);

        if (!this.textures.exists(textureKey)) {
          this.load.image(textureKey, face);
        }
      });
    }

    setMode(mode: GameMode) {
      if (this.currentMode === mode) return;
      this.currentMode = mode;
      this.scene.restart();
    }

    create() {
      this.cameras.main.setBackgroundColor("#09121b");
      this.add.rectangle(195, 320, 390, 640, 0x09121b, 1);

      if (this.currentMode === "memory") {
        this.renderMemory();
        return;
      }

      this.renderPuzzle();
    }

    private renderTopMeta(title: string, subtitle: string) {
      this.add.text(24, 24, title, {
        fontFamily: "Manrope, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#f4f7ff",
      });

      this.add.text(24, 54, subtitle, {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        color: "#8ea2c0",
      });
    }

    private renderPuzzle() {
      this.renderTopMeta("Puzzle com foto real", "toque em duas pecas para trocar de lugar");

      const cover = this.add.image(195, 290, `${this.textureKeyPrefix}-cover`);
      cover.setDisplaySize(304, 304);
      cover.setAlpha(0.14);

      const board = this.add.rectangle(195, 290, 318, 318, 0x0d1727, 1);
      board.setStrokeStyle(1, 0x1e3149, 1);

      const tileSize = 92;
      const gap = 8;
      const originX = 195 - tileSize - gap;
      const originY = 290 - tileSize - gap;
      const slotPositions = Array.from({ length: 9 }).map((_, index) => ({
        x: originX + (index % 3) * (tileSize + gap),
        y: originY + Math.floor(index / 3) * (tileSize + gap),
      }));

      slotPositions.forEach((position) => {
        const slot = this.add.rectangle(position.x, position.y, tileSize, tileSize, 0x122035, 0.76);
        slot.setStrokeStyle(1, 0x26364f, 1);
      });

      let order = Phaser.Utils.Array.NumberArray(0, 8);
      order = Phaser.Utils.Array.Shuffle(order);

      while (order.every((pieceIndex, index) => pieceIndex === index)) {
        order = Phaser.Utils.Array.Shuffle([...order]);
      }

      const pieces: PuzzlePiece[] = [];
      let selectedPiece: PuzzlePiece | null = null;
      let moves = 0;

      const movesValue = this.add.text(24, 602, "0", {
        fontFamily: "Space Grotesk, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#73ecff",
      });

      this.add.text(24, 584, "Movimentos", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      });

      const statusValue = this.add.text(146, 602, "Monte a foto", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        fontStyle: "700",
        color: "#ffbf72",
      });

      this.add.text(146, 584, "Status", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      });

      const syncValue = this.add.text(360, 602, "QR + galeria", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        fontStyle: "700",
        color: "#79f1ba",
      });

      this.add.text(360, 584, "Origem", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      }).setOrigin(1, 0);

      const updateSelection = (piece: PuzzlePiece | null) => {
        pieces.forEach((entry) => {
          entry.outline.setStrokeStyle(2, entry === piece ? 0x73ecff : 0xffffff, entry === piece ? 0.96 : 0.08);
        });
      };

      const checkSolved = () => {
        const solved = pieces.every((piece) => piece.currentIndex === piece.correctIndex);

        if (!solved) {
          return;
        }

        statusValue.setText("Foto montada");
        statusValue.setColor("#79f1ba");

        this.tweens.add({
          targets: pieces.map((piece) => piece.container),
          scale: 1.04,
          duration: 180,
          yoyo: true,
          ease: "Quad.easeOut",
          stagger: 28,
        });
      };

      const swapPieces = (first: PuzzlePiece, second: PuzzlePiece) => {
        const firstTarget = slotPositions[first.currentIndex];
        const secondTarget = slotPositions[second.currentIndex];
        const firstSlot = first.currentIndex;

        first.currentIndex = second.currentIndex;
        second.currentIndex = firstSlot;

        this.tweens.add({
          targets: first.container,
          x: secondTarget.x,
          y: secondTarget.y,
          duration: 180,
          ease: "Quad.easeInOut",
        });

        this.tweens.add({
          targets: second.container,
          x: firstTarget.x,
          y: firstTarget.y,
          duration: 180,
          ease: "Quad.easeInOut",
          onComplete: () => checkSolved(),
        });
      };

      order.forEach((pieceIndex, slotIndex) => {
        const position = slotPositions[slotIndex];
        const container = this.add.container(position.x, position.y);
        const shell = this.add.rectangle(0, 0, tileSize, tileSize, 0x132237, 0.98);
        const image = this.add.image(0, 0, createTextureKey("puzzle", this.textureKeyPrefix, pieceIndex));
        image.setDisplaySize(tileSize - 8, tileSize - 8);

        const outline = this.add.rectangle(0, 0, tileSize, tileSize, 0xffffff, 0);
        outline.setStrokeStyle(2, 0xffffff, 0.08);

        container.add([shell, image, outline]);
        container.setSize(tileSize, tileSize);
        container.setInteractive({ useHandCursor: true });

        const piece: PuzzlePiece = {
          correctIndex: pieceIndex,
          currentIndex: slotIndex,
          container,
          outline,
        };

        container.on("pointerdown", () => {
          if (pieces.every((entry) => entry.currentIndex === entry.correctIndex)) {
            return;
          }

          if (!selectedPiece) {
            selectedPiece = piece;
            updateSelection(piece);
            statusValue.setText("Escolha a segunda peca");
            statusValue.setColor("#ffbf72");
            return;
          }

          if (selectedPiece === piece) {
            selectedPiece = null;
            updateSelection(null);
            statusValue.setText("Monte a foto");
            statusValue.setColor("#ffbf72");
            return;
          }

          swapPieces(selectedPiece, piece);
          selectedPiece = null;
          updateSelection(null);
          moves += 1;
          movesValue.setText(String(moves));
          statusValue.setText("Troca aplicada");
          statusValue.setColor("#73ecff");
        });

        pieces.push(piece);
      });

      const footer = this.add.rectangle(195, 548, 342, 46, 0x132237, 1);
      footer.setStrokeStyle(1, 0x273751, 1);

      const footerText = this.add.text(195, 548, "A foto do evento vira jogo de verdade no celular", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "13px",
        fontStyle: "700",
        color: "#f4f7ff",
      });
      footerText.setOrigin(0.5);

      footer.setDepth(-1);
      footerText.setDepth(2);
      board.setDepth(-2);
      cover.setDepth(-3);
      syncValue.setOrigin(1, 0);
      this.add.text(360, 36, "ranking ao vivo", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      }).setOrigin(1, 0);
      this.add.text(360, 50, "Mesa 4 · 1.240 pts", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        fontStyle: "700",
        color: "#f4f7ff",
      }).setOrigin(1, 0);
    }

    private renderMemory() {
      this.renderTopMeta("Memoria com fotos reais", "toque para virar e encontrar os pares do evento");

      let moves = 0;
      let matchedPairs = 0;
      let lockBoard = false;
      let openCards: MemoryCard[] = [];

      const pairIds = Phaser.Utils.Array.Shuffle([
        0, 0,
        1, 1,
        2, 2,
        3, 3,
        4, 4,
        5, 5,
      ]);

      this.add.text(24, 584, "Pares", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      });
      const pairsValue = this.add.text(24, 602, "0/6", {
        fontFamily: "Space Grotesk, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#79f1ba",
      });

      this.add.text(124, 584, "Jogadas", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      });
      const movesValue = this.add.text(124, 602, "0", {
        fontFamily: "Space Grotesk, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#73ecff",
      });

      this.add.text(232, 584, "Status", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      });
      const statusValue = this.add.text(232, 602, "Toque em duas cartas", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        fontStyle: "700",
        color: "#ffbf72",
      });

      const cardWidth = 86;
      const cardHeight = 102;
      const gap = 10;
      const startX = 195 - cardWidth - gap;
      const startY = 154;
      const cards: MemoryCard[] = [];

      const flipToFace = (card: MemoryCard, callback?: () => void) => {
        this.tweens.add({
          targets: [card.back, card.outline],
          scaleX: 0.08,
          duration: 120,
          ease: "Sine.easeIn",
          onComplete: () => {
            card.back.setVisible(false);
            card.outline.setVisible(false);
            card.face.setVisible(true);
            this.tweens.add({
              targets: card.face,
              scaleX: 1,
              duration: 140,
              ease: "Sine.easeOut",
              onComplete: callback,
            });
          },
        });
      };

      const flipToBack = (card: MemoryCard, callback?: () => void) => {
        this.tweens.add({
          targets: card.face,
          scaleX: 0.08,
          duration: 120,
          ease: "Sine.easeIn",
          onComplete: () => {
            card.face.setVisible(false);
            card.back.setVisible(true);
            card.outline.setVisible(true);
            this.tweens.add({
              targets: [card.back, card.outline],
              scaleX: 1,
              duration: 140,
              ease: "Sine.easeOut",
              onComplete: callback,
            });
          },
        });
      };

      const finishIfReady = () => {
        if (matchedPairs !== 6) {
          return;
        }

        statusValue.setText("Ranking liberado");
        statusValue.setColor("#79f1ba");

        this.tweens.add({
          targets: cards.map((card) => card.box),
          y: "-=4",
          duration: 180,
          ease: "Quad.easeOut",
          yoyo: true,
          stagger: 24,
        });
      };

      pairIds.forEach((pairId, index) => {
        const column = index % 3;
        const row = Math.floor(index / 3);
        const x = startX + column * (cardWidth + gap);
        const y = startY + row * (cardHeight + gap);
        const box = this.add.container(x, y);
        const back = this.add.rectangle(0, 0, cardWidth, cardHeight, 0x152235, 1);
        back.setStrokeStyle(1, 0x24354f, 1);

        const backGlow = this.add.rectangle(0, 0, cardWidth - 10, cardHeight - 10, 0x1b2d44, 1);
        const backLabel = this.add.text(0, 0, "EV", {
          fontFamily: "Space Grotesk, sans-serif",
          fontSize: "22px",
          fontStyle: "700",
          color: "#73ecff",
        });
        backLabel.setOrigin(0.5);
        const faceImage = this.add.image(0, -6, createTextureKey("memory", this.textureKeyPrefix, pairId));
        faceImage.setDisplaySize(cardWidth - 10, cardHeight - 28);
        const faceStrip = this.add.rectangle(0, 32, cardWidth - 10, 18, 0x09121b, 0.92);
        const faceLabel = this.add.text(0, 32, `match 0${pairId + 1}`, {
          fontFamily: "Manrope, sans-serif",
          fontSize: "11px",
          fontStyle: "700",
          color: "#f4f7ff",
        });
        faceLabel.setOrigin(0.5);
        const face = this.add.container(0, 0, [faceImage, faceStrip, faceLabel]);
        face.setVisible(false);
        face.setScale(0.08, 1);

        const outline = this.add.rectangle(0, 0, cardWidth, cardHeight, 0xffffff, 0);
        outline.setStrokeStyle(1, 0xffffff, 0.08);

        box.add([back, backGlow, backLabel, face, outline]);
        box.setSize(cardWidth, cardHeight);
        box.setInteractive({ useHandCursor: true });

        const card: MemoryCard = {
          pairId,
          box,
          back,
          face,
          outline,
          faceUp: false,
          matched: false,
        };

        box.on("pointerdown", () => {
          if (lockBoard || card.faceUp || card.matched) {
            return;
          }

          card.faceUp = true;
          flipToFace(card, () => {
            openCards = [...openCards, card];

            if (openCards.length < 2) {
              statusValue.setText("Procure o segundo par");
              statusValue.setColor("#ffbf72");
              return;
            }

            moves += 1;
            movesValue.setText(String(moves));
            lockBoard = true;

            const [first, second] = openCards;

            if (first.pairId === second.pairId) {
              first.matched = true;
              second.matched = true;
              matchedPairs += 1;
              pairsValue.setText(`${matchedPairs}/6`);
              statusValue.setText("Par encontrado");
              statusValue.setColor("#79f1ba");
              openCards = [];
              lockBoard = false;
              finishIfReady();
              return;
            }

            statusValue.setText("Nao combinou");
            statusValue.setColor("#ff6f87");

            this.time.delayedCall(560, () => {
              flipToBack(first);
              flipToBack(second, () => {
                first.faceUp = false;
                second.faceUp = false;
                openCards = [];
                lockBoard = false;
                statusValue.setText("Toque em duas cartas");
                statusValue.setColor("#ffbf72");
              });
            });
          });
        });

        cards.push(card);
      });

      const footer = this.add.rectangle(195, 548, 342, 46, 0x132237, 1);
      footer.setStrokeStyle(1, 0x273751, 1);

      const footerText = this.add.text(195, 548, "Memoria rapida, visual premium e fotos do proprio evento", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "13px",
        fontStyle: "700",
        color: "#f4f7ff",
      });
      footerText.setOrigin(0.5);

      footer.setDepth(-1);
      footerText.setDepth(2);
      this.add.text(360, 36, "ranking ao vivo", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "11px",
        fontStyle: "700",
        color: "#8ea2c0",
      }).setOrigin(1, 0);
      this.add.text(360, 50, "Convidados jogando no celular", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        fontStyle: "700",
        color: "#f4f7ff",
      }).setOrigin(1, 0);
    }
  }

  const scene = new ShowcaseScene(initialMode, preparedAssets, assetKey);
  const game = new Phaser.Game({
    type: Phaser.AUTO,
    parent: target,
    width: 390,
    height: 640,
    backgroundColor: "#09121b",
    transparent: false,
    resolution: Math.min(window.devicePixelRatio || 1, 2),
    audio: {
      noAudio: true,
    },
    banner: false,
    disableContextMenu: true,
    autoFocus: false,
    scene: [scene],
    scale: {
      mode: Phaser.Scale.FIT,
      autoCenter: Phaser.Scale.CENTER_BOTH,
    },
    input: {
      activePointers: 1,
    },
  });

  return {
    setMode: (mode) => scene.setMode(mode),
    destroy: () => game.destroy(true),
  };
}

export default function PhaserGamesShowcase({
  mode,
  className,
  puzzleImageSrc,
  memoryImageSrcs,
}: PhaserGamesShowcaseProps) {
  const mountRef = useRef<HTMLDivElement | null>(null);
  const controllerRef = useRef<ShowcaseController | null>(null);
  const creatingRef = useRef(false);

  useEffect(() => {
    const node = mountRef.current;
    if (!node || controllerRef.current || creatingRef.current) {
      return undefined;
    }

    let disposed = false;
    creatingRef.current = true;

    createGame(node, mode, puzzleImageSrc, memoryImageSrcs).then((controller) => {
      creatingRef.current = false;

      if (disposed) {
        controller.destroy();
        return;
      }

      controllerRef.current = controller;
    });

    return () => {
      disposed = true;
      creatingRef.current = false;

      if (controllerRef.current) {
        controllerRef.current.destroy();
        controllerRef.current = null;
      }

      node.innerHTML = "";
    };
  }, [memoryImageSrcs, mode, puzzleImageSrc]);

  useEffect(() => {
    controllerRef.current?.setMode(mode);
  }, [mode]);

  return <div ref={mountRef} className={className} />;
}
