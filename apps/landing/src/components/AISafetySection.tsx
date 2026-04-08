import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { EyeOff, ShieldAlert, ShieldCheck, UserCheck } from "lucide-react";
import styles from "./AISafetySection.module.scss";
import LazyRivePanel from "@/components/rive/LazyRivePanel";
import { galleryImages } from "@/data/landing";

const moderationModes = [
  {
    id: "none",
    icon: EyeOff,
    title: "Sem moderação",
    text: "Fluxo direto para eventos onde velocidade total pesa mais que filtro prévio.",
  },
  {
    id: "manual",
    icon: UserCheck,
    title: "Moderação manual",
    text: "A equipe aprova caso a caso quando o contexto pede intervenção humana.",
  },
  {
    id: "ai",
    icon: ShieldCheck,
    title: "Moderação com IA",
    text: "A análise acontece antes do telão, reduz risco e deixa a operação mais previsível.",
  },
] as const;

const moderationMedia = [
  {
    id: "featured",
    src: galleryImages[0].src,
    alt: galleryImages[0].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "sem filtro prévio, vai para galeria e telão" },
      manual: { state: "review", label: "aguardando operador", detail: "a equipe decide antes de liberar" },
      ai: { state: "approved", label: "aprovada pela IA", detail: "segue para galeria, jogo e telão" },
    },
  },
  {
    id: "sensitive",
    src: galleryImages[2].src,
    alt: galleryImages[2].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "sem barreira entre envio e exibição" },
      manual: { state: "review", label: "fila de revisão", detail: "a equipe segura o envio para validar" },
      ai: { state: "blocked", label: "bloqueada pela IA", detail: "conteúdo não segue para experiência pública" },
    },
  },
  {
    id: "secondary",
    src: galleryImages[1].src,
    alt: galleryImages[1].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "entra no fluxo sem checkpoints" },
      manual: { state: "approved", label: "aprovada pelo operador", detail: "segue depois da revisão humana" },
      ai: { state: "approved", label: "aprovada pela IA", detail: "liberada em segundos com risco controlado" },
    },
  },
] as const;

type ModerationModeId = (typeof moderationModes)[number]["id"];
type ModerationMediaId = (typeof moderationMedia)[number]["id"];

export default function AISafetySection() {
  const reduceMotion = useReducedMotion();
  const [activeMode, setActiveMode] = useState<ModerationModeId>("ai");
  const [activeMediaId, setActiveMediaId] = useState<ModerationMediaId>("sensitive");

  const activeDefinition = moderationModes.find((mode) => mode.id === activeMode) ?? moderationModes[2];
  const activeMedia = moderationMedia.find((media) => media.id === activeMediaId) ?? moderationMedia[1];
  const activeDecision = activeMedia.states[activeMode];

  const laneSummary = {
    approved: moderationMedia.filter((media) => media.states[activeMode].state === "approved").length,
    review: moderationMedia.filter((media) => media.states[activeMode].state === "review").length,
    blocked: moderationMedia.filter((media) => media.states[activeMode].state === "blocked").length,
  };

  return (
    <section id="ia" className={`section-shell ${styles.section}`} aria-labelledby="ai-safety-title">
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Segurança operacional por IA</span>
          <h2 id="ai-safety-title" className="section-title">Mais segurança para o evento. Menos risco para a operação.</h2>
          <p className="section-lead">
            Escolha o nível de moderação ideal para cada evento. A IA analisa conteúdo antes de publicar,
            reduzindo risco sem travar o fluxo. Você mantém controle total da operação.
          </p>

          <div className={styles.modeList} role="radiogroup" aria-labelledby="ai-safety-title">
            {moderationModes.map((mode) => {
              const Icon = mode.icon;
              const isActive = activeMode === mode.id;

              return (
                <button
                  key={mode.id}
                  type="button"
                  role="radio"
                  aria-checked={isActive}
                  className={isActive ? styles.activeMode : ""}
                  onClick={() => setActiveMode(mode.id)}
                  aria-label={`${mode.title}: ${mode.text}`}
                >
                  <Icon size={18} aria-hidden="true" />
                  <div>
                    <strong>{mode.title}</strong>
                    <span>{mode.text}</span>
                  </div>
                </button>
              );
            })}
          </div>

          <div className={styles.noteCard} aria-live="polite">
            <strong>{activeDefinition.title}</strong>
            <p>{activeDefinition.text}</p>
          </div>
        </div>

        <div className={styles.stageColumn}>
          <LazyRivePanel
            enabled={false}
            src="/rive/moderation-scanner.riv"
            stateMachines="Moderation"
            className={styles.riveWrapper}
            fallback={
              <div className={styles.fallbackDemo}>
                <div className={styles.demoHeader}>
                  <div>
                    <span>scanner de IA</span>
                    <strong>Esteira visual de decisão antes do telão</strong>
                  </div>
                  <div className={styles.headerModeBadge} data-state={activeDecision.state} aria-live="polite">
                    {activeDecision.label}
                  </div>
                </div>

                <div className={styles.mediaRail} role="tablist" aria-label="Exemplos de mídia">
                  {moderationMedia.map((media) => {
                    const decision = media.states[activeMode];
                    const isActive = media.id === activeMediaId;

                    return (
                      <button
                        key={media.id}
                        type="button"
                        role="tab"
                        aria-selected={isActive}
                        aria-controls={`media-panel-${media.id}`}
                        className={`${styles.mediaThumb} ${isActive ? styles.activeThumb : ""}`}
                        data-state={decision.state}
                        onClick={() => setActiveMediaId(media.id)}
                      >
                        <img src={media.src} alt={media.alt} loading="lazy" decoding="async" />
                        <span>{decision.label}</span>
                      </button>
                    );
                  })}
                </div>

                <AnimatePresence mode="wait">
                  <motion.div
                    key={`${activeMode}-${activeMedia.id}`}
                    id={`media-panel-${activeMedia.id}`}
                    role="tabpanel"
                    className={styles.scannerStage}
                    initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: reduceMotion ? 0 : -12 }}
                    transition={{ duration: reduceMotion ? 0.01 : 0.24 }}
                  >
                    <div className={styles.activeFrame} data-state={activeDecision.state}>
                      <img src={activeMedia.src} alt={activeMedia.alt} loading="lazy" decoding="async" />
                      <div className={styles.frameOverlay}>
                        <div className={styles.scanHud}>
                          <span>análise em fluxo</span>
                          <strong>{activeDecision.label}</strong>
                        </div>
                        <div className={styles.scanLine} aria-hidden="true" />
                        {activeDecision.state === "blocked" ? (
                          <div className={styles.blockMask}>bloqueado antes do telão</div>
                        ) : null}
                      </div>
                    </div>

                    <div className={styles.explainerPanel} aria-live="polite">
                      <span className={styles.signalLabel}>resultado do scanner</span>
                      <strong>{activeDecision.label}</strong>
                      <p>{activeDecision.detail}</p>
                      <div className={styles.ruleChips} role="list" aria-label="Critérios de análise">
                        <span role="listitem">conteúdo impróprio</span>
                        <span role="listitem">risco operacional</span>
                        <span role="listitem">publicação segura</span>
                      </div>
                    </div>
                  </motion.div>
                </AnimatePresence>

                <div className={styles.statusGrid} aria-live="polite" aria-atomic="true">
                  <div data-state="approved">
                    <strong>{laneSummary.approved} aprovadas</strong>
                    <span>seguem para galeria, jogo e telão</span>
                  </div>
                  <div data-state="review">
                    <strong>{laneSummary.review} em revisão</strong>
                    <span>aguardam decisão manual quando necessário</span>
                  </div>
                  <div data-state="blocked">
                    <strong>{laneSummary.blocked} bloqueadas</strong>
                    <span>não chegam à experiência pública</span>
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
