/**
 * Real User Monitoring (RUM) for Core Web Vitals
 * Tracks LCP, INP, CLS in production for real user experience
 */

import { analytics } from './analytics';

export interface WebVitalMetric {
  name: 'LCP' | 'INP' | 'CLS' | 'FCP' | 'TTFB';
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
  delta: number;
  id: string;
}

/**
 * Thresholds for Web Vitals ratings
 * Based on web.dev recommendations
 */
const THRESHOLDS = {
  LCP: { good: 2500, poor: 4000 },
  INP: { good: 200, poor: 500 },
  CLS: { good: 0.1, poor: 0.25 },
  FCP: { good: 1800, poor: 3000 },
  TTFB: { good: 800, poor: 1800 },
};

/**
 * Get rating for a metric value
 */
function getRating(name: WebVitalMetric['name'], value: number): WebVitalMetric['rating'] {
  const threshold = THRESHOLDS[name];
  
  if (value <= threshold.good) {
    return 'good';
  }
  
  if (value <= threshold.poor) {
    return 'needs-improvement';
  }
  
  return 'poor';
}

/**
 * Send Web Vital metric to analytics
 */
function sendToAnalytics(metric: WebVitalMetric): void {
  analytics.trackEvent({
    category: 'web_vitals',
    action: metric.name,
    label: metric.rating,
    value: Math.round(metric.value),
    metadata: {
      metric_id: metric.id,
      metric_delta: metric.delta,
    },
  });
}

/**
 * Initialize Web Vitals tracking
 * Uses web-vitals library if available
 */
export async function initWebVitals(): Promise<void> {
  // Only track in production
  if (import.meta.env.DEV) {
    return;
  }

  try {
    // Dynamic import to avoid bundling in development
    const { onLCP, onINP, onCLS, onFCP, onTTFB } = await import('web-vitals');

    // Track Largest Contentful Paint
    onLCP((metric) => {
      sendToAnalytics({
        name: 'LCP',
        value: metric.value,
        rating: getRating('LCP', metric.value),
        delta: metric.delta,
        id: metric.id,
      });
    });

    // Track Interaction to Next Paint (replaces FID)
    onINP((metric) => {
      sendToAnalytics({
        name: 'INP',
        value: metric.value,
        rating: getRating('INP', metric.value),
        delta: metric.delta,
        id: metric.id,
      });
    });

    // Track Cumulative Layout Shift
    onCLS((metric) => {
      sendToAnalytics({
        name: 'CLS',
        value: metric.value,
        rating: getRating('CLS', metric.value),
        delta: metric.delta,
        id: metric.id,
      });
    });

    // Track First Contentful Paint
    onFCP((metric) => {
      sendToAnalytics({
        name: 'FCP',
        value: metric.value,
        rating: getRating('FCP', metric.value),
        delta: metric.delta,
        id: metric.id,
      });
    });

    // Track Time to First Byte
    onTTFB((metric) => {
      sendToAnalytics({
        name: 'TTFB',
        value: metric.value,
        rating: getRating('TTFB', metric.value),
        delta: metric.delta,
        id: metric.id,
      });
    });
  } catch (error) {
    console.error('Failed to initialize Web Vitals tracking:', error);
  }
}

/**
 * Manual performance mark for custom metrics
 */
export function markPerformance(name: string): void {
  if (typeof performance !== 'undefined' && performance.mark) {
    performance.mark(name);
  }
}

/**
 * Measure performance between two marks
 */
export function measurePerformance(
  name: string,
  startMark: string,
  endMark: string
): number | null {
  if (typeof performance !== 'undefined' && performance.measure) {
    try {
      const measure = performance.measure(name, startMark, endMark);
      return measure.duration;
    } catch (error) {
      console.error('Failed to measure performance:', error);
    }
  }
  
  return null;
}

/**
 * Get navigation timing metrics
 */
export function getNavigationTiming(): Record<string, number> | null {
  if (typeof performance === 'undefined' || !performance.getEntriesByType) {
    return null;
  }

  const [navigation] = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
  
  if (!navigation) {
    return null;
  }

  return {
    dns: navigation.domainLookupEnd - navigation.domainLookupStart,
    tcp: navigation.connectEnd - navigation.connectStart,
    ttfb: navigation.responseStart - navigation.requestStart,
    download: navigation.responseEnd - navigation.responseStart,
    domInteractive: navigation.domInteractive - navigation.fetchStart,
    domComplete: navigation.domComplete - navigation.fetchStart,
    loadComplete: navigation.loadEventEnd - navigation.fetchStart,
  };
}

/**
 * Track resource loading performance
 */
export function trackResourceTiming(): void {
  if (typeof performance === 'undefined' || !performance.getEntriesByType) {
    return;
  }

  const resources = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
  
  // Group by resource type
  const byType: Record<string, { count: number; totalDuration: number }> = {};
  
  resources.forEach((resource) => {
    const type = resource.initiatorType;
    
    if (!byType[type]) {
      byType[type] = { count: 0, totalDuration: 0 };
    }
    
    byType[type].count++;
    byType[type].totalDuration += resource.duration;
  });

  // Send to analytics
  Object.entries(byType).forEach(([type, stats]) => {
    analytics.trackEvent({
      category: 'performance',
      action: 'resource_timing',
      label: type,
      value: Math.round(stats.totalDuration / stats.count),
      metadata: {
        resource_count: stats.count,
        total_duration: Math.round(stats.totalDuration),
      },
    });
  });
}
