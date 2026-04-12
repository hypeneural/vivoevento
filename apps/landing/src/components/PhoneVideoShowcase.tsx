import { useEffect, useRef, useState } from "react";
import { useReducedMotion } from "motion/react";
import { Play, X } from "lucide-react";
import { trackMicroconversion } from "@/utils/tracking";
import styles from "./PhoneVideoShowcase.module.scss";

type PhoneVideoShowcaseProps = {
  poster: string;
  previewMp4Src: string;
  previewWebmSrc?: string;
  fullDemoMp4Src: string;
  frameSrc: string;
  title?: string;
};

export default function PhoneVideoShowcase({
  poster,
  previewMp4Src,
  previewWebmSrc,
  fullDemoMp4Src,
  frameSrc,
  title = "Demonstração do Evento Vivo",
}: PhoneVideoShowcaseProps) {
  const reduceMotion = useReducedMotion();
  const rootRef = useRef<HTMLDivElement | null>(null);
  const previewVideoRef = useRef<HTMLVideoElement | null>(null);
  const modalVideoRef = useRef<HTMLVideoElement | null>(null);

  const [shouldLoadPreview, setShouldLoadPreview] = useState(false);
  const [isInView, setIsInView] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);

  useEffect(() => {
    const node = rootRef.current;
    if (!node) {
      return undefined;
    }

    if (typeof IntersectionObserver === "undefined") {
      setShouldLoadPreview(true);
      setIsInView(true);
      return undefined;
    }

    const observer = new IntersectionObserver(
      ([entry]) => {
        const visible = entry.isIntersecting || entry.intersectionRatio > 0;
        setIsInView(visible);

        if (visible) {
          setShouldLoadPreview(true);
        }
      },
      {
        rootMargin: "280px 0px",
        threshold: 0.2,
      },
    );

    observer.observe(node);

    return () => {
      observer.disconnect();
    };
  }, []);

  useEffect(() => {
    const video = previewVideoRef.current;
    if (!video || !shouldLoadPreview) {
      return;
    }

    if (reduceMotion || isModalOpen || !isInView) {
      video.pause();
      return;
    }

    const playPromise = video.play();
    if (playPromise && typeof playPromise.catch === "function") {
      playPromise.catch(() => {
        // Mantém o poster como fallback visual se o autoplay falhar.
      });
    }
  }, [isInView, isModalOpen, reduceMotion, shouldLoadPreview]);

  useEffect(() => {
    if (!isModalOpen) {
      return undefined;
    }

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsModalOpen(false);
      }
    };

    document.addEventListener("keydown", handleKeyDown);

    const modalVideo = modalVideoRef.current;
    if (modalVideo) {
      const playPromise = modalVideo.play();
      if (playPromise && typeof playPromise.catch === "function") {
        playPromise.catch(() => {
          // Alguns navegadores ainda exigem ação adicional do usuário.
        });
      }
    }

    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", handleKeyDown);
      modalVideoRef.current?.pause();
    };
  }, [isModalOpen]);

  const handleOpenModal = () => {
    trackMicroconversion("watch-demo", "hero");
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
  };

  return (
    <>
      <div className={styles.stage} ref={rootRef}>
        <div className={styles.glow} aria-hidden="true" />
        <div className={styles.glowSecondary} aria-hidden="true" />

        <div className={styles.phoneScene}>
          <div className={styles.liveBadge} role="status" aria-live="polite">
            <span className={styles.liveDot} aria-hidden="true" />
            Ao vivo no evento
          </div>

          <div className={styles.signalPill}>
            <span>36s de demonstração real</span>
            <strong>QR + galeria + telão</strong>
          </div>

          <div className={styles.phone}>
            <div className={styles.viewport}>
              {!shouldLoadPreview ? (
                <img
                  src={poster}
                  alt={title}
                  className={styles.poster}
                  loading="eager"
                  decoding="async"
                />
              ) : (
                <video
                  ref={previewVideoRef}
                  className={styles.video}
                  poster={poster}
                  muted
                  playsInline
                  loop
                  preload="metadata"
                  aria-label={title}
                >
                  {previewWebmSrc ? <source src={previewWebmSrc} type="video/webm" /> : null}
                  <source src={previewMp4Src} type="video/mp4" />
                </video>
              )}

              <div className={styles.viewportShade} aria-hidden="true" />
            </div>

            <img
              src={frameSrc}
              alt=""
              aria-hidden="true"
              className={styles.frame}
              loading="eager"
              decoding="async"
            />

            <div className={styles.reflection} aria-hidden="true" />
          </div>

          <div className={styles.proofCard}>
            <p className={styles.proofEyebrow}>Veja acontecendo</p>
            <p className={styles.proofText}>
              Convidados enviam fotos em tempo real e a experiência já aparece na
              galeria, no telão e nas ativações do evento.
            </p>

            <button
              type="button"
              className={styles.watchButton}
              onClick={handleOpenModal}
              aria-label="Ver demonstração completa em vídeo"
            >
              <Play size={16} aria-hidden="true" />
              Ver demonstração completa
            </button>
          </div>
        </div>
      </div>

      {isModalOpen ? (
        <div className={styles.modalBackdrop} onClick={handleCloseModal}>
          <div
            className={styles.modalCard}
            role="dialog"
            aria-modal="true"
            aria-labelledby="hero-demo-title"
            onClick={(event) => event.stopPropagation()}
          >
            <div className={styles.modalHeader}>
              <div>
                <p className={styles.modalEyebrow}>Demonstração completa</p>
                <h3 id="hero-demo-title" className={styles.modalTitle}>
                  {title}
                </h3>
              </div>

              <button
                type="button"
                className={styles.closeButton}
                onClick={handleCloseModal}
                aria-label="Fechar vídeo"
              >
                <X size={18} aria-hidden="true" />
              </button>
            </div>

            <div className={styles.modalVideoWrap}>
              <video
                ref={modalVideoRef}
                className={styles.modalVideo}
                poster={poster}
                controls
                playsInline
                preload="metadata"
              >
                <source src={fullDemoMp4Src} type="video/mp4" />
              </video>
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
