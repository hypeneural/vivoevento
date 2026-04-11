# Wall QR overlay current state analysis - 2026-04-11

## Objetivo

Este documento consolida o estado real do QR Code exibido no telao do modulo `Wall`, com foco em:

- stack atualizada do wall;
- como a logica do telao funciona hoje;
- onde o QR entra no fluxo do player;
- o que e padrao e o que e realmente personalizavel;
- por que ele parece "sumir" em alguns temas;
- o que precisa melhorar para um QR flutuante, editavel e profissional.

Esta analise foi feita por leitura direta do codigo e por testes locais do frontend.

---

## Veredito executivo

O QR do telao hoje e um **overlay global padronizado**.

Ele nao e um elemento nativo de cada tema.

Em termos praticos:

- o player ao vivo renderiza o QR pelo componente `BrandingOverlay`;
- o valor do QR vem de `event.upload_url`;
- a exibicao depende principalmente de `show_qr` e do status do player;
- o visual do card e fixo no frontend;
- o texto do card e fixo;
- `instructions_text` nao personaliza o QR;
- o tema `puzzle` tem uma opcao chamada `qr_prompt`, mas hoje ela entrega so uma ancora textual, nao um QR real.

Conclusao curta:

- sim, o QR atual e padrao;
- nao, ele ainda nao e um subsistema de overlay realmente configuravel;
- a sensacao de que ele "nao aparece em alguns temas" hoje vem mais de semantica de produto, preview desalinhado e expectativa errada sobre o `puzzle` do que de uma regra formal de layout.

---

## Stack relevante atualizada

## Frontend do wall

Stack efetivamente ativa no player e no manager do wall hoje:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- TailwindCSS `3.4.17`
- Framer Motion `12.38.0`
- TanStack Query `5.83.0`
- Pusher JS `8.4.3`
- `qrcode.react` `4.2.0`
- `qr-code-styling` `1.9.2`
- Vitest `3.2.4`
- Playwright `1.59.1`

Leitura pratica:

- o QR do wall hoje usa `qrcode.react`;
- `qr-code-styling` ja esta instalado no frontend, mas ainda nao entra no fluxo real do wall;
- isso abre caminho para personalizacao futura sem exigir nova dependencia.

## Backend do wall

Stack efetivamente ativa no backend do modulo `Wall`:

- PHP `>=8.3 <8.4`
- Laravel Framework `13.x`
- Laravel Horizon `5.45`
- Laravel Reverb `1.9`
- Laravel Telescope `5.19`
- Laravel Sanctum `4.3`
- Laravel Pulse `1.7`
- AWS SDK PHP `3.378`
- Spatie Laravel Data `4.20`
- Spatie Medialibrary `11.21`
- Spatie Permission `7.2`

Leitura pratica:

- o backend ja tem maturidade para persistir configuracoes reais do QR;
- o gap atual nao e de infra, e de contrato de produto e integracao do overlay.

---

## Arquivos analisados

### Frontend

- `apps/web/src/modules/wall/player/components/WallPlayerRoot.tsx`
- `apps/web/src/modules/wall/player/components/BrandingOverlay.tsx`
- `apps/web/src/modules/wall/player/hooks/useStageGeometry.ts`
- `apps/web/src/modules/wall/player/hooks/useQRDraggable.ts`
- `apps/web/src/modules/wall/player/hooks/useQRFlip.ts`
- `apps/web/src/modules/wall/player/hooks/useEmbedMode.ts`
- `apps/web/src/modules/wall/player/components/QRFlipCard.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzleLayout.tsx`
- `apps/web/src/modules/wall/player/themes/puzzle/PuzzlePiece.tsx`
- `apps/web/src/modules/wall/components/manager/stage/WallPreviewCanvas.tsx`
- `apps/web/src/modules/wall/components/manager/inspector/WallAppearanceTab.tsx`
- `apps/web/src/modules/wall/manager-config.ts`

### Backend

- `apps/api/app/Modules/Wall/Http/Resources/WallBootResource.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/app/Modules/Wall/Enums/WallLayout.php`
- `apps/api/app/Modules/Wall/Actions/ResetWallAction.php`
- `apps/api/app/Modules/Wall/Models/EventWallSetting.php`

### Shared types

- `packages/shared-types/src/wall.ts`

---

## Como a logica do telao funciona e onde o QR entra

O QR nao faz parte da logica de selecao da fila.

Ele entra como uma camada de shell do player.

## 1. Boot

O backend entrega no boot:

- `event`
- `files`
- `settings`
- `ads`

O dado-chave do QR esta em:

- `event.upload_url`

O backend so entrega isso quando o evento pode expor o upload publico.

## 2. Estado do player

`useWallPlayer()` hidrata:

- evento;
- settings;
- fila de midias;
- anuncios;
- estado de conexao;
- runtime de video.

O QR nao muda a fila, nao seleciona midia e nao interfere no reducer de playback.

## 3. Stage-aware settings

Antes de renderizar a tela, `WallPlayerRoot` usa:

- `useStageGeometry()`
- `applyStageGeometryToWallSettings()`

Essa etapa:

- mede largura e altura uteis;
- calcula safe areas de QR, branding, sender credit e caption;
- em tema `puzzle`, pode reduzir o preset do board quando a area util ficar apertada.

Importante:

- isso afeta o palco;
- nao cria o QR;
- e hoje nao esconde o QR por tema.

## 4. Composicao final do player

A ordem real hoje e:

1. `PlayerShell`
2. `BrandingOverlay`
3. `ConnectionOverlay`
4. `LayoutRenderer` ou `AdOverlay`
5. floating caption
6. featured badge
7. side thumbnails

Leitura pratica:

- o QR vive acima do palco principal;
- ele nao pertence aos layouts;
- o tema controla o palco;
- o QR atual controla o shell.

Essa e a explicacao mais importante da logica do telao:

**o QR atual e overlay de player, nao um asset de tema.**

---

## Como o QR e montado hoje

## 1. Origem do valor do QR

No boot publico, `WallBootResource` expoe:

- `event.upload_url`

Leitura pratica:

- o QR nao nasce de um arquivo salvo;
- ele nasce em runtime a partir da URL publica de upload do evento;
- se `upload_url` for `null`, o QR simplesmente nao renderiza.

## 2. Quem entrega as configuracoes do QR

`WallPayloadFactory::settings()` devolve, entre outros:

- `show_qr`
- `show_branding`
- `show_sender_credit`
- `instructions_text`
- `theme_config`

Mas a separacao atual e esta:

- `show_qr` controla se o QR pode aparecer;
- `instructions_text` controla a mensagem do `IdleScreen`;
- `theme_config` influencia o tema `puzzle`;
- nenhum desses campos hoje descreve o visual do QR como um objeto proprio.

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

`BrandingOverlay` usa `QRCodeSVG` de `qrcode.react`.

A composicao atual e fixa:

- card branco semitransparente;
- QR `88x88`;
- kicker fixo:
  - `Envie sua foto`
- texto principal fixo:
  - `Aponte a camera para entrar no upload do evento.`

O card fica:

- no canto inferior direito;
- acima do dock de branding quando `show_branding = true`;
- no rodape direito quando branding esta desligado.

Importante:

- o texto e hardcoded;
- tamanho, paleta, espacamento e copy nao vem do backend;
- nao existe hoje um `qr_overlay_config`.

## 5. Como o preview do manager difere do wall real

`WallPreviewCanvas` nao usa a URL real do evento.

Ele usa:

- `const PREVIEW_QR_URL = 'https://eventovivo.local/preview-upload'`

Isso cria um gap real:

- o preview consegue sempre desenhar um QR fake;
- o player ao vivo depende de `event.upload_url` real;
- o preview atual nao prova que o QR do wall esta realmente configurado.

---

## Ele e padrao?

Sim.

## 1. Padrao de configuracao

No backend, `show_qr` nasce como `true` por default.

No `ResetWallAction`, o reset do wall recoloca:

- `show_qr = true`
- `show_branding = true`

Logo:

- ligar QR e comportamento padrao da stack;
- ocultar QR e decisao explicita do operador.

## 2. Padrao visual

O card atual e unico para o player inteiro.

Hoje nao existe:

- tema com QR proprio;
- preset visual por evento;
- QR com copy propria por wall;
- posicao custom persistida por evento;
- contrato visual por layout.

O que existe hoje e um overlay global unico.

---

## Ele e personalizado?

Hoje, so de forma muito limitada.

## O que realmente muda

- o valor do QR muda porque `event.upload_url` muda por evento;
- a presenca do QR muda por `show_qr`;
- o empilhamento vertical muda um pouco quando `show_branding` esta ligado;
- o `puzzle` pode mudar o palco para abrir espaco ao overlay.

## O que nao muda hoje

- titulo do card;
- subtitulo do card;
- tamanho do QR;
- cor do card;
- borda;
- posicao base;
- estilo por tema;
- copy por fase do evento;
- comportamento de operador.

Portanto:

- o QR atual e parametrico no valor;
- mas o visual e a UX ainda sao padronizados.

---

## Onde nasce a confusao de "em alguns temas ele aparece, em outros nao"

Hoje a arquitetura mistura tres conceitos sob a mesma intuicao mental de "QR do telao".

## 1. Overlay global do player

Esse e o QR real do wall.

Ele:

- vem de `event.upload_url`;
- e gerado por `QRCodeSVG`;
- fica no `BrandingOverlay`;
- deveria existir em qualquer tema enquanto `show_qr` e `upload_url` estiverem validos.

## 2. Preview do manager

O preview usa uma URL placeholder fixa.

Logo:

- ele parece sempre funcional;
- mas nao representa fielmente o estado real do evento.

## 3. Ancora `qr_prompt` do tema `puzzle`

No `puzzle`, `anchor_mode = qr_prompt` hoje faz isto:

- troca o label da ancora para `Envie sua foto`

Mas a ancora:

- nao renderiza `QRCodeSVG`;
- nao usa `event.upload_url`;
- nao mostra QR visual;
- e apenas uma peca textual.

Esse e o principal ponto de inconsistencia de produto.

Hoje a opcao no manager fala:

- `Convite com QR`

Mas o que a implementacao entrega e:

- convite textual;
- sem QR real dentro da ancora.

Entao o operador conclui:

- "nesse tema o QR sumiu"

quando, na pratica:

- o overlay global continua separado;
- o tema nunca ganhou um QR proprio.

---

## O QR some por causa do tema?

Pelo codigo atual, nao de forma formal.

O overlay global do QR nao e desligado por layout.

Hoje nao existe algo como:

- `fullscreen suporta QR`;
- `gallery esconde QR`;
- `mosaic bloqueia QR`.

O que existe e:

- o overlay global permanece o mesmo;
- o tema muda apenas o palco principal;
- no `puzzle`, a geometria considera o QR como safe area e pode reduzir o board de `standard` para `compact`.

`useStageGeometry()`:

- contabiliza area de QR, branding, sender credit e caption;
- calcula pressao de safe area;
- ajusta o preset do `puzzle` quando necessario.

Mas ele nao desliga o QR.

Logo:

- o QR nao deveria sumir por decisao formal de tema;
- quando parece sumir, o problema hoje e mais de UX, configuracao ou expectativa errada.

---

## Motivos reais para o QR nao aparecer hoje

## 1. `show_qr = false`

O toggle foi desligado no wall.

## 2. `event.upload_url = null`

O evento nao esta entregando URL publica de upload no boot.

Isso pode acontecer se:

- o modulo `live` nao estiver habilitado;
- ou a resolucao da URL publica nao estiver fechada.

## 3. O player esta em `expired`, `stopped` ou `error`

Nesses estados o `WallPlayerRoot` nao manda o QR para o overlay.

## 4. O operador esta olhando o preview e nao o wall real

O preview usa QR fake.

## 5. O tema `puzzle` esta em `qr_prompt`

Nesse caso pode existir expectativa de QR na ancora, mas a ancora hoje so mostra texto.

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

- existe base de experimentacao para QR mais rico;
- mas a UX real do wall continua no overlay padrao;
- isso aumenta ruido cognitivo e expectativa falsa sobre personalizacao.

## Achado novo desta rodada

`useQRDraggable` parece mais maduro do que realmente esta.

Hoje ele:

- salva posicao em `localStorage`;
- trabalha por `wall_code` no navegador local;
- nao persiste no backend;
- nao faz clamp real nas bordas apesar do comentario prometer isso;
- nao esta ligado ao `BrandingOverlay`;
- nao expoe toolbar `+`, `-` ou `x`.

Em resumo:

- existe uma base de experimento;
- mas ela ainda nao resolve o caso de uso de operacao real do telão.

---

## O QR atual conversa com o editor universal de QR?

Hoje, nao.

Mesmo com a analise do editor universal em:

- `docs/architecture/event-universal-qr-editor-analysis-2026-04-11.md`

o QR do wall atual continua:

- gerado em runtime com `qrcode.react`;
- baseado diretamente no `event.upload_url`;
- sem contrato proprio de estilo salvo no backend;
- sem integracao com `qr-code-styling`;
- sem toolbar propria no manager;
- sem persistencia de configuracao visual por wall.

Em outras palavras:

- o QR do wall atual ainda e um CTA funcional;
- nao e um ativo editorial configuravel.

---

## Diagnostico de produto

O principal problema hoje nao e tecnico de renderizacao.

O principal problema e de modelo mental.

Hoje coexistem:

1. QR overlay global real;
2. preview com URL fake;
3. ancora `qr_prompt` sem QR;
4. hooks/componentes legados de QR mais sofisticado fora do fluxo final.

Resultado:

- o operador acha que o QR pertence ao tema;
- quando troca de tema, espera comportamento diferente;
- o `puzzle` promete QR, mas entrega texto;
- o preview reforca uma leitura que o player real nao garante;
- o repo sugere drag e interacao, mas a UI atual e completamente passiva.

## Achados novos desta rodada

### 1. O overlay atual e completamente passivo

`BrandingOverlay` usa `pointer-events-none`.

Impacto:

- o operador nao consegue clicar no QR ao vivo;
- nao existem controles `+`, `-` ou `x`;
- nao existe drag na composicao publica atual.

### 2. O hook de drag existente ainda nao serve como feature final

`useQRDraggable` hoje e:

- local ao navegador;
- desktop-only;
- sem persistencia em backend;
- sem clamp real de viewport;
- sem integracao com o overlay atual.

### 3. O repo ja tem dependencia para QR mais rico

`qr-code-styling` ja esta instalado em `apps/web`.

Impacto:

- da para evoluir o QR do wall sem nova dependencia;
- falta conectar isso a um contrato real de overlay.

---

## Recomendacao objetiva

## P0 - alinhar semantica e reduzir ambiguidade

### 1. Separar `QR global` de `CTA do tema`

O produto precisa assumir duas coisas distintas:

- `global_qr_overlay`
- `theme_anchor_cta`

Hoje as duas ideias estao misturadas.

### 2. Renomear `qr_prompt` se o QR real nao entrar agora

Se a ancora do `puzzle` continuar sem QR real, o nome atual esta errado.

Melhor renomear para algo como:

- `cta_prompt`
- `upload_prompt`
- `invite_prompt`

Hoje `Convite com QR` promete mais do que entrega.

### 3. Preview do manager deve usar estado real ou avisar que e placeholder

O preview hoje usa:

- `https://eventovivo.local/preview-upload`

Isso deveria ir para uma destas trilhas:

- usar a URL real do evento quando existir;
- ou exibir badge explicita de `QR de preview`;
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

Isso resolveria:

- copy custom;
- variacao editorial;
- previsibilidade no manager;
- semantica clara entre overlay e tema.

## P1 - suportar modo operador para mover, redimensionar e ocultar o QR

A sua proposta faz sentido, mas a implementacao correta nao e tornar o wall publico clicavel por default.

Minha recomendacao:

- o QR deve ficar interativo apenas em `modo operador`;
- o melhor lugar inicial e o manager preview;
- no player publico, isso so deveria existir em modo protegido.

Motivo:

- o wall publico e superficie de exibicao;
- tornar o overlay clicavel sempre abre margem para toques acidentais no device do palco;
- `pointer-events-none` hoje protege a composicao.

### Comportamento recomendado

Quando o operador entra em modo de edicao do QR:

- o card ganha handles visiveis;
- aparecem controles:
  - `+` aumentar
  - `-` diminuir
  - `x` ocultar
  - `reset` voltar ao preset salvo
- o card passa a aceitar drag;
- o player mostra limite de viewport e safe area;
- ao soltar, a posicao e salva no backend.

### Campos que faltam no contrato

Algo nessa linha:

```ts
qr_overlay: {
  enabled: boolean;
  title?: string | null;
  subtitle?: string | null;
  style_preset?: 'clean' | 'glass' | 'editorial';
  size_scale?: number;
  position_mode?: 'preset' | 'custom';
  preset_position?: 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left';
  offset_x?: number;
  offset_y?: number;
  hidden_by_operator?: boolean;
  allow_live_drag?: boolean;
}
```

### O que precisa melhorar tecnicamente

Para essa feature ficar profissional, faltam pelo menos:

1. persistir posicao e escala no backend, nao em `localStorage`
2. fazer clamp real na area visivel do stage
3. distinguir `modo operador` de `modo publico`
4. aplicar a mesma configuracao no preview e no player real
5. salvar por wall/evento, nao por navegador local
6. respeitar branding, caption, sender credit e side thumbnails ao resolver a posicao
7. ter reset oficial para o preset do layout
8. permitir esconder sem desligar globalmente o QR do wall

## P1 - puzzle com QR real ou sem promessa de QR

Para o `puzzle`, a decisao correta precisa ser explicita:

### Opcao A - entregar QR real na ancora

Vantagem:

- a opcao `qr_prompt` passa a ser verdadeira.

Requisito:

- renderizar QR real dentro da peca ancora;
- validar contraste, legibilidade e budget visual.

### Opcao B - manter ancora textual

Se essa for a decisao:

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
- ou remover/arquivar para reduzir ruido cognitivo.

## P2 - personalizacoes que fazem sentido no QR do wall

Essas personalizacoes fazem sentido no produto atual:

- copy do titulo
- copy do subtitulo
- tamanho do card
- tamanho do QR
- preset visual do card
- posicao preset
- posicao custom com drag
- escala do bloco
- modo `somente QR`
- modo `QR + texto`
- modo `somente CTA textual`
- ancora de tema opcional
- fallback automatico quando a safe area estiver pressionada

Essas eu nao colocaria no primeiro corte:

- edicao livre do conteudo do QR
- logos arbitrarios dentro do QR sem guardrails
- drag liberado no wall publico sem modo operador
- persistencia so em browser local
- comportamento diferente por device sem fonte central de verdade

---

## Sugestao de produto por tema

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
  - renomear `qr_prompt` para CTA textual se o QR real nao entrar;
- medio prazo:
  - permitir ancora com QR real quando o stage budget comportar;
  - fallback automatico para texto quando nao comportar.

## Sugestao de rollout

### Fase 1

- alinhar preview e player real
- renomear `qr_prompt`
- criar `qr_overlay` config minima
- permitir copy e tamanho

### Fase 2

- modo operador no manager preview
- drag com persistencia real
- `+`, `-`, `x`, `reset`
- clamp e safe area visivel

### Fase 3

- opcao de editar no player protegido por permissao
- ancora de tema com QR real quando o tema suportar
- integracao com o editor universal de QR

---

## Bateria de validacao adicionada nesta revisao

Para sustentar esta leitura, esta rodada adicionou:

- `apps/web/src/modules/wall/player/components/BrandingOverlay.test.tsx`
  - valida que o QR overlay atual renderiza com copy fixa quando `showQr` e `qrUrl` existem;
  - valida que o card nao aparece sem `qrUrl`;
  - valida que o overlay atual e passivo e nao expoe botoes de operador;
- `apps/web/src/modules/wall/player/wall-qr-overlay-characterization.test.ts`
  - valida que o player real usa `event.upload_url`;
  - valida que o preview usa `PREVIEW_QR_URL` fixa;
  - valida que a copy do overlay e hardcoded;
  - valida que `puzzle qr_prompt` hoje e ancora textual;
  - valida que `QRFlipCard` e `useEmbedMode` continuam fora do fluxo real do player;
- `apps/web/src/modules/wall/player/hooks/useQRDraggable.test.ts`
  - valida que o hook restaura posicao local;
  - valida que ele so ativa no desktop;
  - valida que o drag atual ainda nao faz clamp da viewport.

## Resultado executado

Frontend:

- `vitest run src/modules/wall/player/components/BrandingOverlay.test.tsx src/modules/wall/player/wall-qr-overlay-characterization.test.ts src/modules/wall/player/hooks/useQRDraggable.test.ts`
  - `3 files passed`
  - `14 tests passed`

- `npm run type-check`
  - `passed`

---

## Checklist tecnico objetivo

- [x] confirmar stack real do wall
- [x] confirmar origem real do QR no boot do wall
- [x] confirmar componente responsavel pela renderizacao real
- [x] confirmar a logica do player e onde o QR entra
- [x] confirmar se o texto do QR e padrao ou customizavel
- [x] confirmar se `instructions_text` controla o QR
- [x] confirmar se o QR depende formalmente do tema
- [x] confirmar gap entre preview e player real
- [x] confirmar comportamento do `puzzle` com `qr_prompt`
- [x] confirmar existencia de componentes legados fora do fluxo
- [x] confirmar se o overlay atual aceita interacao do operador
- [x] confirmar se o drag atual ja esta pronto para uso real
- [x] mapear o que falta para um QR flutuante personalizavel e persistido

---

## Conclusao

Hoje o QR do telao e uma camada global padronizada, funcional, mas ainda pouco formalizada como sistema de produto.

O maior problema nao e "o QR nao renderiza".

O maior problema e:

- o produto sugere mais personalizacao do que realmente entrega;
- o preview e o player real nao usam exatamente a mesma fonte de verdade;
- o tema `puzzle` usa a palavra QR para uma ancora que nao renderiza QR;
- a trilha de drag existente ainda nao tem persistencia, clamp e modo operador.

Se a meta for deixar essa experiencia profissional e previsivel, o caminho correto e:

1. separar QR global de CTA do tema;
2. parar de chamar de QR o que hoje e apenas texto;
3. dar configuracao propria ao overlay;
4. alinhar preview e player real na mesma semantica;
5. transformar o drag em feature real de operador, nao em experimento local.

Em uma frase:

**o QR do wall hoje funciona como CTA padrao do player, mas ainda nao virou um subsistema explicito, persistido e operavel de overlay e tema.**
