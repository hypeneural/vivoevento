/**
 * Feature flags infrastructure for A/B testing and experiments
 * Minimal implementation for landing page optimization
 */

export type FeatureFlagId = 
  | 'hero_variant_a'
  | 'hero_variant_b'
  | 'cta_text_variant'
  | 'trust_signals_position'
  | 'floating_cta_enabled'
  | 'persona_selector_enabled'
  | 'microconversion_enabled';

export type ExperimentVariant = 'control' | 'variant_a' | 'variant_b';

export interface FeatureFlag {
  id: FeatureFlagId;
  enabled: boolean;
  variant?: ExperimentVariant;
  rolloutPercentage?: number;
}

export interface ExperimentConfig {
  id: string;
  name: string;
  variants: ExperimentVariant[];
  enabled: boolean;
}

/**
 * Feature flags configuration
 * In production, this would come from a remote config service
 */
const FEATURE_FLAGS: Record<FeatureFlagId, FeatureFlag> = {
  hero_variant_a: {
    id: 'hero_variant_a',
    enabled: false,
    variant: 'control',
  },
  hero_variant_b: {
    id: 'hero_variant_b',
    enabled: false,
    variant: 'control',
  },
  cta_text_variant: {
    id: 'cta_text_variant',
    enabled: false,
    variant: 'control',
  },
  trust_signals_position: {
    id: 'trust_signals_position',
    enabled: false,
    variant: 'control',
  },
  floating_cta_enabled: {
    id: 'floating_cta_enabled',
    enabled: true,
  },
  persona_selector_enabled: {
    id: 'persona_selector_enabled',
    enabled: true,
  },
  microconversion_enabled: {
    id: 'microconversion_enabled',
    enabled: true,
  },
};

/**
 * Check if a feature flag is enabled
 */
export function isFeatureEnabled(flagId: FeatureFlagId): boolean {
  const flag = FEATURE_FLAGS[flagId];
  return flag?.enabled ?? false;
}

/**
 * Get variant for an experiment
 * Uses deterministic assignment based on user ID or session
 */
export function getExperimentVariant(flagId: FeatureFlagId): ExperimentVariant {
  const flag = FEATURE_FLAGS[flagId];
  
  if (!flag?.enabled) {
    return 'control';
  }
  
  // Check if variant is forced via URL (for testing)
  const urlVariant = getVariantFromURL(flagId);
  if (urlVariant) {
    return urlVariant;
  }
  
  // Check localStorage for persistent assignment
  const storedVariant = getStoredVariant(flagId);
  if (storedVariant) {
    return storedVariant;
  }
  
  // Assign new variant based on session
  const variant = assignVariant(flagId);
  storeVariant(flagId, variant);
  
  return variant;
}

/**
 * Get variant from URL parameter (for testing)
 * Example: ?experiment_hero=variant_a
 */
function getVariantFromURL(flagId: FeatureFlagId): ExperimentVariant | null {
  const params = new URLSearchParams(window.location.search);
  const experimentKey = `experiment_${flagId}`;
  const variant = params.get(experimentKey);
  
  if (variant === 'control' || variant === 'variant_a' || variant === 'variant_b') {
    return variant;
  }
  
  return null;
}

/**
 * Get stored variant from localStorage
 */
function getStoredVariant(flagId: FeatureFlagId): ExperimentVariant | null {
  try {
    const key = `ev_experiment_${flagId}`;
    const stored = localStorage.getItem(key);
    
    if (stored === 'control' || stored === 'variant_a' || stored === 'variant_b') {
      return stored;
    }
  } catch (error) {
    console.error('Failed to read stored variant:', error);
  }
  
  return null;
}

/**
 * Store variant assignment in localStorage
 */
function storeVariant(flagId: FeatureFlagId, variant: ExperimentVariant): void {
  try {
    const key = `ev_experiment_${flagId}`;
    localStorage.setItem(key, variant);
  } catch (error) {
    console.error('Failed to store variant:', error);
  }
}

/**
 * Assign variant using deterministic hash
 * Uses session ID or generates one
 */
function assignVariant(flagId: FeatureFlagId): ExperimentVariant {
  const flag = FEATURE_FLAGS[flagId];
  const sessionId = getOrCreateSessionId();
  
  // Simple hash function for deterministic assignment
  const hash = hashString(`${sessionId}_${flagId}`);
  const bucket = hash % 100;
  
  // Default 50/50 split between control and variant_a
  const rollout = flag.rolloutPercentage ?? 50;
  
  if (bucket < rollout) {
    return 'variant_a';
  }
  
  return 'control';
}

/**
 * Get or create session ID for consistent variant assignment
 */
function getOrCreateSessionId(): string {
  try {
    const key = 'ev_session_id';
    let sessionId = sessionStorage.getItem(key);
    
    if (!sessionId) {
      sessionId = generateSessionId();
      sessionStorage.setItem(key, sessionId);
    }
    
    return sessionId;
  } catch (error) {
    // Fallback to random ID if storage fails
    return generateSessionId();
  }
}

/**
 * Generate unique session ID
 */
function generateSessionId(): string {
  return `${Date.now()}_${Math.random().toString(36).substring(2, 15)}`;
}

/**
 * Simple string hash function
 */
function hashString(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32-bit integer
  }
  return Math.abs(hash);
}

/**
 * Get all active experiments for the current session
 */
export function getActiveExperiments(): Record<FeatureFlagId, ExperimentVariant> {
  const experiments: Partial<Record<FeatureFlagId, ExperimentVariant>> = {};
  
  Object.keys(FEATURE_FLAGS).forEach((key) => {
    const flagId = key as FeatureFlagId;
    const flag = FEATURE_FLAGS[flagId];
    
    if (flag.enabled && flag.variant !== undefined) {
      experiments[flagId] = getExperimentVariant(flagId);
    }
  });
  
  return experiments as Record<FeatureFlagId, ExperimentVariant>;
}

/**
 * Override feature flag (for development/testing)
 */
export function overrideFeatureFlag(
  flagId: FeatureFlagId,
  enabled: boolean,
  variant?: ExperimentVariant
): void {
  if (import.meta.env.DEV) {
    FEATURE_FLAGS[flagId] = {
      ...FEATURE_FLAGS[flagId],
      enabled,
      variant,
    };
  }
}
