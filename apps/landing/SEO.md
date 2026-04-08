# SEO Implementation Guide

## Overview

The landing page implements comprehensive SEO with dynamic meta tags, Open Graph tags, Twitter Cards, and structured data (JSON-LD) that adapt based on the selected persona.

## Architecture

### Files Structure

```
apps/landing/
├── src/
│   ├── types/
│   │   └── seo.ts                    # SEO type definitions
│   ├── config/
│   │   └── seo.ts                    # SEO configuration per persona
│   ├── components/
│   │   └── SEO.tsx                   # SEO component (react-helmet-async)
│   └── utils/
│       └── seoValidation.ts          # Validation and testing utilities
├── index.html                        # Base HTML with minimal meta tags
└── SEO.md                            # This file
```

### How It Works

1. **Base Configuration**: `index.html` contains minimal meta tags (charset, viewport, fonts)
2. **Dynamic Meta Tags**: `SEO.tsx` component uses `react-helmet-async` to inject meta tags dynamically
3. **Persona Adaptation**: SEO configuration changes based on `PersonaContext` (assessora, social, corporativo)
4. **Structured Data**: JSON-LD for Organization and WebPage is injected via `<script type="application/ld+json">`

## Persona Variations

### Base (No Persona)
- **URL**: `https://eventovivo.com.br`
- **Title**: "Evento Vivo | Transforme fotos em experiências ao vivo no seu evento"
- **Focus**: General value proposition for all audiences

### Assessora
- **URL**: `https://eventovivo.com.br?persona=assessora`
- **Title**: "Evento Vivo para Assessoras | Controle total e segurança operacional"
- **Focus**: Control, security, moderation for event planners

### Social
- **URL**: `https://eventovivo.com.br?persona=social`
- **Title**: "Evento Vivo para Casamentos | Fotos ao vivo com emoção e facilidade"
- **Focus**: Emotion, ease of use for weddings and parties

### Corporativo
- **URL**: `https://eventovivo.com.br?persona=corporativo`
- **Title**: "Evento Vivo para Eventos Corporativos | Engajamento e escala"
- **Focus**: Engagement, scale, analytics for corporate events

## Meta Tags Implemented

### Primary Meta Tags
- `<title>` - Page title (50-60 characters recommended)
- `<meta name="description">` - Page description (150-160 characters recommended)
- `<meta name="keywords">` - Keywords (optional, low SEO impact)
- `<meta name="author">` - Author
- `<link rel="canonical">` - Canonical URL

### Open Graph Tags (Facebook, LinkedIn)
- `og:type` - Content type (website)
- `og:url` - Page URL
- `og:title` - OG title
- `og:description` - OG description
- `og:image` - Preview image (1200x630px recommended)
- `og:locale` - Locale (pt_BR)
- `og:site_name` - Site name

### Twitter Card Tags
- `twitter:card` - Card type (summary_large_image)
- `twitter:url` - Page URL
- `twitter:title` - Twitter title
- `twitter:description` - Twitter description
- `twitter:image` - Preview image
- `twitter:site` - Twitter handle (@eventovivo)

### Structured Data (JSON-LD)

#### Organization
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Evento Vivo",
  "url": "https://eventovivo.com.br",
  "logo": "https://eventovivo.com.br/assets/logo.png",
  "description": "Plataforma premium de experiências visuais ao vivo para eventos",
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+5548996553954",
    "contactType": "customer service",
    "availableLanguage": ["Portuguese", "pt-BR"]
  },
  "sameAs": [
    "https://instagram.com/eventovivo"
  ]
}
```

#### WebPage
```json
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Evento Vivo | Transforme fotos em experiências ao vivo",
  "description": "Plataforma premium de experiências visuais ao vivo para eventos",
  "url": "https://eventovivo.com.br",
  "inLanguage": "pt-BR",
  "isPartOf": {
    "@type": "WebSite",
    "name": "Evento Vivo",
    "url": "https://eventovivo.com.br"
  }
}
```

## Canonical URL Strategy

### Decision (from Task 0.3)
Each persona variation has its own canonical URL pointing to the persona-specific URL:

- Base: `https://eventovivo.com.br`
- Assessora: `https://eventovivo.com.br?persona=assessora`
- Social: `https://eventovivo.com.br?persona=social`
- Corporativo: `https://eventovivo.com.br?persona=corporativo`

**Rationale**: Each persona variation provides significantly different content and value propositions, warranting separate indexing.

**Alternative considered**: All variations pointing to base URL (consolidated indexing) - rejected because persona-specific content is valuable for targeted search queries.

## Validation and Testing

### Development Validation

Use the validation utilities in development:

```typescript
import { logSEOConfig, validateSEOConfig } from '@/utils/seoValidation';
import { getSEOConfig } from '@/config/seo';

// Log SEO config for a persona
const config = getSEOConfig('assessora');
logSEOConfig(config, 'assessora');

// Validate configuration
const validation = validateSEOConfig(config);
console.log(validation);
```

### Google Rich Results Test

1. Deploy to staging/production
2. Run in browser console:
```javascript
import { testStructuredData } from '@/utils/seoValidation';
testStructuredData(); // Opens Google Rich Results Test
```

Or manually: https://search.google.com/test/rich-results

### Facebook Debugger (Open Graph)

1. Deploy to staging/production
2. Run in browser console:
```javascript
import { testOpenGraph } from '@/utils/seoValidation';
testOpenGraph(); // Opens Facebook Debugger
```

Or manually: https://developers.facebook.com/tools/debug/

### Twitter Card Validator

1. Deploy to staging/production
2. Run in browser console:
```javascript
import { testTwitterCard } from '@/utils/seoValidation';
testTwitterCard(); // Opens Twitter Card Validator
```

Or manually: https://cards-dev.twitter.com/validator

### LinkedIn Post Inspector

Manually test: https://www.linkedin.com/post-inspector/

## Assets Required (Task 4.3)

### Preview Images (1200x630px)
- `/public/assets/og-image-default.jpg` - Base variation
- `/public/assets/og-image-assessora.jpg` - Assessora variation
- `/public/assets/og-image-social.jpg` - Social variation
- `/public/assets/og-image-corporativo.jpg` - Corporativo variation

### Twitter Card Images (1200x630px)
- `/public/assets/twitter-card-default.jpg`
- `/public/assets/twitter-card-assessora.jpg`
- `/public/assets/twitter-card-social.jpg`
- `/public/assets/twitter-card-corporativo.jpg`

### Logo
- `/public/assets/logo.png` - For structured data (square, min 112x112px)

### Favicon
- `/public/favicon.svg` - SVG favicon (preferred)
- `/public/favicon.ico` - Fallback ICO favicon

## Best Practices

### Title Tags
- Keep between 50-60 characters
- Include brand name
- Front-load important keywords
- Make unique per page/variation

### Meta Descriptions
- Keep between 150-160 characters
- Include call-to-action
- Accurately describe page content
- Make unique per page/variation

### Open Graph Images
- Size: 1200x630px (recommended)
- Format: JPG or PNG
- File size: < 1MB
- Include text overlay for context
- Test on multiple devices

### Canonical URLs
- Always use absolute URLs
- Include protocol (https://)
- Be consistent with trailing slashes
- Update when URL structure changes

### Structured Data
- Validate with Google Rich Results Test
- Keep data accurate and up-to-date
- Don't mark up content not visible to users
- Use specific types when possible

## Troubleshooting

### Meta tags not updating
- Check that `HelmetProvider` is wrapping the app in `main.tsx`
- Verify `SEO` component is rendered in `App.tsx`
- Check browser console for errors
- Clear browser cache

### Structured data not validating
- Validate JSON syntax
- Check all required fields are present
- Ensure URLs are absolute
- Test with Google Rich Results Test

### Preview images not showing
- Verify image URLs are absolute
- Check image dimensions (1200x630px)
- Ensure images are publicly accessible
- Clear social media cache (Facebook Debugger, Twitter Card Validator)

### Canonical URL issues
- Verify canonical URL is absolute
- Check for duplicate canonical tags
- Ensure canonical points to preferred version
- Test with Google Search Console

## Monitoring

### Google Search Console
- Monitor index coverage
- Check for crawl errors
- Review search performance by query
- Monitor Core Web Vitals

### Social Media Insights
- Facebook: Check post engagement with different OG images
- Twitter: Monitor card impressions
- LinkedIn: Track post performance

## Future Improvements

- [ ] Add breadcrumb structured data
- [ ] Add FAQ structured data (from FAQSection)
- [ ] Add Review/Rating structured data (from testimonials)
- [ ] Implement hreflang tags for internationalization
- [ ] Add Article structured data for blog posts
- [ ] Implement dynamic sitemap generation
- [ ] Add robots.txt configuration
