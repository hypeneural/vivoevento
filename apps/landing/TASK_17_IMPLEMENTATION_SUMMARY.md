# Task 17 Implementation Summary: Modelo de Conversão Adaptado

## Overview

Implementação completa do modelo de conversão adaptado por persona com propagação automática de parâmetros UTM, conforme Requisitos 26 e 37.

## Arquivos Criados

### 1. `src/hooks/usePersonaCTAs.ts`
Hook principal que retorna CTAs específicos por persona com URLs incluindo UTM params.

**Exports:**
- `usePersonaCTAs()` - CTAs completos adaptados por persona
- `useWhatsAppCTA(customMessage?)` - URL do WhatsApp com mensagem pré-preenchida
- `useSchedulingCTA()` - URL de agendamento com tipo de persona

### 2. `src/hooks/usePersonaCTAs.test.tsx`
Suite de testes completa com 16 testes cobrindo:
- Comportamento padrão (sem persona)
- CTAs específicos por persona (assessora, social, corporativo)
- Priorização correta de CTAs primário/secundário
- Mensagens WhatsApp específicas por persona
- Propagação de UTM params

**Resultado:** ✅ 16/16 testes passando

### 3. `src/hooks/usePersonaCTAs.README.md`
Documentação completa do hook incluindo:
- Comportamento por persona
- Exemplos de uso
- Configuração
- Integração com contextos

## Arquivos Modificados

### 1. `src/data/landing.ts`
**Adicionado:**
- Tipos `PersonaCTAConfig` e `WhatsAppMessageTemplate`
- Constante `WHATSAPP_PHONE` com número do WhatsApp
- Objeto `whatsAppMessages` com mensagens pré-preenchidas por persona
- Objeto `personaCTAs` com configurações de CTA por persona
- Objeto `defaultCTAs` para visitantes sem persona selecionada

### 2. `src/utils/routing.ts`
**Adicionado:**
- Função `buildWhatsAppUrl()` para construir URLs do WhatsApp com mensagem e UTM tracking

**Já existiam:**
- `getPersonaFromURL()` - Extrai persona da URL
- `setPersonaInURL()` - Define persona na URL
- `getUTMParams()` - Extrai parâmetros UTM
- `buildCTAUrl()` - Constrói URL com UTM params

### 3. `src/hooks/index.ts`
**Adicionado:**
- Export de `usePersonaCTAs`, `useWhatsAppCTA`, `useSchedulingCTA`

## Comportamento por Persona

### Assessora / Cerimonialista
```typescript
{
  primary: {
    text: 'Agendar demonstração',
    url: 'https://eventovivo.com/agendar?tipo=assessora&utm_...',
    icon: 'Calendar',
  },
  secondary: {
    text: 'Falar no WhatsApp',
    url: 'https://wa.me/5511999999999?text=...#utm_...',
    icon: 'MessageCircle',
  },
}
```

**Mensagem WhatsApp:**
> Olá! Sou assessora/cerimonialista e quero conhecer a plataforma Evento Vivo. Gostaria de agendar uma demonstração para entender como funciona a moderação por IA e a operação segura.

**Foco:** Controle total e segurança operacional

### Social (Noivas / Debutantes / Famílias)
```typescript
{
  primary: {
    text: 'Falar no WhatsApp',  // ← Prioriza baixa fricção
    url: 'https://wa.me/5511999999999?text=...#utm_...',
    icon: 'MessageCircle',
  },
  secondary: {
    text: 'Agendar demonstração',
    url: 'https://eventovivo.com/agendar?tipo=social&utm_...',
    icon: 'Calendar',
  },
}
```

**Mensagem WhatsApp:**
> Olá! Quero usar a plataforma Evento Vivo no meu evento. Gostaria de saber mais sobre a galeria ao vivo, jogos interativos e busca facial.

**Foco:** Facilidade e experiência emocional

### Corporativo (Promotores / Produtores)
```typescript
{
  primary: {
    text: 'Agendar demonstração',
    url: 'https://eventovivo.com/agendar?tipo=corporativo&utm_...',
    icon: 'Calendar',
  },
  secondary: {
    text: 'Falar no WhatsApp',
    url: 'https://wa.me/5511999999999?text=...#utm_...',
    icon: 'MessageCircle',
  },
}
```

**Mensagem WhatsApp:**
> Olá! Sou produtor/promotor e quero conhecer a plataforma Evento Vivo. Gostaria de agendar uma demonstração para entender a arquitetura para alto volume e as possibilidades de branding.

**Foco:** Engajamento em escala e robustez

## Propagação de UTM Parameters

### URLs de Agendamento
UTM params como query parameters:
```
https://eventovivo.com/agendar?tipo=assessora&utm_source=google&utm_medium=cpc&utm_campaign=casamentos
```

### URLs do WhatsApp
UTM params como fragment (hash) para analytics:
```
https://wa.me/5511999999999?text=Olá...#utm_source=google&utm_campaign=casamentos
```

**Parâmetros rastreados:**
- `utm_source` - Origem do tráfego
- `utm_medium` - Meio de marketing
- `utm_campaign` - Campanha específica
- `utm_content` - Variação de conteúdo
- `utm_term` - Termo de pesquisa

## Exemplo de Uso

```tsx
import { usePersonaCTAs } from '@/hooks';

function HeroSection() {
  const ctas = usePersonaCTAs();
  
  return (
    <div className="hero-ctas">
      <a 
        href={ctas.primary.url} 
        className="btn-primary"
      >
        {ctas.primary.text}
      </a>
      <a 
        href={ctas.secondary.url} 
        className="btn-secondary"
      >
        {ctas.secondary.text}
      </a>
    </div>
  );
}
```

## Integração com Contextos

O hook integra automaticamente com:

1. **PersonaContext**: Detecta persona selecionada ou variação de entrada via URL
2. **AttributionContext**: Captura parâmetros UTM na inicialização

Não é necessário passar props manualmente.

## Validação

### ✅ Testes
```bash
npm test -- usePersonaCTAs
# 16/16 testes passando
```

### ✅ Type Check
```bash
npm run type-check
# Sem erros de TypeScript
```

### ✅ Build
```bash
npm run build
# Build bem-sucedido
```

## Requisitos Atendidos

### Requisito 37: Modelo de Conversão Adaptado por Persona
- ✅ Social: Prioriza "Falar no WhatsApp" com mensagem pré-preenchida
- ✅ Assessora: Prioriza "Agendar demonstração" com formulário qualificado
- ✅ Corporativo: Prioriza "Agendar demonstração" com formulário multi-step
- ✅ Mensagens WhatsApp contextualizadas por persona
- ✅ URLs de agendamento com tipo de persona

### Requisito 26: Analytics e Conversão
- ✅ Captura de parâmetros UTM na inicialização
- ✅ Propagação de UTM params em todos CTAs
- ✅ Rastreamento de origem por parâmetros UTM
- ✅ Preservação de atribuição através do fluxo de conversão

## Próximos Passos

Para usar os CTAs adaptados em componentes existentes:

1. **HeroSection**: Substituir CTAs estáticos por `usePersonaCTAs()`
2. **FinalCTASection**: Substituir CTAs estáticos por `usePersonaCTAs()`
3. **Navbar**: Substituir CTAs estáticos por `usePersonaCTAs()`
4. **AudienceSection**: Usar `useSchedulingCTA()` ou `useWhatsAppCTA()` conforme perfil
5. **CTAFloating**: Usar `usePersonaCTAs()` para CTA flutuante

## Configuração de Produção

Antes de publicar, atualizar em `src/data/landing.ts`:

```typescript
// Atualizar número do WhatsApp
export const WHATSAPP_PHONE = '5511999999999'; // ← Número real

// Atualizar URLs de agendamento
const schedulingBaseUrl = 'https://eventovivo.com/agendar'; // ← URL real
```

## Notas Técnicas

1. **Baixa fricção para Social**: Persona "social" prioriza WhatsApp como CTA primário para reduzir fricção e aumentar conversão
2. **Formulários qualificados**: Personas "assessora" e "corporativo" priorizam agendamento com formulários mais detalhados
3. **UTM tracking**: Todos os CTAs preservam atribuição de origem para analytics
4. **Mensagens contextualizadas**: Cada persona recebe mensagem WhatsApp alinhada com suas necessidades
5. **Fallback seguro**: Quando nenhuma persona está selecionada, usa CTAs balanceados padrão
6. **Type-safe**: Toda implementação é fortemente tipada com TypeScript
7. **Testado**: 16 testes cobrindo todos os cenários de uso

## Status

✅ **Task 17.1 Completa**: CTAs específicos por persona implementados
✅ **Task 17.2 Completa**: buildCTAUrl com UTM params implementado
✅ **Task 17 Completa**: Modelo de conversão adaptado implementado

**Todos os testes passando. Build bem-sucedido. Pronto para integração.**
