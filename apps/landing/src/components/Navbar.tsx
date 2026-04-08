import { useEffect, useState, type MouseEvent } from "react";
import {
  ArrowUpRight,
  Menu,
  MessageCircle,
  X,
  Zap,
} from "lucide-react";
import { motion, useMotionValueEvent, useScroll, useSpring } from "motion/react";
import styles from "./Navbar.module.scss";
import { navItems } from "@/data/landing";
import { useActiveSection } from "@/hooks/useActiveSection";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";
import { siteConfig } from "@/config/site";

export default function Navbar() {
  const [isScrolled, setIsScrolled] = useState(false);
  const [isCompact, setIsCompact] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [manualActiveSection, setManualActiveSection] = useState<string | null>(null);
  const activeSection = useActiveSection(navItems.map((item) => item.id), 150);
  const { scrollToId } = useSmoothScroll();
  const { scrollY, scrollYProgress } = useScroll();
  const progressScaleX = useSpring(scrollYProgress, {
    stiffness: 160,
    damping: 28,
    mass: 0.24,
  });

  useMotionValueEvent(scrollY, "change", (current) => {
    const previous = scrollY.getPrevious() ?? 0;
    setIsScrolled(current > 14);
    setIsCompact(current > 72 && current > previous);
  });

  useEffect(() => {
    document.body.style.overflow = isOpen ? "hidden" : "";

    return () => {
      document.body.style.overflow = "";
    };
  }, [isOpen]);

  useEffect(() => {
    if (!manualActiveSection) {
      return undefined;
    }

    if (manualActiveSection === activeSection) {
      setManualActiveSection(null);
      return undefined;
    }

    const timer = window.setTimeout(() => {
      setManualActiveSection(null);
    }, 1600);

    return () => window.clearTimeout(timer);
  }, [activeSection, manualActiveSection]);

  const handleSectionClick = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
    event.preventDefault();
    setIsOpen(false);
    setManualActiveSection(id);
    scrollToId(id);
    window.history.replaceState(null, "", `#${id}`);
  };

  const displayActiveSection = manualActiveSection ?? activeSection;
  const activeItem = navItems.find((item) => item.id === displayActiveSection) || navItems[0];

  return (
    <header className={`${styles.header} ${isScrolled ? styles.scrolled : ""} ${isCompact ? styles.compact : ""}`}>
      <motion.div className={styles.progressBar} style={{ scaleX: progressScaleX }} />

      <div className={`container ${styles.shell}`}>
        <div className={styles.inner}>
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

          <nav className={styles.nav} aria-label="Navegacao principal">
            {navItems.map((item) => (
              <a
                key={item.id}
                href={`#${item.id}`}
                onClick={(event) => handleSectionClick(event, item.id)}
                className={displayActiveSection === item.id ? styles.active : ""}
              >
                {item.label}
              </a>
            ))}
          </nav>

          <div className={styles.actions}>
            <a className={styles.secondaryAction} href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              <MessageCircle size={16} />
              WhatsApp
            </a>

            <a className={styles.desktopCta} href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              Agendar demonstração
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
      </div>

      <div className={`${styles.mobilePanel} ${isOpen ? styles.open : ""}`} id="landing-mobile-menu">
        <div className={styles.mobileCard}>
          <div className={styles.mobileIntro}>
            <strong>Agora em {activeItem.label}</strong>
            <span>Menu principal</span>
          </div>

          <div className={styles.mobileLinks}>
            {navItems.map((item) => (
              <a key={item.id} href={`#${item.id}`} onClick={(event) => handleSectionClick(event, item.id)}>
                {item.label}
              </a>
            ))}
          </div>

          <div className={styles.mobileButtons}>
            <a className="button" data-variant="primary" href={siteConfig.primaryCtaUrl} target="_blank" rel="noreferrer">
              Agendar demonstração
            </a>
            <a className="button" data-variant="ghost" href={siteConfig.whatsappUrl} target="_blank" rel="noreferrer">
              Falar no WhatsApp
            </a>
          </div>
        </div>
      </div>
    </header>
  );
}
