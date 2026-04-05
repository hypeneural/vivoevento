import { useEffect, useRef } from "react";

type GameMode = "puzzle" | "memory";

type PhaserGamesShowcaseProps = {
  mode: GameMode;
  className?: string;
};

type PhaserNamespace = typeof import("phaser");

const palette = [0x73ecff, 0x89a5ff, 0xffbf72, 0x79f1ba, 0xff6f87];

async function createGame(target: HTMLDivElement, mode: GameMode) {
  const phaserModule = await import("phaser");
  const Phaser = (phaserModule.default ?? phaserModule) as PhaserNamespace;

  class MemoryScene extends Phaser.Scene {
    create() {
      const { width, height } = this.scale;
      this.cameras.main.setBackgroundColor("#09121b");

      this.add.rectangle(width / 2, height / 2, width, height, 0x09121b);
      this.add.text(24, 22, "Memoria ao vivo", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#f4f7ff",
      });
      this.add.text(24, 52, "cartas alimentadas pelas fotos do evento", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        color: "#8ea2c0",
      });

      const columns = 3;
      const rows = 4;
      const cardWidth = 96;
      const cardHeight = 110;
      const gap = 12;
      const startX = (width - columns * cardWidth - (columns - 1) * gap) / 2 + cardWidth / 2;
      const startY = 132;

      for (let row = 0; row < rows; row += 1) {
        for (let column = 0; column < columns; column += 1) {
          const x = startX + column * (cardWidth + gap);
          const y = startY + row * (cardHeight + gap);
          const card = this.add.container(x, y);
          const back = this.add.rectangle(0, 0, cardWidth, cardHeight, 0x152235, 1);
          back.setStrokeStyle(1, 0x24354f, 1);

          const front = this.add.rectangle(0, 0, cardWidth - 10, cardHeight - 10, palette[(row + column) % palette.length], 0.82);
          const accent = this.add.circle(0, -12, 18, 0xffffff, 0.22);
          const label = this.add.text(0, 18, `0${(row * columns + column) % 6 + 1}`, {
            fontFamily: "Space Grotesk, sans-serif",
            fontSize: "22px",
            fontStyle: "700",
            color: "#04111d",
          });
          label.setOrigin(0.5);

          const frontContainer = this.add.container(0, 0, [front, accent, label]);
          frontContainer.setScale(0.08, 1);

          card.add([back, frontContainer]);

          this.time.addEvent({
            delay: 900 + (row * columns + column) * 120,
            loop: true,
            callback: () => {
              this.tweens.add({
                targets: back,
                scaleX: 0.06,
                duration: 180,
                ease: "Sine.easeIn",
                yoyo: true,
              });

              this.tweens.add({
                targets: frontContainer,
                scaleX: 1,
                duration: 180,
                ease: "Sine.easeOut",
                yoyo: true,
                delay: 180,
                onYoyo: () => {
                  const nextColor = palette[(row + column + Math.floor(Math.random() * palette.length)) % palette.length];
                  front.fillColor = nextColor;
                },
              });
            },
          });
        }
      }

      const footer = this.add.text(width / 2, height - 38, "Mesa 4 assumiu a lideranca com 1.240 pts", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "13px",
        fontStyle: "700",
        color: "#73ecff",
      });
      footer.setOrigin(0.5);
    }
  }

  class PuzzleScene extends Phaser.Scene {
    create() {
      const { width, height } = this.scale;
      this.cameras.main.setBackgroundColor("#09121b");
      this.add.rectangle(width / 2, height / 2, width, height, 0x09121b);
      this.add.text(24, 22, "Puzzle ao vivo", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "22px",
        fontStyle: "700",
        color: "#f4f7ff",
      });
      this.add.text(24, 52, "foto recem-enviada vira desafio em segundos", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "12px",
        color: "#8ea2c0",
      });

      const size = 94;
      const gap = 10;
      const startX = (width - size * 3 - gap * 2) / 2 + size / 2;
      const startY = 146;
      const pieces: Array<{ box: PhaserNamespace.GameObjects.Container; slotX: number; slotY: number }> = [];

      for (let row = 0; row < 3; row += 1) {
        for (let column = 0; column < 3; column += 1) {
          const index = row * 3 + column;
          const x = startX + column * (size + gap);
          const y = startY + row * (size + gap);
          const container = this.add.container(x, y);
          const tile = this.add.rectangle(0, 0, size, size, palette[index % palette.length], 0.9);
          tile.setStrokeStyle(1, 0xffffff, 0.08);
          const overlay = this.add.rectangle(0, 0, size - 12, size - 12, 0xffffff, 0.08);
          const indexText = this.add.text(0, 0, `${index + 1}`, {
            fontFamily: "Space Grotesk, sans-serif",
            fontSize: "26px",
            fontStyle: "700",
            color: "#04111d",
          });
          indexText.setOrigin(0.5);
          container.add([tile, overlay, indexText]);
          pieces.push({ box: container, slotX: x, slotY: y });
        }
      }

      this.time.addEvent({
        delay: 1100,
        loop: true,
        callback: () => {
          const firstIndex = Math.floor(Math.random() * pieces.length);
          let secondIndex = Math.floor(Math.random() * pieces.length);

          while (secondIndex === firstIndex) {
            secondIndex = Math.floor(Math.random() * pieces.length);
          }

          const first = pieces[firstIndex];
          const second = pieces[secondIndex];
          const firstPosition = { x: first.box.x, y: first.box.y };

          this.tweens.add({
            targets: first.box,
            x: second.box.x,
            y: second.box.y,
            duration: 280,
            ease: "Quad.easeInOut",
          });

          this.tweens.add({
            targets: second.box,
            x: firstPosition.x,
            y: firstPosition.y,
            duration: 280,
            ease: "Quad.easeInOut",
          });
        },
      });

      const scoreTag = this.add.rectangle(width / 2, height - 50, 250, 48, 0x132237, 1);
      scoreTag.setStrokeStyle(1, 0x89a5ff, 0.18);
      const scoreText = this.add.text(width / 2, height - 50, "Combo x4 · foto nova liberada para puzzle", {
        fontFamily: "Manrope, sans-serif",
        fontSize: "14px",
        fontStyle: "700",
        color: "#ffbf72",
      });
      scoreText.setOrigin(0.5);
    }
  }

  return new Phaser.Game({
    type: Phaser.AUTO,
    parent: target,
    width: 390,
    height: 640,
    transparent: true,
    backgroundColor: "#09121b",
    scene: mode === "memory" ? MemoryScene : PuzzleScene,
    scale: {
      mode: Phaser.Scale.FIT,
      autoCenter: Phaser.Scale.CENTER_BOTH,
    },
  });
}

export default function PhaserGamesShowcase({ mode, className }: PhaserGamesShowcaseProps) {
  const mountRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const node = mountRef.current;
    if (!node) return undefined;

    let disposed = false;
    let game: PhaserNamespace.Game | null = null;

    createGame(node, mode).then((instance) => {
      if (disposed) {
        instance.destroy(true);
        return;
      }

      game = instance;
    });

    return () => {
      disposed = true;
      if (game) {
        game.destroy(true);
      }
      node.innerHTML = "";
    };
  }, [mode]);

  return <div ref={mountRef} className={className} />;
}
