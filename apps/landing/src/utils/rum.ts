/**
 * Real User Monitoring (RUM) Utilities
 * 
 * Provides comprehensive monitoring of real user experience:
 * - Core Web Vitals (LCP, INP, CLS) from actual users
 * - Performance metrics (TTFB, FCP, DOM load)
 * - User interactions and engagement
 * - Error tracking
 * - Network conditions
 * - Device and browser information
 * 
 * Differentiates between:
 * - Laboratory metrics (Lighthouse, synthetic tests)
 * - Field metrics (real users in production)
 */

import { WebVitalMetric, reportWebVital } from './webVitals';

export type RUMMetric = {
  type: 'web-vital' | 'performance' | 'interaction' | 'error' | 'navigation';
  name: string;
  value: number | string;
  timestamp: number;
  sessionId: string;
  pageUrl: string;
  userAgent: string;
  deviceType: 'mobile' | 'tablet' | 'desktop';
  connectionType?: string;
  effectiveType?: string;
  metadata?: Record<string, any>;
};

export type RUMConfig = {
  endpoint?: string;
  sampleRate?: number;
  enableErrorTracking?: boolean;
  enableInteractionTracking?: boolean;
  enableNavigationTracking?: boolean;
  enableResourceTracking?: boolean;
  batchSize?: number;
  batchInterval?: number;
};

/**
 * RUM Manager
 * Collects and batches metrics for efficient transmission
 */
class RUMManager {
  private config: Required<RUMConfig>;
  private sessionId: string;
  private metricsQueue: RUMMetric[] = [];
  private batchTimer: ReturnType<typeof setTimeout> | null = null;
  private isInitialized = false;
  
  constructor(config: RUMConfig = {}) {
    this.config = {
      endpoint: config.endpoint || '/api/analytics/rum',
      sampleRate: config.sampleRate ?? 1.0,
      enableErrorTracking: config.enableErrorTracking ?? true,
      enableInteractionTracking: config.enableInteractionTracking ?? true,
      enableNavigationTracking: config.enableNavigationTracking ?? true,
      enableResourceTracking: config.enableResourceTracking ?? false,
      batchSize: config.batchSize ?? 10,
      batchInterval: config.batchInterval ?? 5000,
    };
    
    this.sessionId = this.generateSessionId();
  }
  
  /**
   * Initialize RUM monitoring
   */
  public init(): void {
    if (this.isInitialized) return;
    if (typeof window === 'undefined') return;
    
    // Check sampling rate
    if (Math.random() > this.config.sampleRate) {
      console.log('[RUM] Session not sampled');
      return;
    }
    
    this.isInitialized = true;
    
    // Track Web Vitals
    this.initWebVitalsTracking();
    
    // Track performance metrics
    this.initPerformanceTracking();
    
    // Track errors
    if (this.config.enableErrorTracking) {
      this.initErrorTracking();
    }
    
    // Track interactions
    if (this.config.enableInteractionTracking) {
      this.initInteractionTracking();
    }
    
    // Track navigation
    if (this.config.enableNavigationTracking) {
      this.initNavigationTracking();
    }
    
    // Track resources
    if (this.config.enableResourceTracking) {
      this.initResourceTracking();
    }
    
    // Send metrics on page unload
    this.initUnloadHandler();
    
    console.log('[RUM] Initialized with session:', this.sessionId);
  }
  
  /**
   * Track Web Vitals from real users
   */
  private async initWebVitalsTracking(): Promise<void> {
    try {
      const { onLCP, onINP, onCLS, onFCP, onTTFB } = await import('web-vitals');
      
      const trackVital = (metric: WebVitalMetric) => {
        this.trackMetric({
          type: 'web-vital',
          name: metric.name,
          value: metric.value,
          metadata: {
            rating: metric.rating,
            delta: metric.delta,
            id: metric.id,
            navigationType: metric.navigationType,
          },
        });
        
        // Also report to standard Web Vitals handler
        reportWebVital(metric);
      };
      
      onLCP(trackVital);
      onINP(trackVital);
      onCLS(trackVital);
      onFCP(trackVital);
      onTTFB(trackVital);
    } catch (error) {
      console.error('[RUM] Failed to initialize Web Vitals tracking:', error);
    }
  }
  
  /**
   * Track performance metrics
   */
  private initPerformanceTracking(): void {
    if (!('performance' in window)) return;
    
    // Wait for page load
    window.addEventListener('load', () => {
      setTimeout(() => {
        const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
        
        if (navigation) {
          // DNS lookup time
          this.trackMetric({
            type: 'performance',
            name: 'dns-lookup',
            value: navigation.domainLookupEnd - navigation.domainLookupStart,
          });
          
          // TCP connection time
          this.trackMetric({
            type: 'performance',
            name: 'tcp-connection',
            value: navigation.connectEnd - navigation.connectStart,
          });
          
          // Request time
          this.trackMetric({
            type: 'performance',
            name: 'request-time',
            value: navigation.responseStart - navigation.requestStart,
          });
          
          // Response time
          this.trackMetric({
            type: 'performance',
            name: 'response-time',
            value: navigation.responseEnd - navigation.responseStart,
          });
          
          // DOM processing time
          this.trackMetric({
            type: 'performance',
            name: 'dom-processing',
            value: navigation.domComplete - navigation.domInteractive,
          });
          
          // Total page load time
          this.trackMetric({
            type: 'performance',
            name: 'page-load',
            value: navigation.loadEventEnd - navigation.fetchStart,
          });
        }
      }, 0);
    });
  }
  
  /**
   * Track JavaScript errors
   */
  private initErrorTracking(): void {
    window.addEventListener('error', (event) => {
      this.trackMetric({
        type: 'error',
        name: 'javascript-error',
        value: event.message,
        metadata: {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
          stack: event.error?.stack,
        },
      });
    });
    
    window.addEventListener('unhandledrejection', (event) => {
      this.trackMetric({
        type: 'error',
        name: 'unhandled-promise-rejection',
        value: event.reason?.message || String(event.reason),
        metadata: {
          stack: event.reason?.stack,
        },
      });
    });
  }
  
  /**
   * Track user interactions
   */
  private initInteractionTracking(): void {
    // Track long tasks (> 50ms)
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            if (entry.duration > 50) {
              this.trackMetric({
                type: 'interaction',
                name: 'long-task',
                value: entry.duration,
                metadata: {
                  startTime: entry.startTime,
                },
              });
            }
          }
        });
        
        observer.observe({ entryTypes: ['longtask'] });
      } catch (error) {
        // longtask not supported
      }
    }
    
    // Track scroll depth
    let maxScrollDepth = 0;
    const trackScrollDepth = () => {
      const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
      if (scrollPercent > maxScrollDepth) {
        maxScrollDepth = scrollPercent;
        
        // Track milestones
        if (scrollPercent >= 25 && maxScrollDepth < 25) {
          this.trackMetric({ type: 'interaction', name: 'scroll-depth', value: 25 });
        } else if (scrollPercent >= 50 && maxScrollDepth < 50) {
          this.trackMetric({ type: 'interaction', name: 'scroll-depth', value: 50 });
        } else if (scrollPercent >= 75 && maxScrollDepth < 75) {
          this.trackMetric({ type: 'interaction', name: 'scroll-depth', value: 75 });
        } else if (scrollPercent >= 90 && maxScrollDepth < 90) {
          this.trackMetric({ type: 'interaction', name: 'scroll-depth', value: 90 });
        }
      }
    };
    
    window.addEventListener('scroll', trackScrollDepth, { passive: true });
  }
  
  /**
   * Track navigation timing
   */
  private initNavigationTracking(): void {
    // Track time to interactive
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            this.trackMetric({
              type: 'navigation',
              name: entry.name,
              value: entry.startTime,
            });
          }
        });
        
        observer.observe({ entryTypes: ['navigation'] });
      } catch (error) {
        // Not supported
      }
    }
  }
  
  /**
   * Track resource loading
   */
  private initResourceTracking(): void {
    if ('PerformanceObserver' in window) {
      try {
        const observer = new PerformanceObserver((list) => {
          for (const entry of list.getEntries()) {
            const resource = entry as PerformanceResourceTiming;
            
            // Only track slow resources (> 1s)
            if (resource.duration > 1000) {
              this.trackMetric({
                type: 'performance',
                name: 'slow-resource',
                value: resource.duration,
                metadata: {
                  url: resource.name,
                  type: resource.initiatorType,
                  size: resource.transferSize,
                },
              });
            }
          }
        });
        
        observer.observe({ entryTypes: ['resource'] });
      } catch (error) {
        // Not supported
      }
    }
  }
  
  /**
   * Send metrics on page unload
   */
  private initUnloadHandler(): void {
    window.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') {
        this.flush();
      }
    });
    
    window.addEventListener('pagehide', () => {
      this.flush();
    });
  }
  
  /**
   * Track a metric
   */
  public trackMetric(metric: Omit<RUMMetric, 'timestamp' | 'sessionId' | 'pageUrl' | 'userAgent' | 'deviceType'>): void {
    if (!this.isInitialized) return;
    
    const fullMetric: RUMMetric = {
      ...metric,
      timestamp: Date.now(),
      sessionId: this.sessionId,
      pageUrl: window.location.href,
      userAgent: navigator.userAgent,
      deviceType: this.getDeviceType(),
      connectionType: this.getConnectionType(),
      effectiveType: this.getEffectiveConnectionType(),
    };
    
    this.metricsQueue.push(fullMetric);
    
    // Batch send
    if (this.metricsQueue.length >= this.config.batchSize) {
      this.flush();
    } else if (!this.batchTimer) {
      this.batchTimer = setTimeout(() => {
        this.flush();
      }, this.config.batchInterval);
    }
  }
  
  /**
   * Flush metrics queue
   */
  public flush(): void {
    if (this.metricsQueue.length === 0) return;
    
    const metrics = [...this.metricsQueue];
    this.metricsQueue = [];
    
    if (this.batchTimer) {
      clearTimeout(this.batchTimer);
      this.batchTimer = null;
    }
    
    this.sendMetrics(metrics);
  }
  
  /**
   * Send metrics to backend
   */
  private sendMetrics(metrics: RUMMetric[]): void {
    if (typeof navigator === 'undefined') return;
    
    const body = JSON.stringify({
      metrics,
      session: {
        id: this.sessionId,
        timestamp: Date.now(),
      },
    });
    
    // Use sendBeacon for reliable delivery
    if ('sendBeacon' in navigator) {
      navigator.sendBeacon(this.config.endpoint, body);
    } else {
      // Fallback to fetch
      fetch(this.config.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
      }).catch((error) => {
        console.error('[RUM] Failed to send metrics:', error);
      });
    }
  }
  
  /**
   * Generate unique session ID
   */
  private generateSessionId(): string {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  }
  
  /**
   * Detect device type
   */
  private getDeviceType(): 'mobile' | 'tablet' | 'desktop' {
    const ua = navigator.userAgent;
    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) {
      return 'tablet';
    }
    if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/.test(ua)) {
      return 'mobile';
    }
    return 'desktop';
  }
  
  /**
   * Get connection type
   */
  private getConnectionType(): string | undefined {
    const connection = (navigator as any).connection || (navigator as any).mozConnection || (navigator as any).webkitConnection;
    return connection?.type;
  }
  
  /**
   * Get effective connection type
   */
  private getEffectiveConnectionType(): string | undefined {
    const connection = (navigator as any).connection || (navigator as any).mozConnection || (navigator as any).webkitConnection;
    return connection?.effectiveType;
  }
}

// Singleton instance
let rumInstance: RUMManager | null = null;

/**
 * Initialize RUM monitoring
 */
export function initRUM(config?: RUMConfig): void {
  if (rumInstance) {
    console.warn('[RUM] Already initialized');
    return;
  }
  
  rumInstance = new RUMManager(config);
  rumInstance.init();
}

/**
 * Track custom metric
 */
export function trackRUMMetric(metric: Omit<RUMMetric, 'timestamp' | 'sessionId' | 'pageUrl' | 'userAgent' | 'deviceType'>): void {
  if (!rumInstance) {
    console.warn('[RUM] Not initialized. Call initRUM() first.');
    return;
  }
  
  rumInstance.trackMetric(metric);
}

/**
 * Flush metrics immediately
 */
export function flushRUM(): void {
  if (!rumInstance) return;
  rumInstance.flush();
}

/**
 * Get RUM configuration for different environments
 */
export function getRUMConfig(env: 'development' | 'staging' | 'production'): RUMConfig {
  const baseConfig: RUMConfig = {
    enableErrorTracking: true,
    enableInteractionTracking: true,
    enableNavigationTracking: true,
    enableResourceTracking: false,
    batchSize: 10,
    batchInterval: 5000,
  };
  
  switch (env) {
    case 'development':
      return {
        ...baseConfig,
        endpoint: '/api/analytics/rum',
        sampleRate: 1.0, // Track all sessions in dev
      };
    
    case 'staging':
      return {
        ...baseConfig,
        endpoint: '/api/analytics/rum',
        sampleRate: 0.5, // Track 50% of sessions
      };
    
    case 'production':
      return {
        ...baseConfig,
        endpoint: '/api/analytics/rum',
        sampleRate: 0.1, // Track 10% of sessions
        enableResourceTracking: true,
      };
    
    default:
      return baseConfig;
  }
}
