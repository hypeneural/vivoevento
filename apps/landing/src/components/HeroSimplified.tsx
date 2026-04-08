import { useRef } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { useReducedMotion } from "motion/react";
import {
  ArrowRight,
  CalendarDays,
  Check,
  Sparkles,
} from "lucide-react";
import styles from "./HeroSimplified.module.scss";
import { heroVariations } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { usePersonaContent } from "@/hooks/usePersonaContent";
import { trackCTAClick } from "@/utils/tracking";

gsap.registerPlugin(useGSAP);

const trustPoints = [
  "Sem app - QR + web + WhatsApp",
  "IA configurável - Você decide o nível",
  "Tempo real - Galeria, jogos, telão, busca",
];

export default function HeroSimplified() {
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
        { autoAlpha: 0, y: 28 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.72,
          stagger: 0.08,
          ease: "power3.out",
        }
      );

      return undefined;
    },
    { scope, dependencies: [reduceMotion] }
  );

  const handleInternalJump = (event: React.MouseEvent<HTMLButtonElement>, id: string) => {
    event.preventDefault();
    scrollToId(id);
    window.history.replaceState(null, "", `#${id}`);
  };

  return (
    <section className={styles.hero} ref={scope} aria-labelledby="hero-title">
      <div className="container">
        <div className={styles.grid}>
          {/* Copy Column - Simplificado */}
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

            {/* Trust Points Simplificados - 3 checkmarks */}
            <div className={styles.trustPoints} data-hero-reveal role="list" aria-label="Benefícios principais">
              {trustPoints.map((point, index) => (
                <div key={index} className={styles.trustPoint} role="listitem">
                  <Check size={18} aria-hidden="true" />
                  <span>{point}</span>
                </div>
              ))}
            </div>

            {/* CTAs Simplificados - Apenas 2 */}
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

          {/* Visual Column - Simplificado */}
          <div className={styles.visualColumn} data-hero-reveal>
            <div className={styles.visualShell}>
              {/* Placeholder para mockup limpo ou vídeo */}
              <div className={styles.mockup}>
                <img 
                  src="/placeholder.svg" 
                  alt="Plataforma Evento Vivo em ação"
                  width={800}
                  height={600}
                />
              </div>
              
              {/* Badge de status discreto */}
              <div className={styles.statusBadge} role="status" aria-live="polite">
                <span className={styles.statusDot} aria-hidden="true" />
                27 envios ativos agora
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
