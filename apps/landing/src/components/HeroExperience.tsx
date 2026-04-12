import { useRef, type MouseEvent } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { useReducedMotion } from "motion/react";
import {
  ArrowRight,
  CalendarDays,
  Check,
  Sparkles,
} from "lucide-react";
import styles from "./HeroExperience.module.scss";
import { heroVariations } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { usePersonaContent } from "@/hooks/usePersonaContent";
import { trackCTAClick } from "@/utils/tracking";
import PhoneVideoShowcase from "@/components/PhoneVideoShowcase";

gsap.registerPlugin(useGSAP);

const trustPoints = [
  { icon: Check, text: "Não precisa instalar app", detail: "Receba por QR, link, WhatsApp ou Telegram" },
  { icon: Check, text: "IA configurável para as mídias", detail: "Modera, trata foto e responde personalizada" },
  { icon: Check, text: "Tempo real", detail: "Galeria personalizada, telão dinâmico, jogos e mini site" },
];

export default function HeroExperience() {
  const reduceMotion = useReducedMotion();
  const scope = useRef<HTMLElement | null>(null);
  const { scrollToId } = useSmoothScroll();

  const heroContent = usePersonaContent(
    heroVariations,
    heroVariations.social,
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
        },
      );

      return undefined;
    },
    { scope, dependencies: [reduceMotion] },
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

          <div className={styles.visualColumn} data-hero-reveal>
            <PhoneVideoShowcase
              poster="/assets/hero-phone/poster-phone.jpg"
              previewMp4Src="/assets/hero-phone/preview-phone.mp4"
              previewWebmSrc="/assets/hero-phone/preview-phone.webm"
              fullDemoMp4Src="/assets/hero-phone/full-demo.mp4"
              frameSrc="/assets/hero-phone/phone-frame.svg"
              title="Demonstração real do Evento Vivo em um evento"
            />
          </div>
        </div>
      </div>
    </section>
  );
}
