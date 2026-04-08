import { X, Calendar, MessageCircle } from "lucide-react";
import { useState, useEffect } from "react";
import { useScrollUI } from "@/contexts/ScrollUIContext";
import { useAttribution } from "@/contexts/AttributionContext";
import { buildCTAUrl } from "@/utils/routing";
import styles from "./CTAFloating.module.scss";

export interface CTAFloatingProps {
  /**
   * Callback para rastrear interações com o CTA flutuante
   * @param action - Tipo de ação: 'primary' | 'secondary' | 'close'
   */
  onInteraction?: (action: 'primary' | 'secondary' | 'close') => void;
}

/**
 * Componente de CTA flutuante
 * 
 * Implementa requisitos 20, 36, 22:
 * - Aparece após 80% de scroll sem clicar CTA (Req 20)
 * - Microconversão para reduzir fricção (Req 36)
 * - Acessibilidade: não bloqueia conteúdo, permite fechar, navegação por teclado (Req 22)
 * 
 * Design discreto e não intrusivo, posicionado no canto inferior direito.
 * Respeita escolha do usuário de dispensar (persiste em sessionStorage).
 */
export default function CTAFloating({ onInteraction }: CTAFloatingProps) {
  const { showFloatingCTA } = useScrollUI();
  const { utmParams } = useAttribution();
  const [isDismissed, setIsDismissed] = useState(false);
  const [isVisible, setIsVisible] = useState(false);

  // Check if user has dismissed the floating CTA in this session
  useEffect(() => {
    const dismissed = sessionStorage.getItem('ev_floating_cta_dismissed');
    if (dismissed === 'true') {
      setIsDismissed(true);
    }
  }, []);

  // Show/hide based on scroll position and dismissal state
  useEffect(() => {
    if (showFloatingCTA && !isDismissed) {
      // Small delay for smooth entrance
      const timer = setTimeout(() => setIsVisible(true), 100);
      return () => clearTimeout(timer);
    } else {
      setIsVisible(false);
    }
  }, [showFloatingCTA, isDismissed]);

  const handleClose = () => {
    setIsVisible(false);
    setIsDismissed(true);
    sessionStorage.setItem('ev_floating_cta_dismissed', 'true');
    onInteraction?.('close');
  };

  const handlePrimaryClick = () => {
    onInteraction?.('primary');
    // Track conversion in analytics
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
    // Track conversion in analytics
    if (typeof window !== 'undefined' && (window as any).gtag) {
      (window as any).gtag('event', 'click', {
        event_category: 'CTA',
        event_label: 'Floating CTA - Secondary',
        value: 1,
      });
    }
  };

  // Build CTA URLs with UTM params
  const primaryUrl = buildCTAUrl(
    'https://eventovivo.com/agendar-demonstracao',
    utmParams
  );

  const secondaryUrl = buildCTAUrl(
    'https://wa.me/5511999999999?text=Olá,%20quero%20conhecer%20a%20plataforma%20Evento%20Vivo',
    utmParams
  );

  // Don't render if dismissed
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
          <X size={18} aria-hidden="true" />
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
