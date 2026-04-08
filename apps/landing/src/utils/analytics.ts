/**
 * Analytics and event tracking for landing page
 * Handles CTA clicks, scroll depth, interactions, and conversions
 */

import type { PersonaId } from './routing';
import type { ExperimentVariant } from './featureFlags';

export type EventCategory = 
  | 'cta'
  | 'navigation'
  | 'scroll'
  | 'interaction'
  | 'conversion'
  | 'experiment';

export type CTAType = 
  | 'primary_demo'
  | 'secondary_whatsapp'
  | 'floating_cta'
  | 'microconversion';

export type InteractionType =
  | 'persona_select'
  | 'module_tab'
  | 'faq_expand'
  | 'plan_view'
  | 'testimonial_view';

export interface AnalyticsEvent {
  category: EventCategory;
  action: string;
  label?: string;
  value?: number;
  metadata?: Record<string, unknown>;
}

export interface CTAClickEvent {
  ctaType: CTAType;
  ctaText: string;
  section: string;
  destination: string;
  persona?: PersonaId;
  utmParams?: Record<string, string>;
}

export interface ScrollDepthEvent {
  depth: number; // 25, 50, 75, 100
  section?: string;
  timeOnPage: number; // seconds
}

export interface InteractionEvent {
  type: InteractionType;
  target: string;
  value?: string;
  section: string;
}

export interface ConversionEvent {
  type: 'macro' | 'micro';
  action: string;
  persona?: PersonaId;
  source: string;
}

export interface ExperimentEvent {
  experimentId: string;
  variant: ExperimentVariant;
  action: 'view' | 'interact' | 'convert';
}

/**
 * Analytics provider interface
 * Allows swapping between Google Analytics, Segment, etc.
 */
interface AnalyticsProvider {
  trackEvent(event: AnalyticsEvent): void;
  trackPageView(path: string, title: string): void;
  setUserProperty(key: string, value: string): void;
}

/**
 * Google Analytics 4 provider
 */
class GA4Provider implements AnalyticsProvider {
  trackEvent(event: AnalyticsEvent): void {
    if (typeof window.gtag === 'function') {
      window.gtag('event', event.action, {
        event_category: event.category,
        event_label: event.label,
        value: event.value,
        ...event.metadata,
      });
    }
  }

  trackPageView(path: string, title: string): void {
    if (typeof window.gtag === 'function') {
      window.gtag('config', window.GA_MEASUREMENT_ID || '', {
        page_path: path,
        page_title: title,
      });
    }
  }

  setUserProperty(key: string, value: string): void {
    if (typeof window.gtag === 'function') {
      window.gtag('set', 'user_properties', {
        [key]: value,
      });
    }
  }
}

/**
 * Console provider for development
 */
class ConsoleProvider implements AnalyticsProvider {
  trackEvent(event: AnalyticsEvent): void {
    console.log('[Analytics Event]', event);
  }

  trackPageView(path: string, title: string): void {
    console.log('[Analytics PageView]', { path, title });
  }

  setUserProperty(key: string, value: string): void {
    console.log('[Analytics UserProperty]', { key, value });
  }
}

/**
 * Analytics manager singleton
 */
class AnalyticsManager {
  private provider: AnalyticsProvider;
  private sessionStartTime: number;
  private scrollDepthTracked: Set<number>;

  constructor() {
    // Use console provider in development, GA4 in production
    this.provider = import.meta.env.DEV 
      ? new ConsoleProvider() 
      : new GA4Provider();
    
    this.sessionStartTime = Date.now();
    this.scrollDepthTracked = new Set();
    
    this.initializeScrollTracking();
  }

  /**
   * Track CTA click
   */
  trackCTAClick(event: CTAClickEvent): void {
    this.provider.trackEvent({
      category: 'cta',
      action: 'click',
      label: event.ctaType,
      metadata: {
        cta_text: event.ctaText,
        section: event.section,
        destination: event.destination,
        persona: event.persona,
        ...event.utmParams,
      },
    });
  }

  /**
   * Track scroll depth milestone
   */
  trackScrollDepth(depth: number, section?: string): void {
    // Only track each depth once per session
    if (this.scrollDepthTracked.has(depth)) {
      return;
    }

    this.scrollDepthTracked.add(depth);
    
    const timeOnPage = Math.floor((Date.now() - this.sessionStartTime) / 1000);
    
    this.provider.trackEvent({
      category: 'scroll',
      action: 'depth',
      label: `${depth}%`,
      value: depth,
      metadata: {
        section,
        time_on_page: timeOnPage,
      },
    });
  }

  /**
   * Track user interaction
   */
  trackInteraction(event: InteractionEvent): void {
    this.provider.trackEvent({
      category: 'interaction',
      action: event.type,
      label: event.target,
      metadata: {
        value: event.value,
        section: event.section,
      },
    });
  }

  /**
   * Track conversion (macro or micro)
   */
  trackConversion(event: ConversionEvent): void {
    this.provider.trackEvent({
      category: 'conversion',
      action: event.type,
      label: event.action,
      metadata: {
        persona: event.persona,
        source: event.source,
      },
    });
  }

  /**
   * Track experiment exposure and interaction
   */
  trackExperiment(event: ExperimentEvent): void {
    this.provider.trackEvent({
      category: 'experiment',
      action: event.action,
      label: event.experimentId,
      metadata: {
        variant: event.variant,
      },
    });
  }

  /**
   * Track page view
   */
  trackPageView(path?: string, title?: string): void {
    const currentPath = path || window.location.pathname;
    const currentTitle = title || document.title;
    
    this.provider.trackPageView(currentPath, currentTitle);
  }

  /**
   * Set user property (persona, experiment variant, etc.)
   */
  setUserProperty(key: string, value: string): void {
    this.provider.setUserProperty(key, value);
  }

  /**
   * Initialize scroll depth tracking
   */
  private initializeScrollTracking(): void {
    let ticking = false;

    const handleScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
          const scrolled = window.scrollY;
          const scrollPercent = Math.floor((scrolled / scrollHeight) * 100);

          // Track milestones: 25%, 50%, 75%, 100%
          [25, 50, 75, 100].forEach((milestone) => {
            if (scrollPercent >= milestone && !this.scrollDepthTracked.has(milestone)) {
              this.trackScrollDepth(milestone);
            }
          });

          ticking = false;
        });

        ticking = true;
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
  }
}

// Export singleton instance
export const analytics = new AnalyticsManager();

/**
 * Helper functions for common tracking scenarios
 */

export function trackPrimaryCTA(
  section: string,
  destination: string,
  persona?: PersonaId,
  utmParams?: Record<string, string>
): void {
  analytics.trackCTAClick({
    ctaType: 'primary_demo',
    ctaText: 'Agendar demonstração',
    section,
    destination,
    persona,
    utmParams,
  });
}

export function trackSecondaryCTA(
  section: string,
  destination: string,
  persona?: PersonaId,
  utmParams?: Record<string, string>
): void {
  analytics.trackCTAClick({
    ctaType: 'secondary_whatsapp',
    ctaText: 'Falar no WhatsApp',
    section,
    destination,
    persona,
    utmParams,
  });
}

export function trackMicroconversion(
  action: string,
  section: string,
  persona?: PersonaId
): void {
  analytics.trackConversion({
    type: 'micro',
    action,
    source: section,
    persona,
  });
}

export function trackMacroconversion(
  action: string,
  section: string,
  persona?: PersonaId
): void {
  analytics.trackConversion({
    type: 'macro',
    action,
    source: section,
    persona,
  });
}

export function trackPersonaSelection(persona: PersonaId, section: string): void {
  analytics.trackInteraction({
    type: 'persona_select',
    target: persona,
    section,
  });
  
  analytics.setUserProperty('persona', persona);
}

export function trackModuleView(moduleId: string, section: string): void {
  analytics.trackInteraction({
    type: 'module_tab',
    target: moduleId,
    section,
  });
}

export function trackFAQExpand(question: string, section: string): void {
  analytics.trackInteraction({
    type: 'faq_expand',
    target: question,
    section,
  });
}

export function trackPlanView(planId: string, section: string): void {
  analytics.trackInteraction({
    type: 'plan_view',
    target: planId,
    section,
  });
}

/**
 * Type declarations for gtag
 */
declare global {
  interface Window {
    gtag?: (
      command: string,
      targetId: string,
      config?: Record<string, unknown>
    ) => void;
    GA_MEASUREMENT_ID?: string;
  }
}
