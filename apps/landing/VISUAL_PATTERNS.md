# Visual Pattern Alternation Strategy

**Requirement 38:** Alternância Visual Emocional/Funcional/Comercial

## Pattern Definitions

### 1. Emotional (Fotografia Real de Evento)
- **Visual:** Real event photography, warm tones
- **Background:** Dark with warm gradient overlay
- **Purpose:** Create emotional connection
- **Examples:** Hero with event photos, Testimonials with event context

### 2. Functional (UI do Produto)
- **Visual:** Product interface screenshots, cyan/blue tones
- **Background:** Soft dark with cyan gradient overlay
- **Purpose:** Show product capabilities
- **Examples:** Experience Modules, Gallery demo, Wall layouts

### 3. Commercial (Layout Limpo)
- **Visual:** Clean layout, minimal decoration
- **Background:** Pure dark, no gradient
- **Purpose:** Focus on conversion and clarity
- **Examples:** Pricing, FAQ, Final CTA

## Section Pattern Sequence

| # | Section | Pattern | Visual Type | Rationale |
|---|---------|---------|-------------|-----------|
| 1 | Hero | **Emotional** | Event photos + trust signals | Immediate emotional hook |
| 2 | How It Works | **Commercial** | Clean icons + flow | Clear explanation |
| 3 | Capture Channels | **Functional** | Channel UI examples | Product capability |
| 4 | Experience Modules | **Functional** | Product UI (gallery/wall/games) | Product showcase |
| 5 | AI Safety | **Commercial** | Clean layout + icons | Trust and clarity |
| 6 | Face Recognition | **Emotional** | Selfie + matches | Emotional benefit |
| 7 | Audience | **Commercial** | Clean cards | Clear segmentation |
| 8 | Testimonials | **Emotional** | Event photos + quotes | Social proof |
| 9 | Pricing | **Commercial** | Clean pricing cards | Conversion focus |
| 10 | FAQ | **Commercial** | Clean accordion | Remove objections |
| 11 | Final CTA | **Emotional** | Event imagery + CTA | Final emotional push |

## Pattern Distribution

- **Emotional:** 3 sections (Hero, Face Recognition, Testimonials, Final CTA) = **4 sections** ✅ (≥3 required)
- **Functional:** 2 sections (Capture Channels, Experience Modules) = **2 sections** ✅ (≥2 required)
- **Commercial:** 5 sections (How It Works, AI Safety, Audience, Pricing, FAQ)

## Consecutive Pattern Check

✅ **No more than 2 consecutive sections with same pattern:**

1. Hero (Emotional)
2. How It Works (Commercial) ← Break
3. Capture Channels (Functional) ← Break
4. Experience Modules (Functional) ← 2 consecutive OK
5. AI Safety (Commercial) ← Break
6. Face Recognition (Emotional) ← Break
7. Audience (Commercial) ← Break
8. Testimonials (Emotional) ← Break
9. Pricing (Commercial) ← Break
10. FAQ (Commercial) ← 2 consecutive OK
11. Final CTA (Emotional) ← Break

**Maximum consecutive:** 2 (Capture Channels + Experience Modules, Pricing + FAQ)

## Implementation

### SCSS Mixins
```scss
@mixin pattern-emotional {
  background: $c-bg-dark;
  &::before {
    background: radial-gradient(circle at 30% 50%, rgba(255, 191, 114, 0.08), transparent 60%);
  }
}

@mixin pattern-functional {
  background: $c-bg-soft;
  &::before {
    background: radial-gradient(circle at 70% 50%, rgba(115, 236, 255, 0.06), transparent 60%);
  }
}

@mixin pattern-commercial {
  background: $c-bg-dark;
  // Clean, no gradient
}
```

### Usage in Components
```scss
.heroSection {
  @include pattern-emotional;
  // ... rest of styles
}

.experienceModulesSection {
  @include pattern-functional;
  // ... rest of styles
}

.pricingSection {
  @include pattern-commercial;
  // ... rest of styles
}
```

## Visual Assets Required

### Emotional Sections (Real Event Photography)
- ✅ Hero: Event photos from `eventImages.heroMobile`, `eventImages.galleryFeatured`
- ✅ Face Recognition: `eventImages.faceSelfie`, `eventImages.faceMatchOne/Two/Three`
- ✅ Testimonials: Event photos from `testimonial.event.photo`
- ✅ Final CTA: Event imagery background

### Functional Sections (Product UI)
- ⚠️ Capture Channels: Channel UI screenshots (placeholder paths exist)
- ⚠️ Experience Modules: Gallery/Wall/Games UI (using event images as placeholder)

### Commercial Sections (Clean Layout)
- ✅ How It Works: Icons only (no photos)
- ✅ AI Safety: Icons + clean layout
- ✅ Audience: Icons + clean cards
- ✅ Pricing: Clean pricing cards
- ✅ FAQ: Clean accordion

## Notes

- **Emotional sections** use warm tones (coral, warm orange) to create connection
- **Functional sections** use cool tones (cyan, blue) to show technology
- **Commercial sections** use neutral dark to focus on content
- Alternation creates visual rhythm that maintains engagement without fatigue
- Pattern breaks prevent monotony and guide user through journey
