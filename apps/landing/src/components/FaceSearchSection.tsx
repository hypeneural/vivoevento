import { useEffect, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { CheckCircle2, ScanFace } from "lucide-react";
import styles from "./FaceSearchSection.module.scss";
import { eventImages } from "@/data/landing";
import LazyRivePanel from "@/components/rive/LazyRivePanel";

const matches = [
  { src: eventImages.faceMatchOne.src, alt: eventImages.faceMatchOne.alt, score: "98.4%" },
  { src: eventImages.faceMatchTwo.src, alt: eventImages.faceMatchTwo.alt, score: "96.1%" },
  { src: eventImages.faceMatchThree.src, alt: eventImages.faceMatchThree.alt, score: "94.8%" },
];

export default function FaceSearchSection() {
  const [activeIndex, setActiveIndex] = useState(0);
  const reduceMotion = useReducedMotion();

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setActiveIndex((current) => (current + 1) % matches.length);
    }, 2800);

    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Busca facial com IA</span>
          <h2 className="section-title">Encontre quem importa. Em segundos.</h2>
          <p className="section-lead">
            Uma selfie. E as melhores memórias aparecem. A busca facial transforma o pós-evento em
            experiência premium, rápida e muito mais tangível para o convidado.
          </p>

          <ul className={styles.bullets}>
            <li>
              <CheckCircle2 size={18} />
              O convidado envia uma selfie e a busca começa sem fricção.
            </li>
            <li>
              <CheckCircle2 size={18} />
              O sistema procura os matches dentro da base do próprio evento.
            </li>
            <li>
              <CheckCircle2 size={18} />
              As memórias retornam organizadas para baixar e compartilhar.
            </li>
          </ul>
        </div>

        <div className={styles.stageColumn}>
          <LazyRivePanel
            src="/rive/face-search.riv"
            stateMachines="FaceSearch"
            className={styles.riveWrapper}
            fallback={
              <div className={styles.fallbackDemo}>
                <div className={styles.selfieCard}>
                  <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
                  <div className={styles.selfieOverlay}>
                    <div className={styles.boundingBox}>
                      <span />
                      <span />
                      <span />
                      <span />
                    </div>
                    <div className={styles.selfieTag}>
                      <ScanFace size={16} />
                      selfie enviada
                    </div>
                  </div>
                </div>

                <div className={styles.resultsPanel}>
                  <div className={styles.resultsHeader}>
                    <span>buscando na base do evento</span>
                    <strong>matches encontrados</strong>
                  </div>

                  <AnimatePresence mode="wait">
                    <motion.div
                      key={activeIndex}
                      className={styles.matchFeature}
                      initial={{ opacity: 0, y: reduceMotion ? 0 : 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: reduceMotion ? 0 : -10 }}
                    >
                      <img src={matches[activeIndex].src} alt={matches[activeIndex].alt} />
                      <div className={styles.matchLabel}>
                        <strong>{matches[activeIndex].score} match</strong>
                        <span>resultado destacado agora</span>
                      </div>
                    </motion.div>
                  </AnimatePresence>

                  <div className={styles.matchGrid}>
                    {matches.map((match, index) => (
                      <button
                        key={match.src}
                        type="button"
                        className={index === activeIndex ? styles.activeThumb : ""}
                        onClick={() => setActiveIndex(index)}
                      >
                        <img src={match.src} alt={match.alt} loading="lazy" />
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            }
          />
        </div>
      </div>
    </section>
  );
}
