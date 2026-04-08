/**
 * Utilities index
 * Central export for all utility modules
 */

// Routing utilities
export {
  getPersonaFromURL,
  setPersonaInURL,
  getUTMParams,
  buildCTAUrl,
  buildWhatsAppUrl,
  getCanonicalUrl,
} from './routing';

export type { PersonaId } from './routing';

// Feature flags
export {
  isFeatureEnabled,
  getExperimentVariant,
  getActiveExperiments,
  overrideFeatureFlag,
} from './featureFlags';

export type {
  FeatureFlagId,
  ExperimentVariant,
  FeatureFlag,
  ExperimentConfig,
} from './featureFlags';

// Analytics
export {
  analytics,
  trackPrimaryCTA,
  trackSecondaryCTA,
  trackMicroconversion,
  trackMacroconversion,
  trackPersonaSelection,
  trackModuleView,
  trackFAQExpand,
  trackPlanView,
} from './analytics';

export type {
  EventCategory,
  CTAType,
  InteractionType,
  AnalyticsEvent,
  CTAClickEvent,
  ScrollDepthEvent,
  InteractionEvent,
  ConversionEvent,
  ExperimentEvent,
} from './analytics';

// Web Vitals (RUM)
export {
  initWebVitals,
  markPerformance,
  measurePerformance,
  getNavigationTiming,
  trackResourceTiming,
} from './webVitals';

export type { WebVitalMetric } from './webVitals';

// Motion utilities
export {
  DURATION,
  EASING,
  prefersReducedMotion,
  getMotionDuration,
  getMotionDistance,
  viewportVariants,
  staggerContainerVariants,
  staggerItemVariants,
  tabContentVariants,
  hoverVariants,
  getTransition,
  getGSAPConfig,
  VIEWPORT_OBSERVER_OPTIONS,
} from './motion';
