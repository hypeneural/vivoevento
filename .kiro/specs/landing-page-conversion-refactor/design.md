# Design Técnico: Refatoração Landing Page Evento Vivo

## Introdução

Este documento detalha o design técnico completo para a refatoração da landing page do Evento Vivo com foco em conversão comercial. A refatoração visa transformar a landing atual (16 componentes, densidade visual uniforme, excesso de informação) em uma experiência premium, clean e orientada a conversão (12 componentes, hierarquia clara, storytelling comercial direto).

**Estratégia CRO:** Baseada em CXL, NN/g e web.dev, incorporando message match, clareza de audiência, prova social contextualizada, microconversões, trust signals e otimização contínua via testes A/B.

**Stack Técnica:**
- React 18.3.1 + TypeScript 5.8.3
- Vite 5.4.19 com SWC
- SCSS Modules (sass 1.99.0)
- GSAP 3.14.2 + Motion 12.38.0
- Lenis 1.3.21 (smooth scroll)
- Rive 4.27.3 (animações)
- Phaser 3.90.0 (jogos - lazy loaded)
- Lucide React 0.462.0 (ícones)

## Overview

A refatoração reduz 16 componentes para 12, fusionando seções redundantes e criando hierarquia clara. A nova estrutura alterna entre seções explicativas (layout limpo), emocionais (fotografia real) e comerciais (UI do produto), criando ritmo visual que mantém engajamento sem fadiga.

**Transformação principal:**
- Hero: De experiência interativa para resultado percebido + trust signals
- Módulos: De 4 seções separadas para 1 seção unificada com tabs
- IA: De técnico para segurança operacional
- Face: De funcional para emocional
- Audiência: De tabs para 3 cards escaneáveis
- Depoimentos: De genéricos para contextualizados por tipo de evento
- Planos: De denso para 3 categorias claras

**Variações por Persona:**
A landing suporta 3 variações de entrada via URL params, adaptando hero, prova social, ordem de módulos e CTAs para cada perfil:
1. Assessoras/Cerimonialistas (controle + segurança)
2. Noivas/Debutantes/Famílias (emoção + facilidade)
3. Promotores/Produtores/Corporativos (engajamento + escala)

## Arquitetura de Alto Nível

### 1.1 Estrutura de Componentes

```
Landing Page Architecture
├── App.tsx (orquestrador principal)
├── Navbar (fixo, compacto após scroll)
├── Main
│   ├── HeroSection (refatorado)
│   ├── HowItWorksSection (novo)
│   ├── CaptureChannelsSection (novo)
│   ├── ExperienceModulesSection (fusão Gallery + Games + Wall)
│   ├── AISafetySection (fusão Moderation + segurança)
│   ├── FaceRecognitionSection (refatorado)
│   ├── AudienceSection (refatorado)
│   ├── TestimonialsSection (refatorado)
│   ├── PricingSection (refatorado)
│   ├── FAQSection (refatorado)
│   └── FinalCTASection
└── FooterSection
```

**Hierarquia de Componentes:**

```typescript
// Componentes de Seção (Section Components)
// Responsabilidade: Orquestrar layout e composição de uma seção completa
HeroSection/
├── HeroContent (copy + CTAs)
├── HeroVisual (demo interativa)
└── TrustSignals (faixa de confiança)

ExperienceModulesSection/
├── ModuleTabs (navegação)
├── ModuleContent (conteúdo ativo)
│   ├── ModuleMedia (visual principal)
│   └── ModuleFeatures (pontos de apoio)
└── ModuleCTA (ação contextual)

// Componentes Reutilizáveis (Shared Components)
// Responsabilidade: UI base reutilizável entre seções
src/components/ui/
├── Button (primário, secundário, ghost)
├── Card (variações)
├── Badge
├── Accordion
├── Tabs
├── Carousel
└── Input

// Componentes de Domínio (Domain Components)
// Responsabilidade: Lógica específica de negócio
src/components/
├── PersonaSelector (seletor de perfil)
├── CTAFloating (CTA flutuante)
├── TrustBadge (selo de confiança)
└── TestimonialCard (depoimento contextualizado)
```

**Lazy Loading Boundaries:**

```typescript
// Carregamento imediato (acima da dobra)
- Navbar
- HeroSection
- TrustSignals

// Lazy load (abaixo da dobra)
const HowItWorksSection = lazy(() => import('./HowItWorksSection'));
const CaptureChannelsSection = lazy(() => import('./CaptureChannelsSection'));
const ExperienceModulesSection = lazy(() => import('./ExperienceModulesSection'));
// ... demais seções

// Lazy load crítico (demos pesadas)
const PhaserGameDemo = lazy(() => import('./games/PhaserGameDemo'));
const RiveAnimation = lazy(() => import('./rive/RiveAnimation'));
```

### 1.2 Arquitetura de Dados

**Estrutura de `landing.ts` Expandida:**

```typescript
// ============================================
// TIPOS BASE
// ============================================

export type PersonaId = 'assessora' | 'social' | 'corporativo';

export type NavItem = {
  id: string;
  label: string;
};

export type TrustSignal = {
  id: string;
  icon: string;
  text: string;
  detail?: string;
};

export type CTAConfig = {
  primary: {
    text: string;
    url: string;
    icon?: string;
  };
  secondary: {
    text: string;
    url: string;
    icon?: string;
  };
};

// ============================================
// HERO
// ============================================

export type HeroContent = {
  eyebrow: string;
  headline: {
    lead: string;
    accent: string[];
  };
  subheadline: string;
  flowSteps: Array<{
    number: number;
    title: string;
    detail: string;
  }>;
  ctas: CTAConfig;
  trustSignals: TrustSignal[];
  metrics: Array<{
    value: string;
    label: string;
  }>;
};

// Variações por persona
export type HeroVariations = {
  assessora: HeroContent;
  social: HeroContent;
  corporativo: HeroContent;
};

// ============================================
// COMO FUNCIONA
// ============================================

export type HowItWorksStep = {
  id: string;
  number: number;
  title: string;
  description: string;
  icon: string;
  visual?: string;
};

export type HowItWorksContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  steps: HowItWorksStep[];
};

// ============================================
// CANAIS DE CAPTURA
// ============================================

export type CaptureChannel = {
  id: string;
  name: string;
  description: string;
  icon: string;
  visual: string;
  features: string[];
  useCases: string[];
};

export type CaptureChannelsContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  channels: CaptureChannel[];
};

// ============================================
// MÓDULOS DE EXPERIÊNCIA
// ============================================

export type ExperienceModule = {
  id: 'gallery' | 'wall' | 'games' | 'face';
  name: string;
  eyebrow: string;
  title: string;
  benefit: string;
  features: string[];
  visual: {
    type: 'image' | 'video' | 'demo';
    src: string;
    alt: string;
    poster?: string;
  };
  cta?: {
    text: string;
    action: string;
  };
};

// Ordem prioritária por persona
export type ModulePriority = {
  assessora: ExperienceModule['id'][];
  social: ExperienceModule['id'][];
  corporativo: ExperienceModule['id'][];
};

export type ExperienceModulesContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  modules: ExperienceModule[];
  priority: ModulePriority;
};

// ============================================
// IA E SEGURANÇA
// ============================================

export type AISafetyFeature = {
  id: string;
  title: string;
  description: string;
  icon: string;
  benefit: string;
};

export type AISafetyContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  features: AISafetyFeature[];
  demo: {
    type: 'visual' | 'interactive';
    src: string;
    alt: string;
  };
  trustNote: string;
};

// ============================================
// RECONHECIMENTO FACIAL
// ============================================

export type FaceRecognitionContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  emotionalHook: string;
  flowSteps: Array<{
    number: number;
    title: string;
    visual: string;
  }>;
  benefits: string[];
  demo: {
    selfie: string;
    matches: Array<{
      src: string;
      confidence: number;
    }>;
  };
};

// ============================================
// AUDIÊNCIA (PERSONAS)
// ============================================

export type AudienceProfile = {
  id: PersonaId;
  name: string;
  icon: string;
  promise: string;
  priorityModules: string[];
  objections: Array<{
    question: string;
    answer: string;
  }>;
  cta: {
    text: string;
    url: string;
  };
};

export type AudienceContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  profiles: AudienceProfile[];
};

// ============================================
// DEPOIMENTOS
// ============================================

export type TestimonialContext = 'casamento' | 'assessoria' | 'corporativo';

export type Testimonial = {
  id: string;
  context: TestimonialContext;
  quote: string;
  author: {
    name: string;
    role: string;
    photo?: string;
  };
  event: {
    type: string;
    volume: string;
    photo: string;
  };
  highlight: string;
  result: string;
};

export type TestimonialsContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  testimonials: Testimonial[];
  contextGroups: {
    casamento: Testimonial[];
    assessoria: Testimonial[];
    corporativo: Testimonial[];
  };
};

// ============================================
// PLANOS
// ============================================

export type PricingPlan = {
  id: string;
  name: string;
  audience: string;
  price: string;
  badge?: string;
  highlight: boolean;
  features: string[];
  cta: {
    text: string;
    url: string;
  };
};

export type PricingContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  plans: PricingPlan[];
  note: string;
};

// ============================================
// FAQ
// ============================================

export type FAQItem = {
  id: string;
  question: string;
  answer: string;
  category?: 'comercial' | 'tecnico' | 'operacional';
};

export type FAQContent = {
  eyebrow: string;
  title: string;
  subtitle: string;
  items: FAQItem[];
};

// ============================================
// CTA FINAL
// ============================================

export type FinalCTAContent = {
  title: string;
  subtitle: string;
  ctas: CTAConfig;
  trustSignals: TrustSignal[];
};

// ============================================
// VALIDAÇÃO COM ZOD
// ============================================

import { z } from 'zod';

export const PersonaIdSchema = z.enum(['assessora', 'social', 'corporativo']);

export const TrustSignalSchema = z.object({
  id: z.string(),
  icon: z.string(),
  text: z.string(),
  detail: z.string().optional(),
});

export const CTAConfigSchema = z.object({
  primary: z.object({
    text: z.string().min(1),
    url: z.string().url(),
    icon: z.string().optional(),
  }),
  secondary: z.object({
    text: z.string().min(1),
    url: z.string().url(),
    icon: z.string().optional(),
  }),
});

export const HeroContentSchema = z.object({
  eyebrow: z.string().min(1),
  headline: z.object({
    lead: z.string().min(1),
    accent: z.array(z.string()).min(1),
  }),
  subheadline: z.string().min(1),
  flowSteps: z.array(z.object({
    number: z.number().int().positive(),
    title: z.string().min(1),
    detail: z.string().min(1),
  })).min(3).max(4),
  ctas: CTAConfigSchema,
  trustSignals: z.array(TrustSignalSchema).min(3).max(5),
  metrics: z.array(z.object({
    value: z.string().min(1),
    label: z.string().min(1),
  })).min(2).max(4),
});

// Validação em runtime
export function validateLandingData<T>(schema: z.ZodSchema<T>, data: unknown): T {
  try {
    return schema.parse(data);
  } catch (error) {
    if (error instanceof z.ZodError) {
      console.error('Landing data validation failed:', error.errors);
      throw new Error(`Invalid landing data: ${error.errors.map(e => e.message).join(', ')}`);
    }
    throw error;
  }
}
```

**Estratégia de Fallbacks:**

```typescript
// Fallback para depoimentos
export const FALLBACK_TESTIMONIALS: Testimonial[] = [
  {
    id: 'fallback-1',
    context: 'casamento',
    quote: 'A plataforma transformou a experiência do nosso casamento. Os convidados adoraram ver as fotos ao vivo.',
    author: {
      name: 'Cliente Satisfeito',
      role: 'Casamento Premium',
    },
    event: {
      type: 'Casamento',
      volume: '1.500+ fotos',
      photo: '/images/fallback-wedding.jpg',
    },
    highlight: 'Galeria ao vivo',
    result: 'Experiência memorável para todos os convidados',
  },
  // ... mínimo 3 fallbacks
];

// Fallback para imagens
export const FALLBACK_IMAGES = {
  hero: '/images/fallback-hero.jpg',
  gallery: '/images/fallback-gallery.jpg',
  wall: '/images/fallback-wall.jpg',
  // ...
};

// Hook para usar dados com fallback
export function useLandingData<T>(
  data: T | undefined,
  fallback: T
): T {
  return data ?? fallback;
}
```

### 1.3 Gerenciamento de Estado

**Estado Global (Context API):**

```typescript
// src/contexts/LandingContext.tsx
import { createContext, useContext, useState, useEffect, ReactNode } from 'react';

type PersonaId = 'assessora' | 'social' | 'corporativo' | null;

type LandingState = {
  // Persona selecionada
  selectedPersona: PersonaId;
  setSelectedPersona: (persona: PersonaId) => void;
  
  // Variação de entrada (URL params)
  entryVariation: PersonaId;
  
  // Seção ativa (scroll tracking)
  activeSection: string;
  setActiveSection: (section: string) => void;
  
  // CTA flutuante
  showFloatingCTA: boolean;
  setShowFloatingCTA: (show: boolean) => void;
  
  // Parâmetros UTM
  utmParams: Record<string, string>;
};

const LandingContext = createContext<LandingState | undefined>(undefined);

export function LandingProvider({ children }: { children: ReactNode }) {
  // Detectar variação de entrada via URL
  const [entryVariation, setEntryVariation] = useState<PersonaId>(() => {
    const params = new URLSearchParams(window.location.search);
    const persona = params.get('persona');
    if (persona === 'assessora' || persona === 'social' || persona === 'corporativo') {
      return persona;
    }
    return null;
  });
  
  // Persona selecionada (pode ser diferente da entrada)
  const [selectedPersona, setSelectedPersona] = useState<PersonaId>(entryVariation);
  
  // Persistir escolha de persona
  useEffect(() => {
    if (selectedPersona) {
      localStorage.setItem('ev_selected_persona', selectedPersona);
    }
  }, [selectedPersona]);
  
  // Restaurar persona salva
  useEffect(() => {
    const saved = localStorage.getItem('ev_selected_persona');
    if (saved && !selectedPersona) {
      setSelectedPersona(saved as PersonaId);
    }
  }, []);
  
  // Seção ativa
  const [activeSection, setActiveSection] = useState('hero');
  
  // CTA flutuante (aparece após 80% scroll)
  const [showFloatingCTA, setShowFloatingCTA] = useState(false);
  
  useEffect(() => {
    const handleScroll = () => {
      const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
      setShowFloatingCTA(scrollPercent > 80);
    };
    
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);
  
  // Capturar parâmetros UTM
  const [utmParams] = useState(() => {
    const params = new URLSearchParams(window.location.search);
    return {
      utm_source: params.get('utm_source') || '',
      utm_medium: params.get('utm_medium') || '',
      utm_campaign: params.get('utm_campaign') || '',
      utm_content: params.get('utm_content') || '',
      utm_term: params.get('utm_term') || '',
    };
  });
  
  const value: LandingState = {
    selectedPersona,
    setSelectedPersona,
    entryVariation,
    activeSection,
    setActiveSection,
    showFloatingCTA,
    setShowFloatingCTA,
    utmParams,
  };
  
  return (
    <LandingContext.Provider value={value}>
      {children}
    </LandingContext.Provider>
  );
}

export function useLanding() {
  const context = useContext(LandingContext);
  if (!context) {
    throw new Error('useLanding must be used within LandingProvider');
  }
  return context;
}
```

**Hooks Customizados:**

```typescript
// src/hooks/usePersonaContent.ts
// Retorna conteúdo adaptado para persona selecionada
export function usePersonaContent<T>(
  content: Record<PersonaId, T>,
  fallback: T
): T {
  const { selectedPersona, entryVariation } = useLanding();
  const persona = selectedPersona || entryVariation;
  
  if (!persona) return fallback;
  return content[persona] || fallback;
}

// src/hooks/useScrollProgress.ts
// Tracking de progresso de scroll
export function useScrollProgress() {
  const [progress, setProgress] = useState(0);
  
  useEffect(() => {
    const handleScroll = () => {
      const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
      const scrolled = window.scrollY;
      setProgress((scrolled / scrollHeight) * 100);
    };
    
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);
  
  return progress;
}

// src/hooks/useIntersectionObserver.ts
// Detectar quando seção entra no viewport
export function useIntersectionObserver(
  ref: RefObject<Element>,
  options?: IntersectionObserverInit
) {
  const [isIntersecting, setIsIntersecting] = useState(false);
  
  useEffect(() => {
    const element = ref.current;
    if (!element) return;
    
    const observer = new IntersectionObserver(
      ([entry]) => setIsIntersecting(entry.isIntersecting),
      { threshold: 0.1, ...options }
    );
    
    observer.observe(element);
    return () => observer.disconnect();
  }, [ref, options]);
  
  return isIntersecting;
}
```

### 1.4 Roteamento e Variações

**Estratégia de URL para Variações:**

```
# Landing base (sem variação)
https://eventovivo.com/

# Variação por persona (via query param)
https://eventovivo.com/?persona=assessora
https://eventovivo.com/?persona=social
https://eventovivo.com/?persona=corporativo

# Com parâmetros UTM
https://eventovivo.com/?persona=assessora&utm_source=google&utm_medium=cpc&utm_campaign=casamentos-q1

# Âncoras de navegação
https://eventovivo.com/#como-funciona
https://eventovivo.com/?persona=corporativo#planos
```

**Implementação:**

```typescript
// src/utils/routing.ts

export type PersonaId = 'assessora' | 'social' | 'corporativo';

export function getPersonaFromURL(): PersonaId | null {
  const params = new URLSearchParams(window.location.search);
  const persona = params.get('persona');
  
  if (persona === 'assessora' || persona === 'social' || persona === 'corporativo') {
    return persona;
  }
  
  return null;
}

export function setPersonaInURL(persona: PersonaId) {
  const url = new URL(window.location.href);
  url.searchParams.set('persona', persona);
  window.history.replaceState({}, '', url.toString());
}

export function getUTMParams(): Record<string, string> {
  const params = new URLSearchParams(window.location.search);
  return {
    utm_source: params.get('utm_source') || '',
    utm_medium: params.get('utm_medium') || '',
    utm_campaign: params.get('utm_campaign') || '',
    utm_content: params.get('utm_content') || '',
    utm_term: params.get('utm_term') || '',
  };
}

export function buildCTAUrl(baseUrl: string, utmParams: Record<string, string>): string {
  const url = new URL(baseUrl);
  
  Object.entries(utmParams).forEach(([key, value]) => {
    if (value) {
      url.searchParams.set(key, value);
    }
  });
  
  return url.toString();
}
```

**Fallback para Landing Base:**

Se nenhuma persona for especificada, a landing exibe conteúdo genérico equilibrado que serve todos os perfis. O seletor de persona fica visível para permitir personalização manual.

