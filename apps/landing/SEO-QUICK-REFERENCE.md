# SEO Quick Reference

## 🚀 Quick Start

SEO is implemented and working automatically. No manual intervention needed.

## 📝 How It Works

1. **SEO component** (`src/components/SEO.tsx`) is rendered in `App.tsx`
2. **PersonaContext** detects URL params (`?persona=assessora`)
3. **SEO config** (`src/config/seo.ts`) provides meta tags per persona
4. **react-helmet-async** injects meta tags dynamically into `<head>`

## 🔗 URLs

| Variation | URL | Title |
|-----------|-----|-------|
| Base | `https://eventovivo.com.br` | Transforme fotos em experiências ao vivo |
| Assessora | `https://eventovivo.com.br?persona=assessora` | Controle total e segurança operacional |
| Social | `https://eventovivo.com.br?persona=social` | Fotos ao vivo com emoção e facilidade |
| Corporativo | `https://eventovivo.com.br?persona=corporativo` | Engajamento em escala |

## 🎨 Assets Needed

Before production deployment, create these assets:

### OG Images (1200x630px)
- [ ] `public/assets/og-image-default.jpg`
- [ ] `public/assets/og-image-assessora.jpg`
- [ ] `public/assets/og-image-social.jpg`
- [ ] `public/assets/og-image-corporativo.jpg`

### Twitter Cards (1200x630px)
- [ ] `public/assets/twitter-card-default.jpg`
- [ ] `public/assets/twitter-card-assessora.jpg`
- [ ] `public/assets/twitter-card-social.jpg`
- [ ] `public/assets/twitter-card-corporativo.jpg`

### Logo
- [ ] `public/assets/logo.png` (512x512px)

### Favicon
- [x] `public/favicon.svg` (already created)

**Tool:** Use `scripts/generate-og-placeholders.html` to generate placeholders

## ✅ Testing Checklist

### Local Testing
```bash
# Start dev server
npm run dev

# Test URLs
http://localhost:5173
http://localhost:5173?persona=assessora
http://localhost:5173?persona=social
http://localhost:5173?persona=corporativo

# Check meta tags in DevTools > Elements > <head>
```

### Production Testing
- [ ] Google Rich Results Test: https://search.google.com/test/rich-results
- [ ] Facebook Debugger: https://developers.facebook.com/tools/debug/
- [ ] Twitter Card Validator: https://cards-dev.twitter.com/validator
- [ ] LinkedIn Post Inspector: https://www.linkedin.com/post-inspector/

## 🛠️ Common Tasks

### Update Title/Description
Edit `src/config/seo.ts`:
```typescript
export const baseSEOConfig: SEOConfig = {
  meta: {
    title: 'Your new title',
    description: 'Your new description',
    // ...
  },
  // ...
};
```

### Update OG Image
Replace file in `public/assets/og-image-default.jpg`

### Add New Persona
1. Add to `PersonaId` type in `src/contexts/PersonaContext.tsx`
2. Add config in `src/config/seo.ts` under `personaSEOConfig`
3. Create OG images for new persona

### Debug SEO
```javascript
// In browser console
import { getCurrentMetaTags } from './src/utils/seoValidation';
console.table(getCurrentMetaTags());
```

## 📚 Full Documentation

- **Implementation Guide:** `SEO.md`
- **Testing Guide:** `TESTING-SEO.md`
- **Asset Guide:** `public/assets/README.md`
- **Task Summary:** `TASK-4-SUMMARY.md`

## 🐛 Troubleshooting

### Meta tags not updating?
1. Check PersonaProvider is wrapping app in `main.tsx`
2. Check SEO component is in `App.tsx`
3. Clear browser cache

### Images not showing on social media?
1. Verify image URLs are absolute
2. Check images are publicly accessible
3. Clear social media cache (Facebook Debugger "Scrape Again")

### Structured data not validating?
1. Check JSON syntax in DevTools
2. Verify all required fields
3. Test with Google Rich Results Test

## 📞 Support

Questions? Check the full documentation in `SEO.md` or `TESTING-SEO.md`.
