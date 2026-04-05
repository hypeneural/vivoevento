# Evento Vivo Landing

Landing page premium do Evento Vivo, isolada em `apps/landing`, focada em conversão para demonstração comercial.

## Stack

- React 18 + TypeScript + Vite 5
- SCSS Modules + tokens globais em `src/styles/`
- GSAP para hero e cenas de storytelling
- Motion para cards, tabs, grids e transições de interface
- Rive com wrapper lazy e fallback visual em SCSS
- Phaser carregado sob demanda na seção de jogos

## Estrutura principal

```text
src/
├── App.tsx
├── data/landing.ts
├── hooks/
│   ├── useActiveSection.ts
│   └── useSmoothScroll.ts
├── components/
│   ├── Navbar.tsx
│   ├── HeroExperience.tsx
│   ├── EcosystemSection.tsx
│   ├── DynamicGallerySection.tsx
│   ├── InteractiveGamesSection.tsx
│   ├── DynamicWallSection.tsx
│   ├── ModerationAISection.tsx
│   ├── FaceSearchSection.tsx
│   ├── TechnicalTrustSection.tsx
│   ├── ComparisonSection.tsx
│   ├── TestimonialsSection.tsx
│   ├── AudienceSection.tsx
│   ├── PricingSection.tsx
│   ├── FAQSection.tsx
│   ├── FinalCTASection.tsx
│   ├── FooterSection.tsx
│   ├── games/PhaserGamesShowcase.tsx
│   └── rive/
│       ├── LazyRivePanel.tsx
│       └── RivePlayer.tsx
└── styles/
    ├── _variables.scss
    ├── _mixins.scss
    └── globals.scss
```

## Princípios da landing

- Hero nasce pronta: a animação só reforça o que já está visível.
- Menu usa âncoras reais com compensação do header fixo.
- Jogos, Rive e demos pesadas só carregam quando entram em viewport.
- `prefers-reduced-motion` é respeitado em GSAP, Motion e smooth scroll.
- Copy centralizada em `src/data/landing.ts` para facilitar evolução comercial.

## Scripts

```bash
cd apps/landing
npm install
npm run dev
npm run build
npm run type-check
```
