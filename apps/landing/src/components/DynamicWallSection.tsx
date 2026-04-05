import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { LayoutTemplate, MonitorPlay, Sparkles } from "lucide-react";
import styles from "./DynamicWallSection.module.scss";
import { eventImages } from "@/data/landing";

gsap.registerPlugin(useGSAP, ScrollTrigger);

const layouts = [
  {
    id: "fullscreen",
    label: "Fullscreen",
    title: "Imagem heroica com identidade do evento",
    text: "Perfeito para dar protagonismo às melhores fotos com leitura imediata.",
  },
  {
    id: "split",
    label: "Split",
    title: "Foto principal + contexto lateral",
    text: "Mistura destaque e volume sem perder elegância visual no telão.",
  },
  {
    id: "polaroid",
    label: "Polaroid",
    title: "Camadas mais afetivas e instagramáveis",
    text: "Ideal para casamentos, aniversários e eventos com clima mais emocional.",
  },
  {
    id: "cinematic",
    label: "Cinematic",
    title: "Transição mais premium e sensação de show",
    text: "O telão performa como parte do produto, não como slide estático.",
  },
] as const;

export default function DynamicWallSection() {
  const scope = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const [activeLayout, setActiveLayout] = useState<(typeof layouts)[number]["id"]>("fullscreen");

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setActiveLayout((current) => {
        const currentIndex = layouts.findIndex((layout) => layout.id === current);
        return layouts[(currentIndex + 1) % layouts.length].id;
      });
    }, 3200);

    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  useGSAP(
    () => {
      gsap.from("[data-wall-stage]", {
        opacity: 0,
        y: 36,
        clipPath: "inset(10% 10% 10% 10% round 32px)",
        duration: 1,
        ease: "power3.out",
        scrollTrigger: {
          trigger: scope.current,
          start: "top 72%",
        },
      });
    },
    { scope }
  );

  const activeDefinition = layouts.find((layout) => layout.id === activeLayout) || layouts[0];

  return (
    <section className={`section-shell ${styles.section}`} ref={scope}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Telão dinâmico</span>
          <h2 className="section-title">Um telao que nao apenas exibe. Ele performa.</h2>
          <p className="section-lead">
            Fullscreen, split, polaroid e cinematic mostram que o telão é só um dos módulos do
            ecossistema, mas um módulo com presença visual de palco.
          </p>

          <div className={styles.layoutTabs}>
            {layouts.map((layout) => (
              <button
                key={layout.id}
                type="button"
                className={activeLayout === layout.id ? styles.activeTab : ""}
                onClick={() => setActiveLayout(layout.id)}
              >
                {layout.label}
              </button>
            ))}
          </div>

          <div className={styles.layoutCard}>
            <strong>{activeDefinition.title}</strong>
            <p>{activeDefinition.text}</p>
          </div>
        </div>

        <div className={styles.stageColumn} data-wall-stage>
          <div className={styles.stageTopline}>
            <span>
              <MonitorPlay size={16} />
              atualização em tempo real
            </span>
            <span>
              <LayoutTemplate size={16} />
              quatro layouts premium
            </span>
          </div>

          <div className={styles.screenShell}>
            <AnimatePresence mode="wait">
              {activeLayout === "fullscreen" ? (
                <motion.div
                  key="fullscreen"
                  className={styles.fullscreen}
                  initial={{ opacity: 0, scale: reduceMotion ? 1 : 0.98 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: reduceMotion ? 1 : 1.02 }}
                >
                  <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
                  <div className={styles.overlayCopy}>
                    <strong>@mesa8</strong>
                    <span>foto heroica destacada no momento certo</span>
                  </div>
                </motion.div>
              ) : null}

              {activeLayout === "split" ? (
                <motion.div
                  key="split"
                  className={styles.split}
                  initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: reduceMotion ? 0 : -12 }}
                >
                  <div className={styles.primaryPanel}>
                    <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
                  </div>
                  <div className={styles.secondaryColumn}>
                    <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
                    <img src={eventImages.gallerySecondary.src} alt={eventImages.gallerySecondary.alt} />
                  </div>
                </motion.div>
              ) : null}

              {activeLayout === "polaroid" ? (
                <motion.div
                  key="polaroid"
                  className={styles.polaroid}
                  initial={{ opacity: 0, scale: reduceMotion ? 1 : 0.96 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: reduceMotion ? 1 : 1.02 }}
                >
                  <figure className={styles.polaroidCard}>
                    <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
                    <figcaption>casamento • pista cheia</figcaption>
                  </figure>
                  <figure className={styles.polaroidCard}>
                    <img src={eventImages.galleryThird.src} alt={eventImages.galleryThird.alt} />
                    <figcaption>show • energia ao vivo</figcaption>
                  </figure>
                  <figure className={styles.polaroidCard}>
                    <img src={eventImages.galleryFourth.src} alt={eventImages.galleryFourth.alt} />
                    <figcaption>corporativo • networking</figcaption>
                  </figure>
                </motion.div>
              ) : null}

              {activeLayout === "cinematic" ? (
                <motion.div
                  key="cinematic"
                  className={styles.cinematic}
                  initial={{ opacity: 0, clipPath: reduceMotion ? "inset(0 round 24px)" : "inset(20% 0 20% 0 round 24px)" }}
                  animate={{ opacity: 1, clipPath: "inset(0 round 24px)" }}
                  exit={{ opacity: 0 }}
                >
                  <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
                  <div className={styles.cinematicOverlay}>
                    <span>
                      <Sparkles size={16} />
                      cinematic mode
                    </span>
                    <strong>Transições suaves e leitura premium em tela cheia</strong>
                  </div>
                </motion.div>
              ) : null}
            </AnimatePresence>
          </div>
        </div>
      </div>
    </section>
  );
}
