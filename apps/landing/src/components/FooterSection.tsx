import { ArrowUpRight, Instagram, Linkedin, Zap } from "lucide-react";
import styles from "./FooterSection.module.scss";
import { navItems } from "@/data/landing";
import { siteConfig } from "@/config/site";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

export default function FooterSection() {
  const { scrollToId } = useSmoothScroll();

  return (
    <footer className={styles.footer}>
      <div className={`container ${styles.grid}`}>
        <div className={styles.brandColumn}>
          <div className={styles.logo}>
            <span className={styles.mark}>
              <Zap size={18} />
            </span>
            <div>
              <strong>Evento Vivo</strong>
              <p>Plataforma premium de experiências visuais ao vivo para eventos.</p>
            </div>
          </div>

          <div className={styles.socials}>
            <a href={siteConfig.instagramUrl} target="_blank" rel="noreferrer" aria-label="Instagram">
              <Instagram size={18} />
            </a>
            {siteConfig.linkedinUrl ? (
              <a href={siteConfig.linkedinUrl} target="_blank" rel="noreferrer" aria-label="LinkedIn">
                <Linkedin size={18} />
              </a>
            ) : null}
          </div>
        </div>

        <div className={styles.linksColumn}>
          <h3>Produto</h3>
          <div className={styles.links}>
            {navItems.map((item) => (
              <button key={item.id} type="button" onClick={() => scrollToId(item.id)}>
                {item.label}
              </button>
            ))}
          </div>
        </div>

        <div className={styles.linksColumn}>
          <h3>Comercial</h3>
          <div className={styles.links}>
            <a href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              Agendar demonstracao
            </a>
            <a href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              Falar com especialista
            </a>
            <a href={siteConfig.adminUrl} target="_blank" rel="noreferrer">
              Area do cliente
            </a>
          </div>
        </div>

        <a className={styles.backToTop} href="#top" onClick={(event) => {
          event.preventDefault();
          scrollToId("top");
        }}>
          Voltar ao topo
          <ArrowUpRight size={16} />
        </a>
      </div>

      <div className={`container ${styles.bottom}`}>
        <span>{new Date().getFullYear()} Evento Vivo. Todos os direitos reservados.</span>
        <span>Suporte premium para operações ao vivo, QR Code, IA e experiências visuais.</span>
      </div>
    </footer>
  );
}
