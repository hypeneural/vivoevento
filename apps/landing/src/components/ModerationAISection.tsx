import { useEffect, useRef, useState } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { motion, useReducedMotion } from "motion/react";
import { EyeOff, ShieldCheck, UserCheck } from "lucide-react";
import styles from "./ModerationAISection.module.scss";
import LazyRivePanel from "@/components/rive/LazyRivePanel";

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
    text: "Nossa IA filtra conteúdos impróprios antes de exibir e reduz risco operacional.",
  },
] as const;

export default function ModerationAISection() {
  const scope = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const [activeMode, setActiveMode] = useState<(typeof moderationModes)[number]["id"]>("ai");

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setActiveMode((current) => {
        const currentIndex = moderationModes.findIndex((mode) => mode.id === current);
        return moderationModes[(currentIndex + 1) % moderationModes.length].id;
      });
    }, 3600);

    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  useGSAP(
    () => {
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      if (reducedMotion) return;

      gsap.to("[data-ai-scan]", {
        xPercent: 240,
        duration: 2.8,
        repeat: -1,
        ease: "none",
      });

      gsap.to("[data-conveyor-card]", {
        y: (index) => (index % 2 === 0 ? -6 : 6),
        duration: 2.2,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut",
        stagger: 0.12,
      });
    },
    { scope }
  );

  const activeDefinition = moderationModes.find((mode) => mode.id === activeMode) || moderationModes[2];

  return (
    <section id="ia" className={`section-shell ${styles.section}`} ref={scope}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow">Moderação inteligente por IA</span>
          <h2 className="section-title">Mais seguranca para o evento. Menos risco para a operacao.</h2>
          <p className="section-lead">
            Nossa IA filtra conteúdos impróprios antes de exibir. O visitante enxerga a tecnologia
            acontecendo, não só uma promessa escrita num card genérico.
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
            src="/rive/moderation-scanner.riv"
            stateMachines="Moderation"
            className={styles.riveWrapper}
            fallback={
              <div className={styles.fallbackDemo}>
                <div className={styles.demoHeader}>
                  <span>scanner de IA</span>
                  <span>{activeMode === "ai" ? "analisando ao vivo" : activeMode === "manual" ? "aguardando operador" : "fluxo direto"}</span>
                </div>

                <div className={styles.conveyor}>
                  <div className={styles.scanBeam} data-ai-scan />
                  <div className={styles.cardQueue}>
                    <div className={styles.mediaCard} data-conveyor-card data-state={activeMode === "none" ? "free" : "approved"} />
                    <div className={styles.mediaCard} data-conveyor-card data-state={activeMode === "ai" ? "blocked" : "review"} />
                    <div className={styles.mediaCard} data-conveyor-card data-state={activeMode === "manual" ? "review" : "approved"} />
                  </div>
                </div>

                <div className={styles.statusGrid}>
                  <div>
                    <strong>Aprovado</strong>
                    <span>segue para galeria e telão</span>
                  </div>
                  <div>
                    <strong>Bloqueado</strong>
                    <span>não chega à experiência pública</span>
                  </div>
                  <div>
                    <strong>Revisão manual</strong>
                    <span>entra na fila certa quando necessário</span>
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
