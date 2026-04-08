# Guia de Testes - Hero Refatorado

## 🚀 Servidor Iniciado

**URL Local:** http://localhost:4174/

---

## ✅ Checklist de Testes Visuais

### 1. Layout e Hierarquia

- [ ] Headline é o elemento mais proeminente
- [ ] Subheadline é legível e clara
- [ ] Trust points estão organizados verticalmente
- [ ] CTAs têm hierarquia clara (primário > secundário)
- [ ] Métricas estão visíveis e organizadas
- [ ] Visual do mockup está centralizado e bonito

### 2. Espaçamento e Respiração

- [ ] Há espaço em branco suficiente entre elementos
- [ ] Elementos não estão apertados ou sobrepostos
- [ ] Padding e margins estão consistentes
- [ ] Layout não parece "cheio demais"

### 3. Cores e Contraste

- [ ] Headline tem gradiente accent visível
- [ ] Texto é legível (contraste adequado)
- [ ] Trust points têm ícones verdes
- [ ] CTA primário se destaca
- [ ] Status badge é visível

### 4. Interatividade

- [ ] Hover nos trust points funciona (transform + border)
- [ ] Hover no CTA secundário funciona (cor + transform)
- [ ] Hover nas métricas funciona (background + border)
- [ ] Animações de reveal funcionam (GSAP)
- [ ] Status dot pulsa suavemente

### 5. Responsividade

#### Desktop (≥1120px)
- [ ] Grid 2 colunas funciona
- [ ] Hero ocupa altura adequada
- [ ] Métricas em 3 colunas
- [ ] Título tem tamanho adequado

#### Tablet (720px - 1119px)
- [ ] Grid 1 coluna funciona
- [ ] Eyebrow está oculto
- [ ] Elementos redimensionam corretamente
- [ ] Gap está adequado

#### Mobile (<720px)
- [ ] Layout vertical funciona
- [ ] Título é legível
- [ ] Status badge está estático (não absolute)
- [ ] Métricas em scroll horizontal
- [ ] Floating badges são visíveis

### 6. Acessibilidade

- [ ] Navegação por Tab funciona
- [ ] Foco é visível
- [ ] Screen reader consegue ler conteúdo
- [ ] ARIA labels estão corretos
- [ ] Animações respeitam prefers-reduced-motion

### 7. Performance

- [ ] Página carrega rápido
- [ ] Sem erros no console
- [ ] Animações são suaves
- [ ] Sem layout shifts
- [ ] Imagens carregam corretamente

---

## 🐛 Problemas Conhecidos

### Resolvidos
- ✅ `stageRef is not defined` - Corrigido
- ✅ Type errors - Corrigidos
- ✅ Build errors - Corrigidos

### Pendentes
- ⏳ Placeholder do mockup precisa ser substituído por imagem real
- ⏳ Imagens de preload não estão sendo usadas (warning no console)

---

## 🎨 O que Observar

### Melhorias Visuais
1. **Hierarquia Clara:** Olho vai naturalmente de headline → subheadline → trust points → CTA
2. **Espaço em Branco:** 50% mais espaço que versão anterior
3. **Trust Points:** Design limpo com ícones verdes e hover sutil
4. **Visual Premium:** Mockup com glow, borders e shadows sofisticados
5. **Badges Decorativos:** Floating badges adicionam interesse visual

### Simplificação
1. **Menos Elementos:** 8 elementos vs 15+ anterior
2. **Menos CTAs:** 2 CTAs vs 5 anterior
3. **Sem Complexidade:** Sem tabs, carrosséis, demos pesadas
4. **Foco Claro:** Ação óbvia (Agendar demonstração)

---

## 📊 Comparação Visual

### Antes
```
┌─────────────────────────────────────────┐
│ [Eyebrow]                               │
│ HEADLINE GRANDE                         │
│ Subheadline                             │
│                                         │
│ [Fluxo 3 passos]                        │
│ [CTA 1] [CTA 2]                         │
│ [Micro 1] [Micro 2] [Micro 3]           │
│ [Trust signals complexos]               │
│ [3 Métricas]                            │
│                                         │
│ [Demo interativa pesada]                │
│ ├─ Card 1                               │
│ ├─ Card 2                               │
│ └─ Card 3                               │
│ [4 Tabs de output]                      │
└─────────────────────────────────────────┘
```

### Depois
```
┌─────────────────────────────────────────┐
│ [Eyebrow]                               │
│ HEADLINE GRANDE                         │
│ Subheadline clara                       │
│                                         │
│ ✓ Trust point 1                         │
│ ✓ Trust point 2                         │
│ ✓ Trust point 3                         │
│                                         │
│ [CTA Primário] Ver como funciona →      │
│                                         │
│ [Métrica 1] [Métrica 2] [Métrica 3]     │
│                                         │
│ ┌─────────────────────────────────┐     │
│ │ [Status badge]                  │     │
│ │                                 │     │
│ │ [Mockup limpo]                  │     │
│ │                                 │     │
│ │ [Badge 1]        [Badge 2]      │     │
│ └─────────────────────────────────┘     │
└─────────────────────────────────────────┘
```

---

## 🔍 Testes Específicos

### Teste 1: Primeira Impressão (5 segundos)
**Objetivo:** Visitante entende proposta em 5 segundos

**Como testar:**
1. Abrir página
2. Contar 5 segundos
3. Fechar olhos
4. Responder: "O que a plataforma faz?"

**Resultado esperado:** "Plataforma para eventos com IA, galeria, jogos e telão em tempo real"

### Teste 2: Ação Clara
**Objetivo:** Visitante sabe qual ação tomar

**Como testar:**
1. Abrir página
2. Olhar por 10 segundos
3. Responder: "Qual é a próxima ação?"

**Resultado esperado:** "Agendar demonstração"

### Teste 3: Hierarquia Visual
**Objetivo:** Olho segue caminho natural

**Como testar:**
1. Abrir página
2. Observar onde olho vai primeiro, segundo, terceiro
3. Anotar sequência

**Resultado esperado:** Headline → Subheadline → Trust points → CTA

### Teste 4: Mobile First
**Objetivo:** Experiência mobile é excelente

**Como testar:**
1. Abrir DevTools
2. Mudar para iPhone 12 Pro
3. Verificar se tudo é legível e funcional
4. Testar scroll e interações

**Resultado esperado:** Tudo funciona perfeitamente, sem zoom necessário

### Teste 5: Performance
**Objetivo:** Carregamento rápido

**Como testar:**
1. Abrir DevTools → Network
2. Throttle para "Fast 3G"
3. Recarregar página
4. Medir tempo até hero estar visível

**Resultado esperado:** < 3 segundos

---

## 📝 Feedback

### O que está funcionando bem?
- [ ] Hierarquia visual
- [ ] Espaçamento
- [ ] Cores e contraste
- [ ] Interatividade
- [ ] Responsividade
- [ ] Performance

### O que precisa melhorar?
- [ ] _____________________
- [ ] _____________________
- [ ] _____________________

### Sugestões
- [ ] _____________________
- [ ] _____________________
- [ ] _____________________

---

## 🚀 Próximos Passos

### Se tudo estiver OK:
1. ✅ Marcar todos checkboxes acima
2. ✅ Substituir placeholder por imagem real
3. ✅ Fazer Lighthouse audit
4. ✅ Preparar para A/B test

### Se houver problemas:
1. ⚠️ Documentar problemas encontrados
2. ⚠️ Priorizar por impacto
3. ⚠️ Corrigir um por um
4. ⚠️ Re-testar

---

**Servidor:** http://localhost:4174/  
**Data:** 2026-04-08  
**Versão:** 2.0 (Simplificado)
