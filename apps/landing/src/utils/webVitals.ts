/**
 * Core Web Vitals Optimization Utilities
 * 
 * Provides utilities for optimizing Core Web Vitals:
 * - LCP (Largest Contentful Paint) ≤ 2.5s
 * - INP (Interaction to Next Paint) ≤ 200ms (replaces FID)
 * - CLS (Cumulative Layout Shift) ≤ 0.1
 * 
 * References:
 * - https://web.dev/vitals/
 * - https://web.dev/inp/
 */

export type WebVitalMetric = {
  name: 'LCP' | 'INP' | 'FID' | 'CLS' | 'FCP' | 'TTFB';
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
  delta: number;
  id: string;
  navigationType: 'navigate' | 'reload' | 'back-forward' | 'prerender';
};

export type WebVitalThresholds = {
  good: number;
  needsImprovement: number;
};

/**
 * Core Web Vitals thresholds (updated for 2024)
 */
export const WEB_VITAL_THRESHOLDS: Record<string, WebVitalThresholds> = {
  LCP: { good: 2500, needsImprovement: 4000 },
  INP: { good: 200, needsImprovement: 500 },  // Replaces FID
  FID: { good: 100, needsImprovement: 300 },  // Deprecated but kept for compatibility
  CLS: { good: 0.1, needsImprovement: 0.25 },
  FCP: { good: 1800, needsImprovement: 3000 },
  TTFB: { good: 800, needsImprovement: 1800 },
};

/**
 * Get rating for a metric value
 */
export function getRating(name: string, value: number): 'good' | 'needs-improvement' | 'poor' {
  const thresholds = WEB_VITAL_THRESHOLDS[name];
  if (!thresholds) return 'good';
  
  if (value <= thresholds.good) return 'good';
  if (value <= thresholds.needsImprovement) return 'needs-improvement';
  return 'poor';
}

/**
 * Report Web Vital to analytics
 */
export function reportWebVital(metric: WebVitalMetric): void {
  // Send to Google Analytics
  if (typeof window !== 'undefined' && (window as any).gtag) {
    (window as any).gtag('event', metric.name, {
      event_category: 'Web Vitals',
      event_label: metric.id,
      value: Math.round(metric.name === 'CLS' ? metric.value * 1000 : metric.value),
      metric_rating: metric.rating,
      metric_delta: Math.round(metric.delta),
      non_interaction: true,
    });
  }
  
  // Send to custom analytics endpoint
  if (typeof navigator !== 'undefined' && 'sendBeacon' in navigator) {
    const body = JSON.stringify({
      name: metric.name,
      value: metric.value,
      rating: metric.rating,
      delta: metric.delta,
      id: metric.id,
      navigationType: metric.navigationType,
      timestamp: Date.now(),
      url: window.location.href,
      userAgent: navigator.userAgent,
    });
    
    // Only send in production or when analytics endpoint is available
    if (import.meta.env.PROD) {
      navigator.sendBeacon('/api/analytics/web-vitals', body);
    }
  }
  
  // Log in development
  if (import.meta.env.DEV) {
    console.log(`[Web Vitals] ${metric.name}:`, {
      value: metric.value,
      rating: metric.rating,
      delta: metric.delta,
    });
  }
}

/**
 * Initialize Web Vitals monitoring
 * Uses web-vitals library for accurate measurements
 */
export async function initWebVitals(): Promise<void> {
  if (typeof window === 'undefined') return;
  
  try {
    const { onLCP, onINP, onCLS, onFCP, onTTFB } = await import('web-vitals');
    
    // Monitor LCP (Largest Contentful Paint)
    onLCP((metric) => {
      const webVital: WebVitalMetric = {
        ...metric,
        rating: getRating('LCP', metric.value),
      };
      reportWebVital(webVital);
    });
    
    // Monitor INP (Interaction to Next Paint) - replaces FID
    onINP((metric) => {
      const webVital: WebVitalMetric = {
        ...metric,
        rating: getRating('INP', metric.value),
      };
      reportWebVital(webVital);
    });
    
    // Monitor CLS (Cumulative Layout Shift)
    onCLS((metric) => {
      const webVital: WebVitalMetric = {
        ...metric,
        rating: getRating('CLS', metric.value),
      };
      reportWebVital(webVital);
    });
    
    // Monitor FCP (First Contentful Paint)
    onFCP((metric) => {
      const webVital: WebVitalMetric = {
        ...metric,
        rating: getRating('FCP', metric.value),
      };
      reportWebVital(webVital);
    });
    
    // Monitor TTFB (Time to First Byte)
    onTTFB((metric) => {
      const webVital: WebVitalMetric = {
        ...metric,
        rating: getRating('TTFB', metric.value),
      };
      reportWebVital(webVital);
    });
  } catch (error) {
    console.error('Failed to initialize Web Vitals:', error);
  }
}

/**
 * Optimize for LCP (Largest Contentful Paint)
 * 
 * Best practices:
 * - Preload critical resources
 * - Optimize server response time
 * - Eliminate render-blocking resources
 * - Optimize images
 */
export function optimizeLCP(): void {
  // Preconnect to required origins
  const origins = [
    'https://fonts.googleapis.com',
    'https://fonts.gstatic.com',
  ];
  
  origins.forEach(origin => {
    const link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = origin;
    link.crossOrigin = 'anonymous';
    document.head.appendChild(link);
  });
}

/**
 * Optimize for INP (Interaction to Next Paint)
 * 
 * Best practices:
 * - Break up long tasks
 * - Defer non-critical JavaScript
 * - Use web workers for heavy computation
 * - Optimize event handlers
 */
export function optimizeINP(): void {
  // Defer non-critical scripts
  if (typeof window !== 'undefined') {
    // Use requestIdleCallback for non-critical work
    const scheduleWork = (callback: () => void) => {
      if ('requestIdleCallback' in window) {
        (window as any).requestIdleCallback(callback, { timeout: 2000 });
      } else {
        setTimeout(callback, 1);
      }
    };
    
    // Example: defer analytics initialization
    scheduleWork(() => {
      // Initialize non-critical analytics
    });
  }
}

/**
 * Optimize for CLS (Cumulative Layout Shift)
 * 
 * Best practices:
 * - Set explicit dimensions for images and videos
 * - Reserve space for dynamic content
 * - Avoid inserting content above existing content
 * - Use CSS aspect-ratio
 */
export function optimizeCLS(): void {
  // Add aspect-ratio support detection
  if (typeof CSS !== 'undefined' && CSS.supports) {
    const supportsAspectRatio = CSS.supports('aspect-ratio', '16 / 9');
    
    if (!supportsAspectRatio) {
      console.warn('aspect-ratio not supported, using fallback');
    }
  }
}

/**
 * Debounce function for optimizing event handlers
 * Helps reduce INP by limiting event handler frequency
 */
export function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: ReturnType<typeof setTimeout> | null = null;
  
  return function executedFunction(...args: Parameters<T>) {
    const later = () => {
      timeout = null;
      func(...args);
    };
    
    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Throttle function for optimizing scroll/resize handlers
 * Helps reduce INP by limiting event handler frequency
 */
export function throttle<T extends (...args: any[]) => any>(
  func: T,
  limit: number
): (...args: Parameters<T>) => void {
  let inThrottle: boolean;
  
  return function executedFunction(...args: Parameters<T>) {
    if (!inThrottle) {
      func(...args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}

/**
 * Request Idle Callback polyfill
 */
export function requestIdleCallback(callback: () => void, options?: { timeout?: number }): number {
  if (typeof window !== 'undefined' && 'requestIdleCallback' in window) {
    return (window as any).requestIdleCallback(callback, options);
  }
  
  // Fallback to setTimeout
  return setTimeout(callback, 1) as unknown as number;
}

/**
 * Cancel Idle Callback polyfill
 */
export function cancelIdleCallback(id: number): void {
  if (typeof window !== 'undefined' && 'cancelIdleCallback' in window) {
    (window as any).cancelIdleCallback(id);
  } else {
    clearTimeout(id);
  }
}

/**
 * Break up long tasks using scheduler.yield() or setTimeout
 */
export async function yieldToMain(): Promise<void> {
  if (typeof window !== 'undefined' && 'scheduler' in window && 'yield' in (window as any).scheduler) {
    return (window as any).scheduler.yield();
  }
  
  // Fallback to setTimeout
  return new Promise(resolve => setTimeout(resolve, 0));
}

/**
 * Measure task duration and warn if too long
 */
export function measureTask<T>(name: string, task: () => T): T {
  const start = performance.now();
  const result = task();
  const duration = performance.now() - start;
  
  if (duration > 50) {
    console.warn(`[Performance] Long task detected: ${name} took ${duration.toFixed(2)}ms`);
  }
  
  return result;
}

/**
 * Initialize all Web Vitals optimizations
 */
export function initWebVitalsOptimizations(): void {
  optimizeLCP();
  optimizeINP();
  optimizeCLS();
  initWebVitals();
}
