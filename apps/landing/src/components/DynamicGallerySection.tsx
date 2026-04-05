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

const initialGallery: GalleryCard[] = galleryImages.slice(0, 6).map((image, index) => ({
  id: `${index}`,
  src: image.src,
  alt: image.alt,
  featured: index === 0,
}));

const flowSteps = [
  {
    icon: QrCode,
    title: "Convidado entra por QR Code",
    text: "A experiência nasce no celular, sem app e sem atrito para o convidado.",
  },
  {
    icon: ShieldCheck,
    title: "IA decide antes de publicar",
    text: "Moderação, aprovação e indexação acontecem no fluxo certo.",
  },
  {
    icon: Images,
    title: "Galeria ganha vida",
    text: "A foto mais recente vira destaque e reorganiza o mosaico automaticamente.",
  },
  {
    icon: Sparkles,
    title: "Jogo, telão e memória recebem",
    text: "A mesma foto alimenta múltiplas experiências ao vivo em poucos segundos.",
  },
];

function rotateGallery(cards: GalleryCard[]) {
  const next = [...cards];
  const moved = next.shift();
  if (!moved) return cards;

  return [...next.map((card) => ({ ...card, featured: false })), { ...moved, featured: true }];
}

export default function DynamicGallerySection() {
  const scope = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const [cards, setCards] = useState(initialGallery);

  useEffect(() => {
    if (reduceMotion) return undefined;

    const timer = window.setInterval(() => {
      setCards((previous) => rotateGallery(previous));
    }, 2600);

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

  const orderedCards = useMemo(
    () => [...cards].sort((first, second) => Number(second.featured) - Number(first.featured)),
    [cards]
  );

  return (
    <section id="como-funciona" className={`section-shell ${styles.section}`} ref={scope}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.storyColumn}>
          <span className="eyebrow">Como uma foto vira experiência</span>
          <h2 className="section-title">As fotos nao ficam paradas. Elas compoem a experiencia.</h2>
          <p className="section-lead">
            Em vez de despejar imagens numa grade estática, o Evento Vivo recebe, analisa, destaca e
            distribui cada foto para galeria, jogo, telão e memória organizada.
          </p>

          <div className={styles.flowRail}>
            <div className={styles.flowTrack}>
              <div className={styles.flowToken} data-flow-token />
            </div>

            <div className={styles.flowList}>
              {flowSteps.map((step) => {
                const Icon = step.icon;

                return (
                  <article key={step.title} className={styles.flowStep} data-flow-step>
                    <div className={styles.flowIcon}>
                      <Icon size={16} />
                    </div>
                    <div>
                      <h3>{step.title}</h3>
                      <p>{step.text}</p>
                    </div>
                  </article>
                );
              })}
            </div>
          </div>
        </div>

        <div className={styles.galleryColumn}>
          <div className={styles.galleryHeader}>
            <div className={styles.liveBadge}>
              <Clock3 size={16} />
              destacando foto recém-chegada
            </div>
            <button className={styles.storyButton} type="button">
              Mosaico responsivo
              <ArrowRight size={16} />
            </button>
          </div>

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
                    <img src={card.src} alt={card.alt} loading="lazy" />
                    <figcaption>
                      <strong>{card.featured ? "Nova em evidência" : "Galeria viva"}</strong>
                      <span>{card.featured ? "ganhando destaque automático" : "reorganização contínua"}</span>
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
