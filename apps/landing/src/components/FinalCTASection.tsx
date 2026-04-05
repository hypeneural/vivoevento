import { ArrowUpRight, CalendarRange, MessageCircle } from "lucide-react";
import styles from "./FinalCTASection.module.scss";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

export default function FinalCTASection() {
  const { scrollToId } = useSmoothScroll();

  return (
    <section className={`section-shell ${styles.section}`}>
      <div className="container">
        <div className={styles.panel}>
          <div className={styles.copy}>
            <span className="eyebrow">Experiência viva para eventos reais</span>
            <h2 className="section-title">Seu evento nao precisa so de um telao. Precisa de uma experiencia viva.</h2>
            <p className="section-lead">
              Transforme fotos em galeria, jogos, telão e inteligência ao vivo com uma plataforma pensada
              para impacto visual, segurança e operação confiável.
            </p>
          </div>

          <div className={styles.actions}>
            <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              <CalendarRange size={18} />
              Agendar demonstracao
            </a>
            <button className="button" data-variant="secondary" type="button" onClick={() => scrollToId("precos")}>
              Ver planos
            </button>
            <a className="button" data-variant="ghost" href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              <MessageCircle size={18} />
              Falar com especialista
            </a>
          </div>

          <div className={styles.ribbon}>
            <span>QR Code sem app</span>
            <span>Jogos com fotos reais</span>
            <span>IA antes do telão</span>
            <span>Busca facial em segundos</span>
            <span>Operação premium</span>
            <ArrowUpRight size={16} />
          </div>
        </div>
      </div>
    </section>
  );
}
