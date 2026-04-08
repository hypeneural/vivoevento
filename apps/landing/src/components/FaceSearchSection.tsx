import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { CheckCircle2, ScanFace } from "lucide-react";
import styles from "./FaceSearchSection.module.scss";
import { eventImages } from "@/data/landing";
import LazyRivePanel from "@/components/rive/LazyRivePanel";

const matches = [
  {
    src: eventImages.faceMatchOne.src,
    alt: eventImages.faceMatchOne.alt,
    score: "98%",
    detail: "Momento perfeito na pista de dança capturado e entregue automaticamente",
  },
  {
    src: eventImages.faceMatchTwo.src,
    alt: eventImages.faceMatchTwo.alt,
    score: "96%",
    detail: "Mais uma memória especial encontrada na galeria do evento",
  },
  {
    src: eventImages.faceMatchThree.src,
    alt: eventImages.faceMatchThree.alt,
    score: "95%",
    detail: "Selfie em grupo identificada e pronta para compartilhar",
  },
];

// Facial landmark nodes calibrated to the Unsplash selfie model
// photo-1494790108377 displayed at object-position: center 18%
const faceNodes = [
  // Eyes — outer corners
  { x: "36%", y: "34%" },
  { x: "62%", y: "33%" },
  // Eyes — inner corners
  { x: "44%", y: "35%" },
  { x: "54%", y: "34%" },
  // Eyebrows — arches
  { x: "38%", y: "29%" },
  { x: "60%", y: "28%" },
  // Nose — bridge
  { x: "49%", y: "38%" },
  // Nose — tip
  { x: "49%", y: "46%" },
  // Nose — wings
  { x: "44%", y: "47%" },
  { x: "54%", y: "47%" },
  // Mouth — corners
  { x: "41%", y: "55%" },
  { x: "57%", y: "55%" },
  // Mouth — center upper lip
  { x: "49%", y: "53%" },
  // Chin
  { x: "49%", y: "65%" },
  // Jaw — left and right
  { x: "33%", y: "52%" },
  { x: "65%", y: "51%" },
];

export default function FaceSearchSection() {
  const [activeIndex, setActiveIndex] = useState(0);
  const reduceMotion = useReducedMotion();
  const activeMatch = matches[activeIndex] ?? matches[0];

  return (
    <section className={`section-shell ${styles.section}`} aria-labelledby="face-search-title">
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Memórias que se encontram sozinhas</span>
          <h2 id="face-search-title" className="section-title">
            Encontre as fotos do noivo, debutante, aniversariante ou de um convidado em segundos
          </h2>
          <p className="section-lead">
            Uma selfie é tudo que você precisa. A plataforma busca automaticamente todas as fotos dessa pessoa no evento e entrega os melhores momentos na palma da mão.
          </p>

          <ul className={styles.bullets}>
            <li>
              <CheckCircle2 size={18} aria-hidden="true" />
              <span>Envie uma selfie e veja a mágica acontecer: todas as fotos dessa pessoa aparecem instantaneamente</span>
            </li>
            <li>
              <CheckCircle2 size={18} aria-hidden="true" />
              <span>Funciona com milhares de fotos do evento, sem precisar procurar uma por uma</span>
            </li>
            <li>
              <CheckCircle2 size={18} aria-hidden="true" />
              <span>Baixe, compartilhe e reviva os melhores momentos sem esforço</span>
            </li>
          </ul>
        </div>

        <div className={styles.stageColumn}>
          <LazyRivePanel
            enabled={false}
            src="/rive/face-search.riv"
            stateMachines="FaceSearch"
            className={styles.riveWrapper}
            fallback={
              <div className={styles.fallbackDemo}>
                <div className={styles.stageHeader}>
                  <div>
                    <span>Como funciona</span>
                    <strong>Da selfie às suas fotos em segundos</strong>
                  </div>
                  <div className={styles.headerBadge} role="status" aria-live="polite">
                    <ScanFace size={16} aria-hidden="true" />
                    {activeMatch.score} de precisão
                  </div>
                </div>

                <div className={styles.visualGrid}>
                  <div className={styles.selfieCard}>
                    <img 
                      src={eventImages.faceSelfie.src} 
                      alt={eventImages.faceSelfie.alt}
                      loading="lazy"
                      decoding="async"
                    />
                    <div className={styles.selfieOverlay}>
                      <div className={styles.targetBox} role="img" aria-label="Rosto detectado e pronto para busca">
                        <span aria-hidden="true" />
                        <span aria-hidden="true" />
                        <span aria-hidden="true" />
                        <span aria-hidden="true" />
                        <div className={styles.crosshair} aria-hidden="true" />
                        <div className={styles.scanLine} aria-hidden="true" />
                        {faceNodes.map((node, index) => (
                          <i key={index} className={styles.faceNode} style={{ left: node.x, top: node.y }} aria-hidden="true" />
                        ))}
                      </div>
                      <div className={styles.selfieTag}>
                        <ScanFace size={16} aria-hidden="true" />
                        Rosto identificado
                      </div>
                    </div>
                  </div>

                  <div className={styles.resultsPanel}>
                    <div className={styles.resultsHeader}>
                      <span>Buscando no evento</span>
                      <strong>Fotos encontradas</strong>
                    </div>

                    <div className={styles.processRail} role="list" aria-label="Etapas do processo de busca">
                      <span role="listitem">1. Selfie enviada</span>
                      <span role="listitem">2. Rosto reconhecido</span>
                      <span role="listitem">3. Fotos entregues</span>
                    </div>

                    <AnimatePresence mode="wait">
                      <motion.div
                        key={activeIndex}
                        className={styles.matchFeature}
                        initial={{ opacity: 0, y: reduceMotion ? 0 : 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: reduceMotion ? 0 : -10 }}
                        transition={{ duration: reduceMotion ? 0.01 : 0.22 }}
                        role="img"
                        aria-label={`Foto encontrada com ${activeMatch.score} de precisão`}
                      >
                        <img src={activeMatch.src} alt={activeMatch.alt} loading="lazy" decoding="async" />
                        <div className={styles.matchLabel}>
                          <strong>{activeMatch.score} de precisão</strong>
                          <span>{activeMatch.detail}</span>
                        </div>
                      </motion.div>
                    </AnimatePresence>

                    <div className={styles.matchGrid} role="tablist" aria-label="Fotos encontradas">
                      {matches.map((match, index) => (
                        <button
                          key={match.src}
                          type="button"
                          role="tab"
                          aria-selected={index === activeIndex}
                          aria-label={`Ver foto ${index + 1} com ${match.score} de precisão`}
                          className={index === activeIndex ? styles.activeThumb : ""}
                          onClick={() => setActiveIndex(index)}
                        >
                          <img src={match.src} alt={match.alt} loading="lazy" decoding="async" />
                          <span aria-hidden="true">{match.score}</span>
                        </button>
                      ))}
                    </div>
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
