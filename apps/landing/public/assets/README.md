# Social Preview Assets Guide

## Overview

This directory contains assets for SEO and social media previews. These images appear when the landing page is shared on social media platforms (Facebook, Twitter, LinkedIn) or in search results.

## Required Assets

### Open Graph Images (1200x630px)

These images appear when the page is shared on Facebook, LinkedIn, and other platforms that support Open Graph.

**Files needed:**
- `og-image-default.jpg` - Base variation (no persona)
- `og-image-assessora.jpg` - Assessora/Cerimonialista variation
- `og-image-social.jpg` - Social (Casamento/Debutante) variation
- `og-image-corporativo.jpg` - Corporativo variation

**Specifications:**
- Dimensions: 1200x630px (1.91:1 aspect ratio)
- Format: JPG (preferred) or PNG
- File size: < 1MB (< 500KB recommended)
- Color space: sRGB
- Safe zone: Keep important content within 1200x600px (avoid edges)

**Design Guidelines:**
- Include brand logo (top-left or top-right)
- Include headline text (large, readable)
- Include visual element (photo, illustration, or product UI)
- Use brand colors (#6366F1 primary)
- Ensure text is readable at small sizes
- Test on dark and light backgrounds

**Content per variation:**

1. **Default** (`og-image-default.jpg`)
   - Headline: "Transforme fotos em experiências ao vivo"
   - Visual: Collage of gallery, wall, games, face search
   - Subtext: "Sem app • Moderação IA • Busca facial"

2. **Assessora** (`og-image-assessora.jpg`)
   - Headline: "Controle total e segurança operacional"
   - Visual: Moderation interface + gallery
   - Subtext: "Para assessoras e cerimonialistas"

3. **Social** (`og-image-social.jpg`)
   - Headline: "Fotos ao vivo com emoção e facilidade"
   - Visual: Wedding/party photos in gallery
   - Subtext: "Para casamentos, debutantes e festas"

4. **Corporativo** (`og-image-corporativo.jpg`)
   - Headline: "Engajamento em escala"
   - Visual: Corporate event with wall + games
   - Subtext: "Para eventos corporativos e ativações"

### Twitter Card Images (1200x630px)

Twitter uses the same dimensions as Open Graph. You can reuse the OG images or create Twitter-specific versions.

**Files needed:**
- `twitter-card-default.jpg`
- `twitter-card-assessora.jpg`
- `twitter-card-social.jpg`
- `twitter-card-corporativo.jpg`

**Note:** If Twitter-specific images are not needed, the OG images will be used as fallback.

### Logo (Square)

Used in structured data and as fallback for social media.

**File needed:**
- `logo.png`

**Specifications:**
- Dimensions: 512x512px (square)
- Format: PNG with transparency
- File size: < 200KB
- Minimum: 112x112px (Google requirement)

### Favicon

Already created: `/public/favicon.svg`

**Specifications:**
- Format: SVG (preferred) or ICO
- Dimensions: 32x32px (SVG is scalable)
- Simple, recognizable at small sizes

## Design Tools

### Recommended Tools
- **Figma** - Professional design tool (free tier available)
- **Canva** - Easy-to-use templates (free tier available)
- **Adobe Photoshop** - Professional image editing
- **Sketch** - Mac-only design tool

### Templates
- Figma template: https://www.figma.com/community/file/1234567890/og-image-template
- Canva template: Search "Open Graph Image" in Canva templates

## Creating Images

### Step 1: Design in Figma/Canva
1. Create artboard at 1200x630px
2. Add brand colors and logo
3. Add headline text (60-80pt font size)
4. Add visual elements
5. Add subtext (24-32pt font size)
6. Keep important content within safe zone

### Step 2: Export
1. Export as JPG at 100% quality
2. Optimize with TinyPNG or ImageOptim
3. Verify file size < 1MB
4. Test on different backgrounds

### Step 3: Validate
1. Upload to Facebook Debugger: https://developers.facebook.com/tools/debug/
2. Upload to Twitter Card Validator: https://cards-dev.twitter.com/validator
3. Check LinkedIn Post Inspector: https://www.linkedin.com/post-inspector/
4. Verify image displays correctly on all platforms

## Placeholder Images (Temporary)

Until final images are created, you can use placeholder images:

### Option 1: Generate with Placeholder Services
```
https://placehold.co/1200x630/6366F1/FFFFFF?text=Evento+Vivo
```

### Option 2: Use Figma Plugin
- Install "Content Reel" or "Unsplash" plugin
- Generate placeholder images with brand colors

### Option 3: Screenshot Product UI
- Take screenshots of actual product (gallery, wall, games)
- Resize to 1200x630px
- Add text overlay with headline

## Testing Checklist

After creating images:

- [ ] All images are 1200x630px
- [ ] File sizes are < 1MB
- [ ] Images display correctly on Facebook
- [ ] Images display correctly on Twitter
- [ ] Images display correctly on LinkedIn
- [ ] Text is readable at thumbnail size
- [ ] Logo is visible and clear
- [ ] Brand colors are consistent
- [ ] Images work on dark and light backgrounds
- [ ] Safe zone is respected (no important content at edges)

## Current Status

- [x] Favicon created (`/public/favicon.svg`)
- [ ] OG image - default
- [ ] OG image - assessora
- [ ] OG image - social
- [ ] OG image - corporativo
- [ ] Twitter card - default
- [ ] Twitter card - assessora
- [ ] Twitter card - social
- [ ] Twitter card - corporativo
- [ ] Logo PNG (512x512px)

## References

- [Facebook Sharing Best Practices](https://developers.facebook.com/docs/sharing/webmasters)
- [Twitter Card Documentation](https://developer.twitter.com/en/docs/twitter-for-websites/cards/overview/abouts-cards)
- [LinkedIn Post Inspector](https://www.linkedin.com/help/linkedin/answer/a521928)
- [Open Graph Protocol](https://ogp.me/)
