# Guia de Teste: Variações de Persona no Hero

## Como Testar as Variações

As variações de persona podem ser testadas adicionando o parâmetro `?persona=` na URL:

### URLs de Teste

1. **Persona Assessora** (Controle + Segurança)
   ```
   http://localhost:5173/?persona=assessora
   ```
   - Headline: "Controle total. Moderação inteligente, operação segura."
   - Trust Signals: Prioriza moderação por IA
   - Métricas: Foco em moderação e aprovação inteligente

2. **Persona Social** (Emoção + Facilidade) - PADRÃO
   ```
   http://localhost:5173/?persona=social
   ```
   - Headline: "Os convidados já estão tirando fotos. Agora elas viram experiência ao vivo no seu evento."
   - Trust Signals: Prioriza facilidade de uso
   - Métricas: Foco em tempo real e busca facial

3. **Persona Corporativo** (Engajamento + Escala)
   ```
   http://localhost:5173/?persona=corporativo
   ```
   - Headline: "Engajamento em escala. Galeria, jogos e telão em tempo real."
   - Trust Signals: Prioriza múltiplos canais
   - Métricas: Foco em alto volume e arquitetura

## O Que Observar em Cada Variação

### Elementos que Mudam

1. **Eyebrow** (texto acima do título)
   - Mantém-se igual em todas: "Plataforma premium de experiências ao vivo"

2. **Headline** (título principal)
   - Assessora: Foco em controle e segurança
   - Social: Foco em transformação e experiência
   - Corporativo: Foco em escala e engajamento

3. **Subheadline** (subtítulo)
   - Assessora: Menciona aprovação com IA
   - Social: Menciona transformação em experiência
   - Corporativo: Menciona arquitetura e múltiplos canais

4. **Flow Steps** (3 passos)
   - Assessora: "IA decide" (aprova, bloqueia e indexa)
   - Social: "IA organiza" (modera e indexa automaticamente)
   - Corporativo: "Múltiplos canais" + "IA em escala"

5. **Trust Signals** (4 sinais)
   - Ordem e ênfase mudam por persona
   - Assessora: Moderação por IA em destaque
   - Social: Busca facial em destaque
   - Corporativo: Múltiplos canais em destaque

6. **Metrics** (3 métricas)
   - Assessora: "Moderação IA" + "aprovação inteligente"
   - Social: "Busca facial" + "encontre suas fotos com selfie"
   - Corporativo: "Alto volume" + "arquitetura preparada para picos"

## Checklist de Teste

### Funcionalidade
- [ ] URL com `?persona=assessora` carrega variação correta
- [ ] URL com `?persona=social` carrega variação correta
- [ ] URL com `?persona=corporativo` carrega variação correta
- [ ] URL sem parâmetro carrega variação social (padrão)
- [ ] Persona é salva em localStorage após seleção
- [ ] Refresh da página mantém persona selecionada

### Acessibilidade
- [ ] h1 único presente em todas variações
- [ ] Todos os botões são focáveis via Tab
- [ ] Aria-labels presentes em CTAs
- [ ] Roles semânticos presentes (list, listitem, tablist, etc.)
- [ ] Contraste de cores adequado (≥4.5:1)

### Responsividade
- [ ] Mobile (<720px): Trust signals em 1 coluna
- [ ] Tablet (720px-1119px): Trust signals em 2 colunas
- [ ] Desktop (≥1120px): Layout otimizado

### Visual
- [ ] Animações GSAP funcionando
- [ ] Hover states nos trust signals
- [ ] Transições suaves entre tabs de output
- [ ] QR code animado
- [ ] Scan line animado no engine card

## Comandos Úteis

### Iniciar servidor de desenvolvimento
```bash
cd apps/landing
npm run dev
```

### Executar testes
```bash
cd apps/landing
npm run test
```

### Build de produção
```bash
cd apps/landing
npm run build
```

### Type checking
```bash
cd apps/landing
npm run type-check
```

## Troubleshooting

### Persona não muda ao alterar URL
1. Limpar localStorage: `localStorage.clear()`
2. Fazer hard refresh: Ctrl+Shift+R (Windows) ou Cmd+Shift+R (Mac)

### Trust signals não aparecem
1. Verificar console do navegador para erros
2. Verificar se `heroVariations` está exportado corretamente em `landing.ts`
3. Verificar se `TrustSignals` component está importado em `HeroExperience.tsx`

### Animações não funcionam
1. Verificar se GSAP está carregado
2. Verificar se `useReducedMotion` não está ativo
3. Verificar console para erros de GSAP

## Métricas de Conversão Sugeridas

Para cada variação de persona, rastrear:
- Taxa de clique no CTA primário ("Agendar demonstração")
- Taxa de clique no CTA secundário ("Ver como funciona")
- Tempo médio na página
- Scroll depth
- Taxa de bounce
- Conversão final (agendamento realizado)
