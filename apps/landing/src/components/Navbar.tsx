import { useEffect, useState, type MouseEvent } from "react";
import { ArrowUpRight, Menu, Sparkles, X, Zap } from "lucide-react";
import styles from "./Navbar.module.scss";
import { navItems } from "@/data/landing";
import { useActiveSection } from "@/hooks/useActiveSection";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { siteConfig } from "@/config/site";

export default function Navbar() {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const activeSection = useActiveSection(navItems.map((item) => item.id), 150);
  const { scrollToId } = useSmoothScroll();

  useEffect(() => {
    const onScroll = () => {
      setIsScrolled(window.scrollY > 16);
    };

    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = isOpen ? "hidden" : "";

    return () => {
      document.body.style.overflow = "";
    };
  }, [isOpen]);

  const handleSectionClick = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
    event.preventDefault();
    setIsOpen(false);
    scrollToId(id);
    window.history.replaceState(null, "", `#${id}`);
  };

  return (
    <header className={`${styles.header} ${isScrolled ? styles.scrolled : ""}`}>
      <div className={`container ${styles.inner}`}>
        <a
          href="#top"
          className={styles.brand}
          onClick={(event) => handleSectionClick(event, "top")}
          aria-label="Voltar para o topo"
        >
          <span className={styles.mark}>
            <Zap size={18} />
          </span>
          <span className={styles.wordmark}>
            Evento Vivo
            <small>experiencias visuais ao vivo</small>
          </span>
        </a>

        <nav className={styles.nav} aria-label="Navegação principal">
          {navItems.map((item) => (
            <a
              key={item.id}
              href={`#${item.id}`}
              onClick={(event) => handleSectionClick(event, item.id)}
              className={activeSection === item.id ? styles.active : ""}
            >
              {item.label}
            </a>
          ))}
        </nav>

        <div className={styles.actions}>
          <a className="button" data-variant="secondary" href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
            <Sparkles size={16} />
            Falar com especialista
          </a>

          <a className={styles.desktopCta} href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
            Agendar demonstracao
            <ArrowUpRight size={16} />
          </a>

          <button
            type="button"
            className={styles.menuButton}
            onClick={() => setIsOpen((open) => !open)}
            aria-expanded={isOpen}
            aria-controls="landing-mobile-menu"
          >
            {isOpen ? <X size={20} /> : <Menu size={20} />}
          </button>
        </div>
      </div>

      <div className={`${styles.mobilePanel} ${isOpen ? styles.open : ""}`} id="landing-mobile-menu">
        <div className={styles.mobileCard}>
          <p className={styles.mobileIntro}>Navegue pelo ecossistema completo</p>

          <div className={styles.mobileLinks}>
            {navItems.map((item) => (
              <a key={item.id} href={`#${item.id}`} onClick={(event) => handleSectionClick(event, item.id)}>
                {item.label}
              </a>
            ))}
          </div>

          <div className={styles.mobileButtons}>
            <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              Agendar demonstracao
            </a>
            <a className="button" data-variant="ghost" href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              WhatsApp comercial
            </a>
          </div>
        </div>
      </div>
    </header>
  );
}
