# Guia de Uso: Fallbacks Seguros para Depoimentos

## Visão Geral

Este guia explica como usar os fallbacks seguros implementados para depoimentos na landing page, conforme Task 14.3 e Requisito 29.

## Arquitetura

### 1. Dados de Fallback (`src/data/landing.ts`)

```typescript
// Fallback images para fotos de eventos ausentes
export const FALLBACK_EVENT_IMAGES = {
  casamento: "https://images.unsplash.com/...",
  assessoria: "https://images.unsplash.com/...",
  corporativo: "https://images.unsplash.com/...",
  generic: "https://images.unsplash.com/...",
};

// Fallback testimonials (mínimo 3 depoimentos)
export const FALLBACK_TESTIMONIALS: Testimonial[];

// Fallback content completo
export const FALLBACK_TESTIMONIALS_CONTENT: TestimonialsContent;
```

### 2. Hook `useLandingData` (`src/hooks/useLandingData.ts`)

```typescript
/**
 * Hook para usar dados com fallback seguro
 * Retorna dados principais se disponíveis, senão retorna fallback
 */
export function useLandingData<T>(
  data: T | undefined,
  fallback: T
): T {
  return data ?? fallback;
}
```

### 3. Componente `TestimonialsSection`

```typescript
import { useLandingData } from "@/hooks/useLandingData";
import { 
  testimonialsContent, 
  FALLBACK_TESTIMONIALS_CONTENT 
} from "@/data/landing";

export default function TestimonialsSection() {
  // Usa dados reais ou fallback seguro
  const testimonials = useLandingData(
    testimonialsContent, 
    FALLBACK_TESTIMONIALS_CONTENT
  );
  
  // ... resto do componente
}
```

## Comportamento por Ambiente

### Desenvolvimento (`NODE_ENV=development`)

**Características:**
- Depoimentos realistas para testar layout
- Conteúdo com cara de real
- Fotos de eventos de alta qualidade
- Útil para desenvolvimento e testes visuais

**Exemplo de depoimento:**
```typescript
{
  id: 'fallback-dev-1',
  context: 'casamento',
  quote: 'A plataforma transformou a experiência do nosso casamento...',
  author: {
    name: 'Cliente Satisfeito',
    role: 'Casamento Premium',
  },
  // ... resto dos dados
}
```

### Produção (`NODE_ENV=production`)

**Características:**
- Depoimentos claramente marcados como exemplos
- Todos os textos prefixados com `[Exemplo ilustrativo]` ou `[Exemplo]`
- Badge visual "Exemplo ilustrativo" em amarelo
- Estilo visual diferenciado (opacidade reduzida, borda tracejada)
- Evita confusão com depoimentos reais

**Exemplo de depoimento:**
```typescript
{
  id: 'fallback-prod-1',
  context: 'casamento',
  quote: '[Exemplo ilustrativo] Depoimento de cliente sobre experiência...',
  author: {
    name: '[Exemplo]',
    role: 'Cliente de Casamento',
  },
  // ... resto dos dados
}
```

**Estilo visual em produção:**
```scss
.card[data-fallback="true"] {
  opacity: 0.7;
  border: 1px dashed rgba(255, 255, 255, 0.2);
  
  .visualOverlay::before {
    content: 'Exemplo ilustrativo';
    background: rgba(255, 193, 7, 0.9);
    // Badge amarelo no topo da foto
  }
}
```

## Como Usar em Outros Componentes

### Exemplo 1: Usar fallback em novo componente

```typescript
import { useLandingData } from "@/hooks/useLandingData";
import { 
  myData, 
  FALLBACK_MY_DATA 
} from "@/data/landing";

export default function MyComponent() {
  // Usa dados reais ou fallback
  const data = useLandingData(myData, FALLBACK_MY_DATA);
  
  return (
    <div>
      {data.items.map(item => (
        <div key={item.id}>{item.content}</div>
      ))}
    </div>
  );
}
```

### Exemplo 2: Fallback para imagens

```typescript
<img 
  src={event.photo}
  alt={`Foto do evento: ${event.type}`}
  onError={(e) => {
    // Fallback visual para imagens que falharem
    e.currentTarget.src = FALLBACK_EVENT_IMAGES[event.context] 
      || FALLBACK_EVENT_IMAGES.generic;
  }}
/>
```

### Exemplo 3: Detectar se é fallback

```typescript
const isFallbackExample = testimonial.author.name.includes('[Exemplo]');

return (
  <article data-fallback={isFallbackExample ? 'true' : undefined}>
    {/* Conteúdo do card */}
  </article>
);
```

## Adicionar Depoimentos Reais

### Passo 1: Adicionar dados em `landing.ts`

```typescript
export const testimonialsContent: TestimonialsContent = {
  eyebrow: "Prova social",
  title: "Quem usa percebe que não é um telão bonito...",
  subtitle: "Casos reais organizados por tipo de evento",
  testimonials: [
    {
      id: "real-testimonial-1",
      context: "casamento",
      quote: "Depoimento real do cliente...",
      author: {
        name: "Nome Real",
        role: "Cargo Real",
        photo: "/path/to/photo.jpg", // Opcional
      },
      event: {
        type: "Casamento Premium",
        volume: "2.100 fotos",
        photo: "/path/to/event-photo.jpg",
      },
      highlight: "Funcionalidade que surpreendeu",
      result: "Resultado percebido pelo cliente",
    },
    // ... mais depoimentos reais
  ],
  contextGroups: {
    casamento: [],
    assessoria: [],
    corporativo: [],
  },
};

// Organizar por contexto
testimonialsContent.contextGroups.casamento = 
  testimonialsContent.testimonials.filter(t => t.context === 'casamento');
testimonialsContent.contextGroups.assessoria = 
  testimonialsContent.testimonials.filter(t => t.context === 'assessoria');
testimonialsContent.contextGroups.corporativo = 
  testimonialsContent.testimonials.filter(t => t.context === 'corporativo');
```

### Passo 2: Verificar em desenvolvimento

```bash
cd apps/landing
npm run dev
```

Acesse `http://localhost:5173/#depoimentos` e verifique se os depoimentos reais aparecem corretamente.

### Passo 3: Testar em produção

```bash
cd apps/landing
npm run build
npm run preview
```

Verifique se os depoimentos reais aparecem sem marcação de exemplo.

## Checklist de Segurança

Antes de publicar em produção, verifique:

- [ ] Mínimo 3 depoimentos reais aprovados por contexto
- [ ] Fotos de eventos reais disponíveis
- [ ] Depoimentos reais não contêm `[Exemplo]` no texto
- [ ] Fallbacks em produção claramente marcados como exemplos
- [ ] Badge visual "Exemplo ilustrativo" aparece em fallbacks
- [ ] Testes passando (`npm test`)
- [ ] Build de produção sem erros (`npm run build`)

## Troubleshooting

### Problema: Depoimentos não aparecem

**Solução:**
1. Verifique se `testimonialsContent` está definido em `landing.ts`
2. Verifique se `contextGroups` está populado
3. Verifique console do navegador para erros

### Problema: Fallbacks aparecem em produção

**Solução:**
1. Adicione depoimentos reais em `testimonialsContent`
2. Verifique se `testimonialsContent` não é `undefined`
3. Se intencional, verifique se badge "Exemplo ilustrativo" aparece

### Problema: Imagens não carregam

**Solução:**
1. Verifique URLs das imagens
2. Verifique se `onError` handler está implementado
3. Use `FALLBACK_EVENT_IMAGES` como fallback

### Problema: Testes falhando

**Solução:**
```bash
cd apps/landing
npm test -- --run
```

Verifique mensagens de erro e corrija conforme necessário.

## Referências

- **Requisito 29:** Estratégia de Conteúdo e Fallbacks
- **Task 14.3:** Criar fallbacks SEGUROS para depoimentos
- **Design Document:** `.kiro/specs/landing-page-conversion-refactor/design.md`
- **Fallback Strategy:** `apps/landing/FALLBACK_STRATEGY.md`

## Contato

Para dúvidas sobre implementação de fallbacks, consulte:
- Documentação da spec: `.kiro/specs/landing-page-conversion-refactor/`
- Testes: `apps/landing/src/components/TestimonialsSection.fallback.test.tsx`
