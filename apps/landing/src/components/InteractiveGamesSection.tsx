import { lazy, Suspense, useEffect, useRef, useState } from "react";
import { motion, useReducedMotion } from "motion/react";
import { Gamepad2, ShieldCheck, Smartphone, Trophy } from "lucide-react";
import styles from "./InteractiveGamesSection.module.scss";
import { galleryImages } from "@/data/landing";

const PhaserGamesShowcase = lazy(() => import("./games/PhaserGamesShowcase"));

const gameModes = [
  {
    id: "puzzle",
    label: "Puzzle",
    title: "Quebra-cabeça com foto recém-enviada",
    text: "A foto do evento vira desafio em segundos, com feedback visual e sensação de novidade ao vivo.",
  },
  {
    id: "memory",
    label: "Memória",
    title: "Jogo da memória pensado para festa",
    text: "Partidas rápidas, visual premium e ranking claro para convidado jogar sem tutorial longo.",
  },
] as const;

export default function InteractiveGamesSection() {
  const [activeMode, setActiveMode] = useState<(typeof gameModes)[number]["id"]>("puzzle");
  const [shouldLoadDemo, setShouldLoadDemo] = useState(false);
  const stageRef = useRef<HTMLDivElement | null>(null);
  const reduceMotion = useReducedMotion();

  useEffect(() => {
    const node = stageRef.current;
    if (!node) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          setShouldLoadDemo(true);
          observer.disconnect();
        }
      },
      { threshold: 0.2 }
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  return (
    <section id="jogos" className={`section-shell ${styles.section}`}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Jogos interativos</span>
          <h2 className="section-title">Mais do que exibir: criar momentos.</h2>
          <p className="section-lead">
            Transforme fotos do evento em jogos que engajam convidados ao vivo. Puzzle, memória,
            ranking e feedback instantâneo fazem a experiência acontecer no celular, não só no telão.
          </p>

          <div className={styles.featureList}>
            <article>
              <Gamepad2 size={18} />
              <div>
                <h3>Puzzle e memória com fotos do evento</h3>
                <p>Os jogos nascem do mesmo fluxo que alimenta galeria e telão.</p>
              </div>
            </article>
            <article>
              <Trophy size={18} />
              <div>
                <h3>Ranking e pontuação anti-fraude</h3>
                <p>Competição rápida e legível, com sensação de ativação premium.</p>
              </div>
            </article>
            <article>
              <Smartphone size={18} />
              <div>
                <h3>Experiência mobile-first</h3>
                <p>Interface pensada para thumb zone e zero instalação.</p>
              </div>
            </article>
            <article>
              <ShieldCheck size={18} />
              <div>
                <h3>Operação confiável</h3>
                <p>A mecânica não depende de improviso no meio da festa.</p>
              </div>
            </article>
          </div>

          <div className={styles.sourceStrip}>
            {galleryImages.slice(0, 4).map((image) => (
              <img key={image.src} src={image.src} alt={image.alt} loading="lazy" />
            ))}
          </div>
        </div>

        <div className={styles.stageColumn}>
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

          <div className={styles.stageInfo}>
            {gameModes.map((mode) =>
              mode.id === activeMode ? (
                <motion.div
                  key={mode.id}
                  initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.24 }}
                >
                  <strong>{mode.title}</strong>
                  <p>{mode.text}</p>
                </motion.div>
              ) : null
            )}
          </div>

          <div className={styles.stageFrame} ref={stageRef}>
            <div className={styles.stageMeta}>
              <span>Demo interativa carregada sob demanda</span>
              <span>mobile-first</span>
            </div>

            <div className={styles.phoneFrame}>
              {shouldLoadDemo ? (
                <Suspense fallback={<div className={styles.loader}>Carregando demo do jogo...</div>}>
                  <PhaserGamesShowcase mode={activeMode} className={styles.demoCanvas} />
                </Suspense>
              ) : (
                <div className={styles.loader}>A demo ativa quando esta seção entra na tela.</div>
              )}
            </div>

            <div className={styles.scorePanel}>
              <div>
                <span>Ranking ao vivo</span>
                <strong>Mesa 4 lidera com 1.240 pts</strong>
              </div>
              <div>
                <span>Tempo de resposta</span>
                <strong>menos de 10s para entrar e jogar</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
