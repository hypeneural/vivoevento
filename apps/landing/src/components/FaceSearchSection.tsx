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
    score: "98.4%",
    detail: "match mais forte retornado primeiro para download e compartilhamento",
  },
  {
    src: eventImages.faceMatchTwo.src,
    alt: eventImages.faceMatchTwo.alt,
    score: "96.1%",
    detail: "resultado complementar encontrado na base do proprio evento",
  },
  {
    src: eventImages.faceMatchThree.src,
    alt: eventImages.faceMatchThree.alt,
    score: "94.8%",
    detail: "outra memoria relevante entregue em segundos apos a selfie",
  },
];

const faceNodes = [
  { x: "43%", y: "29%" },
  { x: "50%", y: "28%" },
  { x: "57%", y: "30%" },
  { x: "41%", y: "39%" },
  { x: "49%", y: "41%" },
  { x: "56%", y: "39%" },
  { x: "46%", y: "47%" },
  { x: "43%", y: "54%" },
  { x: "50%", y: "56%" },
  { x: "57%", y: "54%" },
  { x: "46%", y: "62%" },
  { x: "53%", y: "62%" },
];

export default function FaceSearchSection() {
  const [activeIndex, setActiveIndex] = useState(0);
  const reduceMotion = useReducedMotion();
  const activeMatch = matches[activeIndex] ?? matches[0];

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Busca facial com IA</span>
          <h2 className="section-title">Encontre quem importa. Em segundos.</h2>
          <p className="section-lead">
            A secao precisa mostrar o processo inteiro: selfie enviada, rosto detectado, busca rodando
            na base do evento e memorias retornando com confianca clara para o convidado.
          </p>

          <ul className={styles.bullets}>
            <li>
              <CheckCircle2 size={18} />
              O convidado envia uma selfie e o sistema trava o rosto com leitura visual imediata.
            </li>
            <li>
              <CheckCircle2 size={18} />
              A base do proprio evento e consultada em segundos, nao horas depois.
            </li>
            <li>
              <CheckCircle2 size={18} />
              Os melhores matches retornam organizados para baixar, compartilhar e estender a memoria.
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
                    <span>scanner facial</span>
                    <strong>Da selfie ao match sem parecer galeria solta</strong>
                  </div>
                  <div className={styles.headerBadge}>
                    <ScanFace size={16} />
                    {activeMatch.score} de confianca
                  </div>
                </div>

                <div className={styles.visualGrid}>
                  <div className={styles.selfieCard}>
                    <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
                    <div className={styles.selfieOverlay}>
                      <div className={styles.targetBox}>
                        <span />
                        <span />
                        <span />
                        <span />
                        <div className={styles.crosshair} />
                        <div className={styles.scanLine} />
                        {faceNodes.map((node, index) => (
                          <i key={index} className={styles.faceNode} style={{ left: node.x, top: node.y }} />
                        ))}
                      </div>
                      <div className={styles.selfieTag}>
                        <ScanFace size={16} />
                        rosto detectado e vetorizado
                      </div>
                    </div>
                  </div>

                  <div className={styles.resultsPanel}>
                    <div className={styles.resultsHeader}>
                      <span>buscando na base do evento</span>
                      <strong>matches encontrados</strong>
                    </div>

                    <div className={styles.processRail}>
                      <span>selfie enviada</span>
                      <span>face reconhecida</span>
                      <span>memorias entregues</span>
                    </div>

                    <AnimatePresence mode="wait">
                      <motion.div
                        key={activeIndex}
                        className={styles.matchFeature}
                        initial={{ opacity: 0, y: reduceMotion ? 0 : 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: reduceMotion ? 0 : -10 }}
                        transition={{ duration: reduceMotion ? 0.01 : 0.22 }}
                      >
                        <img src={activeMatch.src} alt={activeMatch.alt} loading="lazy" decoding="async" />
                        <div className={styles.matchLabel}>
                          <strong>{activeMatch.score} match</strong>
                          <span>{activeMatch.detail}</span>
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
                          <img src={match.src} alt={match.alt} loading="lazy" decoding="async" />
                          <span>{match.score}</span>
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
