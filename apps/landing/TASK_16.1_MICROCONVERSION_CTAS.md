# Task 16.1: Adicionar CTAs de Microconversão

## Resumo da Implementação

Implementação completa dos CTAs de microconversão conforme requisito 36 da especificação de refatoração da landing page.

## Arquivos Criados

### 1. `src/components/MicroconversionCTAs.tsx`
Componente React que implementa os 3 CTAs de microconversão:
- **Ver evento exemplo**: Navega para seção de depoimentos (casos reais)
- **Ver como funciona em 30s**: Navega para seção "Como funciona"
- **Abrir demonstração visual**: Navega para seção de módulos de experiência

**Características:**
- Suporte a 2 variantes de layout: `inline` (horizontal) e `stacked` (vertical)
- Callback `onMicroconversion` para rastreamento de analytics
- Navegação suave usando hook `useSmoothScroll`
- Acessibilidade completa com ARIA labels
- TypeScript com tipos exportados

### 2. `src/components/MicroconversionCTAs.module.scss`
Estilos SCSS Module para o componente:
- Layout responsivo (inline em desktop, stacked em mobile)
- Estados de hover, focus e active
- Suporte a `prefers-reduced-motion`
- Contraste adequado para acessibilidade
- Touch targets otimizados para mobile (min-height: 44px)

### 3. `src/utils/tracking.ts`
Utilitário completo de analytics e tracking:
- Rastreamento de CTAs primários e secundários
- Rastreamento de microconversões
- Rastreamento de scroll depth (25%, 50%, 75%, 100%)
- Rastreamento de interações com seções
- Captura de parâmetros UTM
- Rastreamento de abandono de página
- Integração com Google Analytics (gtag)
- Fallback para console.log em desenvolvimento

### 4. `src/components/MicroconversionCTAs.test.tsx`
Suite de testes completa com 10 casos de teste:
- ✅ Renderização dos 3 CTAs
- ✅ Variantes de layout (inline/stacked)
- ✅ Callbacks de microconversão
- ✅ Acessibilidade (ARIA labels)
- ✅ Ícones renderizados
- ✅ Funcionamento sem callback

**Resultado:** 10/10 testes passando

## Arquivos Modificados

### 1. `src/components/HeroExperience.tsx`
**Mudanças:**
- Importação do componente `MicroconversionCTAs`
- Importação das funções de tracking
- Adição de tracking ao CTA primário
- Integração do componente de microconversão abaixo dos CTAs principais

### 2. `src/components/FinalCTASection.tsx`
**Mudanças:**
- Importação do componente `MicroconversionCTAs`
- Importação das funções de tracking
- Adição de tracking aos CTAs primário e secundário
- Integração do componente de microconversão abaixo dos CTAs principais

### 3. `src/components/FinalCTASection.module.scss`
**Mudanças:**
- Adição de classe `.microconversions` para posicionar os CTAs de microconversão
- Espaçamento adequado (margin-top: 0.5rem)

### 4. `src/components/Navbar.tsx`
**Mudanças:**
- Importação da função de tracking
- Adição de tracking aos CTAs no navbar (desktop e mobile)
- Tracking diferenciado para versões desktop e mobile

## Conformidade com Requisitos

### Requisito 36: Microconversões e Redução de Fricção

✅ **36.1** - Oferece microconversão além da macro: "Ver evento exemplo", "Ver como funciona em 30s", "Abrir demonstração visual"

✅ **36.2** - Posiciona microconversão próxima ao CTA principal (Hero e Final CTA sections)

✅ **36.3** - Rastreia taxa de microconversão (função `trackMicroconversion` implementada)

✅ **36.4** - Quando visitante completa microconversão, apresenta CTA macro de forma não intrusiva (navegação suave para seções relevantes)

✅ **36.5** - Usa microconversão como caminho alternativo para visitantes com alta ansiedade (CTAs de baixo risco posicionados estrategicamente)

### Requisito 26: Analytics e Conversão

✅ **26.1** - Rastreia cliques em CTA primário e secundário (implementado em Hero, Navbar e Final CTA)

✅ **26.2** - Rastreia scroll depth por faixas (25%, 50%, 75%, 100%) (função `initScrollDepthTracking`)

✅ **26.3** - Rastreia interação com planos, FAQ e módulos (funções específicas implementadas)

✅ **26.4** - Permite identificação de origem por parâmetros UTM (função `getUTMParams`)

✅ **26.5** - Registra seção de maior abandono (função `initAbandonmentTracking`)

## Posicionamento dos CTAs de Microconversão

### 1. Hero Section
- **Localização**: Abaixo dos CTAs principais e trust signals
- **Variante**: `inline` (horizontal em desktop, vertical em mobile)
- **Tracking**: Location `"hero"`

### 2. Final CTA Section
- **Localização**: Abaixo dos 2 botões principais
- **Variante**: `inline` (horizontal em desktop, vertical em mobile)
- **Tracking**: Location `"final"`

## Estratégia de Tracking

### Eventos Rastreados

1. **CTA Clicks**
   - Tipo: `primary` ou `secondary`
   - Localização: `hero`, `navbar`, `pricing`, `final`, `floating`
   - Valor: 10 (primary), 5 (secondary)

2. **Microconversões**
   - Ações: `view-example`, `watch-demo`, `open-visual-demo`
   - Localização: `hero`, `final`
   - Valor: 3

3. **Scroll Depth**
   - Marcos: 25%, 50%, 75%, 100%
   - Rastreamento automático via `initScrollDepthTracking()`

4. **Interações com Seções**
   - Seções: planos, FAQ, módulos, persona
   - Ações customizadas por tipo de interação

5. **Abandono de Página**
   - Última seção visível antes de sair
   - Rastreamento via `initAbandonmentTracking()`

### Integração com Google Analytics

```typescript
// Exemplo de evento enviado
window.gtag("event", "Microconversion - view-example", {
  event_category: "Engagement",
  event_label: "hero",
  value: 3,
});
```

## Acessibilidade

✅ **ARIA Labels**: Todos os botões têm labels descritivos
✅ **Keyboard Navigation**: Navegação por teclado funcional
✅ **Focus Visible**: Estados de foco claramente visíveis
✅ **Reduced Motion**: Suporte a `prefers-reduced-motion`
✅ **Touch Targets**: Mínimo 44px de altura em mobile
✅ **Contraste**: Cores com contraste adequado (WCAG AA)

## Responsividade

### Desktop
- Layout inline (horizontal)
- 3 botões lado a lado
- Hover states com elevação sutil

### Mobile
- Layout stacked (vertical) automático em < 640px
- Botões ocupam largura total
- Touch targets otimizados (44px mínimo)
- Espaçamento adequado entre botões

## Testes

### Cobertura de Testes
- ✅ Renderização de componentes
- ✅ Variantes de layout
- ✅ Callbacks de tracking
- ✅ Acessibilidade
- ✅ Comportamento sem callbacks
- ✅ Ícones e elementos visuais

### Resultado
```
Test Files  1 passed (1)
Tests       10 passed (10)
```

### Type Checking
```
tsc --noEmit
✓ No type errors
```

## Próximos Passos (Opcional)

1. **Integração com Google Analytics**
   - Adicionar script do Google Analytics no `index.html`
   - Configurar ID de rastreamento via variável de ambiente

2. **Testes A/B**
   - Testar diferentes textos de CTA
   - Testar diferentes posicionamentos
   - Medir taxa de conversão por variação

3. **Análise de Dados**
   - Monitorar taxa de microconversão vs conversão macro
   - Identificar qual microconversão tem melhor performance
   - Otimizar baseado em dados reais

4. **Expansão**
   - Adicionar microconversões em outras seções (Pricing, Audience)
   - Implementar CTA flutuante após 80% de scroll
   - Adicionar microconversões específicas por persona

## Conclusão

Implementação completa e testada dos CTAs de microconversão conforme requisito 36. O sistema de tracking está pronto para integração com Google Analytics e permite análise detalhada do comportamento do visitante. A solução é acessível, responsiva e segue as melhores práticas de UX para redução de fricção na conversão.
