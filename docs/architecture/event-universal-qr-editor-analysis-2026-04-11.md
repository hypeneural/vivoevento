# Event universal QR editor analysis - 2026-04-11

## Objetivo

Este documento consolida:

- o estado atual real do monorepo para QR codes em paginas de evento e subpaginas;
- o que a documentacao oficial do `qr-code-styling` confirma sobre capacidades, limites e fluxo de uso;
- como transformar isso em um editor universal de QR code, simples para usuario leigo e coerente com a stack atual;
- qual deve ser o ownership tecnico no backend e no frontend;
- como persistir configuracao, renderizar preview, exportar arquivos e evoluir para assets gerados no servidor.

O foco aqui nao e um QR editor generico para qualquer payload do produto.

O foco recomendado para a V1 e:

- QR codes de links publicos do evento;
- abrindo a partir do clique no QR ja exibido em `events/:id` e paginas irmas;
- com configuracao salva por evento e por link;
- com download simples e regras automaticas de seguranca para leitura.

---

## Veredito executivo

O melhor caminho para o Evento Vivo e construir um editor proprio usando `qr-code-styling` como motor de renderizacao e exportacao.

O motivo e direto:

- o frontend atual ja e SPA React 18 + Vite 5, entao a API da lib baseada em `new QRCodeStyling(...)`, `append(...)` e `update(...)` encaixa bem em um preview ao vivo;
- o produto ja tem primitives maduras para modal responsivo com `Dialog`, `Drawer`, `Tabs`, `Accordion` e `useIsMobile`;
- o backend de `Events` ja centraliza os links publicos do evento em `EventPublicLinksService`, inclusive expondo `qr_value` por link;
- o QR exibido hoje no admin e estatico, pequeno e sem edicao; a feature pedida exige presets, logo, exportacao e persistencia de configuracao;
- a propria documentacao oficial do `qr-code-styling` organiza o demo em grupos que casam com um editor por abas: `Main Options`, `Dots Options`, `Corners Square Options`, `Corners Dot Options`, `Background Options`, `Image Options`, `QR Options` e `Export`.

Decisao recomendada:

1. Manter ownership de persistencia no modulo backend `Events`.
2. Criar uma engine de editor/renderizacao reutilizavel no frontend.
3. Salvar JSON de configuracao por `event_id + link_key`.
4. Fazer preview e download no browser desde a V1.
5. Tratar assets server-side como fase seguinte, usando renderer Node para manter paridade com `qr-code-styling`.

Decisao igualmente importante:

- nao tentar substituir todos os QR codes do produto de uma vez;
- nao usar esse editor para QR transacional de Pix nem QR temporario de conexao do WhatsApp;
- nao persistir apenas imagem final;
- nao deixar o usuario editar livremente o payload do QR em links publicos do evento.

---

## Leitura real da stack atual

## Frontend

O `apps/web` ja entrega quase tudo que a experiencia precisa:

- React 18 + TypeScript + Vite 5;
- `@tanstack/react-query` para carregar e salvar configuracao;
- `React Hook Form` + `Zod` como padrao natural para a camada de formulario do editor;
- `shadcn/ui` + Radix + Tailwind para modal, tabs, accordion, select, slider, switch e button;
- `vaul` via `Drawer` para experiencia mobile;
- hook `useIsMobile()` pronto em `apps/web/src/hooks/use-mobile.tsx`;
- suite de testes com Vitest em `jsdom`.

Hoje o admin usa `qrcode.react` em pontos especificos:

- `apps/web/src/modules/events/components/PublicLinkCard.tsx`
- `apps/web/src/modules/billing/public-checkout/components/PaymentStatusCard.tsx`
- `apps/web/src/modules/wall/player/components/BrandingOverlay.tsx`
- `apps/web/src/modules/whatsapp/components/WhatsAppConnectionPanel.tsx`

Isso mostra dois grupos diferentes de uso:

1. QR de propriedade do evento e com potencial de branding:
   - galeria
   - upload
   - wall
   - hub
   - play
   - find_me
2. QR efemero ou transacional:
   - Pix
   - autenticacao do WhatsApp

O editor universal deve nascer no grupo 1.

## Backend

O modulo `Events` ja e o centro natural dessa feature:

- `EventPublicLinksService` resolve `url`, `api_url` e `qr_value` para `gallery`, `upload`, `wall`, `hub`, `play` e `find_me`;
- `EventDetailResource` injeta `public_links` e `public_identifiers` no payload da pagina de evento;
- as rotas atuais ja concentram gestao de links publicos em `apps/api/app/Modules/Events/routes/api.php`;
- existe um endpoint legado `POST /api/v1/events/{event}/generate-qr`, mas hoje ele so devolve links e um `qr_code_path` antigo;
- existe um campo `events.qr_code_path`, mas ele e singular e insuficiente para o modelo atual, porque um evento tem varios links publicos e cada um pode querer um visual proprio.

Leitura pratica:

- a feature ja tem fonte de verdade para o conteudo do QR;
- o que falta e uma camada de configuracao visual, renderizacao e exportacao.

## Onde o QR ja aparece no evento

Na pagina `apps/web/src/modules/events/EventDetailPage.tsx`, o QR aparece via `PublicLinkCard` em:

- Galeria
- Wall
- Play

O mesmo contrato de `public_links` tambem permite estender isso para:

- Upload
- Hub
- Buscar minhas fotos

Isso confirma o desenho correto de produto:

- um editor universal;
- com varias entradas;
- compartilhando a mesma engine;
- mas persistindo configuracao por `link_key`.

---

## O que a documentacao oficial do `qr-code-styling` confirma

## API principal

Pelo README oficial e pela demo oficial, a API central da lib gira em torno de:

- `new QRCodeStyling(options)`
- `append(container)`
- `update(options)`
- `download({ name, extension })`
- `getRawData(extension)`
- `applyExtension(extension)`
- `deleteExtension()`

Isso e suficiente para:

- montar preview ao vivo;
- atualizar o QR sem recriar toda a arvore React;
- baixar em `png`, `jpeg`, `webp` e `svg`;
- obter `Blob` no browser e `Buffer` em Node;
- adicionar decoracoes SVG extras quando isso fizer sentido.

## Grupos de opcoes oficiais

A demo oficial exposta em `qr-code-styling.com` separa o editor nestes grupos:

- `Main Options`
- `Dots Options`
- `Corners Square Options`
- `Corners Dot Options`
- `Background Options`
- `Image Options`
- `QR Options`
- `Export`

Esse agrupamento confirma que a lib ja foi pensada para um editor configuravel.

O mapeamento recomendado para o Evento Vivo e:

| Demo oficial | Aba do produto |
|---|---|
| Main Options | Conteudo |
| Dots + Corners + Background | Estilo |
| Image Options | Logo |
| Export | Exportacao |
| QR Options | Avancado |

## Capacidade de customizacao confirmada

Pelos docs oficiais, a lib suporta:

- cor unica ou gradiente para dots;
- estilos diferentes para dots;
- estilos e cores separados para `cornersSquareOptions` e `cornersDotOptions`;
- fundo com cor ou gradiente;
- imagem/logo central;
- configuracoes de `imageSize`, `margin`, `hideBackgroundDots`, `crossOrigin`, `saveAsBlob`;
- `qrOptions.typeNumber`, `mode` e `errorCorrectionLevel`;
- `shape` em renderizacao mais decorativa;
- exportacao em `png`, `jpeg`, `webp` e `svg`;
- suporte server-side com `node-canvas` e `jsdom`.

## Limites e observacoes relevantes da doc oficial

Os docs oficiais tambem deixam pistas importantes para regras de produto:

- `imageSize` acima de `0.5` nao e recomendado;
- `errorCorrectionLevel` aceita `L`, `M`, `Q` e `H`;
- `saveAsBlob` melhora compatibilidade de SVG com logo, mas aumenta o tamanho do arquivo;
- para Node server-side, `png` depende de `node-canvas` e `svg` depende de `jsdom`;
- se `saveAsBlob` for usado em SVG com imagem, o servidor precisa dos dois;
- `crossOrigin: "anonymous"` deve ser configurado quando a logo vier de outra origem e houver download/export.

## Leitura complementar da especificacao oficial de QR

As paginas oficiais da DENSO WAVE reforcam dois pontos que o editor deve proteger:

- quiet zone minima de 4 modulos em todos os lados;
- niveis de correcao de erro aproximados:
  - `L`: cerca de 7%
  - `M`: cerca de 15%
  - `Q`: cerca de 25%
  - `H`: cerca de 30%

Traducao pratica para o produto:

- quiet zone nao pode ser configuravel para menos do que o minimo;
- QR com logo nao deve operar com `L`;
- quando houver logo, o fluxo deve sugerir `H` ou subir automaticamente para `H`.

## Observacao de manutencao

Inferencia a partir das fontes oficiais:

- a documentacao publica da lib tem sinais leves de inconsistencia de versao;
- a busca do site oficial mostra `npm v1.8.3`;
- o npm publica `1.9.2`;
- os exemplos do README ainda citam script tag antiga em `1.5.0`.

Isso nao invalida a escolha da lib, mas recomenda:

- travar uma versao especifica no `package.json`;
- fazer um spike rapido antes da implementacao final;
- evitar depender de exemplos antigos do README sem testar no Vite atual.

---

## Por que `qrcode.react` nao resolve esse caso

`qrcode.react` continua bom para QR simples, rapido e sem customizacao profunda.

Mas ele nao cobre bem o que essa feature pede:

- editor com preview ao vivo orientado por presets;
- controle separado de dots e olhos;
- gradiente por parte do QR;
- logo com controles de tamanho e margem;
- exportacao rica com `getRawData`;
- persistencia de JSON de estilo;
- futura geracao server-side com o mesmo motor.

Conclusao pratica:

- manter `qrcode.react` para Pix e WhatsApp continua aceitavel;
- para QR editavel de links publicos do evento, o motor correto e `qr-code-styling`.

---

## Escopo recomendado

## Escopo V1

O editor deve atender os links publicos derivados do evento:

- `gallery`
- `upload`
- `wall`
- `hub`
- `play`
- `find_me`

O payload do QR deve continuar vindo do backend, a partir de `EventPublicLinksService`.

Ou seja:

- o usuario edita visual;
- nao edita o dado encoded livremente dentro do editor;
- quando o slug ou `wall_code` mudar, o visual persiste e o conteudo do QR acompanha o link novo.

## Fora de escopo inicial

Nao recomendo incluir na primeira fase:

- QR de Pix do checkout;
- QR de conexao do WhatsApp;
- QR de automacoes internas;
- QR generico com texto livre;
- decoracoes SVG extremamente livres via `applyExtension` expostas ao usuario final.

O motivo e simples:

- Pix e QR de autenticacao tem semantica, validade e risco diferentes;
- o problema pedido esta concentrado no compartilhamento visual dos links do evento.

---

## Experiencia de produto para usuario leigo

## Principio central

O editor precisa parecer um configurador guiado, nao um painel tecnico.

O usuario leigo deve conseguir:

1. abrir clicando no QR;
2. trocar preset;
3. ajustar cor principal e fundo;
4. colocar logo;
5. escolher formato e baixar;
6. salvar sem entender QR em nivel tecnico.

O usuario avancado pode expandir o resto.

## Estrutura recomendada do modal

### Desktop

Modal central grande, em 2 colunas:

- esquerda fixa com preview grande e status de leitura;
- direita rolavel com abas curtas.

Layout recomendado:

- cabecalho:
  - titulo `Editar QR Code`
  - subtitulo com nome do link: `Galeria publica`, `Upload`, `Wall` etc.
- coluna esquerda:
  - preview principal ao vivo
  - badge de leitura
  - nome do preset
  - resumo do destino do QR
- coluna direita:
  - abas `Conteudo`, `Estilo`, `Logo`, `Exportacao`
  - toggle `Mostrar avancado`
  - aba `Avancado` aparece so quando o toggle estiver ativo
- rodape fixo:
  - `Cancelar`
  - `Restaurar padrao`
  - `Salvar`
  - `Baixar`

### Mobile

No mobile, o ideal e usar o padrao que o repositorio ja adota em outras telas:

- `Drawer` responsivo com `useIsMobile()`;
- altura total ou quase total;
- preview no topo;
- secoes em `Accordion`.

Layout recomendado:

- topo com voltar/fechar, titulo e `Salvar`;
- preview em card fixo no topo;
- secoes em accordion:
  - Conteudo
  - Estilo
  - Logo
  - Exportacao
  - Avancado
- acao principal fixa na parte inferior.

## Dois niveis de complexidade

### Basico

Deve ficar visivel por padrao:

- preset;
- cor principal;
- cor de fundo;
- formato do arquivo;
- tamanho de exportacao;
- usar logo do evento/organizacao;
- upload de logo;
- restaurar padrao.

### Avancado

Fica escondido atras de toggle:

- dots style;
- corners square style;
- corners dot style;
- gradiente;
- margem;
- `errorCorrectionLevel`;
- `shape`;
- `roundSize`;
- `typeNumber`;
- `mode`;
- transparencia;
- `saveAsBlob`.

## Abas recomendadas

### Conteudo

Para QR de link publico do evento, esta aba deve ser majoritariamente informativa:

- tipo do QR;
- URL atual encoded;
- estado do identificador publico;
- botao para copiar link;
- botao para abrir;
- opcionalmente atalho para tela de enderecos publicos.

Importante:

- o campo de conteudo nao deve ser editavel livremente na V1;
- o valor encoded precisa continuar sincronizado com `public_links.<key>.qr_value`.

### Estilo

Controles mais frequentes:

- preset;
- cor principal;
- cor de fundo;
- dots style;
- estilo dos olhos;
- gradiente simples;
- opcionalmente moldura visual do preview.

### Logo

Controles:

- sem logo;
- usar logo do evento;
- usar logo da organizacao;
- upload manual;
- tamanho da logo;
- margem da logo;
- esconder dots atras da logo.

### Exportacao

Controles:

- formato:
  - `svg`
  - `png`
  - `jpeg`
  - `webp`
- tamanho:
  - 512
  - 1024
  - 2048
- nome do arquivo;
- baixar agora;
- copiar configuracao para outro link do evento.

### Avancado

Controles tecnicos:

- `errorCorrectionLevel`;
- `shape`;
- `roundSize`;
- `typeNumber`;
- `mode`;
- fundo transparente;
- `saveAsBlob`;
- aviso de impacto na legibilidade.

## Presets recomendados

Os presets devem existir para acelerar a decisao, nao como gimmick visual.

Sugestao inicial:

- `Classico`
  - dots escuros, fundo branco, sem logo, margem segura
- `Premium`
  - usa branding do evento, logo pequena, olhos personalizados, `H`
- `Minimalista`
  - monocromatico, alto contraste, poucos enfeites
- `Escuro`
  - card e moldura escuros, mas mantendo area util do QR em alto contraste seguro

Observacao importante:

- o preset `Escuro` nao deve inverter o QR por padrao;
- o simbolo em si deve continuar com contraste robusto para leitura;
- o "escuro" pode vir da moldura e do preview, nao do miolo do simbolo.

## Badge de leitura

Recomendacao de UX:

- `Otima leitura`
- `Boa`
- `Arriscada`

Esse badge deve ser heuristico na V1.

Fatores que devem influenciar a nota:

- contraste entre modulos e fundo;
- quiet zone minima;
- uso de gradiente;
- transparencia;
- tamanho da logo;
- `errorCorrectionLevel`;
- forma decorativa mais agressiva.

Se o time quiser uma camada extra de seguranca depois, vale adicionar:

- tentativa automatica de decodificar o preview gerado antes de confirmar o save.

---

## Regras automaticas de seguranca

Estas regras sao obrigatorias para o produto:

1. Nunca permitir quiet zone abaixo de 4 modulos.
2. Quando houver logo:
   - subir para `H` automaticamente ou exigir confirmacao forte se o usuario tentar baixar;
   - limitar `imageSize` para no maximo `0.4` no modo basico;
   - bloquear acima de `0.5`.
3. Se o contraste entre dots e fundo cair abaixo do minimo interno:
   - exibir `Arriscada`;
   - desabilitar `Salvar` e `Baixar` em cenarios extremos.
4. Se o fundo for transparente:
   - avisar que o QR depende do fundo onde sera aplicado;
   - exportar `jpeg` apenas com fundo solido.
5. Se o usuario escolher gradiente muito suave:
   - manter badge em `Boa` ou `Arriscada`;
   - sugerir preset seguro.
6. Se a combinacao de `shape` + logo + gradiente reduzir demais a confiabilidade:
   - o editor volta para um preset seguro com confirmacao explicita.

Sugestao pratica de guardrails:

- `marginModules`: minimo `4`, padrao `4`;
- `errorCorrectionLevel`: padrao `Q`, com auto-upgrade para `H` quando houver logo;
- `imageSize`: padrao `0.22`, ideal ate `0.35`, hard limit `0.5`;
- `backgroundOptions.color`: usar branco por padrao;
- `dotsOptions.color`: usar tons escuros por padrao.

---

## Ownership de dominio recomendado

## Backend

O ownership deve ficar no modulo `Events`.

Justificativa:

- os QR editaveis da V1 sao derivados de links publicos do evento;
- a fonte de verdade do conteudo encoded ja esta em `EventPublicLinksService`;
- permissao e contexto sao de evento;
- slug, `upload_slug` e `wall_code` ja vivem dentro da governanca de `Events`.

Nao recomendo abrir um modulo backend novo para a V1.

## Frontend

No frontend, o melhor desenho e separar:

1. motor reutilizavel de editor/renderizacao;
2. adaptadores por dominio.

Sugestao:

```text
apps/web/src/modules/qr-code/
  components/
    QrCodeEditorDialog.tsx
    QrCodeEditorDrawer.tsx
    QrCodePreviewPane.tsx
    QrCodePresetStrip.tsx
    QrCodeReadabilityBadge.tsx
  hooks/
    useQrCodeEditor.ts
    useQrCodePreview.ts
    useQrCodeReadability.ts
  support/
    qrDefaults.ts
    qrPresets.ts
    qrGuardrails.ts
    qrOptionsBuilder.ts
    qrExport.ts
    qrTypes.ts

apps/web/src/modules/events/qr/
  EventPublicLinkQrTrigger.tsx
  EventPublicLinkQrEditor.tsx
  api.ts
```

Justificativa:

- a engine visual vira reutilizavel;
- o contrato e a persistencia continuam event-scoped;
- isso permite reuso futuro sem acoplar tudo a `EventDetailPage`.

---

## Modelo de dados recomendado

## Problema do modelo atual

`events.qr_code_path` e um campo singular.

Ele nao resolve:

- varios links por evento;
- configuracoes diferentes por link;
- persistencia do JSON de estilo;
- historico ou reabertura do editor no estado salvo.

## Modelo sugerido para V1

Criar uma tabela dedicada por link publico do evento.

Sugestao:

```text
event_public_link_qr_configs
```

Colunas recomendadas:

| Coluna | Tipo | Observacao |
|---|---|---|
| `id` | bigint | PK |
| `event_id` | foreignId | FK para `events` |
| `link_key` | string | `gallery`, `upload`, `wall`, `hub`, `play`, `find_me` |
| `preset_key` | string nullable | `classic`, `premium`, `minimal`, `dark` |
| `config_version` | string | ex.: `event-public-link-qr.v1` |
| `config_json` | jsonb | estilo e export defaults |
| `logo_asset_path` | string nullable | logo escolhida pelo usuario |
| `svg_path` | string nullable | asset renderizado |
| `png_path` | string nullable | asset renderizado |
| `last_rendered_at` | timestamp nullable | quando assets foram gerados |
| `created_by` | foreignId nullable | usuario que criou |
| `updated_by` | foreignId nullable | usuario que editou |
| `created_at` / `updated_at` | timestamps | padrao |

Indices:

- unique em `event_id + link_key`;
- indice por `event_id`;
- opcional indice parcial por `last_rendered_at`.

## JSON recomendado

O JSON salvo nao deve guardar o `data` final como fonte de verdade.

O `data` deve ser reconstruido no read model usando o link publico atual.

Exemplo:

```json
{
  "version": "event-public-link-qr.v1",
  "preset": "premium",
  "render": {
    "type": "svg",
    "width": 1024,
    "height": 1024,
    "margin_modules": 4,
    "background_mode": "solid"
  },
  "style": {
    "dots": {
      "type": "rounded",
      "color": "#0f172a",
      "gradient": null
    },
    "corners_square": {
      "type": "extra-rounded",
      "color": "#0f172a",
      "gradient": null
    },
    "corners_dot": {
      "type": "dot",
      "color": "#0f172a",
      "gradient": null
    },
    "background": {
      "color": "#ffffff",
      "gradient": null,
      "transparent": false
    }
  },
  "logo": {
    "mode": "event_logo",
    "asset_path": null,
    "image_size": 0.24,
    "margin_px": 8,
    "hide_background_dots": true,
    "save_as_blob": true
  },
  "advanced": {
    "error_correction_level": "H",
    "shape": "square",
    "round_size": true,
    "type_number": 0,
    "mode": "Byte"
  },
  "export_defaults": {
    "extension": "svg",
    "download_name_pattern": "evento-{event_id}-{link_key}"
  }
}
```

## Regra importante de persistencia

Salvar no banco:

- estilo;
- preset;
- logo escolhida;
- defaults de exportacao.

Nao salvar como fonte de verdade:

- URL encoded do link publico;
- output de preview temporario;
- estado do badge de leitura.

O badge pode ser recalculado sempre.

---

## Contrato de API recomendado

## Endpoints

Sugestao de rotas novas no modulo `Events`:

```text
GET    /api/v1/events/{event}/qr-codes
GET    /api/v1/events/{event}/qr-codes/{linkKey}
PUT    /api/v1/events/{event}/qr-codes/{linkKey}
POST   /api/v1/events/{event}/qr-codes/{linkKey}/render
POST   /api/v1/events/{event}/qr-codes/{linkKey}/reset
```

## Payload de leitura

O response precisa juntar:

- link publico atual;
- config salva;
- defaults derivados do branding;
- assets existentes;
- avaliacoes de seguranca.

Exemplo:

```json
{
  "link": {
    "key": "upload",
    "label": "Controle remoto / Upload",
    "url": "https://app.eventovivo.com/u/abc123",
    "qr_value": "https://app.eventovivo.com/u/abc123"
  },
  "config": { "...": "..." },
  "defaults": {
    "primary_color": "#0f172a",
    "secondary_color": "#334155",
    "logo_url": "https://cdn.example.com/logo.png"
  },
  "assets": {
    "svg_url": null,
    "png_url": null
  },
  "readability": {
    "status": "good",
    "warnings": []
  }
}
```

## Actions e classes sugeridas no backend

```text
apps/api/app/Modules/Events/
  Actions/
    GetEventPublicLinkQrConfigAction.php
    UpsertEventPublicLinkQrConfigAction.php
    ResetEventPublicLinkQrConfigAction.php
    RenderEventPublicLinkQrAssetsAction.php
  Data/
    EventPublicLinkQrConfigData.php
    EventPublicLinkQrAssetsData.php
    EventPublicLinkQrReadabilityData.php
  Http/
    Controllers/
      EventPublicLinkQrController.php
    Requests/
      UpsertEventPublicLinkQrConfigRequest.php
      RenderEventPublicLinkQrRequest.php
    Resources/
      EventPublicLinkQrResource.php
  Jobs/
    RenderEventPublicLinkQrAssetsJob.php
  Models/
    EventPublicLinkQrConfig.php
```

## Permissoes

Sugestao pratica:

- visualizar configuracao: mesmo gate de leitura do evento;
- salvar/editar: `events.update`;
- resetar/restaurar padrao: `events.update`.

Isso evita divergencia entre modulo `Events`, `Wall`, `Hub` e `Play` para um artefato que continua sendo do evento.

---

## Arquitetura de renderizacao recomendada

## V1: preview e download no browser

Fluxo:

1. tela abre;
2. frontend busca configuracao atual;
3. `qr-code-styling` instancia preview;
4. mudancas disparam `update(...)`;
5. usuario baixa usando `download(...)` ou `getRawData(...)`;
6. usuario salva JSON no Laravel.

Vantagens:

- menor complexidade;
- entrega rapida;
- sem dependencia inicial de `node-canvas`.

Limitacao:

- nao existe asset "oficial" renderizado no servidor imediatamente.

## V1.1 ou V2: assets gerados no servidor

Se o produto quiser:

- reaproveitar o mesmo SVG/PNG em varias telas;
- exibir exatamente o QR salvo sem rerender no admin;
- disponibilizar asset pronto para wall, hub e outras superficies;

entao vale adicionar renderizacao server-side.

## Como fazer server-side sem brigar com a stack

Como a lib documenta suporte Node com `jsdom` e `node-canvas`, a opcao com melhor paridade visual e:

- Laravel salva JSON;
- Job em fila dispara renderer Node;
- renderer gera SVG e PNG;
- Laravel persiste caminhos dos assets.

Recomendacao pragmatica:

- deixar isso para a segunda fase;
- nao tentar reproduzir o mesmo estilo no PHP com outra biblioteca;
- se o objetivo for "igual ao preview", o renderer server-side deve usar o mesmo motor.

Observacao importante sobre a stack atual:

- o `apps/web` ja usa `jsdom` em ambiente de teste;
- isso nao elimina a necessidade de instalar `jsdom` como dependencia de runtime do renderer Node, caso a fase de assets server-side seja implementada.

## Onde colocar o renderer Node

Tres opcoes:

1. `scripts/qr/`
2. um pequeno app/worker Node fora do `apps/web`
3. um servico sidecar no deploy

Para este monorepo, a opcao mais simples de iniciar e:

- `scripts/qr/render-event-public-link.mjs`

Se crescer:

- migrar para worker proprio.

## Risco conhecido do renderer server-side

`node-canvas` traz dependencia nativa.

Isso significa:

- setup mais chato em maquina local;
- cuidado em CI;
- cuidado em containers.

Por isso a recomendacao continua sendo:

- browser-first na V1;
- job de assets como fase seguinte.

---

## Fluxo recomendado na UI

## Gatilho de abertura

No `PublicLinkCard`, o bloco visual do QR deve virar um trigger.

Sugestao:

- clique no QR abre o editor;
- adicionar texto auxiliar `Clique para editar e baixar`;
- manter botoes `Copiar` e `Abrir` separados.

Melhoria de acessibilidade:

- transformar o container do QR em `button`;
- `aria-label` com o nome do link;
- foco retorna ao trigger quando o modal fecha.

## Estado do editor

Sugestao de state local:

```ts
type QrEditorTab = 'content' | 'style' | 'logo' | 'export' | 'advanced';
type QrReadabilityStatus = 'great' | 'good' | 'risky';

interface QrEditorDraft {
  linkKey: 'gallery' | 'upload' | 'wall' | 'hub' | 'play' | 'find_me';
  preset: 'classic' | 'premium' | 'minimal' | 'dark';
  style: { ... };
  logo: { ... };
  advanced: { ... };
  exportDefaults: { ... };
}
```

O fluxo de preview deve ser controlado por um hook dedicado.

Pseudo fluxo:

```ts
const qrRef = useRef<QRCodeStyling | null>(null);

useEffect(() => {
  if (!containerRef.current) return;

  if (!qrRef.current) {
    qrRef.current = new QRCodeStyling(buildQrOptions(draft, resolvedQrValue));
    qrRef.current.append(containerRef.current);
    return;
  }

  qrRef.current.update(buildQrOptions(draft, resolvedQrValue));
}, [draft, resolvedQrValue]);
```

## Hooks recomendados

- `useQrCodeEditor()`
- `useQrCodePreview()`
- `useQrCodeReadability()`
- `useEventPublicLinkQrConfig(linkKey)`
- `useSaveEventPublicLinkQrConfig(linkKey)`

## Presets e defaults

Os presets devem ser codigo, nao ifs soltos dentro do componente.

Arquivos dedicados:

- `qrDefaults.ts`
- `qrPresets.ts`
- `qrGuardrails.ts`
- `qrOptionsBuilder.ts`

Beneficio:

- menos regressao;
- testes unitarios claros;
- reuso no preview e no save.

---

## Integracao com branding do evento

O produto ja resolve branding efetivo do evento na `EventDetailPage`.

Isso cria uma vantagem clara:

- o editor pode nascer ja contextualizado com cor primaria, secundaria e logo efetiva;
- o preset `Premium` pode ser derivado automaticamente do branding atual;
- o usuario leigo nao precisa comecar do zero.

Sugestao de defaults:

- se existir logo efetiva, oferecer `Usar logo do evento` como opcao principal;
- se existir `primary_color`, usar como base do preset `Premium`;
- se nao existir branding, cair no preset `Classico`.

Isso reduz friccao sem inventar um sistema novo.

---

## Reaproveitamento em outras subpaginas

O editor deve ser universal no motor, mas acionado pelos modulos consumidores.

Sugestao de reuso:

- pagina de evento `events/:id`
- manager do wall
- manager do hub
- pagina de play
- futuras telas de compartilhamento

Padrao recomendado:

- cada modulo consumidor usa um trigger pequeno;
- todos chamam o mesmo `EventPublicLinkQrEditor`.

Exemplo:

- no wall manager, `Editar QR do telao`
- no hub manager, `Editar QR do hub`
- no play manager, `Editar QR do play`

O que muda e so o `linkKey`.

---

## Recomendacao de rollout

## Fase 0 - Spike tecnico curto

Objetivo:

- instalar `qr-code-styling` em `apps/web`;
- validar integracao com Vite;
- testar logo remota com `crossOrigin: "anonymous"`;
- validar `download` e `getRawData` no browser;
- confirmar se a versao fixada funciona sem workarounds no setup atual.

Saida esperada:

- prototipo local dentro de `EventDetailPage`;
- escolha da versao definitiva da lib.

## Fase 1 - Editor local com download

Entregas:

- modulo frontend do editor;
- clique no QR abre editor;
- preview ao vivo;
- presets;
- modo basico/avancado;
- download local;
- sem persistencia ainda.

Beneficio:

- valida UX rapidamente;
- reduz risco antes de mexer no backend.

## Fase 2 - Persistencia no backend

Entregas:

- migration;
- model;
- endpoints de leitura e save;
- carregamento da config salva;
- `Restaurar padrao`.

Critico:

- manter encoded data vindo de `EventPublicLinksService`.

## Fase 3 - Substituir preview estatico nos cards

Entregas:

- cards passam a renderizar o visual salvo;
- fallback para default se nao existir configuracao.

## Fase 4 - Assets server-side

Entregas:

- job de render;
- SVG/PNG oficiais;
- reuso desses assets em superficies de leitura.

## Fase 5 - Melhorias de produto

Entregas possiveis:

- `Duplicar estilo para outro link`;
- validar leitura por tentativa de decode;
- biblioteca de presets por organizacao;
- template por tipo de evento;
- historico de versoes.

---

## Testes recomendados

## Frontend

Unitarios e de componente:

- abrir modal ao clicar no QR;
- aplicar preset muda preview;
- toggle avancado mostra e esconde opcoes;
- logo sobe `errorCorrectionLevel` quando necessario;
- `Restaurar padrao` volta ao draft inicial;
- exportacao chama formato correto;
- mobile usa `Drawer`;
- desktop usa `Dialog`;
- badge muda para `Arriscada` nos cenarios limite.

Arquivos provaveis:

- `QrCodeEditorDialog.test.tsx`
- `useQrCodeReadability.test.ts`
- `qrGuardrails.test.ts`
- `EventPublicLinkQrEditor.test.tsx`

## Backend

Feature tests:

- ler config inexistente retorna default derivado do branding;
- salvar config valida persiste por `event_id + link_key`;
- resetar remove configuracao custom;
- trocar slug do evento nao perde configuracao;
- response sempre devolve `qr_value` atualizado.

Unit tests:

- normalizacao do payload salvo;
- bloqueio de `imageSize > 0.5`;
- coercao minima de quiet zone;
- montagem da resposta a partir de `EventPublicLinksService`.

Se entrar renderer server-side:

- job gera SVG e PNG;
- renderer respeita `saveAsBlob` quando houver logo;
- falha do renderer nao corrompe configuracao salva.

---

## Riscos e decisoes que precisam ficar claras

## 1. Universal nao significa configuracao unica

A melhor leitura de "editor universal" aqui e:

- um editor unico;
- para varios QR codes do evento;
- com configuracao por link.

Nao recomendo uma unica configuracao obrigatoria para todos os links logo na V1.

Motivo:

- upload pode pedir destaque e CTA diferente;
- wall pode pedir leitura a distancia;
- hub pode aceitar logo menor e acabamento mais refinado.

## 2. Conteudo do QR nao deve ser livre na V1

Se o editor permitir alterar a URL encoded sem governanca, ele deixa de representar o link publico real do evento.

Isso quebra:

- previsibilidade do produto;
- consistencia com slugs;
- expectativas de suporte.

## 3. Assets server-side podem esperar

O maior risco tecnico e o renderer Node com `node-canvas`.

Nao vale travar a V1 por isso.

## 4. `qrcode.react` ainda tera lugar

Nao ha motivo para migrar Pix e QR temporario do WhatsApp para esse editor agora.

## 5. `qr_code_path` legado deve ser tratado como obsoleto

Esse campo pode continuar existindo por compatibilidade, mas nao deve ser a base da feature nova.

---

## Recomendacao final

Para o Evento Vivo, a melhor arquitetura e:

- backend em `Events` como dono da configuracao;
- frontend com um modulo reutilizavel de editor;
- `qr-code-styling` como motor oficial do editor;
- preview e download no browser na V1;
- JSON salvo por `event_id + link_key`;
- assets server-side como evolucao posterior.

Para o usuario leigo, a melhor experiencia e:

- clicar no QR ja existente;
- abrir editor em modal grande;
- escolher entre poucos presets;
- ver preview ao vivo;
- ajustar cor, fundo, logo e formato;
- salvar e baixar sem entrar em termos tecnicos.

Para o time tecnico, o ponto mais importante e:

- manter o payload do QR derivado do link publico real;
- persistir o visual, nao o conteudo;
- proteger leitura com guardrails automaticos desde o primeiro release.

---

## Proximos passos recomendados

1. Fazer um spike de 1 dia no `apps/web` com `qr-code-styling`.
2. Validar a melhor versao da lib no Vite atual.
3. Implementar a Fase 1 no `EventDetailPage`, inicialmente para `upload` e `gallery`.
4. Fechar migration e endpoints da Fase 2.
5. Trocar o `PublicLinkCard` estatico pelo trigger/editor persistido.

---

## Referencias

- QR Code Styling demo oficial: https://qr-code-styling.com/
- QR Code Styling repository/README: https://github.com/kozakdenys/qr-code-styling
- QR Code Styling no npm: https://www.npmjs.com/package/qr-code-styling
- DENSO WAVE, quiet zone / code area: https://www.qrcode.com/en/howto/code.html
- DENSO WAVE, error correction levels: https://www.qrcode.com/en/about/error_correction.html
