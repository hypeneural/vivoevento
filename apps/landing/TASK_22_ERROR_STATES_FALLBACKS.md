# Task 22: Implementar Estados de Erro e Fallbacks

## Visão Geral

Implementação completa de degradação graciosa e fallbacks de conteúdo conforme Requirements 29 e 30.

**Status:** ✅ Completo

**Requirements:**
- Requirement 29: Estratégia de Conteúdo e Fallbacks
- Requirement 30: Estados de Erro e Degradação Graciosa

## Subtask 22.1: Implementar Degradação Graciosa ✅

### 1. Conteúdo Principal com JS Desabilitado

**Arquivo:** `apps/landing/src/components/NoScriptFallback.tsx`

Componente `<noscript>` que exibe conteúdo completo quando JavaScript está desabilitado:

- ✅ Hero com proposta de valor
- ✅ Como funciona (3 passos)
- ✅ Recursos principais (5 módulos)
- ✅ Para quem é (3 personas)
- ✅ FAQ (5 perguntas principais)
- ✅ CTAs funcionais (Agendar demonstração + WhatsApp)
- ✅ Footer com links de privacidade

**Características:**
- HTML semântico puro (sem dependência de JS)
- CTAs com links diretos (não dependem de JavaScript)
- Estilos inline e SCSS Module para garantir renderização
- Banner informativo sobre JavaScript desabilitado
- Conteúdo organizado e escaneável

**Integração:**
```tsx
// apps/landing/src/App.tsx
import { NoScriptFallback } from "@/components/NoScriptFallback";

export default function App() {
  return (
    <div>
      <NoScriptFallback />
      {/* Resto da aplicação */}
    </div>
  );
}
```

### 2. Fallback para Imagens que Falharem

**Arquivo:** `apps/landing/src/utils/imageFallback.tsx` (já existente)

Utilitários para fallback de imagens:

- ✅ `generateImagePlaceholder()` - Gera SVG placeholder
- ✅ `handleImageError()` - Handler para evento onError
- ✅ `ImageWithFallback` - Componente wrapper com fallback automático

**Uso:**
```tsx
import { ImageWithFallback } from "@/utils/imageFallback";

<ImageWithFallback 
  src={image.src}
  alt={image.alt}
  fallbackText="Imagem de evento indisponível"
/>
```

### 3. Fallback para Vídeos Indisponíveis

**Arquivo:** `apps/landing/src/utils/videoFallback.tsx` ✅ NOVO

Utilitários completos para fallback de vídeos:

- ✅ `generateVideoPlaceholder()` - Gera SVG placeholder com ícone de play
- ✅ `handleVideoError()` - Handler para evento onError
- ✅ `useAutoplayDetection()` - Hook para detectar autoplay bloqueado
- ✅ `VideoWithFallback` - Componente wrapper com fallback automático

**Características:**
- Detecta autoplay bloqueado pelo navegador
- Exibe botão de play manual quando autoplay falha
- Fallback para poster se vídeo não carregar
- Fallback para placeholder SVG se não houver poster
- Suporte a eventos customizados (onError)

**Uso:**
```tsx
import { VideoWithFallback } from "@/utils/videoFallback";

<VideoWithFallback 
  src={video.src}
  poster={video.poster}
  autoPlay
  loop
  muted
  playsInline
  fallbackText="Vídeo de demonstração indisponível"
  showPlayButton
/>
```

**Integração em ExperienceModulesSection:**
```tsx
// apps/landing/src/components/ExperienceModulesSection.tsx
import { VideoWithFallback } from "@/utils/videoFallback";
import { ImageWithFallback } from "@/utils/imageFallback";

{activeModule.visual.type === "video" ? (
  <VideoWithFallback
    src={activeModule.visual.src}
    poster={activeModule.visual.poster}
    autoPlay
    loop
    muted
    playsInline
    fallbackText="Vídeo de demonstração indisponível"
    showPlayButton
  />
) : (
  <ImageWithFallback
    src={activeModule.visual.src}
    alt={activeModule.visual.alt}
    fallbackText="Imagem de demonstração indisponível"
  />
)}
```

### 4. Manter CTAs Funcionais com Falha de Componentes Interativos

**Arquivo:** `apps/landing/src/components/ErrorBoundary.tsx` ✅ NOVO

Error Boundary que captura erros de componentes e mantém CTAs funcionais:

- ✅ Captura erros de renderização de componentes
- ✅ Exibe fallback gracioso com mensagem clara
- ✅ Mantém CTAs funcionais (Agendar demonstração + WhatsApp)
- ✅ Tracking de erros em analytics
- ✅ Detalhes de erro em desenvolvimento

**Características:**
- Fallback customizável por componente
- Opção de exibir CTAs no fallback (`showCTA`)
- Nome do componente no erro (`componentName`)
- Detalhes técnicos apenas em desenvolvimento
- Não quebra a experiência do usuário

**Uso:**
```tsx
import { ErrorBoundary } from "@/components/ErrorBoundary";

<ErrorBoundary componentName="Hero" showCTA>
  <HeroExperience />
</ErrorBoundary>

<ErrorBoundary componentName="Depoimentos" showCTA>
  <TestimonialsSection />
</ErrorBoundary>
```

**Integração em App.tsx:**
Todas as seções principais foram envolvidas com ErrorBoundary:
- Hero (com CTA)
- Todas as seções de conteúdo
- Depoimentos (com CTA)
- Planos (com CTA)
- CTA Final (com CTA)

## Subtask 22.2: Implementar Fallbacks de Conteúdo ✅

### 1. FALLBACK_IMAGES para Todas Imagens Críticas

**Arquivo:** `apps/landing/src/data/landing.ts` ✅ ATUALIZADO

Constante `FALLBACK_IMAGES` com fallbacks para todas imagens críticas:

```typescript
export const FALLBACK_IMAGES = {
  // Hero section
  hero: "https://images.unsplash.com/...",
  heroMobile: "https://images.unsplash.com/...",
  
  // Gallery module
  gallery: "https://images.unsplash.com/...",
  galleryFeatured: "https://images.unsplash.com/...",
  
  // Wall module
  wall: "https://images.unsplash.com/...",
  wallHero: "https://images.unsplash.com/...",
  
  // Face recognition module
  faceSelfie: "https://images.unsplash.com/...",
  faceMatch: "https://images.unsplash.com/...",
  
  // Games module
  games: "https://images.unsplash.com/...",
  
  // AI Safety module
  aiModeration: "https://images.unsplash.com/...",
  
  // Capture channels
  channels: "https://images.unsplash.com/...",
  
  // Generic fallback
  generic: "https://images.unsplash.com/...",
};
```

**Cobertura:**
- ✅ Hero (desktop e mobile)
- ✅ Gallery module
- ✅ Wall module
- ✅ Face recognition module
- ✅ Games module
- ✅ AI Safety module
- ✅ Capture channels
- ✅ Generic fallback

### 2. FALLBACK_TESTIMONIALS (Mínimo 3, Seguros para Produção)

**Arquivo:** `apps/landing/src/data/landing.ts` (já existente)

Fallbacks de depoimentos já implementados em Task 14.3:

- ✅ Mínimo 3 depoimentos fallback
- ✅ Versão de desenvolvimento (realistas para testes)
- ✅ Versão de produção (claramente marcados como exemplos)
- ✅ Detecção automática de ambiente
- ✅ Fallback por contexto (casamento, assessoria, corporativo)

**Estrutura:**
```typescript
export const FALLBACK_TESTIMONIALS: Testimonial[] = isDevelopment
  ? DEVELOPMENT_FALLBACK_TESTIMONIALS  // Realistas
  : PRODUCTION_FALLBACK_TESTIMONIALS;  // Marcados como [Exemplo]

export const FALLBACK_TESTIMONIALS_CONTENT: TestimonialsContent = {
  eyebrow: isDevelopment ? "Prova social" : "[Exemplos ilustrativos]",
  title: isDevelopment 
    ? "Casos de sucesso da plataforma" 
    : "[Exemplos] Depoimentos de clientes",
  testimonials: FALLBACK_TESTIMONIALS,
  contextGroups: { ... },
};
```

**Uso:**
```tsx
import { useLandingData } from "@/hooks/useLandingData";
import { 
  testimonialsContent, 
  FALLBACK_TESTIMONIALS_CONTENT 
} from "@/data/landing";

const testimonials = useLandingData(
  testimonialsContent,
  FALLBACK_TESTIMONIALS_CONTENT
);
```

### 3. Estados de Loading Apropriados

**Arquivo:** `apps/landing/src/App.tsx` (já existente)

Estados de loading já implementados:

- ✅ `SectionFallback` - Spinner para seções lazy loaded
- ✅ `MinimalFallback` - Fallback mínimo para conteúdo crítico
- ✅ `HeavyDemoFallback` - Fallback para demos pesadas (Phaser, Rive)

**Características:**
- Spinners com aria-label para acessibilidade
- Diferentes prioridades de fallback
- Mensagens contextuais
- Estilos consistentes com design system

## Arquitetura de Fallbacks

### Hierarquia de Fallbacks

```
1. Conteúdo Real (dados reais disponíveis)
   ↓
2. Fallback de Dados (FALLBACK_TESTIMONIALS, FALLBACK_IMAGES)
   ↓
3. Fallback de Componente (ErrorBoundary com CTA)
   ↓
4. Fallback de Mídia (ImageWithFallback, VideoWithFallback)
   ↓
5. Fallback de JavaScript (NoScriptFallback)
```

### Fluxo de Degradação

```
JavaScript Habilitado
├─ Componente Renderiza
│  ├─ Sucesso → Exibe conteúdo
│  └─ Erro → ErrorBoundary
│     ├─ Fallback customizado
│     └─ CTAs funcionais
│
├─ Imagem Carrega
│  ├─ Sucesso → Exibe imagem
│  └─ Erro → ImageWithFallback
│     └─ SVG placeholder
│
└─ Vídeo Carrega
   ├─ Sucesso → Exibe vídeo
   ├─ Autoplay bloqueado → Botão de play manual
   └─ Erro → VideoWithFallback
      ├─ Poster (se disponível)
      └─ SVG placeholder

JavaScript Desabilitado
└─ NoScriptFallback
   ├─ HTML semântico
   ├─ Conteúdo principal
   └─ CTAs funcionais
```

## Testes

### Testes Implementados

**videoFallback.test.tsx** ✅
- ✅ `generateVideoPlaceholder()` com dimensões padrão
- ✅ `generateVideoPlaceholder()` com dimensões customizadas
- ✅ `handleVideoError()` com poster
- ✅ `handleVideoError()` sem poster
- ✅ `VideoWithFallback` renderiza corretamente
- ✅ `VideoWithFallback` mostra poster em erro
- ✅ `VideoWithFallback` mostra texto em erro sem poster
- ✅ `VideoWithFallback` chama onError customizado

**imageFallback.test.tsx** (já existente)
- ✅ Testes de fallback de imagens

### Testes Manuais Necessários

1. **JavaScript Desabilitado:**
   - [ ] Desabilitar JS no navegador
   - [ ] Verificar que NoScriptFallback é exibido
   - [ ] Verificar que CTAs funcionam (links diretos)
   - [ ] Verificar que conteúdo é legível e organizado

2. **Autoplay Bloqueado:**
   - [ ] Configurar navegador para bloquear autoplay
   - [ ] Verificar que botão de play manual aparece
   - [ ] Verificar que vídeo reproduz ao clicar no botão

3. **Imagens Quebradas:**
   - [ ] Simular falha de carregamento de imagem
   - [ ] Verificar que placeholder SVG é exibido
   - [ ] Verificar que texto alternativo é apropriado

4. **Vídeos Quebrados:**
   - [ ] Simular falha de carregamento de vídeo
   - [ ] Verificar que poster é exibido (se disponível)
   - [ ] Verificar que placeholder SVG é exibido (se sem poster)

5. **Erro de Componente:**
   - [ ] Forçar erro em componente (throw new Error)
   - [ ] Verificar que ErrorBoundary captura erro
   - [ ] Verificar que CTAs permanecem funcionais
   - [ ] Verificar que erro é logado em analytics

## Checklist de Validação

### Subtask 22.1: Degradação Graciosa

- ✅ Conteúdo principal exibido com JS desabilitado
- ✅ Fallback para imagens que falharem (ImageWithFallback)
- ✅ Fallback para vídeos indisponíveis (VideoWithFallback)
- ✅ Detecção de autoplay bloqueado
- ✅ Botão de play manual quando autoplay falha
- ✅ CTAs funcionais com falha de componentes (ErrorBoundary)
- ✅ Tracking de erros em analytics

### Subtask 22.2: Fallbacks de Conteúdo

- ✅ FALLBACK_IMAGES para todas imagens críticas
- ✅ FALLBACK_TESTIMONIALS (mínimo 3, seguros para produção)
- ✅ Estados de loading apropriados (SectionFallback, etc.)
- ✅ Fallbacks diferenciados por ambiente (dev/prod)

### Integração

- ✅ NoScriptFallback integrado em App.tsx
- ✅ ErrorBoundary envolvendo todas seções
- ✅ VideoWithFallback em ExperienceModulesSection
- ✅ ImageWithFallback em ExperienceModulesSection
- ✅ FALLBACK_IMAGES exportado em landing.ts

### Testes

- ✅ Testes unitários para videoFallback
- ✅ Testes unitários para imageFallback (já existente)
- [ ] Testes manuais de JS desabilitado
- [ ] Testes manuais de autoplay bloqueado
- [ ] Testes manuais de imagens/vídeos quebrados
- [ ] Testes manuais de erro de componente

## Próximos Passos

1. **Executar testes manuais** conforme checklist acima
2. **Validar em diferentes navegadores:**
   - Chrome (autoplay policies)
   - Firefox (autoplay policies)
   - Safari (autoplay policies mais restritivas)
   - Edge
3. **Validar em diferentes dispositivos:**
   - Desktop
   - Mobile (iOS Safari, Chrome Android)
   - Tablet
4. **Monitorar erros em produção:**
   - Configurar alertas para ErrorBoundary
   - Monitorar taxa de fallback de imagens/vídeos
   - Monitorar taxa de autoplay bloqueado

## Observações Importantes

⚠️ **Autoplay Policies:**
- Safari e Chrome mobile bloqueiam autoplay por padrão
- Vídeos com `muted` têm mais chance de autoplay
- Sempre fornecer botão de play manual como fallback

⚠️ **NoScript:**
- Conteúdo em `<noscript>` não é indexado por alguns crawlers
- Manter HTML semântico e estruturado
- CTAs devem ser links diretos (não dependem de JS)

⚠️ **ErrorBoundary:**
- Não captura erros em event handlers
- Não captura erros em código assíncrono
- Não captura erros no próprio ErrorBoundary

✅ **Boas Práticas:**
- Sempre fornecer `alt` text para imagens
- Sempre fornecer `poster` para vídeos
- Sempre fornecer fallback text customizado
- Sempre manter CTAs funcionais em fallbacks

## Referências

- [Requirement 29: Estratégia de Conteúdo e Fallbacks](../.kiro/specs/landing-page-conversion-refactor/requirements.md#requisito-29)
- [Requirement 30: Estados de Erro e Degradação Graciosa](../.kiro/specs/landing-page-conversion-refactor/requirements.md#requisito-30)
- [Decision 0.2: Política de Carregamento de Conteúdo](../.kiro/specs/landing-page-conversion-refactor/tasks.md#fase-0--decisões-arquiteturais)
- [Task 14.3: Fallbacks Seguros para Depoimentos](./TASK_14.3_IMPLEMENTATION_SUMMARY.md)
- [React Error Boundaries](https://react.dev/reference/react/Component#catching-rendering-errors-with-an-error-boundary)
- [MDN: noscript element](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/noscript)
- [Autoplay Policy](https://developer.chrome.com/blog/autoplay/)
