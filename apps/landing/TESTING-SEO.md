# SEO Testing Guide

## Quick Start

After implementing SEO (Task 4), follow this guide to validate the implementation.

## Local Testing

### 1. Start Development Server

```bash
cd apps/landing
npm run dev
```

### 2. Test Base Landing (No Persona)

Open: http://localhost:5173

**Expected meta tags:**
- Title: "Evento Vivo | Transforme fotos em experiências ao vivo no seu evento"
- Description: "Os convidados já estão tirando fotos. Agora elas viram galeria ao vivo..."
- Canonical: https://eventovivo.com.br

**Verify in browser:**
1. Open DevTools (F12)
2. Go to Elements tab
3. Check `<head>` section for meta tags
4. Look for `<script type="application/ld+json">` tags

### 3. Test Assessora Variation

Open: http://localhost:5173?persona=assessora

**Expected meta tags:**
- Title: "Evento Vivo para Assessoras | Controle total e segurança operacional"
- Description: "Plataforma completa para assessoras e cerimonialistas..."
- Canonical: https://eventovivo.com.br?persona=assessora

### 4. Test Social Variation

Open: http://localhost:5173?persona=social

**Expected meta tags:**
- Title: "Evento Vivo para Casamentos | Fotos ao vivo com emoção e facilidade"
- Description: "Transforme seu casamento, debutante ou festa..."
- Canonical: https://eventovivo.com.br?persona=social

### 5. Test Corporativo Variation

Open: http://localhost:5173?persona=corporativo

**Expected meta tags:**
- Title: "Evento Vivo para Eventos Corporativos | Engajamento e escala"
- Description: "Plataforma enterprise para eventos corporativos..."
- Canonical: https://eventovivo.com.br?persona=corporativo

## Browser Console Testing

### Check Current Meta Tags

```javascript
// Get all meta tags
const metaTags = {};
document.querySelectorAll('meta').forEach(meta => {
  const name = meta.getAttribute('name') || meta.getAttribute('property');
  const content = meta.getAttribute('content');
  if (name && content) metaTags[name] = content;
});
console.table(metaTags);

// Get title
console.log('Title:', document.title);

// Get canonical
const canonical = document.querySelector('link[rel="canonical"]');
console.log('Canonical:', canonical?.href);

// Get structured data
const scripts = document.querySelectorAll('script[type="application/ld+json"]');
scripts.forEach((script, i) => {
  console.log(`Structured Data ${i + 1}:`, JSON.parse(script.textContent));
});
```

### Validate SEO Config

```javascript
// Import validation utilities (in dev mode)
import { validateSEOConfig, logSEOConfig } from './src/utils/seoValidation';
import { getSEOConfig } from './src/config/seo';

// Validate base config
const baseConfig = getSEOConfig(null);
logSEOConfig(baseConfig, 'base');

// Validate assessora config
const assessoraConfig = getSEOConfig('assessora');
logSEOConfig(assessoraConfig, 'assessora');
```

## Production Testing

### 1. Deploy to Staging

```bash
cd apps/landing
npm run build
# Deploy dist/ to staging environment
```

### 2. Google Rich Results Test

**URL:** https://search.google.com/test/rich-results

**Steps:**
1. Enter staging URL: `https://staging.eventovivo.com.br`
2. Click "Test URL"
3. Wait for results
4. Check for errors or warnings

**Expected results:**
- ✅ Organization structured data detected
- ✅ WebPage structured data detected
- ✅ No errors
- ⚠️ Warnings are acceptable (e.g., recommended fields)

**Test all variations:**
- Base: `https://staging.eventovivo.com.br`
- Assessora: `https://staging.eventovivo.com.br?persona=assessora`
- Social: `https://staging.eventovivo.com.br?persona=social`
- Corporativo: `https://staging.eventovivo.com.br?persona=corporativo`

### 3. Facebook Debugger (Open Graph)

**URL:** https://developers.facebook.com/tools/debug/

**Steps:**
1. Enter staging URL
2. Click "Debug"
3. Check preview image
4. Verify title and description
5. Click "Scrape Again" if needed

**Expected results:**
- ✅ Preview image displays (1200x630px)
- ✅ Title matches OG title
- ✅ Description matches OG description
- ✅ No errors or warnings

**Common issues:**
- Image not loading: Check image URL is absolute and publicly accessible
- Old data showing: Click "Scrape Again" to refresh cache
- Missing tags: Verify OG tags in page source

### 4. Twitter Card Validator

**URL:** https://cards-dev.twitter.com/validator

**Steps:**
1. Enter staging URL
2. Click "Preview card"
3. Check preview

**Expected results:**
- ✅ Card type: summary_large_image
- ✅ Preview image displays
- ✅ Title and description correct

**Note:** Twitter Card Validator may require Twitter developer account.

### 5. LinkedIn Post Inspector

**URL:** https://www.linkedin.com/post-inspector/

**Steps:**
1. Enter staging URL
2. Click "Inspect"
3. Check preview

**Expected results:**
- ✅ Preview image displays
- ✅ Title and description correct
- ✅ No errors

## Automated Testing

### TypeScript Type Check

```bash
cd apps/landing
npm run type-check
```

**Expected:** No errors

### Build Test

```bash
cd apps/landing
npm run build
```

**Expected:** Build succeeds without errors

### SEO Validation Script

Create a test file: `apps/landing/src/__tests__/seo.test.ts`

```typescript
import { describe, it, expect } from 'vitest';
import { validateSEOConfig } from '../utils/seoValidation';
import { baseSEOConfig, personaSEOConfig } from '../config/seo';

describe('SEO Configuration', () => {
  it('should have valid base config', () => {
    const validation = validateSEOConfig(baseSEOConfig);
    expect(validation.valid).toBe(true);
    expect(validation.errors).toHaveLength(0);
  });

  it('should have valid assessora config', () => {
    const validation = validateSEOConfig(personaSEOConfig.assessora);
    expect(validation.valid).toBe(true);
    expect(validation.errors).toHaveLength(0);
  });

  it('should have valid social config', () => {
    const validation = validateSEOConfig(personaSEOConfig.social);
    expect(validation.valid).toBe(true);
    expect(validation.errors).toHaveLength(0);
  });

  it('should have valid corporativo config', () => {
    const validation = validateSEOConfig(personaSEOConfig.corporativo);
    expect(validation.valid).toBe(true);
    expect(validation.errors).toHaveLength(0);
  });

  it('should have unique titles per persona', () => {
    const titles = [
      baseSEOConfig.meta.title,
      personaSEOConfig.assessora.meta.title,
      personaSEOConfig.social.meta.title,
      personaSEOConfig.corporativo.meta.title,
    ];
    const uniqueTitles = new Set(titles);
    expect(uniqueTitles.size).toBe(4);
  });

  it('should have unique descriptions per persona', () => {
    const descriptions = [
      baseSEOConfig.meta.description,
      personaSEOConfig.assessora.meta.description,
      personaSEOConfig.social.meta.description,
      personaSEOConfig.corporativo.meta.description,
    ];
    const uniqueDescriptions = new Set(descriptions);
    expect(uniqueDescriptions.size).toBe(4);
  });

  it('should have correct canonical URLs', () => {
    expect(baseSEOConfig.meta.canonical).toContain('eventovivo.com.br');
    expect(personaSEOConfig.assessora.meta.canonical).toContain('persona=assessora');
    expect(personaSEOConfig.social.meta.canonical).toContain('persona=social');
    expect(personaSEOConfig.corporativo.meta.canonical).toContain('persona=corporativo');
  });
});
```

Run tests:
```bash
npm run test
```

## Checklist

### Task 4.1 - Meta Tags Essenciais
- [x] Title tag implemented
- [x] Meta description implemented
- [x] Open Graph tags implemented (og:title, og:description, og:image, og:url)
- [x] Twitter Card tags implemented
- [x] Canonical tag implemented
- [x] Meta tags adapt per persona

### Task 4.2 - Structured Data
- [x] Organization JSON-LD implemented
- [x] WebPage JSON-LD implemented
- [ ] Validated with Google Rich Results Test (requires deployment)

### Task 4.3 - Preview Social
- [x] Favicon created (favicon.svg)
- [x] OG image placeholders documented
- [x] Image generation script created
- [ ] Final OG images created (1200x630px)
- [ ] Final Twitter Card images created (1200x630px)
- [ ] Logo PNG created (512x512px)
- [ ] Tested on Facebook
- [ ] Tested on Twitter
- [ ] Tested on LinkedIn

### Task 4.4 - Meta Tags por Persona
- [x] Assessora variation implemented
- [x] Social variation implemented
- [x] Corporativo variation implemented
- [x] Canonical URLs correct per persona
- [x] SEO component adapts to PersonaContext

## Common Issues

### Issue: Meta tags not updating when persona changes

**Solution:**
1. Check that `PersonaProvider` is wrapping the app
2. Verify `SEO` component is rendered in `App.tsx`
3. Check browser console for errors
4. Clear browser cache and reload

### Issue: Structured data not validating

**Solution:**
1. Check JSON syntax in browser DevTools
2. Verify all required fields are present
3. Ensure URLs are absolute (include https://)
4. Test with Google Rich Results Test

### Issue: Preview images not showing on social media

**Solution:**
1. Verify image URLs are absolute
2. Check images are publicly accessible
3. Verify image dimensions (1200x630px)
4. Clear social media cache:
   - Facebook: Use Facebook Debugger "Scrape Again"
   - Twitter: Wait 7 days or use different URL
   - LinkedIn: Use Post Inspector

### Issue: Canonical URL not updating

**Solution:**
1. Check that canonical link exists in index.html
2. Verify SEO component is updating the href attribute
3. Check browser console for errors
4. Inspect DOM to see current canonical URL

## Next Steps

After completing Task 4:

1. **Generate final OG images** using `scripts/generate-og-placeholders.html` or design tool
2. **Deploy to staging** and test with all validation tools
3. **Fix any issues** found during validation
4. **Deploy to production** after all tests pass
5. **Monitor** Google Search Console for indexing issues
6. **Track** social media engagement with different OG images

## Resources

- [SEO Implementation Guide](./SEO.md)
- [Assets Guide](./public/assets/README.md)
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Facebook Debugger](https://developers.facebook.com/tools/debug/)
- [Twitter Card Validator](https://cards-dev.twitter.com/validator)
- [LinkedIn Post Inspector](https://www.linkedin.com/post-inspector/)
