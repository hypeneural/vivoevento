import { CalendarRange, MessageCircle } from "lucide-react";
import styles from "./FinalCTASection.module.scss";
import { siteConfig } from "@/config/site";
import MicroconversionCTAs from "./MicroconversionCTAs";
import { trackCTAClick, trackMicroconversion } from "@/utils/tracking";

export default function FinalCTASection() {
  return (
    <section className={`section-shell ${styles.section}`} aria-labelledby="final-cta-title">
      <div className="container">
        <div className={styles.content}>
          <div className={styles.copy}>
            <h2 id="final-cta-title" className={styles.title}>
              Pronto para transformar seu evento?
            </h2>
            <p className={styles.subtitle}>
              Agende uma demonstração e veja como funciona na prática.
            </p>
          </div>

          <div className={styles.actions}>
            <a 
              className="button" 
              data-variant="primary" 
              href={siteConfig.primaryCtaUrl} 
              target="_blank" 
              rel="noreferrer"
              aria-label="Agendar demonstração - abre em nova aba"
              onClick={() => trackCTAClick("primary", "final", "Agendar demonstração")}
            >
              <CalendarRange size={20} aria-hidden="true" />
              Agendar demonstração
            </a>
            <a 
              className="button" 
              data-variant="secondary" 
              href={siteConfig.whatsappUrl} 
              target="_blank" 
              rel="noreferrer"
              aria-label="Falar no WhatsApp - abre em nova aba"
              onClick={() => trackCTAClick("secondary", "final", "Falar no WhatsApp")}
            >
              <MessageCircle size={20} aria-hidden="true" />
              Falar no WhatsApp
            </a>
          </div>

          <div className={styles.microconversions}>
            <MicroconversionCTAs 
              variant="inline"
              onMicroconversion={(action) => trackMicroconversion(action, "final")}
            />
          </div>
        </div>
      </div>
    </section>
  );
}
