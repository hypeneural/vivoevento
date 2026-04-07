import { ArrowUpRight, Instagram, Linkedin, MessageCircle, Zap } from "lucide-react";
import styles from "./FooterSection.module.scss";
import { navItems } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

export default function FooterSection() {
  const { scrollToId } = useSmoothScroll();

  return (
    <footer className={styles.footer}>
      <div className={`container ${styles.callout}`}>
        <div className={styles.copy}>
          <span className="eyebrow">Feche a operacao com produto visivel</span>
          <h2>QR, galeria, jogos, telao e IA no mesmo fluxo.</h2>
          <p>
            A proposta precisa terminar simples: uma captura entra e o evento ganha exibicao,
            engajamento, moderacao e memoria premium sem improviso.
          </p>
        </div>

        <div className={styles.actions}>
          <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
            Agendar demonstracao
            <ArrowUpRight size={16} />
          </a>
          <a className={styles.inlineAction} href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
            <MessageCircle size={16} />
            WhatsApp comercial
          </a>
        </div>
      </div>

      <div className={`container ${styles.meta}`}>
        <div className={styles.brand}>
          <span className={styles.mark}>
            <Zap size={16} />
          </span>
          <div>
            <strong>Evento Vivo</strong>
            <p>Plataforma premium de experiencias visuais ao vivo para eventos.</p>
          </div>
        </div>

        <nav className={styles.links} aria-label="Links do rodape">
          {navItems.slice(0, 5).map((item) => (
            <button key={item.id} type="button" onClick={() => scrollToId(item.id)}>
              {item.label}
            </button>
          ))}
        </nav>

        <div className={styles.contact}>
          <a href={siteConfig.instagramUrl} target="_blank" rel="noreferrer" aria-label="Instagram">
            <Instagram size={18} />
          </a>
          {siteConfig.linkedinUrl ? (
            <a href={siteConfig.linkedinUrl} target="_blank" rel="noreferrer" aria-label="LinkedIn">
              <Linkedin size={18} />
            </a>
          ) : null}
          <span>{siteConfig.whatsappDisplay}</span>
        </div>
      </div>

      <div className={`container ${styles.bottom}`}>
        <span>{new Date().getFullYear()} Evento Vivo. Todos os direitos reservados.</span>
        <button type="button" className={styles.backToTop} onClick={() => scrollToId("top")}>
          Voltar ao topo
          <ArrowUpRight size={14} />
        </button>
      </div>
    </footer>
  );
}
