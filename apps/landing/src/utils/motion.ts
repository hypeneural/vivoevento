/**
 * Motion Utilities
 * 
 * Provides utilities for implementing subtle, premium animations
 * with automatic prefers-reduced-motion support.
 * 
 * **Validates: Requirements 18**
 * - Subtle and premium motion
 * - Respects prefers-reduced-motion
 * - 90% of animations between 200ms-600ms
 */

/**
 * Animation duration presets (in seconds)
 * 90% of animations should use fast, normal, or medium
 */
export const DURATION = {
  instant: 0.01,    // For reduced motion fallback
  fast: 0.2,        // Quick interactions (hover, focus)
  normal: 0.3,      // Standard transitions (tabs, modals)
  medium: 0.5,      // Entrance animations
  slow: 0.6,        // Complex animations
  verySlow: 0.8,    // Special cases only
} as const;

/**
 * Easing presets for premium feel
 */
export const EASING = {
  // Smooth, natural easing for most animations
  ease: [0.25, 0.1, 0.25, 1],
  
  // Slightly bouncy for playful interactions
  easeOut: [0.16, 1, 0.3, 1],
  
  // Sharp entrance, smooth exit
  easeInOut: [0.65, 0, 0.35, 1],
  
  // For spring-like animations
  spring: { type: "spring", stiffness: 300, damping: 30 },
} as const;

/**
 * Detects if user prefers reduced motion
 */
export function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Returns appropriate duration based on user preference
 * 
 * @param duration - Desired duration in seconds
 * @returns Duration (instant if reduced motion, original otherwise)
 */
export function getMotionDuration(duration: number): number {
  return prefersReducedMotion() ? DURATION.instant : duration;
}

/**
 * Returns appropriate distance based on user preference
 * 
 * @param distance - Desired distance in pixels
 * @returns Distance (0 if reduced motion, original otherwise)
 */
export function getMotionDistance(distance: number): number {
  return prefersReducedMotion() ? 0 : distance;
}

/**
 * Framer Motion variants for viewport entrance animations
 * Automatically respects prefers-reduced-motion
 */
export function getViewportVariants() {
  return {
    hidden: {
      opacity: 0,
      y: getMotionDistance(24),
    },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: getMotionDuration(DURATION.medium),
        ease: EASING.easeOut,
      },
    },
  };
}

export const viewportVariants = {
  get hidden() {
    return getViewportVariants().hidden;
  },
  get visible() {
    return getViewportVariants().visible;
  },
};

/**
 * Framer Motion variants for staggered children
 */
export function getStaggerContainerVariants() {
  return {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: prefersReducedMotion() ? 0 : 0.08,
        delayChildren: prefersReducedMotion() ? 0 : 0.1,
      },
    },
  };
}

export const staggerContainerVariants = {
  get hidden() {
    return getStaggerContainerVariants().hidden;
  },
  get visible() {
    return getStaggerContainerVariants().visible;
  },
};

/**
 * Framer Motion variants for staggered items
 */
export function getStaggerItemVariants() {
  return {
    hidden: {
      opacity: 0,
      y: getMotionDistance(16),
    },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: getMotionDuration(DURATION.normal),
        ease: EASING.easeOut,
      },
    },
  };
}

export const staggerItemVariants = {
  get hidden() {
    return getStaggerItemVariants().hidden;
  },
  get visible() {
    return getStaggerItemVariants().visible;
  },
};

/**
 * Framer Motion variants for tab transitions
 */
export function getTabContentVariants() {
  return {
    hidden: {
      opacity: 0,
      y: getMotionDistance(20),
    },
    visible: {
      opacity: 1,
      y: 0,
      transition: {
        duration: getMotionDuration(DURATION.normal),
        ease: EASING.easeOut,
      },
    },
    exit: {
      opacity: 0,
      y: getMotionDistance(-20),
      transition: {
        duration: getMotionDuration(DURATION.fast),
        ease: EASING.ease,
      },
    },
  };
}

export const tabContentVariants = {
  get hidden() {
    return getTabContentVariants().hidden;
  },
  get visible() {
    return getTabContentVariants().visible;
  },
  get exit() {
    return getTabContentVariants().exit;
  },
};

/**
 * Framer Motion variants for hover interactions
 */
export function getHoverVariants() {
  return {
    rest: {
      scale: 1,
    },
    hover: {
      scale: prefersReducedMotion() ? 1 : 1.02,
      transition: {
        duration: getMotionDuration(DURATION.fast),
        ease: EASING.easeOut,
      },
    },
  };
}

export const hoverVariants = {
  get rest() {
    return getHoverVariants().rest;
  },
  get hover() {
    return getHoverVariants().hover;
  },
};

/**
 * CSS transition string for simple hover states
 * Use this for CSS-based transitions instead of JS animations
 * 
 * @example
 * .button {
 *   transition: ${getTransition(['transform', 'opacity'])};
 * }
 */
export function getTransition(properties: string[], duration: number = DURATION.fast): string {
  if (prefersReducedMotion()) {
    return 'none';
  }
  
  return properties
    .map(prop => `${prop} ${duration}s cubic-bezier(0.16, 1, 0.3, 1)`)
    .join(', ');
}

/**
 * GSAP animation config with reduced motion support
 * 
 * @example
 * gsap.from(element, getGSAPConfig({
 *   y: 24,
 *   opacity: 0,
 *   duration: DURATION.medium,
 * }));
 */
export function getGSAPConfig(config: Record<string, unknown>): Record<string, unknown> {
  if (prefersReducedMotion()) {
    return {
      ...config,
      duration: DURATION.instant,
      y: 0,
      x: 0,
      scale: 1,
      rotate: 0,
    };
  }
  
  return config;
}

/**
 * Intersection Observer options for viewport animations
 */
export const VIEWPORT_OBSERVER_OPTIONS: IntersectionObserverInit = {
  threshold: 0.1,
  rootMargin: '-10% 0px -10% 0px',
};
