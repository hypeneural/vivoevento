import { useEffect, useMemo, useRef, useState, type MouseEvent, type PointerEvent } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import {
  ArrowRight,
  Bot,
  CalendarDays,
  Gamepad2,
  Images,
  type LucideIcon,
  MessageSquare,
  MonitorPlay,
  ScanFace,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import styles from "./HeroExperience.module.scss";
import { eventImages, heroVariations } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { usePersonaContent } from "@/hooks/usePersonaContent";
import TrustSignals from "./TrustSignals";

gsap.registerPlugin(useGSAP);

type OutputId = "gallery" | "games" | "wall" | "face";

type OutputItem = {
  id: OutputId;
  label: string;
  title: string;
  detail: string;
  icon: LucideIcon;
};

const outputItems: OutputItem[] = [
  {
    id: "gallery",
    label: "Galeria",
    title: "Fotos entram e reorganizam a galeria sozinhas.",
    detail: "Cada envio ja nasce publicado no mosaico certo, com destaque automatico para o que acabou de chegar.",
    icon: Images,
  },
  {
    id: "games",
    label: "Jogos",
    title: "Puzzle e memoria usam as fotos reais do evento.",
    detail: "O convidado entende na hora que nao e um telao com brinde: e uma experiencia jogavel feita para o celular.",
    icon: Gamepad2,
  },
  {
    id: "wall",
    label: "Telao",
    title: "O telao performa como palco, nao como slideshow.",
    detail: "Layouts vivos, cinematic e atualizacao em tempo real sem depender de refresh manual.",
    icon: MonitorPlay,
  },
  {
    id: "face",
    label: "Busca facial",
    title: "Uma selfie encontra memorias em segundos.",
    detail: "O mesmo fluxo que publica a foto tambem indexa e entrega matches prontos para baixar e compartilhar.",
    icon: ScanFace,
  },
];

const qrPattern = [
  1, 1, 0, 1, 1, 0, 0, 1, 0,
  0, 1, 1, 0, 0, 1, 1, 0, 1,
  1, 0, 1, 0, 1, 0, 1, 1, 0,
  0, 1, 0, 1, 1, 0, 0, 1, 1,
  1, 0, 0, 1, 0, 1, 1, 0, 1,
  0, 1, 1, 0, 1, 1, 0, 1, 0,
  1, 0, 1, 1, 0, 0, 1, 0, 1,
  0, 1, 0, 0, 1, 1, 0, 1, 1,
  1, 1, 0, 1, 0, 1, 1, 0, 1,
];

function OutputPreview({ activeOutput }: { activeOutput: OutputId }) {
  if (activeOutput === "gallery") {
    return (
      <div className={styles.galleryPreview}>
        <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
        <img src={eventImages.gallerySecondary.src} alt={eventImages.gallerySecondary.alt} />
        <img src={eventImages.galleryThird.src} alt={eventImages.galleryThird.alt} />
        <img src={eventImages.galleryFourth.src} alt={eventImages.galleryFourth.alt} />
        <span>nova foto em destaque</span>
      </div>
    );
  }

  if (activeOutput === "games") {
    return (
      <div className={styles.gamesPreview}>
        <div className={styles.gamesGrid}>
          <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
          <img src={eventImages.gallerySecondary.src} alt={eventImages.gallerySecondary.alt} />
          <img src={eventImages.galleryFourth.src} alt={eventImages.galleryFourth.alt} />
          <img src={eventImages.galleryFifth.src} alt={eventImages.galleryFifth.alt} />
        </div>
        <div className={styles.gamesScore}>
          <strong>Mesa 7 liderando</strong>
          <span>ranking ao vivo 1240 pts</span>
        </div>
      </div>
    );
  }

  if (activeOutput === "wall") {
    return (
      <div className={styles.wallPreview}>
        <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
        <div className={styles.wallCaption}>
          <strong>layout cinematic</strong>
          <span>@mesa8 em destaque</span>
        </div>
      </div>
    );
  }

  return (
    <div className={styles.facePreview}>
      <div className={styles.faceSelfie}>
        <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
        <div className={styles.faceLock} aria-hidden="true">
          <span />
          <span />
          <span />
          <span />
        </div>
      </div>

      <div className={styles.faceMatches}>
        <div className={styles.faceMatchLead}>
          <img src={eventImages.faceMatchOne.src} alt={eventImages.faceMatchOne.alt} />
          <strong>98.4% match</strong>
        </div>
        <img src={eventImages.faceMatchTwo.src} alt={eventImages.faceMatchTwo.alt} />
        <img src={eventImages.faceMatchThree.src} alt={eventImages.faceMatchThree.alt} />
      </div>
    </div>
  );
}

export default function HeroExperience() {
  const [activeOutput, setActiveOutput] = useState<OutputId>("gallery");
  const reduceMotion = useReducedMotion();
  const scope = useRef<HTMLElement | null>(null);
  const stageRef = useRef<HTMLDivElement | null>(null);
  const scanLineRef = useRef<HTMLSpanElement | null>(null);
  const { scrollToId } = useSmoothScroll();

  // Get persona-specific content
  const heroContent = usePersonaContent(
    heroVariations,
    heroVariations.social // fallback to social persona
  );

  const activeOutputItem = outputItems.find((item) => item.id === activeOutput) ?? outputItems[0];
  const ActiveOutputIcon = activeOutputItem.icon;
  const qrCells = useMemo(() => qrPattern.map((filled, index) => ({ filled: Boolean(filled), index })), []);

  useEffect(() => {
    if (reduceMotion) {
      return undefined;
    }

    const interval = window.setInterval(() => {
      setActiveOutput((current) => {
        const currentIndex = outputItems.findIndex((item) => item.id === current);
        return outputItems[(currentIndex + 1) % outputItems.length].id;
      });
    }, 4200);

    return () => window.clearInterval(interval);
  }, [reduceMotion]);

  useGSAP(
    () => {
      if (reduceMotion) {
        return undefined;
      }

      const revealNodes = gsap.utils.toArray<HTMLElement>("[data-hero-reveal]");
      const stageNodes = gsap.utils.toArray<HTMLElement>("[data-stage-card]");

      gsap.fromTo(
        revealNodes,
        { autoAlpha: 0, y: 28 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.72,
          stagger: 0.08,
          ease: "power3.out",
        }
      );

      gsap.fromTo(
        stageNodes,
        { autoAlpha: 0, y: 32, scale: 0.98 },
        {
          autoAlpha: 1,
          y: 0,
          scale: 1,
          duration: 0.84,
          stagger: 0.1,
          ease: "power3.out",
          delay: 0.12,
        }
      );

      if (scanLineRef.current) {
        gsap.fromTo(
          scanLineRef.current,
          { xPercent: -130 },
          {
            xPercent: 330,
            duration: 2.8,
            repeat: -1,
            ease: "none",
            repeatDelay: 0.18,
          }
        );
      }

      return undefined;
    },
    { scope, dependencies: [reduceMotion] }
  );

  const handleStagePointerMove = (event: PointerEvent<HTMLDivElement>) => {
    if (reduceMotion || !stageRef.current) {
      return;
    }

    const bounds = stageRef.current.getBoundingClientRect();
    const x = (event.clientX - bounds.left) / bounds.width;
    const y = (event.clientY - bounds.top) / bounds.height;
    const rotateX = (0.5 - y) * 7;
    const rotateY = (x - 0.5) * 8;

    stageRef.current.style.setProperty("--hero-rx", `${rotateX.toFixed(2)}deg`);
    stageRef.current.style.setProperty("--hero-ry", `${rotateY.toFixed(2)}deg`);
    stageRef.current.style.setProperty("--hero-gx", `${(x * 100).toFixed(1)}%`);
    stageRef.current.style.setProperty("--hero-gy", `${(y * 100).toFixed(1)}%`);
  };

  const resetStagePerspective = () => {
    if (!stageRef.current) {
      return;
    }

    stageRef.current.style.setProperty("--hero-rx", "0deg");
    stageRef.current.style.setProperty("--hero-ry", "0deg");
    stageRef.current.style.setProperty("--hero-gx", "50%");
    stageRef.current.style.setProperty("--hero-gy", "18%");
  };

  const handleInternalJump = (event: MouseEvent<HTMLButtonElement>, id: string) => {
    event.preventDefault();
    scrollToId(id);
    window.history.replaceState(null, "", `#${id}`);
  };

  return (
    <section className={styles.hero} ref={scope} aria-labelledby="hero-title">
      <div className="container">
        <div className={styles.grid}>
          <div className={styles.copyColumn}>
            <span className="eyebrow" data-hero-reveal>
              <Sparkles size={14} aria-hidden="true" />
              {heroContent.eyebrow}
            </span>

            <h1 id="hero-title" className={styles.title} data-hero-reveal>
              <span className={styles.titleLead}>{heroContent.headline.lead}</span>
              {heroContent.headline.accent.map((line, index) => (
                <span key={index} className={styles.titleAccent}>
                  {line}
                </span>
              ))}
            </h1>

            <p className={styles.lead} data-hero-reveal>
              {heroContent.subheadline}
            </p>

            <div className={styles.flowSummary} data-hero-reveal role="list" aria-label="Fluxo de funcionamento">
              {heroContent.flowSteps.map((step, index) => (
                <>
                  <div key={step.number} className={styles.summaryStep} role="listitem">
                    <span aria-label={`Passo ${step.number}`}>{step.number}</span>
                    <strong>{step.title}</strong>
                    <small>{step.detail}</small>
                  </div>
                  {index < heroContent.flowSteps.length - 1 && (
                    <div key={`connector-${index}`} className={styles.summaryConnector} aria-hidden="true" />
                  )}
                </>
              ))}
            </div>

            <div className={styles.actions} data-hero-reveal>
              <a 
                className="button" 
                data-variant="primary" 
                href={siteConfig.primaryCtaUrl} 
                target="_blank" 
                rel="noreferrer"
                aria-label="Agendar demonstração da plataforma"
              >
                <CalendarDays size={18} aria-hidden="true" />
                {heroContent.ctas.primary.text}
              </a>

              <button 
                type="button" 
                className={styles.textAction} 
                onClick={(event) => handleInternalJump(event, "como-funciona")}
                aria-label="Ver como funciona a plataforma"
              >
                Ver como funciona
                <ArrowRight size={17} aria-hidden="true" />
              </button>
            </div>

            <div data-hero-reveal>
              <TrustSignals signals={heroContent.trustSignals} />
            </div>

            <div className={styles.metricRow} data-hero-reveal role="list" aria-label="Métricas da plataforma">
              {heroContent.metrics.map((metric) => (
                <div key={metric.value} className={styles.metricCard} role="listitem">
                  <strong>{metric.value}</strong>
                  <span>{metric.label}</span>
                </div>
              ))}
            </div>
          </div>

          <div
            ref={stageRef}
            className={styles.stageShell}
            onPointerMove={handleStagePointerMove}
            onPointerLeave={resetStagePerspective}
            role="region"
            aria-label="Demonstração interativa do fluxo da plataforma"
          >
            <div className={styles.stageHeader} data-stage-card>
              <div>
                <span className={styles.stageEyebrow}>Fluxo visivel acima da dobra</span>
                <strong>Do envio a publicacao no mesmo fluxo</strong>
              </div>

              <span className={styles.stageStatus} role="status" aria-live="polite">
                <span className={styles.statusDot} aria-hidden="true" />
                27 envios ativos agora
              </span>
            </div>

            <div className={styles.stageFlow} data-stage-card role="list" aria-label="Etapas do fluxo">
              <div className={styles.flowNode} data-active="true" role="listitem">
                <span>Entrada</span>
                <strong>QR + selfie</strong>
              </div>
              <div className={styles.flowNode} data-active="true" role="listitem">
                <span>Motor</span>
                <strong>IA + operacao</strong>
              </div>
              <div className={styles.flowNode} data-active="true" role="listitem">
                <span>Saidas</span>
                <strong>Galeria, jogos, telao, match</strong>
              </div>
            </div>

            <div className={styles.stageGrid}>
              <article className={styles.captureCard} data-stage-card aria-labelledby="capture-title">
                <header>
                  <span className={styles.cardTag}>
                    <Sparkles size={15} aria-hidden="true" />
                    Convidado entra
                  </span>
                  <strong id="capture-title">Escaneia, envia e entra sem app.</strong>
                </header>

                <div className={styles.captureSurface}>
                  <img src={eventImages.heroMobile.src} alt={eventImages.heroMobile.alt} />

                  <div className={styles.captureOverlay}>
                    <div className={styles.qrPanel}>
                      <div className={styles.qrCode} role="img" aria-label="QR Code de exemplo">
                        {qrCells.map((cell) => (
                          <span key={cell.index} data-filled={cell.filled} aria-hidden="true" />
                        ))}
                      </div>
                      <p>Escaneie e envie</p>
                    </div>

                    <span className={styles.uploadBadge} role="status">foto recebida em 3s</span>
                  </div>
                </div>
              </article>

              <article className={styles.engineCard} data-stage-card aria-labelledby="engine-title">
                <header>
                  <span className={styles.cardTag}>
                    <Bot size={15} aria-hidden="true" />
                    IA em operacao
                  </span>
                  <strong id="engine-title">O fluxo decide antes de publicar.</strong>
                </header>

                <div className={styles.enginePanel}>
                  <div className={styles.engineMedia}>
                    <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
                    <span ref={scanLineRef} className={styles.scanLine} aria-hidden="true" />
                    <span className={styles.scanBadge} role="status">moderando agora</span>
                  </div>

                  <div className={styles.engineLanes} role="list" aria-label="Resultados da moderação">
                    <span className={styles.engineApproved} role="listitem">
                      <ShieldCheck size={14} aria-hidden="true" />
                      Aprovado para galeria e telao
                    </span>
                    <span className={styles.engineReview} role="listitem">
                      <Bot size={14} aria-hidden="true" />
                      Revisao assistida quando necessario
                    </span>
                    <span className={styles.engineFace} role="listitem">
                      <ScanFace size={14} aria-hidden="true" />
                      Face indexada para match depois
                    </span>
                  </div>
                </div>
              </article>

              <article className={styles.outputCard} data-stage-card aria-labelledby="output-title">
                <header>
                  <span className={styles.cardTag}>
                    <ActiveOutputIcon size={15} aria-hidden="true" />
                    Resultado vivo
                  </span>
                  <strong id="output-title">{activeOutputItem.title}</strong>
                </header>

                <div className={styles.outputTabs} role="tablist" aria-label="Módulos de saída">
                  {outputItems.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      role="tab"
                      aria-selected={activeOutput === item.id}
                      aria-controls={`output-panel-${item.id}`}
                      id={`output-tab-${item.id}`}
                      className={activeOutput === item.id ? styles.activeTab : ""}
                      onClick={() => setActiveOutput(item.id)}
                      onMouseEnter={() => setActiveOutput(item.id)}
                      onFocus={() => setActiveOutput(item.id)}
                    >
                      <item.icon size={14} aria-hidden="true" />
                      {item.label}
                    </button>
                  ))}
                </div>

                <AnimatePresence mode="wait">
                  <motion.div
                    key={activeOutput}
                    id={`output-panel-${activeOutput}`}
                    role="tabpanel"
                    aria-labelledby={`output-tab-${activeOutput}`}
                    className={styles.outputSurface}
                    initial={reduceMotion ? false : { opacity: 0, y: 18 }}
                    animate={reduceMotion ? undefined : { opacity: 1, y: 0 }}
                    exit={reduceMotion ? undefined : { opacity: 0, y: -12 }}
                    transition={{ duration: 0.26, ease: "easeOut" }}
                  >
                    <OutputPreview activeOutput={activeOutput} />

                    <div className={styles.outputCopy}>
                      <span>{activeOutputItem.label}</span>
                      <p>{activeOutputItem.detail}</p>
                    </div>
                  </motion.div>
                </AnimatePresence>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
