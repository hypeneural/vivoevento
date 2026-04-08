import { useRef, useState, useMemo, useEffect, KeyboardEvent } from "react";
import { AnimatePresence, motion, useReducedMotion } from "motion/react";
import { useGSAP } from "@gsap/react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import { ArrowRight, Check } from "lucide-react";
import styles from "./ExperienceModulesSection.module.scss";
import { experienceModulesContent, type ExperienceModule } from "@/data/landing";
import { usePersona } from "@/contexts/PersonaContext";
import { VideoWithFallback } from "@/utils/videoFallback";
import { ImageWithFallback } from "@/utils/imageFallback";

gsap.registerPlugin(useGSAP, ScrollTrigger);

export default function ExperienceModulesSection() {
  const scope = useRef<HTMLDivElement>(null);
  const tabListRef = useRef<HTMLDivElement>(null);
  const reduceMotion = useReducedMotion();
  const { selectedPersona, entryVariation } = usePersona();
  
  // Get persona-based module priority
  const persona = selectedPersona || entryVariation;
  
  // Reorder modules based on persona priority
  const orderedModules = useMemo(() => {
    if (!persona) {
      return experienceModulesContent.modules;
    }
    
    const priority = experienceModulesContent.priority[persona];
    
    // Create a map for quick lookup
    const moduleMap = new Map(
      experienceModulesContent.modules.map(m => [m.id, m])
    );
    
    // Reorder based on priority
    const ordered = priority
      .map(id => moduleMap.get(id))
      .filter((m): m is ExperienceModule => m !== undefined);
    
    // Add any modules not in priority list at the end
    const prioritySet = new Set(priority);
    const remaining = experienceModulesContent.modules.filter(
      m => !prioritySet.has(m.id)
    );
    
    return [...ordered, ...remaining];
  }, [persona]);
  
  // Set default active module to first in prioritized order
  const [activeModuleId, setActiveModuleId] = useState<ExperienceModule["id"]>(
    orderedModules[0]?.id || "gallery"
  );
  
  // Update active module when persona changes
  useEffect(() => {
    setActiveModuleId(orderedModules[0]?.id || "gallery");
  }, [orderedModules]);

  /**
   * Keyboard navigation handler following WAI-ARIA APG Tab pattern
   * https://www.w3.org/WAI/ARIA/apg/patterns/tabs/
   * 
   * Supports:
   * - Arrow Left/Right: Navigate between tabs
   * - Home: Focus first tab
   * - End: Focus last tab
   */
  const handleTabKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    const currentIndex = orderedModules.findIndex(m => m.id === activeModuleId);
    let nextIndex = currentIndex;

    switch (event.key) {
      case "ArrowLeft":
        event.preventDefault();
        nextIndex = currentIndex > 0 ? currentIndex - 1 : orderedModules.length - 1;
        break;
      
      case "ArrowRight":
        event.preventDefault();
        nextIndex = currentIndex < orderedModules.length - 1 ? currentIndex + 1 : 0;
        break;
      
      case "Home":
        event.preventDefault();
        nextIndex = 0;
        break;
      
      case "End":
        event.preventDefault();
        nextIndex = orderedModules.length - 1;
        break;
      
      default:
        return;
    }

    const nextModule = orderedModules[nextIndex];
    if (nextModule) {
      setActiveModuleId(nextModule.id);
      
      // Focus the next tab button
      const tabButtons = tabListRef.current?.querySelectorAll<HTMLButtonElement>('[role="tab"]');
      if (tabButtons && tabButtons[nextIndex]) {
        tabButtons[nextIndex].focus();
      }
    }
  };

  useGSAP(
    () => {
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

      gsap.from("[data-module-header]", {
        y: reducedMotion ? 0 : 24,
        opacity: 0,
        duration: 0.8,
        scrollTrigger: {
          trigger: scope.current,
          start: "top 72%",
        },
      });

      gsap.from("[data-module-tab]", {
        y: reducedMotion ? 0 : 16,
        opacity: 0,
        stagger: 0.08,
        duration: 0.6,
        scrollTrigger: {
          trigger: scope.current,
          start: "top 68%",
        },
      });
    },
    { scope }
  );

  const activeModule = orderedModules.find((m) => m.id === activeModuleId) ?? orderedModules[0];

  return (
    <section id="recursos" className={`section-shell ${styles.section}`} ref={scope}>
      <div className={`container ${styles.container}`}>
        <header className={styles.header} data-module-header>
          <span className="eyebrow">{experienceModulesContent.eyebrow}</span>
          <h2 className="section-title">{experienceModulesContent.title}</h2>
          <p className="section-lead">{experienceModulesContent.subtitle}</p>
        </header>

        <div className={styles.moduleTabs} role="tablist" aria-label="Módulos de experiência" ref={tabListRef}>
          {orderedModules.map((module) => {
            const isActive = module.id === activeModuleId;

            return (
              <button
                key={module.id}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-controls={`module-panel-${module.id}`}
                id={`module-tab-${module.id}`}
                tabIndex={isActive ? 0 : -1}
                className={`${styles.moduleTab} ${isActive ? styles.activeTab : ""}`}
                data-module-tab
                onClick={() => setActiveModuleId(module.id)}
                onKeyDown={handleTabKeyDown}
              >
                {!reduceMotion && isActive ? (
                  <motion.span layoutId="module-indicator" className={styles.tabIndicator} />
                ) : null}
                <span className={styles.tabLabel}>{module.name}</span>
                <span className={styles.tabEyebrow}>{module.eyebrow}</span>
              </button>
            );
          })}
        </div>

        <AnimatePresence mode="wait">
          <motion.div
            key={activeModule.id}
            id={`module-panel-${activeModule.id}`}
            role="tabpanel"
            aria-labelledby={`module-tab-${activeModule.id}`}
            tabIndex={0}
            className={styles.moduleContent}
            initial={{ opacity: 0, y: reduceMotion ? 0 : 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: reduceMotion ? 0 : -20 }}
            transition={{ duration: reduceMotion ? 0.01 : 0.3 }}
          >
            <div className={styles.contentGrid}>
              <div className={styles.visualColumn}>
                <div className={styles.visualFrame} role="img" aria-label={activeModule.visual.alt}>
                  {activeModule.visual.type === "image" ? (
                    <ImageWithFallback
                      src={activeModule.visual.src}
                      alt={activeModule.visual.alt}
                      loading="lazy"
                      decoding="async"
                      className={styles.moduleImage}
                      fallbackText="Imagem de demonstração indisponível"
                    />
                  ) : activeModule.visual.type === "video" ? (
                    <VideoWithFallback
                      src={activeModule.visual.src}
                      poster={activeModule.visual.poster}
                      autoPlay
                      loop
                      muted
                      playsInline
                      className={styles.moduleVideo}
                      aria-label={activeModule.visual.alt}
                      fallbackText="Vídeo de demonstração indisponível"
                      showPlayButton
                    />
                  ) : (
                    <div className={styles.moduleDemoPlaceholder} role="presentation">
                      <span>Demo interativa</span>
                      <p>{activeModule.visual.alt}</p>
                    </div>
                  )}
                  <div className={styles.visualBadge} aria-label={`Categoria: ${activeModule.eyebrow}`}>
                    <span>{activeModule.eyebrow}</span>
                  </div>
                </div>
              </div>

              <div className={styles.detailsColumn}>
                <div className={styles.moduleHeader}>
                  <h3 className={styles.moduleTitle}>{activeModule.title}</h3>
                  <p className={styles.moduleBenefit}>{activeModule.benefit}</p>
                </div>

                <ul className={styles.featureList} role="list">
                  {activeModule.features.map((feature, index) => (
                    <li key={index} className={styles.featureItem}>
                      <Check size={20} className={styles.featureIcon} aria-hidden="true" />
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>

                {activeModule.cta ? (
                  <div className={styles.moduleCTA}>
                    <button className="button" data-variant="secondary" type="button">
                      {activeModule.cta.text}
                      <ArrowRight size={18} />
                    </button>
                  </div>
                ) : null}
              </div>
            </div>
          </motion.div>
        </AnimatePresence>

        <div className={styles.moduleFooter}>
          <p>
            <strong>Prioridade é de módulos, não de seções.</strong> Cada foto alimenta galeria, telão, jogos e busca facial
            simultaneamente, criando um ecossistema integrado.
          </p>
        </div>
      </div>
    </section>
  );
}
