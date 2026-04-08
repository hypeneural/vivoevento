# Performance Optimization Guide

## Overview

This document describes the performance optimization strategy for the Evento Vivo landing page, focusing on real user experience through Core Web Vitals and Real User Monitoring (RUM).

## Core Web Vitals Targets

| Metric | Target | Description |
|--------|--------|-------------|
| **LCP** | ≤ 2.5s | Largest Contentful Paint - measures loading performance |
| **INP** | ≤ 200ms | Interaction to Next Paint - measures interactivity (replaces FID) |
| **CLS** | ≤ 0.1 | Cumulative Layout Shift - measures visual stability |
| **FCP** | ≤ 1.8s | First Contentful Paint - measures perceived load speed |
| **TTFB** | ≤ 800ms | Time to First Byte - measures server response time |

## Optimization Strategies

### 1. Selective Lazy Loading

**Strategy:** Load critical content immediately, defer non-critical content.

**Implementation:**
- ✅ Immediate load: Navbar, HeroSection, TrustSignals
- ✅ Lazy load: All sections below the fold
- ✅ Heavy demos: Phaser games, Rive animations (loaded on user interaction)
- ✅ Suspense boundaries with appropriate fallbacks

**Files:**
- `src/App.tsx` - Lazy loading configuration
- `src/App.module.scss` - Fallback styles

### 2. Image Optimization

**Strategy:** Optimize images for performance without sacrificing quality.

**Implementation:**
- ✅ WebP format with fallback to JPG/PNG
- ✅ Responsive srcset for different viewport sizes
- ✅ Lazy loading for below-the-fold images
- ✅ Preload for critical above-the-fold images
- ✅ Aspect ratio preservation to prevent CLS
- ✅ Image decode before display to prevent jank

**Files:**
- `src/utils/imageOptimization.ts` - Image optimization utilities
- `src/components/ui/OptimizedImage.tsx` - Optimized image component
- `src/components/ui/OptimizedImage.module.scss` - Image component styles

**Usage:**
```tsx
import OptimizedImage from '@/components/ui/OptimizedImage';

<OptimizedImage
  src="/images/hero.jpg"
  alt="Hero visual"
  width={1200}
  height={800}
  priority={true}  // For above-the-fold images
  sizes="(max-width: 640px) 100vw, 1200px"
/>
```

### 3. Core Web Vitals Optimization

**Strategy:** Optimize for LCP, INP, and CLS through targeted improvements.

**LCP Optimization:**
- Preload critical resources
- Optimize server response time
- Eliminate render-blocking resources
- Optimize images and fonts

**INP Optimization (replaces FID):**
- Break up long tasks using `yieldToMain()`
- Defer non-critical JavaScript
- Use `requestIdleCallback` for non-critical work
- Optimize event handlers with debounce/throttle

**CLS Optimization:**
- Set explicit dimensions for images and videos
- Reserve space for dynamic content
- Use CSS `aspect-ratio`
- Avoid inserting content above existing content

**Files:**
- `src/utils/webVitals.ts` - Web Vitals utilities and monitoring

**Usage:**
```tsx
import { initWebVitalsOptimizations } from '@/utils/webVitals';

// In App.tsx
useEffect(() => {
  initWebVitalsOptimizations();
}, []);
```

### 4. Real User Monitoring (RUM)

**Strategy:** Monitor real user experience in production to identify performance issues.

**What RUM Tracks:**
- Core Web Vitals (LCP, INP, CLS, FCP, TTFB)
- Performance metrics (DNS, TCP, request/response times)
- JavaScript errors and unhandled promise rejections
- User interactions (scroll depth, long tasks)
- Navigation timing
- Resource loading (slow resources > 1s)
- Device type and connection quality

**Laboratory vs Field Metrics:**
- **Laboratory (Lighthouse):** Synthetic tests in controlled environment
- **Field (RUM):** Real users with varying devices, networks, and conditions
- **Why both matter:** Lab tests are reproducible, field data shows real experience

**Files:**
- `src/utils/rum.ts` - RUM implementation

**Configuration:**
```tsx
import { initRUM, getRUMConfig } from '@/utils/rum';

// Development: Track 100% of sessions
const devConfig = getRUMConfig('development');

// Staging: Track 50% of sessions
const stagingConfig = getRUMConfig('staging');

// Production: Track 10% of sessions
const prodConfig = getRUMConfig('production');

initRUM(prodConfig);
```

**Custom Metrics:**
```tsx
import { trackRUMMetric } from '@/utils/rum';

trackRUMMetric({
  type: 'interaction',
  name: 'cta-click',
  value: 'primary',
  metadata: {
    section: 'hero',
    persona: 'assessora',
  },
});
```

### 5. Development Tools

**Performance Monitor:**
- Real-time FPS monitoring
- Live Core Web Vitals display
- Color-coded ratings (good/needs-improvement/poor)
- Toggle with `Ctrl+Shift+P`

**Files:**
- `src/components/dev/PerformanceMonitor.tsx` - Performance monitor component
- `src/components/dev/PerformanceMonitor.module.scss` - Monitor styles

**Note:** Only available in development mode.

## Performance Checklist

### Before Deployment

- [ ] Run Lighthouse audit (Performance ≥ 85, Accessibility ≥ 90)
- [ ] Test on 3G Fast throttling (1.6 Mbps)
- [ ] Verify LCP ≤ 2.5s on mobile
- [ ] Verify INP ≤ 200ms
- [ ] Verify CLS ≤ 0.1
- [ ] Test lazy loading works correctly
- [ ] Verify images have correct dimensions
- [ ] Check for console errors
- [ ] Test on iOS Safari, Chrome Android, Desktop browsers

### After Deployment

- [ ] Monitor RUM metrics for 24-48 hours
- [ ] Check for performance regressions
- [ ] Review error rates
- [ ] Analyze slow resources
- [ ] Compare lab vs field metrics
- [ ] Set up alerts for degradation

## Monitoring and Alerts

### RUM Endpoint

RUM metrics are sent to: `/api/analytics/rum`

**Payload structure:**
```json
{
  "metrics": [
    {
      "type": "web-vital",
      "name": "LCP",
      "value": 2100,
      "timestamp": 1234567890,
      "sessionId": "abc123",
      "pageUrl": "https://eventovivo.com/",
      "userAgent": "...",
      "deviceType": "mobile",
      "connectionType": "4g",
      "effectiveType": "4g",
      "metadata": {
        "rating": "good",
        "delta": 100,
        "id": "v3-...",
        "navigationType": "navigate"
      }
    }
  ],
  "session": {
    "id": "abc123",
    "timestamp": 1234567890
  }
}
```

### Recommended Alerts

1. **LCP > 4s** - Poor loading performance
2. **INP > 500ms** - Poor interactivity
3. **CLS > 0.25** - Poor visual stability
4. **Error rate > 1%** - High error rate
5. **Slow resources > 5s** - Resource loading issues

## Best Practices

### Images

1. Always use `OptimizedImage` component
2. Set `priority={true}` for above-the-fold images
3. Provide explicit `width` and `height`
4. Use appropriate `sizes` attribute
5. Provide fallback images

### JavaScript

1. Break up long tasks (> 50ms)
2. Use `requestIdleCallback` for non-critical work
3. Debounce/throttle event handlers
4. Defer non-critical scripts
5. Use code splitting for large components

### CSS

1. Use `aspect-ratio` for responsive images
2. Avoid layout shifts
3. Minimize render-blocking CSS
4. Use CSS containment where appropriate
5. Optimize animations with `will-change`

### Fonts

1. Preconnect to font origins
2. Use `font-display: swap`
3. Subset fonts to reduce size
4. Preload critical fonts
5. Use system fonts as fallback

## Troubleshooting

### High LCP

- Check image sizes and formats
- Verify preload is working
- Check server response time (TTFB)
- Look for render-blocking resources
- Verify lazy loading isn't hiding LCP element

### High INP

- Profile long tasks in DevTools
- Check for heavy event handlers
- Look for synchronous operations
- Verify debounce/throttle is working
- Check for blocking third-party scripts

### High CLS

- Verify all images have dimensions
- Check for dynamic content insertion
- Look for web fonts causing shifts
- Verify aspect-ratio is working
- Check for ads or embeds causing shifts

## Resources

- [Web Vitals](https://web.dev/vitals/)
- [INP (Interaction to Next Paint)](https://web.dev/inp/)
- [Optimize LCP](https://web.dev/optimize-lcp/)
- [Optimize INP](https://web.dev/optimize-inp/)
- [Optimize CLS](https://web.dev/optimize-cls/)
- [Real User Monitoring](https://web.dev/vitals-measurement-getting-started/)
