/**
 * Analytics and Tracking Utilities
 * 
 * Implementa requisito 26: Analytics e Conversão
 * Implementa requisito 36: Microconversões e Redução de Fricção
 * 
 * Rastreia:
 * - Cliques em CTAs primários e secundários
 * - Microconversões (ações de baixo risco)
 * - Scroll depth
 * - Interações com seções
 * - Origem via parâmetros UTM
 */

export type CTAType = "primary" | "secondary";
export type CTALocation = "hero" | "navbar" | "pricing" | "final" | "floating";
export type MicroconversionAction = "view-example" | "watch-demo" | "open-visual-demo";
export type ScrollDepth = 25 | 50 | 75 | 100;

export interface TrackingEvent {
  event: string;
  category: string;
  action: string;
  label?: string;
  value?: number;
  [key: string]: unknown;
}

/**
 * Envia evento de tracking para Google Analytics (gtag)
 * Se gtag não estiver disponível, registra no console em desenvolvimento
 */
function sendTrackingEvent(event: TrackingEvent): void {
  // Check if gtag is available (Google Analytics)
  if (typeof window !== "undefined" && "gtag" in window && typeof window.gtag === "function") {
    window.gtag("event", event.action, {
      event_category: event.category,
      event_label: event.label,
      value: event.value,
      ...event,
    });
  } else if (import.meta.env.DEV) {
    // Log to console in development
    console.log("[Tracking]", event);
  }
}

/**
 * Rastreia clique em CTA primário ou secundário
 * 
 * @param type - Tipo de CTA (primary ou secondary)
 * @param location - Localização do CTA na página
 * @param label - Label adicional (opcional)
 */
export function trackCTAClick(type: CTAType, location: CTALocation, label?: string): void {
  sendTrackingEvent({
    event: "cta_click",
    category: "Conversion",
    action: `CTA Click - ${type}`,
    label: label || location,
    value: type === "primary" ? 10 : 5, // Primary CTA has higher value
  });
}

/**
 * Rastreia microconversão (ação de baixo risco)
 * 
 * Requisito 36: Microconversões e Redução de Fricção
 * 
 * @param action - Tipo de microconversão
 * @param location - Localização na página
 */
export function trackMicroconversion(action: MicroconversionAction, location: CTALocation): void {
  sendTrackingEvent({
    event: "microconversion",
    category: "Engagement",
    action: `Microconversion - ${action}`,
    label: location,
    value: 3, // Lower value than macro conversion
  });
}

/**
 * Rastreia progresso de scroll
 * 
 * Requisito 26: Analytics e Conversão
 * 
 * @param depth - Profundidade de scroll (25%, 50%, 75%, 100%)
 */
export function trackScrollDepth(depth: ScrollDepth): void {
  sendTrackingEvent({
    event: "scroll_depth",
    category: "Engagement",
    action: "Scroll Depth",
    label: `${depth}%`,
    value: depth,
  });
}

/**
 * Rastreia interação com seção específica
 * 
 * @param section - ID da seção
 * @param action - Tipo de interação (view, click, expand, etc.)
 */
export function trackSectionInteraction(section: string, action: string): void {
  sendTrackingEvent({
    event: "section_interaction",
    category: "Engagement",
    action: `Section ${action}`,
    label: section,
  });
}

/**
 * Rastreia interação com planos
 * 
 * @param planId - ID do plano
 * @param action - Tipo de interação (view, click_cta, etc.)
 */
export function trackPlanInteraction(planId: string, action: string): void {
  sendTrackingEvent({
    event: "plan_interaction",
    category: "Conversion",
    action: `Plan ${action}`,
    label: planId,
  });
}

/**
 * Rastreia interação com FAQ
 * 
 * @param questionId - ID da pergunta
 * @param action - Tipo de interação (expand, collapse)
 */
export function trackFAQInteraction(questionId: string, action: "expand" | "collapse"): void {
  sendTrackingEvent({
    event: "faq_interaction",
    category: "Engagement",
    action: `FAQ ${action}`,
    label: questionId,
  });
}

/**
 * Rastreia interação com módulos de experiência
 * 
 * @param moduleId - ID do módulo (gallery, wall, games, face)
 * @param action - Tipo de interação (view, click, etc.)
 */
export function trackModuleInteraction(moduleId: string, action: string): void {
  sendTrackingEvent({
    event: "module_interaction",
    category: "Engagement",
    action: `Module ${action}`,
    label: moduleId,
  });
}

/**
 * Rastreia seleção de persona
 * 
 * @param personaId - ID da persona selecionada
 */
export function trackPersonaSelection(personaId: string): void {
  sendTrackingEvent({
    event: "persona_selection",
    category: "Engagement",
    action: "Persona Selected",
    label: personaId,
  });
}

/**
 * Captura parâmetros UTM da URL
 * 
 * Requisito 26: Analytics e Conversão
 * 
 * @returns Objeto com parâmetros UTM
 */
export function getUTMParams(): Record<string, string> {
  if (typeof window === "undefined") {
    return {};
  }

  const params = new URLSearchParams(window.location.search);
  
  return {
    utm_source: params.get("utm_source") || "",
    utm_medium: params.get("utm_medium") || "",
    utm_campaign: params.get("utm_campaign") || "",
    utm_content: params.get("utm_content") || "",
    utm_term: params.get("utm_term") || "",
  };
}

/**
 * Rastreia origem do visitante via parâmetros UTM
 * Deve ser chamado uma vez no carregamento da página
 */
export function trackPageView(): void {
  const utmParams = getUTMParams();
  
  sendTrackingEvent({
    event: "page_view",
    category: "Navigation",
    action: "Page View",
    label: window.location.pathname,
    ...utmParams,
  });
}

/**
 * Hook de scroll depth tracking
 * Rastreia automaticamente quando visitante atinge marcos de scroll
 */
export function initScrollDepthTracking(): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  const trackedDepths = new Set<ScrollDepth>();

  const handleScroll = () => {
    const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
    const scrolled = window.scrollY;
    const scrollPercent = (scrolled / scrollHeight) * 100;

    const depths: ScrollDepth[] = [25, 50, 75, 100];
    
    for (const depth of depths) {
      if (scrollPercent >= depth && !trackedDepths.has(depth)) {
        trackedDepths.add(depth);
        trackScrollDepth(depth);
      }
    }
  };

  window.addEventListener("scroll", handleScroll, { passive: true });

  return () => {
    window.removeEventListener("scroll", handleScroll);
  };
}

/**
 * Rastreia seção de maior abandono
 * Registra última seção visível antes de sair da página
 */
export function initAbandonmentTracking(): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  let lastVisibleSection = "";

  const observer = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting && entry.target.id) {
          lastVisibleSection = entry.target.id;
        }
      }
    },
    { threshold: 0.5 }
  );

  // Observe all sections
  const sections = document.querySelectorAll("section[id]");
  for (const section of sections) {
    observer.observe(section);
  }

  const handleBeforeUnload = () => {
    if (lastVisibleSection) {
      sendTrackingEvent({
        event: "page_abandonment",
        category: "Engagement",
        action: "Page Exit",
        label: lastVisibleSection,
      });
    }
  };

  window.addEventListener("beforeunload", handleBeforeUnload);

  return () => {
    observer.disconnect();
    window.removeEventListener("beforeunload", handleBeforeUnload);
  };
}

// Type augmentation for gtag
declare global {
  interface Window {
    gtag?: (
      command: string,
      action: string,
      params?: Record<string, unknown>
    ) => void;
  }
}
