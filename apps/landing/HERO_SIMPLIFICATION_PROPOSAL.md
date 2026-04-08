# Proposta de Simplificação do Hero

## Problemas Identificados

### 1. Sobrecarga Visual
- ❌ Fluxo de 3 passos (entrada → motor → saídas)
- ❌ Demo interativa com 3 cards grandes
- ❌ 4 tabs de output (galeria, jogos, telão, busca facial)
- ❌ Trust signals
- ❌ 3 métricas
- ❌ Microconversões
- **Resultado:** Visitante não sabe onde olhar primeiro

### 2. Múltiplos CTAs Competindo
- ❌ "Agendar demonstração" (primário)
- ❌ "Ver como funciona" (secundário)
- ❌ "Ver evento exemplo" (microconversão)
- ❌ "Ver como funciona em 30s" (microconversão)
- ❌ "Abrir demonstração visual" (microconversão)
- **Resultado:** Paralisia de decisão

### 3. Boxes com Conteúdo Transbordando
- ❌ Cards da demo interativa não contêm bem o conteúdo
- ❌ Textos longos em espaços pequenos
- ❌ Imagens cortadas ou mal posicionadas

### 4. Falta de Hierarquia Clara
- ❌ Tudo tem peso visual similar
- ❌ Não há caminho claro para o olho seguir
- ❌ Mensagem principal se perde no ruído

---

## Proposta: Hero Simplificado

### Estrutura Nova (Regra 1-3-1)

**1 Mensagem Principal**
```
Controle total.
Moderação inteligente,
operação segura.
```

**3 Pontos de Apoio** (ao invés de fluxo de 3 passos)
```
✓ Sem app - QR + web + WhatsApp
✓ IA configurável - Você decide o nível
✓ Tempo real - Galeria, jogos, telão, busca facial
```

**1 CTA Primário**
```
[Agendar demonstração] (destaque máximo)
```

**1 Visual de Apoio**
- Mockup limpo do produto em uso
- OU vídeo curto (15s) em loop
- OU carrossel de 3 screenshots (auto-play lento)

### Layout Proposto

```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│  EXPERIÊNCIAS AO VIVO                                   │
│                                                         │
│  Controle total.                                        │
│  Moderação inteligente,                                 │
│  operação segura.                                       │
│                                                         │
│  Receba por QR, aprove com IA e publique em            │
│  galeria, jogos, telão e busca facial.                 │
│                                                         │
│  ✓ Sem app                                              │
│  ✓ IA configurável                                      │
│  ✓ Tempo real                                           │
│                                                         │
│  [Agendar demonstração]                                 │
│                                                         │
│  Ver como funciona →                                    │
│                                                         │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                         │
│  [Visual limpo: mockup ou vídeo curto]                 │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Mudanças Específicas

### Remover
1. ❌ Fluxo de 3 passos detalhado (mover para seção "Como Funciona")
2. ❌ Demo interativa com 3 cards (muito pesado para hero)
3. ❌ Tabs de output (mover para seção "Módulos")
4. ❌ Microconversões inline (mover para depois do hero)
5. ❌ Métricas (mover para seção de prova social)
6. ❌ Trust signals detalhados (simplificar para 3 checkmarks)

### Manter (Simplificado)
1. ✅ Headline forte e clara
2. ✅ Subheadline concisa (1 frase)
3. ✅ 3 pontos de apoio (checkmarks simples)
4. ✅ 1 CTA primário
5. ✅ 1 link secundário discreto
6. ✅ 1 visual de apoio limpo

### Adicionar
1. ✅ Espaço em branco (50% mais)
2. ✅ Hierarquia visual clara (tamanhos contrastantes)
3. ✅ Caminho visual óbvio (olho → headline → pontos → CTA)

---

## Hierarquia Visual Proposta

### Nível 1 (Máxima Atenção)
- Headline: "Controle total. Moderação inteligente, operação segura."
- Tamanho: 4.5-5.5rem
- Peso: 800
- Cor: Branco + gradiente accent

### Nível 2 (Atenção Secundária)
- Subheadline: "Receba por QR, aprove com IA e publique em galeria, jogos, telão e busca facial."
- Tamanho: 1.1-1.2rem
- Peso: 400
- Cor: White 80%

### Nível 3 (Apoio)
- 3 checkmarks com texto curto
- Tamanho: 0.95rem
- Peso: 600
- Cor: White 70%

### Nível 4 (Ação)
- CTA primário: Grande, contrastante, impossível de ignorar
- Link secundário: Discreto, menor, sem competir

---

## Benefícios da Simplificação

### Para o Visitante
1. ✅ Entende a proposta em 3-5 segundos
2. ✅ Sabe exatamente qual ação tomar
3. ✅ Não sofre fadiga cognitiva
4. ✅ Experiência premium e confiável

### Para Conversão
1. ✅ Reduz paralisia de decisão
2. ✅ Aumenta foco no CTA primário
3. ✅ Melhora taxa de clique
4. ✅ Reduz bounce rate

### Para Performance
1. ✅ Menos JavaScript acima da dobra
2. ✅ LCP mais rápido (menos elementos)
3. ✅ CLS menor (layout mais simples)
4. ✅ Carregamento mais rápido

---

## Onde Vai o Conteúdo Removido?

### Fluxo de 3 Passos Detalhado
→ Seção "Como Funciona" (já existe, Task 9)

### Demo Interativa com Cards
→ Seção "Módulos de Experiência" (já existe, Task 10)

### Tabs de Output
→ Seção "Módulos de Experiência" (já existe, Task 10)

### Microconversões
→ Logo após o hero, antes de "Como Funciona"
→ OU dentro da seção "Como Funciona"

### Métricas
→ Seção "Depoimentos" ou "Para Quem É"
→ OU seção dedicada de "Números"

### Trust Signals Detalhados
→ Simplificar para 3 checkmarks no hero
→ Versão completa na seção "IA e Segurança"

---

## Implementação Sugerida

### Fase 1: Simplificar Copy
1. Reduzir headline para 2-3 linhas máximo
2. Subheadline para 1 frase (≤20 palavras)
3. 3 pontos de apoio (≤5 palavras cada)

### Fase 2: Simplificar Visual
1. Remover demo interativa com 3 cards
2. Substituir por 1 mockup limpo OU vídeo curto
3. Garantir que visual cabe em 100vh mobile

### Fase 3: Simplificar CTAs
1. Manter apenas 1 CTA primário
2. 1 link secundário discreto
3. Remover microconversões inline

### Fase 4: Ajustar Layout
1. Aumentar espaço em branco em 50%
2. Melhorar hierarquia de tamanhos
3. Garantir caminho visual claro

---

## Métricas de Sucesso

### Antes vs Depois

| Métrica | Antes | Meta Depois |
|---------|-------|-------------|
| Tempo para entender proposta | 15-20s | 3-5s |
| Elementos acima da dobra | 15+ | 6-8 |
| CTAs visíveis | 5 | 2 |
| Taxa de clique no CTA primário | ? | +30% |
| Bounce rate | ? | -20% |
| LCP | ? | ≤2.0s |

---

## Referências de Boas Práticas

### Exemplos de Hero Limpo
- Stripe: 1 headline + 1 subheadline + 1 CTA + 1 visual
- Linear: 1 headline + 1 subheadline + 1 CTA + vídeo de produto
- Vercel: 1 headline + 1 subheadline + 1 CTA + terminal animado

### Princípios CRO
- **Clareza > Persuasão:** Visitante precisa entender antes de converter
- **Menos é mais:** Cada elemento adicional reduz conversão em 5-10%
- **1 ação clara:** Múltiplos CTAs reduzem conversão em 20-30%
- **Hierarquia visual:** Olho precisa de caminho óbvio

---

## Próximos Passos

1. ✅ Validar proposta com stakeholders
2. ⏳ Criar wireframe simplificado
3. ⏳ Implementar versão simplificada
4. ⏳ Teste A/B (hero atual vs simplificado)
5. ⏳ Medir impacto em conversão

---

**Conclusão:** O hero atual é tecnicamente impressionante mas cognitivamente sobrecarregado. A simplificação proposta mantém a essência da mensagem enquanto remove 60% do ruído visual, criando uma experiência mais clara, rápida e conversora.
