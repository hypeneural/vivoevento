import { lazy, Suspense, useMemo, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { Gamepad2, ImagePlus, Pointer, Smartphone, Trophy } from "lucide-react";
import styles from "./InteractiveGamesSection.module.scss";
import { galleryImages } from "@/data/landing";

const PhaserGamesShowcase = lazy(() => import("./games/PhaserGamesShowcase"));

const gameModes = [
  {
    id: "puzzle",
    label: "Puzzle",
    title: "Quebra-cabeca com foto recem-enviada",
    text: "A foto do evento vira tabuleiro jogavel. O convidado toca nas pecas, reorganiza a imagem e sente novidade de verdade.",
    cta: "Experimentar puzzle com foto real",
  },
  {
    id: "memory",
    label: "Memoria",
    title: "Jogo da memoria com fotos reais do evento",
    text: "As cartas usam imagens do proprio evento e entregam uma dinamica rapida, perfeita para celular, fila de bar e mesa de convidados.",
    cta: "Experimentar memoria com fotos reais",
  },
] as const;

function PuzzlePreview({ imageSrc }: { imageSrc: string }) {
  return (
    <div className={styles.previewBoard} data-mode="puzzle">
      {Array.from({ length: 9 }).map((_, index) => {
        const row = Math.floor(index / 3);
        const column = index % 3;

        return (
          <div
            key={index}
            className={styles.previewTile}
            style={{
              backgroundImage: `url(${imageSrc})`,
              backgroundPosition: `${column * 50}% ${row * 50}%`,
            }}
          />
        );
      })}
    </div>
  );
}

function MemoryPreview({ imageSources }: { imageSources: string[] }) {
  return (
    <div className={styles.previewBoard} data-mode="memory">
      {imageSources.slice(0, 6).map((imageSrc, index) => (
        <button key={`${imageSrc}-${index}`} type="button" className={styles.memoryPreviewCard}>
          <img src={imageSrc} alt="" loading="lazy" decoding="async" />
          <span>{index % 2 === 0 ? "par ativo" : "toque para virar"}</span>
        </button>
      ))}
    </div>
  );
}

export default function InteractiveGamesSection() {
  const reduceMotion = useReducedMotion();
  const [activeMode, setActiveMode] = useState<(typeof gameModes)[number]["id"]>("puzzle");
  const [selectedImageIndex, setSelectedImageIndex] = useState(0);
  const [isDemoEnabled, setIsDemoEnabled] = useState(false);
  const gameImages = useMemo(() => galleryImages.slice(0, 6), []);
  const selectedImage = gameImages[selectedImageIndex] ?? gameImages[0];
  const activeModeDefinition = gameModes.find((mode) => mode.id === activeMode) ?? gameModes[0];

  const handleSelectImage = (index: number) => {
    setSelectedImageIndex(index);
    setIsDemoEnabled(false);
  };

  return (
    <section id="jogos" className={`section-shell ${styles.section}`}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Jogos interativos</span>
          <h2 className="section-title">Mais do que exibir: criar momentos.</h2>
          <p className="section-lead">
            O diferencial nao esta em prometer jogos. Esta em mostrar que a foto do evento entra no
            celular e vira uma dinamica jogavel de verdade, com resposta rapida e cara de produto premium.
          </p>

          <div className={styles.featureList}>
            <article>
              <ImagePlus size={18} />
              <div>
                <h3>Fotos reais como materia-prima do jogo</h3>
                <p>Nada de numeros abstratos. O tabuleiro nasce das imagens do proprio evento.</p>
              </div>
            </article>
            <article>
              <Pointer size={18} />
              <div>
                <h3>Interacao real no clique e no toque</h3>
                <p>O visitante experimenta a mecanica. Nao assiste uma animacao fingindo ser jogo.</p>
              </div>
            </article>
            <article>
              <Smartphone size={18} />
              <div>
                <h3>Phaser carregado sob intencao do usuario</h3>
                <p>O canvas pesado so sobe quando alguem decide jogar. A landing continua rapida.</p>
              </div>
            </article>
            <article>
              <Trophy size={18} />
              <div>
                <h3>Ranking e valor comercial mais tangivel</h3>
                <p>O visitante entende como isso engaja a festa e prolonga permanencia no evento.</p>
              </div>
            </article>
          </div>
        </div>

        <div className={styles.stageColumn}>
          <div className={styles.stageHeader}>
            <div>
              <span>Jogos com fotos do evento</span>
              <strong>Escolha uma imagem real e abra a demo</strong>
            </div>
            <div className={styles.stagePills}>
              <span>mobile-first</span>
              <span>sob clique</span>
              <span>sem audio</span>
            </div>
          </div>

          <div className={styles.photoPicker}>
            {gameImages.map((image, index) => (
              <button
                key={image.src}
                type="button"
                className={index === selectedImageIndex ? styles.activeImage : ""}
                onClick={() => handleSelectImage(index)}
              >
                <img src={image.src} alt={image.alt} loading="lazy" decoding="async" />
              </button>
            ))}
          </div>

          <div className={styles.modeTabs}>
            {gameModes.map((mode) => (
              <button
                key={mode.id}
                type="button"
                className={activeMode === mode.id ? styles.activeTab : ""}
                onClick={() => setActiveMode(mode.id)}
              >
                {activeMode === mode.id && !reduceMotion ? <motion.span layoutId="game-pill" className={styles.tabBackground} /> : null}
                <span>{mode.label}</span>
              </button>
            ))}
          </div>

          <AnimatePresence mode="wait">
            <motion.div
              key={`${activeMode}-${selectedImage.src}`}
              className={styles.stageInfo}
              initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: reduceMotion ? 0 : -12 }}
              transition={{ duration: reduceMotion ? 0.01 : 0.22 }}
            >
              <div>
                <strong>{activeModeDefinition.title}</strong>
                <p>{activeModeDefinition.text}</p>
              </div>

              <button className="button" data-variant="primary" type="button" onClick={() => setIsDemoEnabled(true)}>
                <Gamepad2 size={18} />
                {activeModeDefinition.cta}
              </button>
            </motion.div>
          </AnimatePresence>

          <div className={styles.stageFrame}>
            <div className={styles.stageMeta}>
              <span>{isDemoEnabled ? "demo jogavel ativa" : "toque no quadro para abrir a demo"}</span>
              <span>{selectedImage.alt}</span>
            </div>

            <div className={styles.phoneFrame}>
              {isDemoEnabled ? (
                <Suspense fallback={<div className={styles.loader}>Preparando fotos reais do evento...</div>}>
                  <PhaserGamesShowcase
                    key={selectedImage.src}
                    mode={activeMode}
                    className={styles.demoCanvas}
                    puzzleImageSrc={selectedImage.src}
                    memoryImageSrcs={gameImages.map((image) => image.src)}
                  />
                </Suspense>
              ) : (
                <button type="button" className={styles.previewShell} onClick={() => setIsDemoEnabled(true)}>
                  {activeMode === "puzzle" ? (
                    <PuzzlePreview imageSrc={selectedImage.src} />
                  ) : (
                    <MemoryPreview imageSources={gameImages.map((image) => image.src)} />
                  )}
                  <div className={styles.previewOverlay}>
                    <strong>Clique para abrir a demo jogavel</strong>
                    <span>Phaser so carrega depois da intencao do usuario.</span>
                  </div>
                </button>
              )}
            </div>

            <div className={styles.scorePanel}>
              <div>
                <span>O que o usuario ve</span>
                <strong>Foto do evento virando jogo de verdade, nao mock abstrato</strong>
              </div>
              <div>
                <span>O que o comercial vende</span>
                <strong>Engajamento ao vivo, ranking e experiencia mobile sem friccao</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
