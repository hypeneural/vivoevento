import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { EyeOff, ShieldAlert, ShieldCheck, UserCheck } from "lucide-react";
import styles from "./ModerationAISection.module.scss";
import LazyRivePanel from "@/components/rive/LazyRivePanel";
import { galleryImages } from "@/data/landing";

const moderationModes = [
  {
    id: "none",
    icon: EyeOff,
    title: "Sem moderacao",
    text: "Fluxo direto para eventos onde velocidade total pesa mais que filtro previo.",
  },
  {
    id: "manual",
    icon: UserCheck,
    title: "Moderacao manual",
    text: "A equipe aprova caso a caso quando o contexto pede intervencao humana.",
  },
  {
    id: "ai",
    icon: ShieldCheck,
    title: "Moderacao com IA",
    text: "A analise acontece antes do telao, reduz risco e deixa a operacao mais previsivel.",
  },
] as const;

const moderationMedia = [
  {
    id: "featured",
    src: galleryImages[0].src,
    alt: galleryImages[0].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "sem filtro previo, vai para galeria e telao" },
      manual: { state: "review", label: "aguardando operador", detail: "a equipe decide antes de liberar" },
      ai: { state: "approved", label: "aprovada pela IA", detail: "segue para galeria, jogo e telao" },
    },
  },
  {
    id: "sensitive",
    src: galleryImages[2].src,
    alt: galleryImages[2].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "sem barreira entre envio e exibicao" },
      manual: { state: "review", label: "fila de revisao", detail: "a equipe segura o envio para validar" },
      ai: { state: "blocked", label: "bloqueada pela IA", detail: "conteudo nao segue para experiencia publica" },
    },
  },
  {
    id: "secondary",
    src: galleryImages[1].src,
    alt: galleryImages[1].alt,
    states: {
      none: { state: "free", label: "publicada direto", detail: "entra no fluxo sem checkpoints" },
      manual: { state: "approved", label: "aprovada pelo operador", detail: "segue depois da revisao humana" },
      ai: { state: "approved", label: "aprovada pela IA", detail: "liberada em segundos com risco controlado" },
    },
  },
] as const;

type ModerationModeId = (typeof moderationModes)[number]["id"];
type ModerationMediaId = (typeof moderationMedia)[number]["id"];

export default function ModerationAISection() {
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
    <section id="ia" className={`section-shell ${styles.section}`}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Moderacao inteligente por IA</span>
          <h2 className="section-title">Mais seguranca para o evento. Menos risco para a operacao.</h2>
          <p className="section-lead">
            A secao precisa mostrar a IA acontecendo. Nao uma promessa vaga. O visitante deve ver a
            foto entrando, sendo analisada e recebendo um destino claro antes de chegar ao publico.
          </p>

          <div className={styles.modeList}>
            {moderationModes.map((mode) => {
              const Icon = mode.icon;

              return (
                <button
                  key={mode.id}
                  type="button"
                  className={activeMode === mode.id ? styles.activeMode : ""}
                  onClick={() => setActiveMode(mode.id)}
                >
                  <Icon size={18} />
                  <div>
                    <strong>{mode.title}</strong>
                    <span>{mode.text}</span>
                  </div>
                </button>
              );
            })}
          </div>

          <div className={styles.noteCard}>
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
                    <strong>Esteira visual de decisao antes do telao</strong>
                  </div>
                  <div className={styles.headerModeBadge} data-state={activeDecision.state}>
                    {activeDecision.label}
                  </div>
                </div>

                <div className={styles.mediaRail}>
                  {moderationMedia.map((media) => {
                    const decision = media.states[activeMode];

                    return (
                      <button
                        key={media.id}
                        type="button"
                        className={`${styles.mediaThumb} ${media.id === activeMediaId ? styles.activeThumb : ""}`}
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
                          <span>analise em fluxo</span>
                          <strong>{activeDecision.label}</strong>
                        </div>
                        <div className={styles.scanLine} />
                        {activeDecision.state === "blocked" ? <div className={styles.blockMask}>bloqueado antes do telao</div> : null}
                      </div>
                    </div>

                    <div className={styles.explainerPanel}>
                      <span className={styles.signalLabel}>resultado do scanner</span>
                      <strong>{activeDecision.label}</strong>
                      <p>{activeDecision.detail}</p>
                      <div className={styles.ruleChips}>
                        <span>conteudo improprio</span>
                        <span>risco operacional</span>
                        <span>publicacao segura</span>
                      </div>
                    </div>
                  </motion.div>
                </AnimatePresence>

                <div className={styles.statusGrid}>
                  <div data-state="approved">
                    <strong>{laneSummary.approved} aprovadas</strong>
                    <span>seguem para galeria, jogo e telao</span>
                  </div>
                  <div data-state="review">
                    <strong>{laneSummary.review} em revisao</strong>
                    <span>aguardam decisao manual quando necessario</span>
                  </div>
                  <div data-state="blocked">
                    <strong>{laneSummary.blocked} bloqueadas</strong>
                    <span>nao chegam a experiencia publica</span>
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
