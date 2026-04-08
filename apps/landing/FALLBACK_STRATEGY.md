# Estratégia de Fallbacks para Depoimentos

## Visão Geral

Implementação de fallbacks SEGUROS para depoimentos conforme Requisito 29 e Task 14.3.

## Estratégia por Ambiente

### Desenvolvimento (`NODE_ENV=development`)
- **Depoimentos realistas** para testar layout e fluxo visual
- Conteúdo com cara de real para validar design
- Fotos de eventos de alta qualidade (Unsplash)
- Útil para desenvolvimento e testes visuais

### Produção (`NODE_ENV=production`)
- **Depoimentos claramente marcados como exemplos ilustrativos**
- Todos os textos prefixados com `[Exemplo ilustrativo]` ou `[Exemplo]`
- Badge visual "Exemplo ilustrativo" em amarelo sobre a foto
- Estilo visual diferenciado (opacidade reduzida, borda tracejada)
- Evita confusão com depoimentos reais

## Implementação

### 1. Dados de Fallback (`apps/landing/src/data/landing.ts`)

```typescript
// Fallback images para fotos ausentes
export const FALLBACK_EVENT_IMAGES = {
  casamento: "https://images.unsplash.com/...",
  assessoria: "https://images.unsplash.com/...",
  corporativo: "https://images.unsplash.com/...",
  generic: "https://images.unsplash.com/...",
};

// Fallback testimonials (mínimo 3)
export const FALLBACK_TESTIMONIALS: Testimonial[] = isDevelopment
  ? DEVELOPMENT_FALLBACK_TESTIMONIALS  // Realistas
  : PRODUCTION_FALLBACK_TESTIMONIALS;  // Marcados como exemplos

// Fallback content completo
export const FALLBACK_TESTIMONIALS_CONTENT: TestimonialsContent = {
  eyebrow: isDevelopment ? "Prova social" : "[Exemplos ilustrativos]",
  title: isDevelopment 
    ? "Casos de sucesso da plataforma" 
    : "[Exemplos] Depoimentos de clientes",
  subtitle: isDevelopment
    ? "Casos reais organizados por tipo de evento"
    : "Exemplos ilustrativos - aguardando depoimentos reais aprovados",
  testimonials: FALLBACK_TESTIMONIALS,
  contextGroups: { ... },
};
```

### 2. Hook `useLandingData`

```typescript
// Hook já existente em apps/landing/src/hooks/useLandingData.ts
export function useLandingData<T>(
  data: T | undefined,
  fallback: T
): T {
  return data ?? fallback;
}
```

### 3. Componente TestimonialsSection

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
  
  // Detecta se é fallback de exemplo
  const isFallbackExample = testimonial.author.name.includes('[Exemplo]');
  
  return (
    <article data-fallback={isFallbackExample ? 'true' : undefined}>
      {/* ... */}
    </article>
  );
}
```

### 4. Estilos Visuais (SCSS)

```scss
.card {
  // Estilo para depoimentos fallback em produção
  &[data-fallback="true"] {
    opacity: 0.7;
    border: 1px dashed rgba(255, 255, 255, 0.2);
    
    // Badge "Exemplo ilustrativo"
    .visualOverlay::before {
      content: 'Exemplo ilustrativo';
      background: rgba(255, 193, 7, 0.9);
      // ...
    }
  }
}
```

### 5. Fallback Visual para Imagens

```typescript
<img 
  src={testimonial.event.photo}
  onError={(e) => {
    // SVG placeholder se imagem falhar
    e.currentTarget.src = 'data:image/svg+xml,...';
  }}
/>
```

## Uso

### Cenário 1: Dados Reais Disponíveis
```typescript
// testimonialsContent tem 5 depoimentos reais
const testimonials = useLandingData(
  testimonialsContent,           // ✅ Usa dados reais
  FALLBACK_TESTIMONIALS_CONTENT  // Não usado
);
```

### Cenário 2: Dados Indisponíveis (Desenvolvimento)
```typescript
// testimonialsContent é undefined
const testimonials = useLandingData(
  undefined,                     // ❌ Dados ausentes
  FALLBACK_TESTIMONIALS_CONTENT  // ✅ Usa fallback realista
);
// Resultado: 3 depoimentos realistas para testar layout
```

### Cenário 3: Dados Indisponíveis (Produção)
```typescript
// testimonialsContent é undefined
const testimonials = useLandingData(
  undefined,                     // ❌ Dados ausentes
  FALLBACK_TESTIMONIALS_CONTENT  // ✅ Usa fallback marcado
);
// Resultado: 3 depoimentos com "[Exemplo ilustrativo]" e badge visual
```

## Checklist de Segurança

- ✅ Fallbacks realistas APENAS em desenvolvimento
- ✅ Fallbacks em produção claramente marcados como exemplos
- ✅ Badge visual "Exemplo ilustrativo" em produção
- ✅ Estilo diferenciado (opacidade, borda tracejada)
- ✅ Mínimo 3 depoimentos fallback
- ✅ Fallback visual para fotos ausentes
- ✅ Hook `useLandingData` implementado
- ✅ Detecção automática de ambiente (development/production)

## Próximos Passos

1. **Adicionar depoimentos reais aprovados** em `testimonialsContent`
2. **Remover fallbacks** quando houver mínimo 3 depoimentos reais por contexto
3. **Testar em produção** para garantir que exemplos estão claramente marcados
4. **Monitorar analytics** para ver se visitantes interagem com seção

## Observações Importantes

⚠️ **NUNCA usar depoimentos falsos com cara de real em produção**
- Risco legal e de reputação
- Pode ser considerado propaganda enganosa
- Sempre marcar claramente como exemplos ilustrativos

✅ **Estratégia recomendada para lançamento**
- Coletar 3-5 depoimentos reais antes de publicar
- Usar fallbacks apenas temporariamente
- Ocultar seção se não houver depoimentos reais suficientes
