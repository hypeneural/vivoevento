import { useMemo, useRef } from "react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import {
  ArrowRight,
  CalendarRange,
  Gamepad2,
  Images,
  MessageCircle,
  MonitorPlay,
  QrCode,
  ScanFace,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import styles from "./HeroExperience.module.scss";
import { eventImages, heroBullets, heroMetrics } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

gsap.registerPlugin(useGSAP, ScrollTrigger);

const qrPattern = [
  1, 1, 1, 1, 1, 0, 1, 0, 1,
  1, 0, 0, 0, 1, 0, 1, 0, 0,
  1, 0, 1, 0, 1, 0, 1, 1, 1,
  1, 0, 0, 0, 1, 0, 0, 0, 1,
  1, 1, 1, 1, 1, 0, 1, 0, 1,
  0, 0, 1, 0, 0, 1, 0, 0, 0,
  1, 1, 1, 0, 1, 1, 1, 0, 1,
  1, 0, 0, 0, 0, 0, 1, 0, 1,
  1, 1, 1, 0, 1, 1, 1, 1, 1,
];

export default function HeroExperience() {
  const scope = useRef<HTMLDivElement>(null);
  const { scrollToId } = useSmoothScroll();
  const bulletIcons = useMemo(() => [Images, Gamepad2, MonitorPlay, ShieldCheck], []);

  useGSAP(
    () => {
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

      const timeline = gsap.timeline({
        defaults: { ease: "power3.out" },
      });

      timeline
        .from("[data-hero-copy]", {
          y: reducedMotion ? 0 : 18,
          opacity: 0,
          duration: 0.66,
          stagger: 0.08,
        })
        .from(
          "[data-stage-shell]",
          {
            y: reducedMotion ? 0 : 20,
            opacity: 0,
            scale: reducedMotion ? 1 : 0.98,
            duration: 0.9,
          },
          "-=0.42"
        );

      if (reducedMotion) return;

      gsap.to("[data-stage-shell]", {
        yPercent: 4,
        ease: "none",
        scrollTrigger: {
          trigger: scope.current,
          start: "top top",
          end: "bottom top",
          scrub: 1,
        },
      });

      gsap.to("[data-scan-line]", {
        xPercent: 240,
        duration: 3.1,
        repeat: -1,
        ease: "none",
      });

      gsap.to("[data-float-card]", {
        y: (_index, target) => (target instanceof HTMLElement && target.dataset.direction === "down" ? 8 : -8),
        duration: 2.8,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut",
        stagger: 0.18,
      });
    },
    { scope }
  );

  return (
    <section className={`section-shell ${styles.hero}`} ref={scope}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.copyColumn}>
          <span className="eyebrow" data-hero-copy>
            <Sparkles size={16} />
            Plataforma premium de experiências ao vivo
          </span>

          <h1 className={styles.title} data-hero-copy>
            Uma foto.
            <span>Várias experiências vivas.</span>
          </h1>

          <p className={styles.lead} data-hero-copy>
            Receba fotos por QR Code e transforme cada envio em galeria dinâmica, jogos interativos,
            telão ao vivo e moderação inteligente por IA.
          </p>

          <div className={styles.actions} data-hero-copy>
            <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              <CalendarRange size={18} />
              Agendar demonstração
            </a>
            <button className="button" data-variant="secondary" type="button" onClick={() => scrollToId("produto")}>
              Ver ecossistema
              <ArrowRight size={18} />
            </button>
            <a className="button" data-variant="ghost" href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              <MessageCircle size={18} />
              Falar com especialista
            </a>
          </div>

          <div className={styles.metricRow} data-hero-copy>
            {heroMetrics.map((metric) => (
              <div key={metric.label} className={styles.metricCard}>
                <strong>{metric.value}</strong>
                <span>{metric.label}</span>
              </div>
            ))}
          </div>

          <ul className={styles.bullets} data-hero-copy>
            {heroBullets.map((bullet, index) => {
              const Icon = bulletIcons[index];
              return (
                <li key={bullet}>
                  <span className={styles.bulletIcon}>
                    <Icon size={16} />
                  </span>
                  <span>{bullet}</span>
                </li>
              );
            })}
          </ul>
        </div>

        <div className={styles.stageShell} data-stage-shell>
          <div className={styles.stageHeader}>
            <span>Ecossistema visível acima da dobra</span>
            <span className={styles.stageStatus}>
              <span className={styles.statusDot} />
              27 envios ativos agora
            </span>
          </div>

          <div className={styles.phoneDock} data-float-card data-direction="up">
            <div className={styles.phoneTopline}>
              <span>
                <QrCode size={14} />
                Entrada do convidado
              </span>
              <span>sem app</span>
            </div>

            <div className={styles.phoneScreen}>
              <img src={eventImages.heroMobile.src} alt={eventImages.heroMobile.alt} loading="eager" />
              <div className={styles.phoneOverlay}>
                <div className={styles.qrPanel}>
                  <div className={styles.qrCode}>
                    {qrPattern.map((cell, index) => (
                      <span key={`${cell}-${index}`} data-filled={cell ? "true" : "false"} />
                    ))}
                  </div>
                  <p>Escaneie e envie</p>
                </div>
                <div className={styles.uploadBadge}>foto recebida em 3s</div>
              </div>
            </div>
          </div>

          <div className={styles.dashboard}>
            <div className={styles.liveRail}>
              <span>QR</span>
              <span>IA</span>
              <span>Galeria</span>
              <span>Jogos</span>
              <span>Telão</span>
              <div className={styles.scanLine} data-scan-line />
            </div>

            <div className={styles.moduleGrid}>
              <article className={`${styles.moduleCard} ${styles.galleryCard}`} data-float-card data-direction="down">
                <header>
                  <Images size={16} />
                  Galeria dinâmica
                </header>
                <div className={styles.galleryPreview}>
                  <img src={eventImages.galleryFeatured.src} alt={eventImages.galleryFeatured.alt} />
                  <img src={eventImages.gallerySecondary.src} alt={eventImages.gallerySecondary.alt} />
                  <img src={eventImages.galleryThird.src} alt={eventImages.galleryThird.alt} />
                </div>
                <p>Nova foto sobe sozinha para o destaque do mosaico.</p>
              </article>

              <article className={`${styles.moduleCard} ${styles.gamesCard}`} data-float-card data-direction="up">
                <header>
                  <Gamepad2 size={16} />
                  Jogos interativos
                </header>
                <div className={styles.gamePreview}>
                  {Array.from({ length: 9 }).map((_, index) => (
                    <span key={index} />
                  ))}
                </div>
                <div className={styles.scoreLine}>
                  <strong>Mesa 4</strong>
                  <span>1.240 pts</span>
                </div>
              </article>

              <article className={`${styles.moduleCard} ${styles.wallCard}`} data-float-card data-direction="down">
                <header>
                  <MonitorPlay size={16} />
                  Telão dinâmico
                </header>
                <div className={styles.wallPreview}>
                  <img src={eventImages.wallHero.src} alt={eventImages.wallHero.alt} />
                  <div className={styles.wallCaption}>
                    <strong>@mesa8</strong>
                    <span>layout cinematic</span>
                  </div>
                </div>
              </article>

              <article className={`${styles.moduleCard} ${styles.aiCard}`} data-float-card data-direction="up">
                <header>
                  <ShieldCheck size={16} />
                  IA em operação
                </header>
                <div className={styles.aiPreview}>
                  <div className={styles.aiLane}>
                    <span>Aprovado</span>
                    <span>Bloqueado</span>
                    <span>Busca facial</span>
                  </div>
                  <div className={styles.aiMeter}>
                    <span>risco</span>
                    <strong>0 antes do telão</strong>
                  </div>
                </div>
              </article>
            </div>

            <div className={styles.faceStrip} data-float-card data-direction="down">
              <div className={styles.faceIntro}>
                <ScanFace size={16} />
                Busca facial encontra matches em segundos
              </div>
              <div className={styles.faceMatches}>
                <img src={eventImages.faceSelfie.src} alt={eventImages.faceSelfie.alt} />
                <img src={eventImages.faceMatchOne.src} alt={eventImages.faceMatchOne.alt} />
                <img src={eventImages.faceMatchTwo.src} alt={eventImages.faceMatchTwo.alt} />
                <img src={eventImages.faceMatchThree.src} alt={eventImages.faceMatchThree.alt} />
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
