# Decisões Arquiteturais - Landing Page Evento Vivo

**Data:** 2026-04-08  
**Status:** Aprovado  
**Versão:** 1.0

## Sumário Executivo

Este documento registra as decisões estruturais que afetam toda a implementação da refatoração da landing page do Evento Vivo. Estas decisões foram tomadas na Fase 0 e devem ser respeitadas durante toda a implementação.

---

## 0.1 Estratégia de Renderização

### Decisão: SPA Pura com Otimizações SEO

**Escolha:** Manter arquitetura SPA (Single Page Application) pura com React + Vite, sem SSR/prerender.

### Justificativa

**Prós da SPA pura:**
- ✅ Simplicidade de deploy (arquivos estáticos)
- ✅ Stack já estabelecida e funcionando
- ✅ Vite otimizado para SPA com code splitting automático
- ✅ Sem complexidade de servidor Node.js em produção
- ✅ CDN-friendly (cache agressivo de assets)
- ✅ Custo de infraestrutura mínimo

**Contras mitigados:**
- ⚠️ SEO: Mitigado com meta tags estáticas no `index.html` + structured data JSON-LD
- ⚠️ Performance inicial: Mitigado com lazy loading seletivo + preload crítico
- ⚠️ Social sharing: Mitigado com Open Graph tags estáticas adequadas

**Por que não SSR/Prerender:**
- Adiciona complexidade significativa (servidor Node.js, hydration, edge cases)
- Google indexa SPAs modernas corretamente desde 2019
- Landing page tem conteúdo relativamente estático (não muda por usuário)
- Meta tags estáticas são suficientes para preview social
- Vite SSR requer configuração adicional e manutenção

### Implementação

```typescript
// index.html - Meta tags estáticas otimizadas
<head>
  <title>Evento Vivo | Transforme fotos em experiências ao vivo</title>
  <meta name="description" content="...">
  
  <!-- Open Graph -->
  <meta property="og:title" content="...">
  <meta property="og:description" content="...">
  <meta property="og:image" content="https://eventovivo.com/og-image.jpg">
  <meta property="og:url" content="https://eventovivo.com">
  
  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  
  <!-- Structured Data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Evento Vivo",
    "url": "https://eventovivo.com"
  }
  </script>
  
  <!-- Preload crítico -->
  <link rel="preload" href="/hero-image.webp" as="image">
  <link rel="preconnect" href="https://fonts.googleapis.com">
</head>
```

### Métricas de Sucesso

- ✅ Google Search Console: páginas indexadas corretamente
- ✅ Facebook Debugger: preview social renderizando
- ✅ Lighthouse SEO score ≥90
- ✅ Time to Interactive ≤3.5s em 3G Fast

---

## 0.2 Política de Carregamento de Conteúdo

### Decisão: Lazy Loading Seletivo (Não Agressivo)

**Escolha:** Renderizar HTML principal de todas as seções. Lazy load APENAS componentes pesados e interativos.

### Conflito Resolvido

**Requirement 30:** "Conteúdo principal com JS desabilitado"  
**Task 16.1 original:** "Lazy load todas seções abaixo da dobra"

**Resolução:** Priorizar SEO e acessibilidade. Google Search Central alerta que lazy loading mal implementado esconde conteúdo para crawlers.

### Estratégia de Carregamento

#### ✅ Carregamento Imediato (Eager)

```typescript
// Componentes carregados no bundle principal
- Navbar
- HeroSection (estrutura HTML + copy)
- TrustSignals
- HowItWorksSection (HTML estrutural)
- CaptureChannelsSection (HTML estrutural)
- ExperienceModulesSection (HTML estrutural)
- AISafetySection (HTML estrutural)
- FaceRecognitionSection (HTML estrutural)
- AudienceSection
- TestimonialsSection
- PricingSection
- FAQSection
- FinalCTASection
- Footer
```

#### 🔄 Lazy Loading (Componentes Pesados)

```typescript
// Lazy load APENAS demos interativas pesadas
const PhaserGameDemo = lazy(() => import('./games/PhaserGameDemo'));
const RiveAnimation = lazy(() => import('./rive/RiveAnimation'));

// Lazy load vídeos (com poster estático)
<video poster="/video-poster.jpg" loading="lazy">
  <source src="/demo.mp4" type="video/mp4">
</video>

// Lazy load carrosséis pesados (se houver)
const HeavyCarousel = lazy(() => import('./HeavyCarousel'));
```

### Implementação

```typescript
// App.tsx
import { Suspense, lazy } from 'react';

// Eager imports (bundle principal)
import Navbar from './components/Navbar';
import HeroSection from './sections/HeroSection';
import HowItWorksSection from './sections/HowItWorksSection';
// ... todas as seções principais

// Lazy imports (demos pesadas)
const PhaserGameDemo = lazy(() => import('./demos/PhaserGameDemo'));
const RiveAnimation = lazy(() => import('./demos/RiveAnimation'));

function App() {
  return (
    <>
      <Navbar />
      <main>
        <HeroSection />
        <HowItWorksSection />
        <CaptureChannelsSection />
        
        <ExperienceModulesSection>
          {/* Demo pesada lazy loaded */}
          <Suspense fallback={<DemoSkeleton />}>
            <PhaserGameDemo />
          </Suspense>
        </ExperienceModulesSection>
        
        {/* Demais seções carregadas normalmente */}
        <AISafetySection />
        <FaceRecognitionSection />
        {/* ... */}
      </main>
    </>
  );
}
```

### Justificativa

- ✅ Google pode indexar todo conteúdo textual
- ✅ Usuários com JS desabilitado veem conteúdo principal
- ✅ Performance mantida (demos pesadas lazy loaded)
- ✅ Acessibilidade preservada (HTML estrutural presente)
- ✅ SEO não comprometido

### Métricas de Sucesso

- ✅ Google Search Console: todas seções indexadas
- ✅ Lighthouse: "Avoid lazy loading above the fold" não aparece
- ✅ Bundle principal ≤200KB gzipped
- ✅ Demos pesadas em chunks separados (Phaser ~500KB, Rive ~100KB)

---

## 0.3 Estratégia de SEO Técnico

### Decisão: Canonical Único + Meta Tags Dinâmicas via JS

**Escolha:** Canonical sempre aponta para URL base. Meta tags adaptadas por persona via JavaScript.

### Canonical Strategy

```html
<!-- index.html - Canonical fixo -->
<link rel="canonical" href="https://eventovivo.com/">
```

**Justificativa:**
- Variações por persona (?persona=assessora) são a MESMA página com conteúdo adaptado
- Não são páginas distintas que competem por ranking
- Canonical único evita duplicate content issues
- Google trata query params como variações da mesma URL por padrão

### Meta Tags por Variação

```typescript
// src/utils/seo.ts
export function updateMetaTags(persona: PersonaId | null) {
  const metaConfig = {
    assessora: {
      title: 'Evento Vivo | Plataforma para Assessoras e Cerimonialistas',
      description: 'Controle total sobre fotos do evento com moderação IA, galeria ao vivo e busca facial configurável.',
    },
    social: {
      title: 'Evento Vivo | Transforme Fotos do Seu Casamento em Experiência',
      description: 'Receba fotos dos convidados por WhatsApp e crie galeria ao vivo, jogos e memórias encontráveis.',
    },
    corporativo: {
      title: 'Evento Vivo | Engajamento Visual para Eventos Corporativos',
      description: 'Telão dinâmico, jogos interativos e galeria ao vivo para ativações e eventos de marca.',
    },
  };
  
  const config = persona ? metaConfig[persona] : {
    title: 'Evento Vivo | Transforme Fotos em Experiências ao Vivo',
    description: 'Plataforma premium de experiências visuais para eventos.',
  };
  
  // Atualizar title
  document.title = config.title;
  
  // Atualizar meta description
  const metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc) metaDesc.setAttribute('content', config.description);
  
  // Atualizar Open Graph
  const ogTitle = document.querySelector('meta[property="og:title"]');
  if (ogTitle) ogTitle.setAttribute('content', config.title);
  
  const ogDesc = document.querySelector('meta[property="og:description"]');
  if (ogDesc) ogDesc.setAttribute('content', config.description);
}
```

### Structured Data (JSON-LD)

```html
<!-- index.html - Structured data estático -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Evento Vivo",
  "url": "https://eventovivo.com",
  "logo": "https://eventovivo.com/logo.png",
  "description": "Plataforma premium de experiências visuais ao vivo para eventos",
  "sameAs": [
    "https://instagram.com/eventovivo",
    "https://linkedin.com/company/eventovivo"
  ]
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Evento Vivo - Landing Page",
  "description": "Transforme fotos em experiências ao vivo",
  "url": "https://eventovivo.com"
}
</script>
```

### Preview Social

**Imagem:** 1200x630px (formato Open Graph)  
**Localização:** `/public/og-image.jpg`  
**Conteúdo:** Logo + tagline + visual de produto

```html
<!-- index.html -->
<meta property="og:image" content="https://eventovivo.com/og-image.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:image" content="https://eventovivo.com/og-image.jpg">
```

### Favicon

```html
<!-- index.html -->
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
```

### Métricas de Sucesso

- ✅ Google Rich Results Test: sem erros
- ✅ Facebook Debugger: preview renderizando corretamente
- ✅ Twitter Card Validator: preview renderizando
- ✅ LinkedIn Post Inspector: preview renderizando
- ✅ Lighthouse SEO: score ≥90

---

## 0.4 Plano de Medição e Analytics

### Decisão: Google Analytics 4 + RUM Customizado

**Escolha:** GA4 para analytics + Web Vitals API para RUM (Real User Monitoring).

### Analytics (GA4)

#### Eventos de Conversão

```typescript
// src/utils/analytics.ts
export const trackEvent = (eventName: string, params?: Record<string, any>) => {
  if (typeof window.gtag !== 'undefined') {
    window.gtag('event', eventName, params);
  }
};

// Eventos principais
export const EVENTS = {
  // Conversão macro
  CTA_DEMO_CLICKED: 'cta_demo_clicked',
  CTA_WHATSAPP_CLICKED: 'cta_whatsapp_clicked',
  
  // Microconversões
  MICROCONVERSION_EXAMPLE: 'microconversion_example_viewed',
  MICROCONVERSION_VIDEO: 'microconversion_video_played',
  
  // Interações
  PERSONA_SELECTED: 'persona_selected',
  MODULE_TAB_CLICKED: 'module_tab_clicked',
  FAQ_OPENED: 'faq_opened',
  PRICING_PLAN_VIEWED: 'pricing_plan_viewed',
  
  // Scroll
  SCROLL_DEPTH: 'scroll_depth',
  SECTION_VIEWED: 'section_viewed',
};
```

#### Parâmetros UTM

```typescript
// src/utils/routing.ts
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

// Propagar UTM em todos CTAs
export function buildCTAUrl(baseUrl: string, utmParams: Record<string, string>): string {
  const url = new URL(baseUrl);
  Object.entries(utmParams).forEach(([key, value]) => {
    if (value) url.searchParams.set(key, value);
  });
  return url.toString();
}
```

### RUM (Real User Monitoring)

```typescript
// src/utils/rum.ts
import { onCLS, onFID, onLCP, onINP, onFCP, onTTFB } from 'web-vitals';

export function initRUM() {
  // Core Web Vitals
  onLCP((metric) => {
    trackEvent('web_vitals', {
      metric_name: 'LCP',
      value: Math.round(metric.value),
      rating: metric.rating,
    });
  });
  
  onINP((metric) => {
    trackEvent('web_vitals', {
      metric_name: 'INP',
      value: Math.round(metric.value),
      rating: metric.rating,
    });
  });
  
  onCLS((metric) => {
    trackEvent('web_vitals', {
      metric_name: 'CLS',
      value: metric.value.toFixed(3),
      rating: metric.rating,
    });
  });
  
  // Métricas adicionais
  onFCP((metric) => {
    trackEvent('web_vitals', {
      metric_name: 'FCP',
      value: Math.round(metric.value),
    });
  });
  
  onTTFB((metric) => {
    trackEvent('web_vitals', {
      metric_name: 'TTFB',
      value: Math.round(metric.value),
    });
  });
}
```

### Infraestrutura de A/B Testing

```typescript
// src/utils/experiments.ts
export type ExperimentId = 'hero_headline' | 'cta_text' | 'trust_signals_position';

export type ExperimentVariant = 'control' | 'variant_a' | 'variant_b';

export function getExperimentVariant(experimentId: ExperimentId): ExperimentVariant {
  // Verificar localStorage para consistência
  const stored = localStorage.getItem(`exp_${experimentId}`);
  if (stored) return stored as ExperimentVariant;
  
  // Atribuir variante aleatória
  const variants: ExperimentVariant[] = ['control', 'variant_a', 'variant_b'];
  const variant = variants[Math.floor(Math.random() * variants.length)];
  
  localStorage.setItem(`exp_${experimentId}`, variant);
  
  // Rastrear atribuição
  trackEvent('experiment_assigned', {
    experiment_id: experimentId,
    variant: variant,
  });
  
  return variant;
}

export function trackExperimentConversion(experimentId: ExperimentId, variant: ExperimentVariant) {
  trackEvent('experiment_conversion', {
    experiment_id: experimentId,
    variant: variant,
  });
}
```

### Métricas Rastreadas

#### Conversão Macro
- Taxa de clique em "Agendar demonstração"
- Taxa de clique em "Falar no WhatsApp"
- Taxa de conversão por persona
- Taxa de conversão por origem (UTM)

#### Microconversões
- Taxa de visualização de exemplo
- Taxa de reprodução de vídeo demo
- Taxa de interação com módulos
- Taxa de seleção de persona

#### Engajamento
- Scroll depth (25%, 50%, 75%, 100%)
- Tempo médio na página
- Taxa de rejeição (bounce rate)
- Seções mais visualizadas

#### Performance (RUM)
- LCP real (P75)
- INP real (P75)
- CLS real (P75)
- FCP real (P75)
- TTFB real (P75)

### Métricas de Sucesso

- ✅ GA4 configurado e rastreando eventos
- ✅ RUM enviando Web Vitals reais
- ✅ UTM params capturados e propagados
- ✅ Dashboard de experimentos funcional

---

## 0.5 Política de Privacidade e Transparência

### Decisão: Transparência Proativa + Conformidade ANPD

**Escolha:** Comunicar upfront que IA e busca facial são configuráveis. Adicionar microcopys de privacidade perto de CTAs.

### Atenção ANPD (Lei Geral de Proteção de Dados)

**Dados Biométricos:** Busca facial processa dados biométricos, que são **dados sensíveis** com proteção reforçada pela LGPD.

**Obrigações:**
- ✅ Consentimento específico e destacado
- ✅ Finalidade legítima e específica
- ✅ Transparência sobre processamento
- ✅ Direito de revogação facilitado

### Comunicação de Transparência

#### Trust Signals (Hero)

```typescript
const trustSignals = [
  {
    icon: 'shield-check',
    text: 'Sem app necessário',
    detail: 'Convidados enviam por WhatsApp, Telegram ou link',
  },
  {
    icon: 'brain',
    text: 'Moderação IA configurável',
    detail: 'Você escolhe: sem moderação, manual ou IA assistida',
  },
  {
    icon: 'scan-face',
    text: 'Busca facial opcional',
    detail: 'Ative apenas quando necessário. Dados processados com segurança.',
  },
  {
    icon: 'lock',
    text: 'Privacidade garantida',
    detail: 'Conformidade com LGPD. Dados protegidos.',
  },
];
```

#### Microcopys de Privacidade

```typescript
// Perto de CTAs principais
<p className="privacy-note">
  Ao agendar, você concorda com nossa{' '}
  <a href="/privacidade">Política de Privacidade</a> e{' '}
  <a href="/termos">Termos de Uso</a>.
</p>

// Seção de Busca Facial
<div className="privacy-callout">
  <Icon name="info" />
  <p>
    A busca facial é <strong>opcional e configurável</strong> por evento.
    Quando ativada, processamos dados biométricos com segurança e conformidade LGPD.
    Você pode desativar a qualquer momento.
  </p>
</div>

// Seção de IA
<div className="privacy-callout">
  <Icon name="settings" />
  <p>
    Você escolhe o nível de moderação: <strong>sem moderação</strong>,{' '}
    <strong>manual</strong> ou <strong>IA assistida</strong>.
    A IA é configurável e você mantém controle total.
  </p>
</div>
```

#### Footer (Links Obrigatórios)

```typescript
<footer>
  <nav>
    <a href="/privacidade">Política de Privacidade</a>
    <a href="/termos">Termos de Uso</a>
    <a href="/lgpd">Conformidade LGPD</a>
    <a href="/contato">Contato</a>
  </nav>
  
  <p className="legal-note">
    © 2026 Evento Vivo. Todos os direitos reservados.
    Processamento de dados em conformidade com LGPD (Lei 13.709/2018).
  </p>
</footer>
```

### Revisão Jurídica/Técnica

**Antes de publicar copy final:**
- [ ] Revisar com jurídico: termos de consentimento, linguagem de privacidade
- [ ] Revisar com técnico: garantias de segurança, processamento de dados
- [ ] Validar: microcopys não criam obrigações não cumpridas
- [ ] Confirmar: links de privacidade e termos estão funcionais

### Política de Transparência Documentada

```markdown
# Política de Transparência - Landing Page

## Princípios

1. **Clareza:** Linguagem simples e direta sobre IA e busca facial
2. **Controle:** Usuário sempre sabe que recursos são configuráveis
3. **Consentimento:** Consentimento específico para dados biométricos
4. **Revogação:** Direito de desativar busca facial a qualquer momento
5. **Segurança:** Comunicar medidas de proteção de dados

## Comunicação Obrigatória

- ✅ Busca facial é OPCIONAL e CONFIGURÁVEL
- ✅ Moderação IA tem NÍVEIS (sem, manual, IA)
- ✅ Dados biométricos processados com SEGURANÇA
- ✅ Conformidade com LGPD
- ✅ Links para Privacidade e Termos visíveis

## Proibido

- ❌ Sugerir que busca facial é obrigatória
- ❌ Omitir que IA é configurável
- ❌ Esconder links de privacidade
- ❌ Usar linguagem que cria obrigações não cumpridas
```

### Métricas de Sucesso

- ✅ Revisão jurídica aprovada
- ✅ Links de privacidade funcionais
- ✅ Microcopys de transparência presentes
- ✅ Nenhuma reclamação de falta de transparência

---

## Resumo das Decisões

| Decisão | Escolha | Impacto |
|---------|---------|---------|
| **Renderização** | SPA pura (sem SSR) | Simplicidade, deploy estático, SEO via meta tags |
| **Lazy Loading** | Seletivo (apenas demos pesadas) | SEO preservado, performance mantida |
| **Canonical** | URL base única | Evita duplicate content |
| **Meta Tags** | Dinâmicas via JS por persona | Personalização sem múltiplas páginas |
| **Analytics** | GA4 + RUM customizado | Conversão + performance real |
| **A/B Testing** | Infraestrutura via localStorage | Otimização contínua |
| **Privacidade** | Transparência proativa + LGPD | Confiança + conformidade legal |

---

## Próximos Passos

Com estas decisões aprovadas, a implementação pode prosseguir para:

1. ✅ **Fase 1:** Fundação Técnica (contextos, dados, schemas)
2. ✅ **Fase 2:** Hero e Navbar
3. ✅ **Fase 3:** Seções principais
4. ✅ **Fase 4:** Conversão e CTAs
5. ✅ **Fase 5:** Performance e não-funcionais
6. ✅ **Fase 6:** Rollout controlado

**Todas as decisões estruturais estão definidas e documentadas.**
