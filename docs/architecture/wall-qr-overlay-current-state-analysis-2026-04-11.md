# Wall QR overlay current state analysis - 2026-04-11

## Objetivo

Este documento consolida o estado real do QR Code exibido no telao do modulo `Wall`, com foco em:

- como o QR chega ao player hoje;
- onde ele e renderizado;
- qual e a logica de exibicao;
- o que e padrao e o que e realmente personalizavel;
- por que a percepcao de "em alguns temas ele some" acontece;
- qual recomendacao de produto e engenharia faz mais sentido a partir da implementacao atual.

Esta analise foi feita por leitura direta do codigo e por testes locais do frontend.

---

## Veredito executivo

O QR do telao hoje e um **overlay global padronizado**, nao um elemento nativo de cada tema.

Em termos praticos:

- o player ao vivo renderiza o QR pelo componente `BrandingOverlay`;
- o QR aparece no canto inferior direito, respeitando safe area;
- o valor codificado vem de `event.upload_url`;
- a estetica visual do card e fixa no frontend;
- o texto do card e fixo;
- `instructions_text` nao controla esse QR;
- os temas nao definem um QR proprio, com uma excecao conceitual importante:
  - no tema `puzzle`, `anchor_mode = qr_prompt` mostra **texto de convite**, nao um QR real.

Conclusao curta:

- sim, o QR atual e "padrao";
- nao, ele ainda nao e "personalizado" no sentido de copy, layout, estilo ou comportamento por tema;
- a sensacao de que ele "nao aparece em alguns temas" hoje tende a vir mais de inconsistencias de produto do que de uma regra formal de tema.

---

## Arquivos analisados

### Frontend

- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/components/BrandingOverlay.tsx`
- `apps/web/src/modules/wall/player/hooks/useStageGeometry.ts`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/manager-config.ts`
- `apps/web/src/modules/wall/player/components/QRFlipCard.tsx`
- `apps/web/src/modules/wall/player/hooks/useQRFlip.ts`
- `apps/web/src/modules/wall/player/hooks/useQRDraggable.ts`
- `apps/web/src/modules/wall/player/hooks/useEmbedMode.ts`

### Backend

- `apps/api/app/Modules/Wall/Http/Resources/WallBootResource.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/api/app/Modules/Wall/Actions/ResetWallAction.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`

### Shared types

- `packages/shared-types/src/wall.ts`

---

## Como o QR e montado hoje

## 1. Origem do valor do QR

No boot publico do wall, `WallBootResource` expõe:

- `event.upload_url`

Esse campo so existe quando o evento tem o modulo `live` habilitado.

Leitura pratica:

- o QR do player nao nasce de um asset salvo;
- ele nasce em runtime a partir da URL publica de upload do evento;
- se `upload_url` for `null`, o QR simplesmente nao renderiza.

## 2. Quem entrega as configuracoes do player

`WallPayloadFactory::settings()` devolve, entre outros:

- `show_qr`
- `show_branding`
- `instructions_text`
- `theme_config`

Mas a separacao atual e importante:

- `show_qr` controla se o overlay de QR pode aparecer;
- `instructions_text` controla a mensagem de espera do `IdleScreen`;
- `theme_config` hoje so influencia de fato o tema `puzzle`.

Ou seja:

- o QR overlay nao consome `instructions_text`;
- o QR overlay tambem nao consome um bloco proprio de configuracao visual.

## 3. Quem renderiza o QR no player ao vivo

No player real, `WallPlayerRoot` sempre usa `BrandingOverlay`.

Ele passa:

- `showQr={(stageAwareSettings?.show_qr ?? true) && state.status !== 'expired' && state.status !== 'stopped' && state.status !== 'error'}`
- `qrUrl={state.event?.upload_url}`

Leitura pratica:

o QR ao vivo aparece somente quando:

- `show_qr = true`;
- existe `event.upload_url`;
- o player nao esta em `expired`, `stopped` ou `error`.

## 4. Como o QR e desenhado visualmente

`BrandingOverlay` renderiza o QR com `QRCodeSVG` de `qrcode.react`.

A composicao hoje e fixa:

- card branco semitransparente;
- QR `88x88`;
- texto pequeno em uppercase:
  - `Envie sua foto`
- texto principal fixo:
  - `Aponte a camera para entrar no upload do evento.`

O card fica:

- no canto inferior direito;
- acima do dock de branding quando `show_branding = true`;
- no rodape direito quando branding esta desligado.

Importante:

- o texto e hardcoded;
- tamanho, paleta, espacos e copy nao vem do backend;
- nao existe hoje um `qr_overlay_config`.

---

## Ele e padrao?

Sim.

Em dois sentidos:

### 1. Padrao de configuracao

No backend, `show_qr` nasce como `true` por default na migration de settings.

Tambem no `ResetWallAction`, o reset do wall recoloca:

- `show_qr = true`
- `show_branding = true`

Logo:

- ligar QR e o comportamento padrao da stack;
- ocultar QR e uma decisao explicita do operador.

### 2. Padrao visual

O card atual e unico para o player inteiro.

Nao existe:

- tema com QR proprio por layout;
- preset visual especifico por evento;
- QR com cor, moldura, logo embutido ou copy propria;
- configuracao de posicao por evento;
- contrato de variacao visual por tema.

O que existe hoje e um unico overlay global, reaproveitado em qualquer layout.

---

## Ele e personalizado?

Hoje, so de forma muito limitada.

## O que realmente muda

- o conteudo do QR muda porque `event.upload_url` muda por evento;
- a presenca do QR muda por `show_qr`;
- a composicao vertical muda um pouco quando `show_branding` esta ligado;
- no manager, o operador pode editar `instructions_text`, mas isso nao altera o card do QR.

## O que nao muda hoje

- titulo do card;
- subtitulo do card;
- tamanho do QR;
- cores;
- borda;
- posicionamento base;
- comportamento por tema;
- comportamento por fase do evento;
- CTA visual no board do puzzle.

Portanto, a resposta correta e:

- o QR atual e parametrico no valor;
- mas o visual e a copy ainda sao padronizados, nao personalizados.

---

## Onde nasce a confusao de "em alguns temas ele aparece, em outros nao"

Hoje a arquitetura mistura tres conceitos diferentes sob o mesmo nome mental de "QR do telao".

## 1. Overlay global do player

Esse e o QR real do wall.

Ele:

- vem de `event.upload_url`;
- e gerado por `QRCodeSVG`;
- fica no `BrandingOverlay`;
- deveria existir de forma igual em qualquer tema, desde que `show_qr` e `upload_url` estejam disponiveis.

## 2. Preview do manager

`WallPreviewCanvas` nao usa a URL real do evento.

Ele usa um placeholder fixo:

- `const PREVIEW_QR_URL = 'https://eventovivo.local/preview-upload'`

Isso cria um gap real:

- no preview o QR sempre pode parecer disponivel;
- no player ao vivo ele depende de `event.upload_url` real.

Conclusao:

- preview e wall real nao estao 100% alinhados nessa trilha.

## 3. Ancora `qr_prompt` do tema `puzzle`

No tema `puzzle`, `anchor_mode = qr_prompt` faz isto:

- troca o label da peca ancora para `Envie sua foto`

Mas a ancora:

- nao renderiza `QRCodeSVG`;
- nao usa `event.upload_url`;
- nao mostra QR visual;
- e apenas uma peca textual.

Esse ponto e central para a percepcao de inconsistência.

Hoje o produto chama a opcao de:

- `Convite com QR`

Mas o que a implementacao entrega e:

- convite textual com CTA;
- sem QR real dentro da ancora.

Entao o operador pode concluir:

- "nesse tema o QR sumiu"

quando, na verdade:

- o tema nunca ganhou um QR proprio;
- ele ganhou apenas um texto ancora;
- e o overlay global continua sendo outra camada separada.

---

## O QR some por causa do tema?

Pelo codigo atual, **nao de forma formal**.

O overlay global do QR nao e desligado por layout.

Nao existe algo como:

- `fullscreen suporta QR`
- `gallery nao suporta QR`
- `mosaic esconde QR`

O que existe hoje e:

- o overlay global permanece o mesmo;
- o tema so muda o palco central;
- no `puzzle`, a geometria do palco considera QR como safe area e pode reduzir o preset do board, mas nao esconder o QR.

`useStageGeometry()` faz:

- contabilizar a area ocupada por QR, branding, sender credit e caption;
- calcular pressao de safe area;
- se necessario, reduzir o `puzzle` de `standard` para `compact`.

Mas ele **nao desliga** o QR.

Logo:

- o QR nao deveria sumir por decisao formal de tema;
- quando parece sumir, o problema e mais de UX, configuracao ou alinhamento de superficies.

---

## Motivos reais para o QR nao aparecer hoje

Os motivos reais hoje sao estes:

### 1. `show_qr = false`

O toggle foi desligado no wall.

### 2. `event.upload_url = null`

O evento nao esta entregando URL publica de upload no boot.

Isso pode acontecer se:

- o modulo `live` nao estiver habilitado;
- ou a regra de URL publica do evento nao estiver resolvendo como esperado.

### 3. O player esta em `expired`, `stopped` ou `error`

Nesses estados o `WallPlayerRoot` nao manda o QR para o overlay.

### 4. O operador esta olhando o preview e nao o player real

O preview usa URL fake fixa.

Isso mascara o estado real do evento.

### 5. O tema `puzzle` esta em `qr_prompt`

Nesse caso pode existir expectativa de um QR dentro da ancora.

Mas hoje a ancora so mostra texto.

Esse e um gap de semantica de produto.

---

## Estrutura atual: o que esta ativo e o que ficou legado

## Ativo no fluxo real

- `BrandingOverlay`
- `WallPlayerRoot`
- `WallBootResource`
- `WallPayloadFactory`
- `show_qr`
- `event.upload_url`

## Implementado, mas fora do fluxo atual

Arquivos presentes no repo e ainda nao plugados no player real:

- `apps/web/src/modules/wall/player/components/QRFlipCard.tsx`
- `apps/web/src/modules/wall/player/hooks/useQRFlip.ts`
- `apps/web/src/modules/wall/player/hooks/useQRDraggable.ts`
- `apps/web/src/modules/wall/player/hooks/useEmbedMode.ts`

Leitura pratica:

- existe codigo de experimentacao/comparativo para QR mais rico;
- mas a UX real do wall continua no overlay padrao;
- isso aumenta ruido arquitetural e expectativa falsa sobre capacidades do produto.

---

## O QR atual conversa com o editor universal de QR?

Hoje, nao.

Mesmo com a analise do editor universal de QR ja aberta em:

- `docs/architecture/event-universal-qr-editor-analysis-2026-04-11.md`

o QR do wall atual continua:

- gerado em runtime com `qrcode.react`;
- baseado diretamente no `event.upload_url`;
- sem contrato proprio de estilo salvo no backend;
- sem `qr_code_styling`;
- sem preset visual por evento;
- sem toolbar especifica de edicao no wall manager.

Em outras palavras:

- o QR do wall atual ainda e um CTA funcional;
- nao e um ativo editorial configuravel.

---

## Diagnostico de produto

O problema principal hoje nao e tecnico de renderizacao.

O problema principal e de **modelo mental do produto**.

Hoje coexistem:

1. QR overlay global real;
2. preview com URL fake;
3. ancora `qr_prompt` que nao tem QR;
4. componentes legados de QR mais sofisticado fora do fluxo.

Isso faz o produto parecer mais configuravel do que realmente e.

Resultado:

- o operador acha que o QR pertence ao tema;
- quando troca de tema, espera comportamento diferente;
- o tema `puzzle` promete QR mas entrega texto;
- o preview reforca uma leitura que o player real nao garante.

---

## Recomendacao objetiva

## P0 - alinhar semantica e reduzir ambiguidade

### 1. Separar `QR global` de `CTA do tema`

O produto precisa assumir duas coisas diferentes:

- `global_qr_overlay`
- `theme_anchor_cta`

Hoje as duas ideias estao misturadas.

### 2. Renomear `qr_prompt` se o QR real nao entrar agora

Se a ancora do `puzzle` continuar sem QR real, o nome atual esta errado.

Melhor renomear para algo como:

- `cta_prompt`
- `upload_prompt`
- `invite_prompt`

Do jeito atual, `Convite com QR` promete mais do que entrega.

### 3. Manager preview deve usar estado real ou avisar que e placeholder

Hoje o preview usa:

- `https://eventovivo.local/preview-upload`

Isso deveria mudar para uma destas trilhas:

- usar a URL real do evento quando existir;
- ou exibir badge explicito de `QR de preview`;
- ou avisar quando o wall real nao tem `upload_url`.

### 4. Expor diagnostico de renderabilidade do QR no manager

O manager deveria mostrar algo como:

- `QR visivel`
- `QR oculto pelo toggle`
- `QR indisponivel: evento sem upload_url`
- `QR oculto: wall parado/expirado`

Hoje esse diagnostico nao esta formalizado.

## P1 - dar configuracao real ao overlay

Criar um bloco proprio de configuracao, por exemplo:

```ts
qr_overlay: {
  enabled: boolean;
  mode: 'global_card' | 'theme_anchor' | 'auto';
  title?: string | null;
  subtitle?: string | null;
  position?: 'bottom-right' | 'bottom-left';
  size?: 'sm' | 'md' | 'lg';
  style_preset?: 'clean' | 'glass' | 'editorial';
}
```

Isso resolveria quatro gaps de uma vez:

- copy custom;
- variacao editorial;
- comportamento por tema;
- previsibilidade no manager.

## P1 - puzzle com QR real ou sem promessa de QR

Para o `puzzle`, a decisao correta precisa ser explicita:

### Opcao A - entregar QR real na ancora

Vantagem:

- a opcao `qr_prompt` passa a ser verdadeira.

Requisito:

- renderizar QR real dentro da peca ancora;
- validar contraste, legibilidade e budget visual.

### Opcao B - manter ancora textual

Se essa for a decisao, o produto precisa:

- renomear a opcao;
- parar de chamar isso de QR;
- manter o QR real apenas no overlay global.

## P2 - limpar trilha morta ou reativar conscientemente

Hoje o repo carrega:

- `QRFlipCard`
- `useQRFlip`
- `useQRDraggable`
- `useEmbedMode`

Sem uso real no player final.

Escolha recomendada:

- ou integrar isso de verdade com contrato de produto;
- ou remover/arquivar para reduzir ruído cognitivo.

---

## Sugestao de produto por tema

Se a decisao for pragmatica e de baixo risco, eu recomendo:

### Single-item themes

- manter o QR overlay global atual como default;
- permitir personalizacao leve de copy e tamanho;
- continuar usando o overlay fora do palco principal.

### Multi-item themes classicos

- continuar com o mesmo overlay global;
- nao tentar enfiar QR dentro dos slots.

### Puzzle

- curto prazo:
  - manter overlay global real;
  - renomear `qr_prompt` para CTA textual, se nao houver QR real;
- medio prazo:
  - permitir ancora com QR real quando o stage budget comportar;
  - fallback automatico para texto quando nao comportar.

Essa trilha e mais honesta com o produto e evita ambiguidade operacional.

---

## Bateria de validacao adicionada nesta revisao

Para sustentar esta leitura, esta rodada adicionou:

- `apps/web/src/modules/wall/player/components/BrandingOverlay.test.tsx`
  - valida que o QR overlay atual renderiza com copy fixa quando `showQr` e `qrUrl` existem;
  - valida que o card nao aparece sem `qrUrl`;
- `apps/web/src/modules/wall/player/wall-qr-overlay-characterization.test.ts`
  - valida que o player real usa `event.upload_url`;
  - valida que o preview usa `PREVIEW_QR_URL` fixa;
  - valida que a copy do overlay e hardcoded;
  - valida que `puzzle qr_prompt` hoje e ancora textual;
  - valida que `QRFlipCard` e `useEmbedMode` continuam fora do fluxo real do player.

## Resultado executado

Frontend:

- `vitest run src/modules/wall/player/components/BrandingOverlay.test.tsx src/modules/wall/player/wall-qr-overlay-characterization.test.ts`
  - `2 files passed`
  - `5 tests passed`

- `npm run type-check`
  - `passed`

---

## Checklist tecnico objetivo

- [x] confirmar origem real do QR no boot do wall
- [x] confirmar componente responsavel pela renderizacao real
- [x] confirmar se o texto do QR e padrao ou customizavel
- [x] confirmar se `instructions_text` controla o QR
- [x] confirmar se o QR depende formalmente do tema
- [x] confirmar gap entre preview e player real
- [x] confirmar comportamento do `puzzle` com `qr_prompt`
- [x] confirmar existencia de componentes legados fora do fluxo

---

## Conclusao

Hoje o QR do telao e uma camada global padronizada, funcional, mas ainda pouco formalizada como sistema de produto.

O maior problema nao e "o QR nao renderiza".

O maior problema e:

- o produto sugere mais personalizacao do que realmente entrega;
- o preview e o player real nao usam exatamente a mesma fonte de verdade;
- o tema `puzzle` usa a palavra QR para uma ancora que nao renderiza QR.

Se a meta for deixar essa experiencia profissional e previsivel, o caminho correto e:

1. separar QR global de CTA do tema;
2. parar de chamar de QR o que hoje e apenas texto;
3. dar configuracao propria ao overlay;
4. alinhar preview e player real na mesma semantica.

Em uma frase:

**o QR do wall hoje funciona como CTA padrao do player, mas ainda nao virou um subsistema explicito de overlay e tema.**
