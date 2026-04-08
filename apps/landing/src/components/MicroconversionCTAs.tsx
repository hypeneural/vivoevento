import { Eye, Play, Sparkles } from "lucide-react";
import { type MouseEvent } from "react";
import styles from "./MicroconversionCTAs.module.scss";
import { useSmoothScroll } from "@/hooks/useSmoothScroll";

export type MicroconversionAction = "view-example" | "watch-demo" | "open-visual-demo";

export interface MicroconversionCTAsProps {
  /**
   * Callback para rastrear microconversões
   * @param action - Tipo de ação de microconversão
   */
  onMicroconversion?: (action: MicroconversionAction) => void;
  
  /**
   * Variante de layout: inline (horizontal) ou stacked (vertical)
   * @default "inline"
   */
  variant?: "inline" | "stacked";
  
  /**
   * Classe CSS adicional
   */
  className?: string;
}

/**
 * Componente de CTAs de microconversão
 * 
 * Implementa requisito 36: Microconversões e Redução de Fricção
 * 
 * Oferece ações de baixo risco para visitantes que ainda não estão prontos
 * para conversão macro (agendar demonstração):
 * - Ver evento exemplo
 * - Ver como funciona em 30s
 * - Abrir demonstração visual
 * 
 * Posicionado próximo aos CTAs principais para oferecer caminho alternativo
 * com menor fricção.
 */
export default function MicroconversionCTAs({
  onMicroconversion,
  variant = "inline",
  className = "",
}: MicroconversionCTAsProps) {
  const { scrollToId } = useSmoothScroll();

  const handleViewExample = (event: MouseEvent<HTMLButtonElement>) => {
    event.preventDefault();
    
    // Track microconversion
    onMicroconversion?.("view-example");
    
    // Scroll to testimonials section (real event examples)
    scrollToId("depoimentos");
    window.history.replaceState(null, "", "#depoimentos");
  };

  const handleWatchDemo = (event: MouseEvent<HTMLButtonElement>) => {
    event.preventDefault();
    
    // Track microconversion
    onMicroconversion?.("watch-demo");
    
    // Scroll to how it works section (30s explanation)
    scrollToId("como-funciona");
    window.history.replaceState(null, "", "#como-funciona");
  };

  const handleOpenVisualDemo = (event: MouseEvent<HTMLButtonElement>) => {
    event.preventDefault();
    
    // Track microconversion
    onMicroconversion?.("open-visual-demo");
    
    // Scroll to experience modules section (visual demo)
    scrollToId("recursos");
    window.history.replaceState(null, "", "#recursos");
  };

  return (
    <div 
      className={`${styles.microconversionCTAs} ${styles[variant]} ${className}`}
      role="group"
      aria-label="Ações de exploração sem compromisso"
    >
      <button
        type="button"
        className={styles.microCTA}
        onClick={handleViewExample}
        aria-label="Ver exemplo de evento real"
      >
        <Eye size={16} aria-hidden="true" />
        <span>Ver evento exemplo</span>
      </button>

      <button
        type="button"
        className={styles.microCTA}
        onClick={handleWatchDemo}
        aria-label="Ver explicação rápida de 30 segundos"
      >
        <Play size={16} aria-hidden="true" />
        <span>Ver como funciona em 30s</span>
      </button>

      <button
        type="button"
        className={styles.microCTA}
        onClick={handleOpenVisualDemo}
        aria-label="Abrir demonstração visual interativa"
      >
        <Sparkles size={16} aria-hidden="true" />
        <span>Abrir demonstração visual</span>
      </button>
    </div>
  );
}
