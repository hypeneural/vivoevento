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
  MonitorPlay,
  ScanFace,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import styles from "./HeroExperience.module.scss";
import { eventImages, heroMetrics } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

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
    <section className={styles.hero} ref={scope}>
      <div className="container">
        <div className={styles.grid}>
          <div className={styles.copyColumn}>
            <span className="eyebrow" data-hero-reveal>
              <Sparkles size={14} />
              Plataforma premium de experiencias ao vivo
            </span>

            <h1 className={styles.title} data-hero-reveal>
              <span className={styles.titleLead}>Uma foto.</span>
              <span className={styles.titleAccent}>Vira galeria, jogos,</span>
              <span className={styles.titleAccent}>telao e IA.</span>
            </h1>

            <p className={styles.lead} data-hero-reveal>
              Receba por QR Code, aprove com IA e transforme o mesmo envio em galeria dinamica,
              jogos interativos, telao ao vivo e busca facial em segundos.
            </p>

            <div className={styles.flowSummary} data-hero-reveal>
              <div className={styles.summaryStep}>
                <span>1</span>
                <strong>Convidado envia</strong>
                <small>QR + web, sem app</small>
              </div>
              <div className={styles.summaryConnector} aria-hidden="true" />
              <div className={styles.summaryStep}>
                <span>2</span>
                <strong>IA decide</strong>
                <small>aprova, bloqueia e indexa</small>
              </div>
              <div className={styles.summaryConnector} aria-hidden="true" />
              <div className={styles.summaryStep}>
                <span>3</span>
                <strong>Publica experiencia</strong>
                <small>galeria, jogos, telao e match</small>
              </div>
            </div>

            <div className={styles.actions} data-hero-reveal>
              <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
                <CalendarDays size={18} />
                Agendar demonstracao
              </a>

              <button type="button" className={styles.textAction} onClick={(event) => handleInternalJump(event, "como-funciona")}>
                Ver como funciona
                <ArrowRight size={17} />
              </button>
            </div>

            <div className={styles.metricRow} data-hero-reveal>
              {heroMetrics.map((metric) => (
                <div key={metric.value} className={styles.metricCard}>
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
          >
            <div className={styles.stageHeader} data-stage-card>
              <div>
                <span className={styles.stageEyebrow}>Fluxo visivel acima da dobra</span>
                <strong>Do envio a publicacao no mesmo fluxo</strong>
              </div>

              <span className={styles.stageStatus}>
                <span className={styles.statusDot} />
                27 envios ativos agora
              </span>
            </div>

            <div className={styles.stageFlow} data-stage-card>
              <div className={styles.flowNode} data-active="true">
                <span>Entrada</span>
                <strong>QR + selfie</strong>
              </div>
              <div className={styles.flowNode} data-active="true">
                <span>Motor</span>
                <strong>IA + operacao</strong>
              </div>
              <div className={styles.flowNode} data-active="true">
                <span>Saidas</span>
                <strong>Galeria, jogos, telao, match</strong>
              </div>
            </div>

            <div className={styles.stageGrid}>
              <article className={styles.captureCard} data-stage-card>
                <header>
                  <span className={styles.cardTag}>
                    <Sparkles size={15} />
                    Convidado entra
                  </span>
                  <strong>Escaneia, envia e entra sem app.</strong>
                </header>

                <div className={styles.captureSurface}>
                  <img src={eventImages.heroMobile.src} alt={eventImages.heroMobile.alt} />

                  <div className={styles.captureOverlay}>
                    <div className={styles.qrPanel}>
                      <div className={styles.qrCode}>
                        {qrCells.map((cell) => (
                          <span key={cell.index} data-filled={cell.filled} />
                        ))}
                      </div>
                      <p>Escaneie e envie</p>
                    </div>

                    <span className={styles.uploadBadge}>foto recebida em 3s</span>
                  </div>
                </div>
              </article>

              <article className={styles.engineCard} data-stage-card>
                <header>
                  <span className={styles.cardTag}>
                    <Bot size={15} />
                    IA em operacao
                  </span>
                  <strong>O fluxo decide antes de publicar.</strong>
                </header>

                <div className={styles.enginePanel}>
                  <div className={styles.engineMedia}>
                    <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
                    <span ref={scanLineRef} className={styles.scanLine} aria-hidden="true" />
                    <span className={styles.scanBadge}>moderando agora</span>
                  </div>

                  <div className={styles.engineLanes}>
                    <span className={styles.engineApproved}>
                      <ShieldCheck size={14} />
                      Aprovado para galeria e telao
                    </span>
                    <span className={styles.engineReview}>
                      <Bot size={14} />
                      Revisao assistida quando necessario
                    </span>
                    <span className={styles.engineFace}>
                      <ScanFace size={14} />
                      Face indexada para match depois
                    </span>
                  </div>
                </div>
              </article>

              <article className={styles.outputCard} data-stage-card>
                <header>
                  <span className={styles.cardTag}>
                    <ActiveOutputIcon size={15} />
                    Resultado vivo
                  </span>
                  <strong>{activeOutputItem.title}</strong>
                </header>

                <div className={styles.outputTabs}>
                  {outputItems.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      className={activeOutput === item.id ? styles.activeTab : ""}
                      onClick={() => setActiveOutput(item.id)}
                      onMouseEnter={() => setActiveOutput(item.id)}
                      onFocus={() => setActiveOutput(item.id)}
                    >
                      <item.icon size={14} />
                      {item.label}
                    </button>
                  ))}
                </div>

                <AnimatePresence mode="wait">
                  <motion.div
                    key={activeOutput}
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
