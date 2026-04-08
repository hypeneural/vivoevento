import { useRef, type MouseEvent } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { useReducedMotion } from "motion/react";
import {
  ArrowRight,
  CalendarDays,
  Check,
  Sparkles,
  Images,
  MonitorPlay,
  Gamepad2,
} from "lucide-react";
import styles from "./HeroExperience.module.scss";
import { heroVariations, eventImages } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { usePersonaContent } from "@/hooks/usePersonaContent";
import { trackCTAClick } from "@/utils/tracking";

gsap.registerPlugin(useGSAP);

// Trust points simplificados
const trustPoints = [
  { icon: Check, text: "Sem app", detail: "QR + web + WhatsApp" },
  { icon: Check, text: "IA configurável", detail: "Você decide o nível" },
  { icon: Check, text: "Tempo real", detail: "Galeria, jogos, telão, busca" },
];

export default function HeroExperience() {
  const reduceMotion = useReducedMotion();
  const scope = useRef<HTMLElement | null>(null);
  const { scrollToId } = useSmoothScroll();

  // Get persona-specific content
  const heroContent = usePersonaContent(
    heroVariations,
    heroVariations.social // fallback to social persona
  );

  useGSAP(
    () => {
      if (reduceMotion) {
        return undefined;
      }

      const revealNodes = gsap.utils.toArray<HTMLElement>("[data-hero-reveal]");

      gsap.fromTo(
        revealNodes,
        { autoAlpha: 0, y: 32 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.8,
          stagger: 0.1,
          ease: "power3.out",
        }
      );

      return undefined;
    },
    { scope, dependencies: [reduceMotion] }
  );

  const handleInternalJump = (event: MouseEvent<HTMLButtonElement>, id: string) => {
    event.preventDefault();
    scrollToId(id);
    window.history.replaceState(null, "", `#${id}`);
  };

  return (
    <section className={styles.hero} ref={scope} aria-labelledby="hero-title">
      <div className="container">
        <div className={styles.grid}>
          {/* Copy Column - Simplificado e Limpo */}
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

            {/* Trust Points - 3 checkmarks limpos */}
            <div className={styles.trustPoints} data-hero-reveal role="list" aria-label="Benefícios principais">
              {trustPoints.map((point, index) => (
                <div key={index} className={styles.trustPoint} role="listitem">
                  <div className={styles.trustIcon}>
                    <point.icon size={16} aria-hidden="true" />
                  </div>
                  <div className={styles.trustContent}>
                    <strong>{point.text}</strong>
                    <span>{point.detail}</span>
                  </div>
                </div>
              ))}
            </div>

            {/* CTAs - Apenas 2, hierarquia clara */}
            <div className={styles.actions} data-hero-reveal>
              <a 
                className="button" 
                data-variant="primary" 
                href={siteConfig.primaryCtaUrl} 
                target="_blank" 
                rel="noreferrer"
                aria-label="Agendar demonstração da plataforma"
                onClick={() => trackCTAClick("primary", "hero", "Agendar demonstração")}
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
          </div>

          {/* Visual Column — Collage de evento real */}
          <div className={styles.visualColumn} data-hero-reveal>
            <div className={styles.visualShell}>
              {/* Badge de status */}
              <div className={styles.statusBadge} role="status" aria-live="polite">
                <span className={styles.statusDot} aria-hidden="true" />
                27 envios ativos agora
              </div>

              {/* Collage de imagens reais */}
              <div className={styles.collageContainer}>
                <div className={styles.collageMain}>
                  <img 
                    src={eventImages.galleryFeatured.src} 
                    alt={eventImages.galleryFeatured.alt} 
                    loading="eager" 
                    decoding="async"
                  />
                  <div className={styles.collageBadge} data-position="top-left">
                    <Images size={14} />
                    <span>Galeria ao vivo</span>
                  </div>
                </div>
                <div className={styles.collageSide}>
                  <div className={styles.collageSideImg}>
                    <img 
                      src={eventImages.wallHero.src} 
                      alt={eventImages.wallHero.alt} 
                      loading="eager" 
                      decoding="async"
                    />
                    <div className={styles.collageBadge} data-position="bottom-left">
                      <MonitorPlay size={14} />
                      <span>Telão</span>
                    </div>
                  </div>
                  <div className={styles.collageSideImg}>
                    <img 
                      src={eventImages.galleryThird.src} 
                      alt={eventImages.galleryThird.alt} 
                      loading="lazy" 
                      decoding="async"
                    />
                    <div className={styles.collageBadge} data-position="bottom-left">
                      <Gamepad2 size={14} />
                      <span>Jogos</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Floating elements — outside shell to avoid overflow:hidden clipping */}
            <div className={styles.floatingBadge} data-position="top-left">
              <Check size={14} />
              <span>IA ativa</span>
            </div>

            <div className={styles.floatingBadge} data-position="bottom-right">
              <Sparkles size={14} />
              <span>Tempo real</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
