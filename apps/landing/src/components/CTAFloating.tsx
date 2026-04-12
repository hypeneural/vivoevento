import { X, Calendar, MessageCircle } from "lucide-react";
import { useState, useEffect } from "react";
import { useScrollUI } from "@/contexts/ScrollUIContext";
import { useAttribution } from "@/contexts/AttributionContext";
import { buildCTAUrl } from "@/utils/routing";
import styles from "./CTAFloating.module.scss";

export interface CTAFloatingProps {
  onInteraction?: (action: 'primary' | 'secondary' | 'close') => void;
}

const DISMISS_STORAGE_KEY = "ev_floating_cta_dismissed";

export default function CTAFloating({ onInteraction }: CTAFloatingProps) {
  const { showFloatingCTA } = useScrollUI();
  const { utmParams } = useAttribution();
  const [isDismissed, setIsDismissed] = useState(() => {
    if (typeof window === "undefined") {
      return false;
    }

    return window.sessionStorage.getItem(DISMISS_STORAGE_KEY) === "true";
  });
  const [isVisible, setIsVisible] = useState(false);

  // Show/hide based on scroll position and dismissal state
  useEffect(() => {
    if (showFloatingCTA && !isDismissed) {
      const timer = setTimeout(() => setIsVisible(true), 200);
      return () => clearTimeout(timer);
    } else {
      setIsVisible(false);
    }
  }, [showFloatingCTA, isDismissed]);

  const handleClose = () => {
    setIsVisible(false);
    if (typeof window !== "undefined") {
      window.sessionStorage.setItem(DISMISS_STORAGE_KEY, "true");
    }
    // Delay dismiss to allow exit animation
    setTimeout(() => setIsDismissed(true), 350);
    onInteraction?.('close');
  };

  const handlePrimaryClick = () => {
    onInteraction?.('primary');
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'click', {
        event_category: 'CTA',
        event_label: 'Floating CTA - Primary',
        value: 1,
      });
    }
  };

  const handleSecondaryClick = () => {
    onInteraction?.('secondary');
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'click', {
        event_category: 'CTA',
        event_label: 'Floating CTA - Secondary',
        value: 1,
      });
    }
  };

  const primaryUrl = buildCTAUrl(
    'https://eventovivo.com/agendar-demonstracao',
    utmParams
  );

  const secondaryUrl = buildCTAUrl(
    'https://wa.me/5511999999999?text=Olá,%20quero%20conhecer%20a%20plataforma%20Evento%20Vivo',
    utmParams
  );

  // Don't render at all only if dismissed (after animation)
  if (isDismissed) {
    return null;
  }

  return (
    <div
      className={`${styles.floatingCTA} ${isVisible ? styles.visible : ''}`}
      role="complementary"
      aria-label="Ações rápidas de conversão"
    >
      <div className={styles.card}>
        {/* Close button */}
        <button
          type="button"
          className={styles.closeButton}
          onClick={handleClose}
          aria-label="Fechar CTA flutuante"
        >
          <X size={16} aria-hidden="true" />
        </button>

        {/* Content */}
        <div className={styles.content}>
          <p className={styles.title}>Pronto para começar?</p>
          <p className={styles.subtitle}>Agende uma demonstração ou fale conosco</p>
        </div>

        {/* Actions */}
        <div className={styles.actions}>
          <a
            href={primaryUrl}
            className={styles.primaryCTA}
            onClick={handlePrimaryClick}
            aria-label="Agendar demonstração da plataforma"
          >
            <Calendar size={18} aria-hidden="true" />
            <span>Agendar demonstração</span>
          </a>

          <a
            href={secondaryUrl}
            className={styles.secondaryCTA}
            onClick={handleSecondaryClick}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Falar no WhatsApp (abre em nova aba)"
          >
            <MessageCircle size={18} aria-hidden="true" />
            <span>WhatsApp</span>
          </a>
        </div>
      </div>
    </div>
  );
}
