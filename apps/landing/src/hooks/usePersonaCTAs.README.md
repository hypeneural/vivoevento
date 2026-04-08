# usePersonaCTAs Hook

Hook para CTAs específicos por persona com propagação automática de parâmetros UTM.

## Requisitos Atendidos

- **Requisito 37**: Modelo de conversão adaptado por persona
- **Requisito 26**: Analytics e conversão com rastreamento UTM

## Comportamento por Persona

### Assessora / Cerimonialista
- **CTA Primário**: "Agendar demonstração" → Formulário qualificado
- **CTA Secundário**: "Falar no WhatsApp" → Mensagem pré-preenchida
- **Foco**: Controle total e segurança operacional
- **Mensagem WhatsApp**: Menciona moderação por IA e operação segura

### Social (Noivas / Debutantes / Famílias)
- **CTA Primário**: "Falar no WhatsApp" → Baixa fricção
- **CTA Secundário**: "Agendar demonstração" → Formulário simples
- **Foco**: Facilidade e experiência emocional
- **Mensagem WhatsApp**: Menciona galeria ao vivo, jogos e busca facial

### Corporativo (Promotores / Produtores)
- **CTA Primário**: "Agendar demonstração" → Formulário multi-step
- **CTA Secundário**: "Falar no WhatsApp" → Mensagem pré-preenchida
- **Foco**: Engajamento em escala e robustez
- **Mensagem WhatsApp**: Menciona alto volume e branding

## Uso Básico

```tsx
import { usePersonaCTAs } from '@/hooks';

function HeroSection() {
  const ctas = usePersonaCTAs();
  
  return (
    <div>
      <a href={ctas.primary.url}>
        {ctas.primary.text}
      </a>
      <a href={ctas.secondary.url}>
        {ctas.secondary.text}
      </a>
    </div>
  );
}
```

## Hooks Disponíveis

### `usePersonaCTAs()`

Retorna configuração completa de CTAs adaptada à persona selecionada.

**Retorno:**
```typescript
{
  primary: {
    text: string;
    url: string;  // Inclui UTM params
    icon?: string;
  };
  secondary: {
    text: string;
    url: string;  // Inclui UTM params
    icon?: string;
  };
}
```

**Exemplo:**
```tsx
const ctas = usePersonaCTAs();
// Assessora: primary = "Agendar demonstração"
// Social: primary = "Falar no WhatsApp"
// Corporativo: primary = "Agendar demonstração"
```

### `useWhatsAppCTA(customMessage?: string)`

Retorna URL do WhatsApp com mensagem pré-preenchida e UTM tracking.

**Parâmetros:**
- `customMessage` (opcional): Mensagem personalizada (sobrescreve padrão da persona)

**Retorno:** `string` - URL completa do WhatsApp

**Exemplo:**
```tsx
const whatsappUrl = useWhatsAppCTA();
// https://wa.me/5511999999999?text=Olá!...#utm_source=google&utm_campaign=casamentos
```

### `useSchedulingCTA()`

Retorna URL de agendamento com tipo de persona e UTM tracking.

**Retorno:** `string` - URL completa de agendamento

**Exemplo:**
```tsx
const schedulingUrl = useSchedulingCTA();
// https://eventovivo.com/agendar?tipo=assessora&utm_source=google&utm_medium=cpc
```

## Propagação de UTM Parameters

Todos os hooks propagam automaticamente os parâmetros UTM capturados na entrada:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `utm_term`

**URLs de agendamento**: UTM params como query parameters
```
https://eventovivo.com/agendar?tipo=assessora&utm_source=google&utm_campaign=casamentos
```

**URLs do WhatsApp**: UTM params como fragment (hash) para analytics
```
https://wa.me/5511999999999?text=Olá...#utm_source=google&utm_campaign=casamentos
```

## Mensagens WhatsApp por Persona

### Assessora
```
Olá! Sou assessora/cerimonialista e quero conhecer a plataforma Evento Vivo. 
Gostaria de agendar uma demonstração para entender como funciona a moderação 
por IA e a operação segura.
```

### Social
```
Olá! Quero usar a plataforma Evento Vivo no meu evento. Gostaria de saber 
mais sobre a galeria ao vivo, jogos interativos e busca facial.
```

### Corporativo
```
Olá! Sou produtor/promotor e quero conhecer a plataforma Evento Vivo. 
Gostaria de agendar uma demonstração para entender a arquitetura para 
alto volume e as possibilidades de branding.
```

## Configuração

As configurações de CTA estão centralizadas em `apps/landing/src/data/landing.ts`:

```typescript
// Número do WhatsApp
export const WHATSAPP_PHONE = '5511999999999';

// Mensagens por persona
export const whatsAppMessages: WhatsAppMessageTemplate = {
  assessora: '...',
  social: '...',
  corporativo: '...',
};

// CTAs por persona
export const personaCTAs: PersonaCTAConfig = {
  assessora: { ... },
  social: { ... },
  corporativo: { ... },
};

// CTAs padrão (sem persona)
export const defaultCTAs: CTAConfig = { ... };
```

## Testes

Testes completos em `usePersonaCTAs.test.ts` cobrem:

- ✅ Comportamento padrão (sem persona)
- ✅ CTAs específicos por persona (assessora, social, corporativo)
- ✅ Priorização correta de CTAs primário/secundário
- ✅ Mensagens WhatsApp específicas por persona
- ✅ Propagação de UTM params em URLs de agendamento
- ✅ Propagação de UTM params em URLs do WhatsApp (como fragment)
- ✅ Tratamento de UTM params vazios

## Integração com Contextos

O hook integra automaticamente com:

- **PersonaContext**: Detecta persona selecionada ou variação de entrada
- **AttributionContext**: Captura e propaga parâmetros UTM

Não é necessário passar props manualmente.

## Exemplo Completo

```tsx
import { usePersonaCTAs, useWhatsAppCTA, useSchedulingCTA } from '@/hooks';

function CTASection() {
  // Opção 1: CTAs completos adaptados
  const ctas = usePersonaCTAs();
  
  // Opção 2: WhatsApp específico
  const whatsappUrl = useWhatsAppCTA();
  
  // Opção 3: Agendamento específico
  const schedulingUrl = useSchedulingCTA();
  
  return (
    <div>
      {/* CTAs adaptados por persona */}
      <a href={ctas.primary.url} className="btn-primary">
        {ctas.primary.text}
      </a>
      <a href={ctas.secondary.url} className="btn-secondary">
        {ctas.secondary.text}
      </a>
      
      {/* WhatsApp direto */}
      <a href={whatsappUrl} className="btn-whatsapp">
        Falar no WhatsApp
      </a>
      
      {/* Agendamento direto */}
      <a href={schedulingUrl} className="btn-schedule">
        Agendar demonstração
      </a>
    </div>
  );
}
```

## Notas de Implementação

1. **Baixa fricção para Social**: Persona "social" prioriza WhatsApp como CTA primário para reduzir fricção
2. **Formulários qualificados**: Personas "assessora" e "corporativo" priorizam agendamento com formulários mais detalhados
3. **UTM tracking**: Todos os CTAs preservam atribuição de origem para analytics
4. **Mensagens contextualizadas**: Cada persona recebe mensagem WhatsApp alinhada com suas necessidades
5. **Fallback seguro**: Quando nenhuma persona está selecionada, usa CTAs balanceados padrão
