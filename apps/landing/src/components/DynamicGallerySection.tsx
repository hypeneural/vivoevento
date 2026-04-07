import { useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, LayoutGroup, motion, useReducedMotion } from "motion/react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { ArrowRight, Clock3, Images, QrCode, ShieldCheck, Sparkles } from "lucide-react";
import styles from "./DynamicGallerySection.module.scss";
import { galleryImages } from "@/data/landing";

gsap.registerPlugin(useGSAP, ScrollTrigger);

type GalleryCard = {
  id: string;
  src: string;
  alt: string;
  featured: boolean;
};

type FlowStepId = "capture" | "moderation" | "gallery" | "distribution";

type FlowStep = {
  id: FlowStepId;
  icon: typeof QrCode;
  label: string;
  title: string;
  text: string;
  panelTitle: string;
  panelBody: string;
  liveLabel: string;
  metric: string;
  stats: Array<{ label: string; value: string }>;
  routes: Array<{ label: string; status: string; state: "idle" | "live" | "ready" }>;
  featuredTitle: string;
  featuredBody: string;
};

const initialGallery: GalleryCard[] = galleryImages.slice(0, 6).map((image, index) => ({
  id: `${index}`,
  src: image.src,
  alt: image.alt,
  featured: index === 0,
}));

const flowSteps: FlowStep[] = [
  {
    id: "capture",
    icon: QrCode,
    label: "Entrada",
    title: "Convidado entra por QR Code",
    text: "A experiencia nasce no celular, sem app, sem fila e sem friccao para participar.",
    panelTitle: "A foto entra pronta para operar.",
    panelBody: "O convidado escaneia, envia e o sistema ja trata esse envio como materia-prima do ecossistema.",
    liveLabel: "captura web em andamento",
    metric: "entrada sem app",
    stats: [
      { label: "Tempo medio", value: "3s para enviar" },
      { label: "Canal", value: "QR + web" },
    ],
    routes: [
      { label: "Recepcao", status: "ao vivo", state: "live" },
      { label: "IA", status: "na fila", state: "idle" },
      { label: "Jogos e telao", status: "aguardando", state: "idle" },
    ],
    featuredTitle: "Foto recebida agora",
    featuredBody: "entrada validada e pronta para seguir para a esteira inteligente",
  },
  {
    id: "moderation",
    icon: ShieldCheck,
    label: "IA",
    title: "IA decide antes de publicar",
    text: "Moderacao, aprovacao e indexacao acontecem no fluxo certo, antes da foto chegar ao publico.",
    panelTitle: "Mais seguranca, menos improviso.",
    panelBody: "A foto entra na esteira, recebe decisao e so avanca quando o evento esta protegido.",
    liveLabel: "scanner inteligente analisando",
    metric: "aprovacao em fluxo",
    stats: [
      { label: "Risco no telao", value: "0 antes da exibicao" },
      { label: "Modo", value: "IA ou manual" },
    ],
    routes: [
      { label: "Recepcao", status: "ok", state: "ready" },
      { label: "IA", status: "analisando", state: "live" },
      { label: "Jogos e telao", status: "travados ate aprovar", state: "idle" },
    ],
    featuredTitle: "Aprovacao inteligente",
    featuredBody: "a foto foi validada e liberada para compor a experiencia ao vivo",
  },
  {
    id: "gallery",
    icon: Images,
    label: "Galeria",
    title: "A galeria reorganiza o mosaico sozinha",
    text: "A foto mais recente ganha destaque automatico e a grade responde em tempo real.",
    panelTitle: "O mosaico nao e um deposito de imagens.",
    panelBody: "Cada nova foto muda a composicao, puxa atencao para o destaque e reforca a sensacao de evento vivo.",
    liveLabel: "nova foto ganhando destaque",
    metric: "destaque automatico",
    stats: [
      { label: "Layout", value: "mosaico responsivo" },
      { label: "Movimento", value: "reordenacao viva" },
    ],
    routes: [
      { label: "Recepcao", status: "ok", state: "ready" },
      { label: "IA", status: "aprovado", state: "ready" },
      { label: "Galeria", status: "em evidencia", state: "live" },
    ],
    featuredTitle: "Nova em evidencia",
    featuredBody: "a imagem sobe para a frente do mosaico e puxa a atencao do visitante",
  },
  {
    id: "distribution",
    icon: Sparkles,
    label: "Distribuicao",
    title: "A mesma foto alimenta jogo, telao e memoria",
    text: "Depois de aprovada, a foto nao para na galeria: ela se espalha pelo restante da plataforma.",
    panelTitle: "Uma captura. Varias experiencias vivas.",
    panelBody: "A foto publicada entra no telao, habilita dinamicas de jogo e segue organizada para o pos-evento.",
    liveLabel: "ecossistema distribuindo a foto",
    metric: "multiplos modulos ativos",
    stats: [
      { label: "Saida", value: "galeria + jogo + telao" },
      { label: "Resposta", value: "segundos, nao minutos" },
    ],
    routes: [
      { label: "Galeria", status: "ao vivo", state: "ready" },
      { label: "Jogos", status: "alimentados", state: "live" },
      { label: "Telao", status: "sincronizado", state: "live" },
    ],
    featuredTitle: "Foto distribuida no ecossistema",
    featuredBody: "o mesmo envio ja esta visivel, jogavel e reaproveitavel no evento",
  },
];

function rotateGallery(cards: GalleryCard[]) {
  const next = [...cards];
  const moved = next.shift();
  if (!moved) return cards;

  return [...next.map((card) => ({ ...card, featured: false })), { ...moved, featured: true }];
}

function getNextStep(current: FlowStepId) {
  const currentIndex = flowSteps.findIndex((step) => step.id === current);
  return flowSteps[(currentIndex + 1) % flowSteps.length]?.id ?? flowSteps[0].id;
}

export default function DynamicGallerySection() {
  const scope = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const [cards, setCards] = useState(initialGallery);
  const [activeStep, setActiveStep] = useState<FlowStepId>("capture");

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setCards((previous) => rotateGallery(previous));
      setActiveStep((previous) => getNextStep(previous));
    }, 3200);

    return () => window.clearInterval(timer);
  }, [reduceMotion]);

  useGSAP(
    () => {
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

      gsap.from("[data-flow-step]", {
        y: reducedMotion ? 0 : 18,
        opacity: 0,
        stagger: 0.12,
        duration: 0.65,
        scrollTrigger: {
          trigger: scope.current,
          start: "top 72%",
        },
      });

      if (reducedMotion) return;

      gsap.to("[data-flow-token]", {
        xPercent: 420,
        duration: 3.6,
        repeat: -1,
        ease: "none",
        scrollTrigger: {
          trigger: scope.current,
          start: "top 78%",
          end: "bottom top",
          toggleActions: "play pause play pause",
        },
      });
    },
    { scope }
  );

  const activeDefinition = flowSteps.find((step) => step.id === activeStep) ?? flowSteps[0];

  const orderedCards = useMemo(
    () => [...cards].sort((first, second) => Number(second.featured) - Number(first.featured)),
    [cards]
  );

  return (
    <section id="como-funciona" className={`section-shell ${styles.section}`} ref={scope}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.storyColumn}>
          <span className="eyebrow">Como uma foto vira experiencia</span>
          <h2 className="section-title">As fotos nao ficam paradas. Elas compoem a experiencia.</h2>
          <p className="section-lead">
            Em vez de despejar imagens numa grade estatica, o Evento Vivo recebe, analisa, destaca e
            distribui cada foto para galeria, jogo, telao e memoria organizada.
          </p>

          <div className={styles.flowRail}>
            <div className={styles.flowTrack}>
              <div className={styles.flowToken} data-flow-token />
            </div>

            <div className={styles.flowList} role="tablist" aria-label="Fluxo da foto no Evento Vivo">
              {flowSteps.map((step) => {
                const Icon = step.icon;
                const isActive = step.id === activeStep;

                return (
                  <motion.button
                    key={step.id}
                    type="button"
                    role="tab"
                    aria-selected={isActive}
                    aria-controls={`gallery-step-${step.id}`}
                    className={`${styles.flowStep} ${isActive ? styles.activeStep : ""}`}
                    data-flow-step
                    whileHover={reduceMotion ? undefined : { y: -2 }}
                    onMouseEnter={() => setActiveStep(step.id)}
                    onFocus={() => setActiveStep(step.id)}
                    onClick={() => setActiveStep(step.id)}
                  >
                    <div className={styles.flowIcon}>
                      <Icon size={16} />
                    </div>
                    <div className={styles.flowContent}>
                      <div className={styles.flowHeading}>
                        <h3>{step.title}</h3>
                        <span>{step.metric}</span>
                      </div>
                      <p>{step.text}</p>
                    </div>
                  </motion.button>
                );
              })}
            </div>

            <AnimatePresence mode="wait">
              <motion.div
                key={activeDefinition.id}
                id={`gallery-step-${activeDefinition.id}`}
                role="tabpanel"
                className={styles.signalBoard}
                initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: reduceMotion ? 0 : -12 }}
                transition={{ duration: reduceMotion ? 0.01 : 0.24 }}
              >
                <div className={styles.signalHeader}>
                  <span>{activeDefinition.metric}</span>
                  <strong>{activeDefinition.panelTitle}</strong>
                  <p>{activeDefinition.panelBody}</p>
                </div>

                <div className={styles.signalStats}>
                  {activeDefinition.stats.map((item) => (
                    <div key={item.label} className={styles.signalStat}>
                      <span>{item.label}</span>
                      <strong>{item.value}</strong>
                    </div>
                  ))}
                </div>

                <div className={styles.routeList}>
                  {activeDefinition.routes.map((route) => (
                    <div key={route.label} className={styles.routeItem} data-state={route.state}>
                      <span>{route.label}</span>
                      <strong>{route.status}</strong>
                    </div>
                  ))}
                </div>
              </motion.div>
            </AnimatePresence>
          </div>
        </div>

        <div className={styles.galleryColumn}>
          <div className={styles.galleryHeader}>
            <div className={styles.liveBadge}>
              <Clock3 size={16} />
              {activeDefinition.liveLabel}
            </div>
            <button className={styles.storyButton} type="button" onClick={() => setActiveStep(getNextStep(activeStep))}>
              Ver proxima etapa
              <ArrowRight size={16} />
            </button>
          </div>

          <AnimatePresence mode="wait">
            <motion.div
              key={activeDefinition.id}
              className={styles.galleryStatus}
              initial={{ opacity: 0, y: reduceMotion ? 0 : 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: reduceMotion ? 0 : -10 }}
              transition={{ duration: reduceMotion ? 0.01 : 0.22 }}
            >
              <strong>{activeDefinition.panelTitle}</strong>
              <p>{activeDefinition.panelBody}</p>
              <div className={styles.statusRoutes}>
                {activeDefinition.routes.map((route) => (
                  <span key={route.label} data-state={route.state}>
                    {route.label}
                  </span>
                ))}
              </div>
            </motion.div>
          </AnimatePresence>

          <LayoutGroup>
            <motion.div className={styles.galleryGrid} layout>
              <AnimatePresence mode="popLayout">
                {orderedCards.map((card) => (
                  <motion.figure
                    key={card.id}
                    layout
                    initial={{ opacity: 0, scale: reduceMotion ? 1 : 0.92 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: reduceMotion ? 1 : 0.92 }}
                    transition={{ duration: reduceMotion ? 0.01 : 0.5 }}
                    className={`${styles.card} ${card.featured ? styles.featured : ""}`}
                  >
                    <img src={card.src} alt={card.alt} loading="lazy" decoding="async" />
                    {card.featured ? <span className={styles.cardSignal}>{activeDefinition.label}</span> : null}
                    <figcaption>
                      <strong>{card.featured ? activeDefinition.featuredTitle : "Galeria viva"}</strong>
                      <span>{card.featured ? activeDefinition.featuredBody : "reorganizacao continua"}</span>
                    </figcaption>
                  </motion.figure>
                ))}
              </AnimatePresence>
            </motion.div>
          </LayoutGroup>
        </div>
      </div>
    </section>
  );
}
