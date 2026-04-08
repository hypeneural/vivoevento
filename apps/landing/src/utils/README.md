# Landing Page Utilities

Utility modules for routing, tracking, feature flags, and performance monitoring.

## Modules

### `routing.ts`

URL and routing utilities for persona detection and UTM parameter handling.

**Key functions:**
- `getPersonaFromURL()` - Extract persona from URL query params
- `setPersonaInURL(persona)` - Update URL with persona without reload
- `getUTMParams()` - Extract all UTM parameters from URL
- `buildCTAUrl(baseUrl, utmParams)` - Build CTA URL with UTM propagation
- `buildWhatsAppUrl(phone, message, utmParams)` - Build WhatsApp URL with tracking
- `getCanonicalUrl(includePersona)` - Get canonical URL for SEO

**Usage:**
```typescript
import { getPersonaFromURL, buildCTAUrl, getUTMParams } from '@/utils';

// Detect persona from URL
const persona = getPersonaFromURL(); // 'assessora' | 'social' | 'corporativo' | null

// Build CTA URL with UTM params
const utmParams = getUTMParams();
const ctaUrl = buildCTAUrl('https://app.eventovivo.com/demo', utmParams);
```

### `featureFlags.ts`

Feature flags and A/B testing infrastructure.

**Key functions:**
- `isFeatureEnabled(flagId)` - Check if feature is enabled
- `getExperimentVariant(flagId)` - Get assigned experiment variant
- `getActiveExperiments()` - Get all active experiments for session
- `overrideFeatureFlag(flagId, enabled, variant)` - Override flag (dev only)

**Usage:**
```typescript
import { isFeatureEnabled, getExperimentVariant } from '@/utils';

// Check feature flag
if (isFeatureEnabled('floating_cta_enabled')) {
  // Show floating CTA
}

// Get experiment variant
const heroVariant = getExperimentVariant('hero_variant_a');
// Returns: 'control' | 'variant_a' | 'variant_b'
```

**Testing experiments:**
Add URL parameter to force variant: `?experiment_hero_variant_a=variant_a`

### `analytics.ts`

Event tracking and analytics integration.

**Key functions:**
- `analytics.trackCTAClick(event)` - Track CTA clicks
- `analytics.trackScrollDepth(depth, section)` - Track scroll milestones
- `analytics.trackInteraction(event)` - Track user interactions
- `analytics.trackConversion(event)` - Track conversions (macro/micro)
- `analytics.trackExperiment(event)` - Track experiment exposure

**Helper functions:**
- `trackPrimaryCTA(section, destination, persona, utmParams)`
- `trackSecondaryCTA(section, destination, persona, utmParams)`
- `trackMicroconversion(action, section, persona)`
- `trackMacroconversion(action, section, persona)`
- `trackPersonaSelection(persona, section)`
- `trackModuleView(moduleId, section)`
- `trackFAQExpand(question, section)`
- `trackPlanView(planId, section)`

**Usage:**
```typescript
import { trackPrimaryCTA, trackPersonaSelection } from '@/utils';

// Track CTA click
const handleCTAClick = () => {
  trackPrimaryCTA('hero', demoUrl, selectedPersona, utmParams);
  window.location.href = demoUrl;
};

// Track persona selection
const handlePersonaSelect = (persona: PersonaId) => {
  trackPersonaSelection(persona, 'audience-section');
  setSelectedPersona(persona);
};
```

**Automatic tracking:**
- Scroll depth (25%, 50%, 75%, 100%) - tracked automatically
- Page views - call `analytics.trackPageView()` on mount

### `webVitals.ts`

Real User Monitoring (RUM) for Core Web Vitals.

**Key functions:**
- `initWebVitals()` - Initialize Web Vitals tracking (production only)
- `markPerformance(name)` - Create performance mark
- `measurePerformance(name, start, end)` - Measure between marks
- `getNavigationTiming()` - Get navigation timing metrics
- `trackResourceTiming()` - Track resource loading performance

**Usage:**
```typescript
import { initWebVitals } from '@/utils';

// Initialize in main.tsx
initWebVitals();
```

**Tracked metrics:**
- LCP (Largest Contentful Paint) - target ≤2.5s
- INP (Interaction to Next Paint) - target ≤200ms
- CLS (Cumulative Layout Shift) - target ≤0.1
- FCP (First Contentful Paint) - target ≤1.8s
- TTFB (Time to First Byte) - target ≤800ms

## Integration

### In `main.tsx`:

```typescript
import { initWebVitals } from '@/utils';

// Initialize Web Vitals tracking
initWebVitals();

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
```

### In components:

```typescript
import {
  getPersonaFromURL,
  buildCTAUrl,
  getUTMParams,
  trackPrimaryCTA,
  isFeatureEnabled,
} from '@/utils';

function HeroSection() {
  const persona = getPersonaFromURL();
  const utmParams = getUTMParams();
  const showFloatingCTA = isFeatureEnabled('floating_cta_enabled');

  const handleCTAClick = () => {
    const demoUrl = buildCTAUrl('https://app.eventovivo.com/demo', utmParams);
    trackPrimaryCTA('hero', demoUrl, persona, utmParams);
    window.location.href = demoUrl;
  };

  return (
    <section>
      <button onClick={handleCTAClick}>Agendar demonstração</button>
    </section>
  );
}
```

## Environment Variables

### Google Analytics 4:

```env
VITE_GA_MEASUREMENT_ID=G-XXXXXXXXXX
```

Set in `index.html`:
```html
<script>
  window.GA_MEASUREMENT_ID = 'G-XXXXXXXXXX';
</script>
```

## Development vs Production

- **Development**: Uses `ConsoleProvider` - logs events to console
- **Production**: Uses `GA4Provider` - sends events to Google Analytics

## Testing

### Feature Flags:
```typescript
import { overrideFeatureFlag } from '@/utils';

// In development only
overrideFeatureFlag('hero_variant_a', true, 'variant_a');
```

### Experiments:
Add URL parameter: `?experiment_hero_variant_a=variant_a`

### Analytics:
Check browser console in development for logged events.

## Dependencies

- `web-vitals` (^4.2.4) - Core Web Vitals measurement
- `zod` (^4.3.6) - Runtime validation (used in routing)

## Requirements Coverage

- **Requirement 26**: Analytics and conversion tracking
- **Requirement 37**: Modelo de conversão adaptado por persona
- **Requirement 39**: Plano de otimização contínua (A/B testing)
- **Requirement 40**: Performance como prioridade (RUM)
