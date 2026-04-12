# Gallery builder, presets and AI analysis - 2026-04-11

## Objetivo

Validar, com base no codigo real do monorepo `eventovivo`, qual e o estado atual da galeria de midias, quais endpoints e contratos ja existem, o que ja esta pronto no git para acelerar um construtor de galeria e qual arquitetura faz mais sentido para entregar:

- galeria publica de fotos e videos mais forte;
- presets prontos para usuario leigo;
- personalizacao facil por tema, cores e blocos;
- assistente por prompt de IA sem deixar o usuario quebrar o layout;
- ownership modular coerente com a arquitetura atual.

---

## Veredito executivo

Sim, faz sentido criar um construtor de galeria com presets e IA no Evento Vivo.

Mas o melhor caminho hoje nao e comecar por um editor externo generico.

O caminho de menor risco e maior aderencia ao monorepo atual e:

1. manter a feature dentro do modulo `Gallery`;
2. espelhar o padrao ja consolidado em `Hub` no que interessa:
   - schema-first
   - presets por organizacao
   - tokens de tema
   - payload publico estruturado
   - preview no frontend
   - validacao profunda no backend
3. **nao** copiar literalmente um `builder_config` unico e gigante:
   - `theme_tokens`
   - `page_schema`
   - `media_behavior`
   - um envelope agregado pode existir no payload, mas nao deve ser a unica fonte de verdade
4. trocar a entrada do produto de "lista de presets" para uma matriz de modelos:
   - `event_type_family`
   - `style_skin`
   - `behavior_profile`
   - o usuario entra por combinacoes humanas como "casamento romantico" ou "corporativo clean", e o sistema deriva layout, paleta, hero, CTA e politica de video
5. incluir seguranca de produto desde o dia 1:
   - `draft_version`
   - `published_version`
   - autosave
   - preview link compartilhavel
   - restore previous version
6. tornar mobile-first um contrato explicito da V1:
   - metas de Web Vitals no percentil 75
   - segmentacao mobile e desktop
   - prioridade visual de carregamento
   - contrato de imagem responsiva
7. usar libs externas para renderizacao da galeria, nao para dominar a arquitetura inteira:
   - `react-photo-album` para layouts
   - `PhotoSwipe` para lightbox de foto
   - player/modal dedicado para video
   - virtualizacao local ou `@tanstack/react-virtual` / `masonic` quando o volume exigir
8. usar IA guardrailed, gerando propostas aplicaveis e JSON validado por schema, nunca JSX/CSS livre;
9. deixar `Puck` como melhor candidato para uma fase 2 ou 3, caso o produto realmente precise de drag-and-drop visual mais livre.

Em uma frase:

**o Eventovivo ja tem quase toda a base arquitetural para um builder de galeria, mas essa base esta espalhada entre `Gallery`, `Hub`, `Wall`, `Journey`, branding e `MediaIntelligence`; o trabalho certo agora e consolidar isso dentro de `Gallery`, em contratos menores e versionados, e nao trocar de stack.**

---

## Resposta curta

### A galeria atual esta pronta?

Parcialmente.

Ela esta pronta como:

- pipeline de ingestao e processamento de midia;
- catalogo administrativo;
- publicacao controlada;
- endpoint publico paginado;
- base de imagens e videos com metadados e variantes;
- CTA de busca facial publica.

Ela **nao** esta pronta como:

- produto de "builder de galeria";
- hot site editorial da galeria;
- sistema de presets;
- sistema de tema e schema de cores;
- experiencia rica para video;
- fluxo de IA para montar layout.

### Existe algo pronto no git para facilitar?

Sim, mas nao do jeito que a pergunta sugere.

Nao existe hoje no repositorio:

- `Puck`
- `Easyblocks`
- `GrapesJS`
- `react-photo-album`
- `PhotoSwipe`
- `react-photoswipe-gallery`
- `masonic`
- `@tanstack/react-virtual`
- branch paralela de `gallery builder`

Existe, sim, muita coisa pronta **dentro do proprio produto** que reduz o custo:

- `Hub` ja e um builder por evento com presets, blocos, tokens e preview;
- `Wall` ja opera com registry de layouts, capacidades e presets;
- `Journey` ja prova que o frontend suporta superficies de builder/canvas;
- `MediaIntelligence` ja tem infraestrutura madura de prompt, preset, schema JSON, laboratorio e historico;
- o frontend ja tem precedente local de virtualizacao em `MediaVirtualFeed`;
- o backend ja entrega width, height, orientation, preview e variantes de foto/video.

---

## Validacao real da stack atual

## Backend real do repo em 2026-04-11

Leitura de `apps/api/composer.json` confirma:

- PHP `>=8.3 <8.4`
- Laravel `^13.0`
- Horizon
- Pennant
- Pulse
- Reverb
- Sanctum
- Telescope
- Spatie Data
- Spatie MediaLibrary
- Spatie Permission
- Intervention Image
- AWS SDK

Observacao importante:

- o `AGENTS.md` fala em Laravel 12;
- o codigo real do repo hoje esta em Laravel 13.

Essa diferenca precisa ser tratada como verdade de implementacao.

## Frontend real do repo em 2026-04-11

Leitura de `apps/web/package.json` confirma:

- React `18.3.1`
- TypeScript `5.8.3`
- Vite `5.4.19`
- TailwindCSS `3.4.17`
- TanStack Query `5.83.0`
- shadcn/ui sobre Radix
- Framer Motion
- `@xyflow/react`
- `react-resizable-panels`

Tambem confirma o que **nao** existe hoje:

- nenhum page builder externo instalado;
- nenhuma lib de layout de galeria instalada;
- nenhuma lib de lightbox instalada;
- nenhuma lib de virtualizacao dedicada instalada.

## Validacao executada nesta analise

### Backend

Comando executado em `2026-04-11`:

```bash
cd apps/api
php artisan test tests/Feature/Gallery/PublicGalleryAvailabilityTest.php tests/Feature/Gallery/GalleryAdminWorkflowTest.php tests/Feature/Hub
```

Resultado:

- `23` testes passaram
- `203` assertions passaram

Leitura pratica:

- a galeria publica/admin esta verde;
- o `Hub` builder atual esta verde;
- a base mais importante para reaproveitamento esta funcional.

### Frontend

Comando executado em `2026-04-11`:

```bash
cd apps/web
npx.cmd vitest run src/modules/gallery/PublicGalleryPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts src/modules/hub/PublicHubPage.test.tsx src/modules/ai/MediaAutomaticRepliesPage.test.tsx
```

Resultado:

- `4` arquivos passaram
- `21` testes passaram

Leitura pratica:

- a pagina publica atual da galeria esta coberta;
- o precedente de builder do `Hub` esta coberto;
- a caracterizacao do `Journey` confirma precedentes de editor por evento;
- a superficie administrativa de IA esta madura e ativa.

### Backend adicional

Comando executado em `2026-04-11`:

```bash
cd apps/api
php artisan test tests/Feature/MediaProcessing/EventMediaListTest.php
```

Resultado:

- `7` testes passaram
- `110` assertions passaram

Leitura pratica:

- o contrato operacional de midia ja entrega `preview_url`, `original_url`, metadados enriquecidos e estado efetivo;
- o backend ja esta preparado para uma experiencia publica mais rica do que a galeria atual consome;
- a parte mais fraca hoje continua sendo renderer/experience, nao pipeline.

### Frontend adicional

Comando executado em `2026-04-11`:

```bash
cd apps/web
npx.cmd vitest run src/modules/qr-code/support/qrReadability.test.ts src/modules/wall/player/runtime-profile.test.ts src/modules/media/MediaPage.test.tsx
```

Resultado:

- `3` arquivos passaram
- `6` testes passaram

Leitura pratica:

- ja existe precedente local para guardrail de contraste;
- ja existe leitura de `prefers-reduced-motion`;
- a stack web atual ja tem exemplos validos para superficies de midia mais densas sem mudar de arquitetura.

### Revalidacao complementar em `2026-04-12`

Para validar as melhorias propostas depois desta analise, a bateria abaixo foi rerodada:

#### Backend

```bash
cd apps/api
php artisan test tests/Feature/Gallery/PublicGalleryAvailabilityTest.php tests/Feature/Gallery/GalleryAdminWorkflowTest.php
php artisan test tests/Feature/MediaProcessing/EventMediaListTest.php
```

Resultado:

- `16` testes passaram
- `191` assertions passaram

Leitura pratica:

- `PublicGalleryAvailabilityTest` confirma que a galeria publica ja entrega `thumbnail_url`, `preview_url`, `width`, `height` e `orientation`;
- `EventMediaListTest` confirma que o backend ja sabe preferir variantes otimizadas, mas ainda nao expoe um mapa de fontes responsivas para `srcset`/`sizes`;
- isso valida a melhoria de criar um contrato novo de `responsive_sources`, em vez de assumir que ele ja existe.

#### Frontend

```bash
cd apps/web
npx.cmd vitest run src/modules/gallery/PublicGalleryPage.test.tsx src/modules/media/MediaPage.test.tsx src/modules/moderation/components/ModerationMediaSurface.test.tsx src/modules/qr-code/support/qrReadability.test.ts src/modules/wall/player/runtime-profile.test.ts
```

Resultado:

- `5` arquivos passaram
- `13` testes passaram

Leitura pratica:

- `PublicGalleryPage` continua presa em `<img src={thumbnail_url} loading="lazy">`, entao a melhoria de priorizacao visual e imagem responsiva e real;
- `ModerationMediaSurface` ja prova precedente local para `sizes`, escolha contextual de asset e separacao forte entre imagem e video;
- `qrReadability` e `runtime-profile` confirmam que contraste e `prefers-reduced-motion` podem e devem virar contrato do renderer, nao so nota de UX.

---

## Estado atual da galeria

## 1. Endpoints existentes hoje

### Modulo `Gallery`

Rotas atuais:

| Metodo | Rota | Papel atual |
|---|---|---|
| GET | `/api/v1/gallery` | catalogo admin da galeria com filtros |
| GET | `/api/v1/events/{event}/gallery` | listagem admin da galeria por evento |
| POST | `/api/v1/events/{event}/gallery/{media}/publish` | publicar item aprovado |
| POST | `/api/v1/events/{event}/gallery/{media}/feature` | destacar item |
| DELETE | `/api/v1/events/{event}/gallery/{media}` | esconder/remover da galeria |
| GET | `/api/v1/public/events/{event:slug}/gallery` | feed publico paginado |

### Modulo `MediaProcessing`

Rotas diretamente relevantes para a galeria:

| Metodo | Rota | Papel atual |
|---|---|---|
| GET | `/api/v1/media` | catalogo geral de midia |
| GET | `/api/v1/media/feed` | feed operacional de moderacao |
| GET | `/api/v1/events/{event}/media` | midias do evento |
| GET | `/api/v1/media/{eventMedia}` | detalhe completo da midia |
| GET | `/api/v1/media/{eventMedia}/duplicates` | cluster de duplicatas |
| GET | `/api/v1/media/{eventMedia}/ia-debug` | debug de IA/VLM |
| POST | `/api/v1/media/{eventMedia}/approve` | aprovar midia |
| POST | `/api/v1/media/{eventMedia}/reject` | rejeitar midia |
| POST | `/api/v1/media/{eventMedia}/reprocess/{stage}` | reprocessar etapa |
| PATCH | `/api/v1/media/{eventMedia}/favorite` | destacar |
| PATCH | `/api/v1/media/{eventMedia}/pin` | fixar ordem |
| DELETE | `/api/v1/media/{eventMedia}` | deletar midia |
| GET | `/api/v1/events/{event}/media/pipeline-metrics` | metricas do pipeline |

## 2. O que a galeria publica faz hoje

O endpoint publico atual:

- resolve o evento por `slug`;
- bloqueia acesso se o modulo `live` estiver desabilitado;
- bloqueia acesso se o evento nao estiver ativo;
- registra analytics `gallery.page_view`;
- retorna somente midia `published()` e `approved()`;
- pagina em lotes de `30`;
- inclui metadados de busca facial publica.

Payload atual do contrato publico:

- `data: ApiEventMediaItem[]`
- `meta.page`
- `meta.per_page`
- `meta.total`
- `meta.last_page`
- `meta.request_id`
- `meta.face_search.public_search_enabled`
- `meta.face_search.find_me_url`

Ponto importante:

- o contrato publico atual **nao** entrega:
  - dados do evento para hero
  - `builder_config`
  - tokens de tema
  - blocos publicos
  - presets aplicados
  - branding consolidado da experiencia

Ou seja:

- hoje o endpoint entrega midia;
- ele ainda nao entrega uma experiencia de galeria configuravel.

## 3. O que o frontend publico faz hoje

`PublicGalleryPage.tsx` hoje:

- busca `getPublicGallery(slug)`;
- mostra loading, erro e empty state;
- exibe CTA de busca facial se habilitado;
- renderiza um masonry simples com CSS columns;
- usa `thumbnail_url`;
- trata a galeria como grade de thumbs.

Limites reais do frontend atual:

- nao existe editor;
- nao existe preset;
- nao existe lightbox;
- nao existe zoom;
- nao existe playback real de video;
- nao existe hero por evento;
- nao existe personalizacao por tema;
- nao existe banner intercalado;
- nao existe bloco institucional;
- nao existe CTA configuravel;
- nao existe diferenciacao de layout por tipo de evento.

## 4. O que a stack atual ja faz bem para foto e video

O backend ja entrega base tecnica importante:

- `thumbnail_url`
- `preview_url`
- `original_url`
- `width`
- `height`
- `orientation`
- `media_type`
- poster e preview de video
- variantes tecnicas geradas pelo pipeline

Os testes confirmam:

- imagem publica usa thumb + preview de galeria;
- video publico usa poster + preview de wall/video;
- metadados de dimensao e orientacao ja existem.

Leitura pratica:

- o backend esta mais pronto do que a UI publica atual sugere;
- o maior gap hoje esta no renderer/experience, nao no pipeline de midia.

## 5. Onde a galeria ainda e fraca

### UX publica

- grade simples demais;
- visual ainda "feed", nao "album";
- nenhum sistema de tema;
- nenhum tratamento forte de video;
- nenhuma narrativa editorial.

### Produto

- sem presets por organizacao;
- sem biblioteca de modelos;
- sem builder por evento;
- sem preview mobile/desktop;
- sem composicao de blocos;
- sem variacao de layout por contexto.

### Contrato

- API publica orientada a feed, nao a experience config;
- falta payload tipado para blocos;
- falta schema versionado da pagina publica.

---

## O que ja existe no git e reduz muito o custo

## 1. `Hub` ja e a base mais forte para o builder

Este e o ponto mais importante da analise.

O `Hub` ja resolve no proprio produto quase tudo que o builder de galeria vai precisar:

- `builder_config` por evento;
- presets por organizacao;
- layouts e temas com `layout_key` e `theme_key`;
- `theme_tokens`;
- `block_order`;
- blocos tipados e validados;
- preview publico;
- editor administrativo;
- uploads auxiliares;
- tracking publico;
- insights;
- validacao profunda no Laravel;
- normalizacao de defaults no backend.

O padrao ja esta pronto em:

- `EventHubSetting`
- `HubPreset`
- `HubBuilderPresetRegistry`
- `HubPayloadFactory`
- `HubPage`
- `HubRenderer`
- requests com validacao profunda de `builder_config`

Conclusao pratica:

- o builder de galeria deve **copiar o padrao do Hub** em registry, defaults, validacao e preview;
- nao precisa inventar arquitetura nova;
- nao precisa nascer como modulo separado de builder generico;
- mas nao vale copiar cegamente um `builder_config` unico: para `Gallery`, a separacao entre tema, pagina e comportamento de midia deixa o produto mais seguro para IA e rollback.

## 2. `Wall` ja prova o padrao de registry de layout + capacidades

O `Wall` ja trabalha com:

- registry de layouts;
- capacidades por layout;
- degradacao por capacidade;
- presets;
- `theme_config`;
- escolha previsivel de comportamento.

Isso e valioso para a galeria porque permite tratar layout como catalogo controlado, e nao como liberdade arbitraria.

Exemplo de reaproveitamento conceitual:

- `masonry` suporta video inline?
- `justified` suporta banner intercalado?
- `editorial` suporta hero e quote?
- `live-stream` suporta virtualizacao?

Essa matriz de capacidades ja e uma linguagem conhecida no repo.

## 3. `Journey` ja prova que o frontend suporta editor mais rico

O `Journey` usa:

- `@xyflow/react`
- `react-resizable-panels`
- canvas/surface dedicada
- inspector lateral
- rails e estados compostos

Isso significa:

- o painel admin atual ja comporta uma pagina de builder mais densa;
- nao precisamos trocar frontend para suportar editor estruturado.

## 4. Branding herdado ja existe

`apps/web/src/modules/events/branding.ts` ja resolve:

- heranca entre evento e organizacao;
- `logo`
- `cover_image`
- `primary_color`
- `secondary_color`

Isso e exatamente o que o builder de galeria precisa para:

- gerar tema default;
- sugerir paleta inicial;
- evitar pedir ao usuario leigo para configurar tudo do zero.

## 5. A infraestrutura de IA ja e muito melhor do que um plugin externo simples

`MediaIntelligence` ja tem:

- presets de prompt;
- categorias;
- laboratorio de teste;
- historico de execucao;
- `response_schema_version`;
- `response_format` com `json_schema`;
- payload OpenAI-compatible;
- validacao de provider/modelo;
- trilha administrativa real.

Isso e decisivo.

Em vez de depender de uma IA acoplada a um editor externo, o Eventovivo ja pode seguir seu padrao atual:

- prompt versionado;
- schema versionado;
- output estritamente estruturado;
- historico e replay;
- validacao no backend;
- UI administrativa para catalogo de prompt.

## 6. Ja existe precedente local de virtualizacao

`MediaVirtualFeed.tsx` ja prova no frontend:

- uso de `ResizeObserver`;
- janela visivel;
- overscan;
- padding virtual;
- feed grande com custo controlado.

Isso significa:

- a galeria publica pode nascer simples;
- se o volume crescer, ja existe precedente local para virtualizacao;
- a equipe nao precisa adotar uma dependencia nova imediatamente.

## 7. O que objetivamente nao existe hoje

Nao foi encontrado no repo:

- dependencia instalada de `Puck`
- dependencia instalada de `Easyblocks`
- dependencia instalada de `GrapesJS`
- dependencia instalada de `react-photo-album`
- dependencia instalada de `PhotoSwipe`
- branch dedicada de builder da galeria
- modulo `GalleryPreset`
- `builder_config` da galeria
- `event_gallery_settings`
- `gallery_presets`

Tambem nao apareceu historico de branch local ou remota fora de `main`.

---

## Analise das opcoes externas

## 1. `Puck`

### O que pesa a favor

Segundo a documentacao oficial e o repo oficial:

- e um editor visual open source para React;
- e MIT;
- usa seus proprios componentes React;
- trabalha com `slot` para areas aninhadas;
- tem plugin API;
- tem trilha oficial de plugin `ai`.

Isso combina muito bem com:

- blocos React do Eventovivo;
- nested areas;
- presets estruturados;
- assistente IA dentro de rails/plugins;
- futuro drag-and-drop mais livre.

### O que pesa contra

- hoje ele nao existe no repo;
- introduz mais uma camada conceitual forte;
- o produto ja tem uma arquitetura interna de `builder_config` no `Hub`;
- a trilha oficial de IA do Puck esta ligada ao `Puck Cloud` e creditos/planos pagos, entao nao faz sentido ignorar a infra de IA que o Eventovivo ja tem.

### Veredito

**Melhor candidato externo para uma fase futura.**

Mas nao e o melhor primeiro passo.

## 2. `Easyblocks`

### O que pesa a favor

A apresentacao oficial bate muito com o caso de uso:

- drag-and-drop;
- design tokens;
- templates;
- componentes fortemente restritos;
- experiencia mais simples para usuario final;
- postura declarada de evitar editor "HTML/CSS livre".

Para usuario leigo isso e muito forte.

### O que pesa contra

- ecossistema publico menor;
- menor aderencia imediata aos precedentes internos do repo;
- o proprio site comercial destaca licenciamento custom quando AGPL3.0 for restritivo.

### Veredito

**Boa alternativa se a prioridade maxima for guardrail para leigo e white-label forte desde o dia 1.**

Ainda assim, no contexto do Eventovivo, eu deixaria como plano B.

## 3. `GrapesJS`

### O que pesa a favor

- ecossistema grande;
- framework maduro;
- muito recurso de builder;
- presets e plugins.

### O que pesa contra

- a propria doc publica descreve GrapesJS como framework de builder para estruturas `HTML-like`;
- a trilha mais forte de React renderer hoje aparece muito associada a `Studio SDK` / linha comercial;
- o modelo mental da ferramenta e menos aderente ao padrao atual do Eventovivo, que ja e schema-first + componentes React + payloads tipados.

### Veredito

**Plano C.**

So faria sentido se o objetivo mudasse para um page builder muito mais livre e menos controlado.

## 4. Renderer da galeria

### `react-photo-album`

Pesa a favor:

- rows
- columns
- masonry
- imagens responsivas
- bom fit com React 18
- mais aderente ao problema real da galeria do que CSS columns puro

Veredito:

- **melhor base para o renderer de galeria**.

### `PhotoSwipe`

Pesa a favor:

- lightbox madura;
- suporte responsivo;
- documentacao oficial para React;
- aceita import dinamico;
- exige width/height, que o backend ja entrega.

Veredito:

- **melhor base para abrir imagem/video com UX forte sem reinventar lightbox**.

### `react-photoswipe-gallery`

Pesa a favor:

- wrapper React pratico em cima do PhotoSwipe.

Veredito:

- opcional;
- pode acelerar implementacao se a equipe preferir wrapper pronto.

## 5. Virtualizacao

### `@tanstack/react-virtual`

Pesa a favor:

- headless;
- muito controle sobre markup;
- encaixa na filosofia atual do frontend.

Veredito:

- **primeira escolha se a equipe quiser dependencia dedicada de virtualizacao**.

### `masonic`

Pesa a favor:

- masonry virtualizada pronta;
- foco explicito em volumes grandes;
- muito boa para grade com alturas variaveis.

Pesa contra:

- adiciona mais opiniao que `react-virtual`.

Veredito:

- excelente para uma variante `live-stream` ou `massive-gallery`;
- nao precisa entrar na primeira sprint.

---

## Recomendacao pratica para o Eventovivo

## Escolha recomendada

### Fase 1 recomendada

**Expandir o modulo `Gallery` seguindo o padrao do `Hub`, com renderer de galeria especializado e IA schema-first.**

Stack recomendada para isso:

- modulo backend `Gallery` ampliado
- modulo frontend `gallery` ampliado
- `react-photo-album` para layouts
- `PhotoSwipe` para lightbox
- virtualizacao local reaproveitada ou `@tanstack/react-virtual` depois
- IA usando schema JSON via padrao ja dominado por `MediaIntelligence`

### Fase 2 opcional

Se o produto provar que precisa de editor visual mais solto:

- introduzir `Puck` como camada de authoring;
- manter o mesmo schema e os mesmos blocos do dominio;
- manter o backend validando `builder_config`.

### O que eu nao recomendo agora

- editor HTML/CSS livre;
- `custom CSS` para usuario leigo;
- IA gerando JSX;
- IA gerando HTML arbitrario;
- trocar o ownership da feature para um modulo generico de builder;
- introduzir `GrapesJS` ou `Puck` antes de consolidar o schema e os blocos internos.

---

## Arquitetura recomendada

## 1. Ownership modular

### Backend

Recomendacao:

- **manter dentro de `apps/api/app/Modules/Gallery`**

Motivo:

- a feature continua sendo galeria;
- o builder e um configurador da experiencia publica da galeria;
- o `Hub` ja provou que o builder pode morar dentro do proprio modulo de dominio;
- isso evita um modulo transversal cedo demais.

### Frontend

Recomendacao:

- **manter dentro de `apps/web/src/modules/gallery`**

Com uma nova pagina principal, por exemplo:

- `GalleryBuilderPage.tsx`

## 2. Estrutura backend sugerida

```text
apps/api/app/Modules/Gallery/
  Actions/
    CreateGalleryPresetAction.php
    UpdateEventGallerySettingsAction.php
    BuildPublicGalleryPayloadAction.php
    RunGalleryBuilderPromptAction.php
  Http/
    Controllers/
      GalleryBuilderController.php
      GalleryPresetController.php
      PublicGalleryController.php
    Requests/
      UpdateEventGallerySettingsRequest.php
      StoreGalleryPresetRequest.php
      RunGalleryBuilderPromptRequest.php
    Resources/
      EventGallerySettingsResource.php
      GalleryPresetResource.php
      PublicGalleryExperienceResource.php
  Models/
    EventGallerySetting.php
    GalleryPreset.php
    GalleryBuilderPromptRun.php
  Queries/
    ListGalleryPresetsQuery.php
  Support/
    GalleryBuilderPresetRegistry.php
    GalleryPayloadFactory.php
    GalleryThemeTokenResolver.php
    GalleryBuilderSchemaRegistry.php
  README.md
  routes/
    api.php
```

## 3. Estrutura frontend sugerida

```text
apps/web/src/modules/gallery/
  GalleryPage.tsx
  GalleryBuilderPage.tsx
  PublicGalleryPage.tsx
  api.ts
  gallery-builder.ts
  components/
    GalleryRenderer.tsx
    GalleryModeSwitch.tsx
    GalleryQuickSetupRail.tsx
    GalleryPresetRail.tsx
    GalleryVibeShortcuts.tsx
    GalleryThemePanel.tsx
    GalleryBlocksPanel.tsx
    GalleryContextInspector.tsx
    GalleryAiVariationsPanel.tsx
    GalleryPreviewFrame.tsx
    GalleryPreviewToolbar.tsx
    GalleryRevisionPanel.tsx
    blocks/
      GalleryHero.tsx
      GalleryStreamBlock.tsx
      GalleryBannerBlock.tsx
      GalleryQuoteBlock.tsx
      GalleryInfoBlock.tsx
      GalleryFooterBlock.tsx
```

## 4. Entidades e versionamento recomendados

## 4.1 Principio estrutural

Ao contrario do `Hub`, eu nao recomendo que `Gallery` nasca com um unico `builder_config` gigante como contrato primario.

O desenho mais seguro para produto, IA e rollback e separar a configuracao em tres camadas:

- `theme_tokens`: cor, tipografia, radius, borda, sombra, contraste e motion;
- `page_schema`: ordem de blocos, hero, banner, CTA, quote, footer e regras de presenca;
- `media_behavior`: layout da grade, politica de video, densidade, lazy load, paginacao, lightbox e interstitials.

Pode existir um envelope agregado chamado `experience` no payload publico, ou ate um `builder_config` derivado no frontend para facilitar preview. Mas a fonte de verdade no backend deve privilegiar essas tres camadas separadas.

### `event_gallery_settings`

Responsabilidade:

- ponteiro atual da experiencia de galeria por evento.

Campos sugeridos:

- `id`
- `event_id`
- `is_enabled`
- `theme_key`
- `layout_key`
- `theme_tokens_json`
- `page_schema_json`
- `media_behavior_json`
- `current_draft_revision_id`
- `current_published_revision_id`
- `draft_version`
- `published_version`
- `preview_share_token`
- `preview_share_expires_at`
- `last_autosaved_at`
- `updated_by`
- `created_at`
- `updated_at`

Observacao importante:

- `hero_image_path`, `intro_title`, `intro_subtitle` e `show_face_search_cta` devem morar em `page_schema_json`, nao em colunas soltas sem necessidade de query;
- `gallery_density`, `lightbox` e politica de video devem morar em `media_behavior_json`;
- `theme_key` e `layout_key` podem ficar denormalizados para busca, analytics e filtros administrativos.

### `event_gallery_revisions`

Responsabilidade:

- historico restauravel da galeria por evento.

Campos sugeridos:

- `id`
- `event_id`
- `version_number`
- `kind` (`draft`, `autosave`, `published`, `restored`)
- `theme_tokens_json`
- `page_schema_json`
- `media_behavior_json`
- `change_summary_json`
- `created_by`
- `created_at`

Leitura pratica:

- sem `draft_version`, `published_version`, autosave e restore, o medo de "estragar" a pagina vira problema de produto;
- essa tabela vale mais para a primeira entrega do que uma camada de drag-and-drop sofisticada.

### `gallery_presets`

Responsabilidade:

- biblioteca de modelos reutilizaveis por organizacao.

Campos sugeridos:

- `id`
- `organization_id`
- `source_event_id`
- `created_by`
- `name`
- `slug`
- `description`
- `layout_key`
- `theme_key`
- `theme_tokens_json`
- `page_schema_json`
- `media_behavior_json`
- `event_type_family`
- `style_skin`
- `behavior_profile`
- `derived_preset_key`
- `created_at`
- `updated_at`

### `gallery_builder_prompt_runs`

Responsabilidade:

- historico do assistente de IA para o builder.

Campos sugeridos:

- `id`
- `event_id`
- `organization_id`
- `user_id`
- `prompt_text`
- `persona_key`
- `event_type_key`
- `target_layer`
- `base_preset_key`
- `request_payload_json`
- `response_payload_json`
- `selected_variation_id`
- `response_schema_version`
- `status`
- `provider_key`
- `model_key`
- `created_at`

Observacao:

- essa tabela pode nascer depois;
- no curto prazo, o padrao de `MediaIntelligence` pode ser copiado primeiro no desenho e so depois isolado em tabelas proprias.

---

## Contrato e endpoints recomendados

## 1. Endpoints admin novos

| Metodo | Rota | Papel |
|---|---|---|
| GET | `/api/v1/events/{event}/gallery/settings` | boot do builder da galeria |
| PATCH | `/api/v1/events/{event}/gallery/settings` | salvar configuracao da galeria |
| POST | `/api/v1/events/{event}/gallery/autosave` | snapshot de autosave do draft atual |
| POST | `/api/v1/events/{event}/gallery/publish` | promover draft atual para publicado |
| GET | `/api/v1/events/{event}/gallery/revisions` | listar versoes anteriores |
| POST | `/api/v1/events/{event}/gallery/revisions/{revision}/restore` | restaurar versao anterior |
| POST | `/api/v1/events/{event}/gallery/preview-link` | gerar link compartilhavel do draft |
| GET | `/api/v1/gallery/presets` | listar presets da organizacao |
| POST | `/api/v1/gallery/presets` | salvar preset reutilizavel |
| POST | `/api/v1/events/{event}/gallery/hero-image` | upload do hero |
| POST | `/api/v1/events/{event}/gallery/banner-image` | upload de banner/interstitial |
| POST | `/api/v1/events/{event}/gallery/ai/proposals` | gerar propostas aplicaveis via IA |

## 2. Endpoint publico recomendado

### Opcao pragmatica

Manter a rota atual:

- `GET /api/v1/public/events/{slug}/gallery`

Mas estender o payload com novos campos, sem quebrar o contrato existente.

Payload sugerido:

```json
{
  "success": true,
  "event": {
    "id": 12,
    "title": "Casamento Ana e Leo",
    "slug": "ana-e-leo",
    "branding": {
      "logo_url": "https://...",
      "cover_image_url": "https://...",
      "primary_color": "#d97786",
      "secondary_color": "#f5d0d6"
    }
  },
  "experience": {
    "version": 1,
    "published_version": 5,
    "theme_key": "wedding-rose",
    "layout_key": "editorial-masonry",
    "theme_tokens": {},
    "page_schema": {},
    "media_behavior": {}
  },
  "data": [
    {
      "id": 501,
      "media_type": "image",
      "thumbnail_url": "https://...",
      "preview_url": "https://...",
      "original_url": "https://...",
      "width": 1600,
      "height": 1067,
      "responsive_sources": {
        "sizes": "(max-width: 640px) 50vw, (max-width: 1200px) 33vw, 25vw",
        "srcset": "https://.../gallery-640.webp 640w, https://.../gallery-960.webp 960w, https://.../gallery-1600.webp 1600w",
        "variants": [
          { "variant_key": "gallery_640", "src": "https://.../gallery-640.webp", "width": 640, "height": 427, "mime_type": "image/webp" },
          { "variant_key": "gallery_960", "src": "https://.../gallery-960.webp", "width": 960, "height": 640, "mime_type": "image/webp" },
          { "variant_key": "gallery", "src": "https://.../gallery-1600.webp", "width": 1600, "height": 1067, "mime_type": "image/webp" }
        ]
      }
    }
  ],
  "meta": {}
}
```

Vantagem:

- additive change;
- frontend atual nao quebra;
- nova UI pode usar os campos extras.

Ponto importante:

- o estado atual do repo confirma que `thumbnail_url` e `preview_url` existem, mas `responsive_sources` ainda nao;
- por isso, `responsive_sources` precisa entrar como contrato novo e testado, e nao como suposicao da V1.

### Opcao reforcada para V1 mobile-first

Separar cedo boot e feed:

- `GET /api/v1/public/events/{slug}/gallery`
- `GET /api/v1/public/events/{slug}/gallery/media?page=2`

Essa separacao deixa de ser "fase 2 talvez" e passa a ser preferencia tecnica da V1 quando:

- o hero e os blocos publicos ficam mais ricos;
- a pagina precisa bater budget mobile de Web Vitals;
- a experiencia precisa renderizar primeiro a parte critica e depois continuar puxando o stream de midia.

Se a equipe quiser rollout mais conservador:

- a rota atual pode nascer como compatibilidade aditiva;
- mas o contrato do frontend deve ser desenhado desde o inicio para suportar `boot da experience` separado de `feed de midia`.

### Preview compartilhavel

Para draft e validacao com noiva, cerimonialista ou fotografo, faz sentido expor tambem:

- `GET /api/v1/public/gallery-previews/{token}`

Esse endpoint deve devolver o mesmo payload da galeria publica, mas resolvendo a revisao draft apontada pelo token compartilhado.

---

## Modelo de contrato do builder

## 1. Principio

O builder da galeria deve seguir o mesmo rigor do `Hub`, mas com contrato melhor separado:

- versionado;
- validado no backend;
- normalizado por registry;
- com enums fechados;
- com defaults seguros;
- com diffs por camada;
- com rollback simples;
- com tokens semanticos, nao CSS arbitrario.

## 2. Shape recomendado

```json
{
  "version": 1,
  "model_matrix": {
    "event_type_family": "wedding",
    "style_skin": "romantic",
    "behavior_profile": "story"
  },
  "theme_key": "wedding-rose",
  "layout_key": "editorial-masonry",
  "theme_tokens": {
    "palette": {
      "page_background": "#fff7f5",
      "surface_background": "#ffffff",
      "surface_border": "#f5d0d6",
      "text_primary": "#4c0519",
      "text_secondary": "#9f1239",
      "accent": "#d97786",
      "button_fill": "#be185d",
      "button_text": "#ffffff"
    },
    "typography": {
      "display_family_key": "editorial-serif",
      "body_family_key": "clean-sans",
      "title_scale": "lg"
    },
    "radius": {
      "card": "xl",
      "button": "pill",
      "media": "lg"
    },
    "borders": {
      "surface": "soft",
      "media": "none"
    },
    "shadows": {
      "card": "soft",
      "hero": "overlay-soft"
    },
    "contrast_rules": {
      "body_text_min_ratio": 4.5,
      "large_text_min_ratio": 3,
      "ui_min_ratio": 3
    },
    "motion": {
      "respect_user_preference": true
    }
  },
  "page_schema": {
    "block_order": [
      "hero",
      "gallery_stream",
      "banner_strip",
      "quote",
      "footer_brand"
    ],
    "blocks": {
      "hero": {
        "enabled": true,
        "show_logo": true,
        "show_count": true,
        "show_face_search_cta": true,
        "style": "cover"
      },
      "gallery_stream": {
        "enabled": true
      },
      "banner_strip": {
        "enabled": false,
        "positions": ["after_12", "after_36"]
      },
      "quote": {
        "enabled": false,
        "style": "editorial"
      },
      "footer_brand": {
        "enabled": true,
        "show_eventovivo": true
      }
    },
    "presence_rules": {
      "hero_required": true,
      "max_banner_blocks": 2,
      "require_preview_before_publish": true
    }
  },
  "media_behavior": {
    "grid": {
      "layout": "masonry",
      "density": "comfortable",
      "breakpoints": [360, 768, 1200]
    },
    "pagination": {
      "mode": "infinite-scroll",
      "page_size": 30,
      "chunk_strategy": "sectioned"
    },
    "loading": {
      "hero_and_first_band": "eager",
      "below_fold": "lazy",
      "content_visibility": "auto"
    },
    "lightbox": {
      "photos": true,
      "videos": false
    },
    "video": {
      "allowed_modes": ["poster_only", "poster_to_modal", "inline_preview"],
      "mode": "poster_to_modal",
      "show_badge": true,
      "allow_inline_preview": false
    },
    "interstitials": {
      "enabled": false,
      "max_per_24_items": 1
    }
  }
}
```

## 3. Por que separar em tres camadas

- a IA pode alterar so o que foi pedido;
- diff visual e rollback ficam menores;
- preview parcial fica mais claro;
- validacao de contraste e motion fica no lugar certo;
- produto fica menos fragil para autosave, restore e publish.

Exemplos de operacao:

- "muda a paleta" -> altera `theme_tokens`;
- "deixa mais premium" -> altera `theme_tokens` e, no maximo, `page_schema.hero`;
- "adiciona banner no meio" -> altera `page_schema`;
- "troca o estilo da grade" -> altera `media_behavior.grid`.

## 4. Matriz de modelos recomendada

Em vez de vender a V1 como uma lista unica de presets tecnicos, o melhor desenho e partir de uma matriz de modelos que fala a lingua do usuario:

- `event_type_family`
- `style_skin`
- `behavior_profile`

### `event_type_family`

Familias iniciais recomendadas:

- `wedding`
- `quince`
- `corporate`

Responsabilidade principal:

- orientar hero, blocos contextuais, copy base e linguagem visual dominante.

### `style_skin`

Skins iniciais recomendadas:

- `romantic`
- `modern`
- `classic`
- `premium`
- `clean`

Responsabilidade principal:

- orientar `theme_tokens`, tipografia, radius, sombras e atmosfera visual.

### `behavior_profile`

Perfis iniciais recomendados:

- `light`
- `story`
- `live`
- `sponsors`

Responsabilidade principal:

- orientar `media_behavior`, densidade, politicas de video, ritmo da pagina e interstitials.

### Como a matriz deriva a experiencia

Exemplos:

- `wedding + romantic + story` -> hero editorial, `masonry`, paleta rose, quote e destaque de momentos;
- `quince + modern + live` -> hero vibrante, `masonry` ou `rows`, cards mais energicos e ticker de momentos;
- `corporate + clean + sponsors` -> `rows`, sponsor strip, CTA objetivo e badge/video mais contido.

Leitura pratica:

- o usuario entra por algo que entende;
- o sistema continua derivando `theme_tokens`, `page_schema` e `media_behavior`;
- presets tecnicos como `editorial-masonry` ou `timeless-rows` viram detalhe interno, nao a porta principal de entrada.

### Layouts derivados recomendados

Recomendacao de default:

- `wedding` e `quince`: `masonry` ou `rows`;
- `corporate`: `rows` por padrao;
- `columns`: apenas para landing curta, bloco editorial curto ou album menor.

Isso reforca o que a documentacao oficial do `react-photo-album` ja mostra:

- `rows` e `masonry` tem trilha propria de infinite scroll;
- `columns` nao e um bom encaixe para feed longo.

### Catalogo de blocos orientado ao caso de uso

O catalogo da V1 deve falar a lingua do evento, mesmo quando a implementacao reaproveita componentes base.

Blocos e variantes recomendados:

- `WeddingHero`
- `QuinceHero`
- `CorporateHero`
- `SponsorsStrip`
- `FindMeCta`
- `TimelineMoments`
- `CeremonyInfo`
- `GiftTableInfo`
- `PhotoHighlightRail`
- `LiveMomentsTicker`

Regra de implementacao:

- nao significa duplicar tudo em componentes isolados;
- significa expor variantes e inspector contextual com semantica de evento, em vez de painel generico demais.

### Atalhos de entrada recomendados para usuario leigo

- `quero algo romantico`
- `quero algo moderno`
- `quero algo clean/premium`

Esses atalhos continuam uteis, mas agora devem ser aplicados junto com o contexto:

- tipo do evento
- branding herdado
- comportamento desejado

---

## Como a IA deve funcionar

## 1. Regra principal

A IA **nao** deve gerar:

- JSX
- HTML final
- CSS livre
- layout arbitrario

Ela deve gerar apenas propostas aplicaveis dentro do catalogo do produto.

## 2. A IA deve propor mudancas, nao "gerar uma pagina"

Entrada da IA:

- prompt do usuario
- draft atual da galeria
- branding efetivo do evento
- tipo do evento
- `event_type_family`, `style_skin` e `behavior_profile` atuais
- persona principal
- modelos disponiveis
- blocos permitidos
- layouts permitidos
- regras de guardrail
- camada alvo quando o pedido for especifico

Saida ideal:

- `3` variacoes seguras;
- cada variacao com `summary`, `patch` e `scope`;
- aplicacao total ou parcial pelo usuario;
- nada de HTML, JSX ou CSS livre.

## 3. Exemplo de resposta recomendada

```json
{
  "response_schema_version": 1,
  "scope": "mixed",
  "variations": [
    {
      "id": "romantic-soft",
      "label": "Romantico suave",
      "summary": "Rose claro, hero mais editorial e grade masonry confortavel.",
      "patch": {
        "theme_tokens": {
          "palette": {
            "accent": "#d97786"
          }
        },
        "page_schema": {
          "blocks": {
            "hero": {
              "enabled": true,
              "show_logo": true
            }
          }
        },
        "media_behavior": {
          "grid": {
            "layout": "masonry"
          }
        }
      }
    },
    {
      "id": "modern-clean",
      "label": "Moderno clean",
      "summary": "Paleta neutra, menos ornamento e mais respiracao visual.",
      "patch": {
        "theme_tokens": {},
        "page_schema": {},
        "media_behavior": {}
      }
    },
    {
      "id": "premium-album",
      "label": "Premium album",
      "summary": "Mais contraste, serif elegante e ritmo editorial.",
      "patch": {
        "theme_tokens": {},
        "page_schema": {},
        "media_behavior": {}
      }
    }
  ]
}
```

## 4. Pipeline recomendado

1. usuario escolhe um preset base ou entra em modo "sugerir por IA";
2. frontend envia prompt + contexto + draft atual + persona + tipo de evento;
3. backend monta payload OpenAI-compatible com `json_schema`;
4. modelo responde com `3` variacoes seguras;
5. backend valida e normaliza cada patch;
6. frontend mostra preview/diff;
7. usuario aplica uma variacao inteira ou so partes dela;
8. autosave cria revisao;
9. usuario publica quando estiver seguro.

Aplicacao parcial recomendada:

- aplicar so a paleta;
- aplicar so hero + textos;
- aplicar so estilo da grade;
- aplicar tudo.

## 5. Como reaproveitar o que ja existe

A IA do builder deve copiar o padrao de `MediaIntelligence`:

- `response_schema_version`
- preset de prompt
- historico
- laboratorio
- provider/model policy
- output estruturado

Isso e mais coerente do que depender da IA nativa de um editor externo.

## 6. Exemplos de prompt de usuario

- "quero uma galeria romantica em tons rose com logo no topo"
- "deixa mais premium e limpa, parecendo album de fotografo"
- "faz uma galeria corporativa clara, com banner dos patrocinadores no meio"
- "quero algo para 15 anos, moderno, com cards suaves e botoes brilhantes"

## 7. Guardrails obrigatorios

- so layouts do catalogo
- so blocos do catalogo
- so tokens permitidos
- maximo de banners por faixa
- contraste minimo validado:
  - texto normal `4.5:1`
  - texto grande `3:1`
  - UI/non-text `3:1`
- respeito a `prefers-reduced-motion`
- sem CSS custom do usuario
- sem posicionamento totalmente livre
- preview antes de publicar
- botao de restaurar versao anterior sempre visivel
- sem publicar sem `draft_version` previewable

Precedentes locais que ja sustentam isso:

- `qrReadability` ja trabalha com faixas de contraste e bloqueio quando a legibilidade cai demais;
- `runtime-profile` do `Wall` ja resolve `prefers-reduced-motion`.

---

## UX recomendada do builder

## 1. Filosofia

O builder deve ser:

- preset-first
- progressive disclosure
- seguro para leigo
- rapido para operador
- personalizavel sem ficar fragil

## 2. Modos explicitos do produto

### Modo rapido

Fluxo recomendado:

1. escolher o tipo do evento
2. escolher a vibe
3. aplicar `Comecar com base do evento`
4. ajustar capa/logo
5. revisar cores sugeridas
6. revisar o preview mobile
7. publicar

### Modo profissional

Fluxo recomendado:

1. escolher preset base
2. editar ordem de blocos
3. ajustar grade, video e interstitials
4. validar mobile/desktop
5. salvar preset ou publicar

## 3. Estrutura da tela

Topo:

- atalhos de vibe
- modo rapido/profissional
- status de autosave
- botao de preview link
- historico/restore
- publicar

Esquerda:

- wizard rapido
- tipo do evento
- presets prontos
- vibe
- paleta sugerida
- capa
- logo
- `Comecar com base do evento`
- botao "ajudar com IA"

Centro:

- preview como area principal
- preview mobile/desktop
- alternancia foto/video

Direita:

- inspector contextual
- blocos
- tema
- textos
- galerias e interstitials
- configuracoes avancadas

## 4. Recognition over recall

- nao expor `layout_key`, `theme_key` ou hex como ponto de entrada principal;
- usar cartoes visuais, nomes humanos e mini previews;
- quando o usuario clicar no `hero`, abrir controles do `hero`;
- quando clicar na grade, abrir controles da grade.

## 5. Seguranca de produto e colaboracao

- autosave desde o inicio;
- `draft_version` separado de `published_version`;
- preview link compartilhavel;
- restore previous version;
- diff visivel antes de publicar.

Leitura pratica:

- o modo rapido precisa nascer como wizard guiado, nao so como inspector reduzido;
- isso reduz escolha vazia e melhora onboarding para noiva, cerimonialista e operador leigo.

---

## Performance e video

## 1. Mobile-first como contrato de produto

A V1 deve nascer com budget explicito de Web Vitals, nao apenas com "boas intencoes" de performance.

Metas recomendadas:

- `LCP <= 2.5s`
- `INP <= 200ms`
- `CLS <= 0.1`

Regra de medicao:

- percentil `75`;
- segmentacao por mobile e desktop;
- leitura em RUM/analytics real quando houver trafego suficiente;
- budgets sinteticos em ambiente controlado para evitar regressao durante desenvolvimento.

Leitura pratica:

- esse contrato precisa entrar antes do hardening final;
- ele afeta hero, lazy loading, chunking, tamanho de midia e decisao de separar boot da experiencia do feed longo.

## 2. Estrategia de layout

Pelas docs oficiais do `react-photo-album`, a decisao de layout nao deve tratar `rows`, `columns` e `masonry` como equivalentes.

Recomendacao:

- priorizar `masonry` e `rows` para galerias publicas longas;
- deixar `columns` para experiencias menores, landing curta ou bloco editorial;
- usar `breakpoints` desde o inicio para reduzir recalculo continuo de layout;
- usar `render` overrides quando precisar badge, CTA ou overlay por item.

Regra de produto por familia:

- `wedding` e `quince`: `masonry` ou `rows` por padrao;
- `corporate`: `rows` por padrao;
- `columns`: fora do centro da V1 longa.

## 3. Contrato de imagem responsiva

Hoje o repo ja entrega:

- `thumbnail_url`
- `preview_url`
- `original_url`
- `width`
- `height`

Mas isso ainda nao e o mesmo que um contrato mobile-first de midia responsiva.

Recomendacao:

- adicionar `responsive_sources` por item;
- expor `srcset` e `sizes`;
- expor variantes por largura e mime;
- manter dimensoes explicitas para layout, lightbox e skeleton previsivel.

Leitura pratica:

- `ModerationMediaSurface` ja prova precedente local para `sizes`;
- `PublicGalleryPage` ainda nao usa isso;
- `PhotoSwipe` se beneficia diretamente de item com `src`, `width`, `height` e `srcset`.

## 4. Estrategia de carregamento por prioridade visual

Migrar de CSS columns simples para renderer dedicado traz ganhos de:

- previsibilidade de layout
- melhor responsividade
- melhor leitura de dimensao
- menos comportamento acidental

Recomendacao pratica:

- hero: `eager`;
- primeira faixa de cards: `eager`;
- resto do feed: `loading="lazy"`;
- banners abaixo da dobra: lazy;
- video no grid: somente poster inicialmente;
- `content-visibility: auto` em blocos longos quando fizer sentido;
- chunking por secoes, nao so por pagina.

Observacao:

- a doc de `HTMLImageElement.loading` e a doc de lazy loading deixam claro que lazy loading existe para recursos fora da area critica e para encurtar o critical rendering path;
- `content-visibility` ajuda, mas perde parte do ganho se o codigo ficar lendo layout/DOM de subarvores ocultas.

## 5. Foto e video nao sao o mesmo problema

O backend ja esta pronto para muito mais do que a UI atual mostra.

As docs oficiais do `PhotoSwipe` confirmam que:

- foto funciona muito bem quando ja existem `width` e `height`;
- imagens responsivas sao recomendadas;
- custom content existe, mas a ferramenta continua sendo mais orientada a foto do que a video/iframe.

Entao a recomendacao correta e formalizar modos de video no dominio:

- `poster_only`
- `poster_to_modal`
- `inline_preview`

Regra recomendada:

- mobile default: `poster_to_modal`;
- `PhotoSwipe` fica exclusivo para foto;
- video abre em modal/player dedicado e nao disputa o mesmo contrato de lightbox.

## 6. Escala e virtualizacao

Para eventos pequenos e medios:

- a primeira versao pode viver sem dependencia nova de virtualizacao.

Para eventos grandes:

- usar o precedente local de virtualizacao;
- ou adotar `@tanstack/react-virtual`;
- ou criar um preset/variant com `masonic`.

Precedente local relevante:

- `MediaVirtualFeed.tsx` ja prova `ResizeObserver`, overscan, padding virtual e feed grande com custo controlado.

Gatilho explicito recomendado:

- se o evento ultrapassar o threshold operacional definido em `Sprint 0` para quantidade de itens, altura renderizada estimada ou custo de DOM por chunk, ativar uma variante otimizada de renderizacao;
- `IntersectionObserver` continua como base nativa para lazy loading e infinite scroll;
- `TanStack Virtual` entra quando o custo de DOM e scroll deixar de caber no renderer normal.

---

## Roadmap recomendado

## P0 - endurecer o renderer publico

Objetivo:

- sair de CSS columns simples;
- suportar video melhor;
- preparar payload publico para branding/experience;
- colocar mobile-first e budget de Web Vitals como contrato desde o inicio;
- desenhar cedo a separacao entre `boot da experience` e `feed de midia`.

Entrega:

- `react-photo-album`
- `PhotoSwipe` para foto
- modal/player dedicado para video
- payload publico estendido
- contrato de `responsive_sources`
- hero simples por evento
- hero e primeira faixa como prioridade visual
- lazy loading correto para o restante
- contrast guard
- preparacao de preview mobile/desktop

## P1 - builder guiado dentro de `Gallery`

Objetivo:

- criar `event_gallery_settings`
- criar `event_gallery_revisions`
- criar `gallery_presets`
- criar `GalleryBuilderPresetRegistry`
- criar editor administrativo com preview
- salvar `theme_tokens`, `page_schema` e `media_behavior`

Entrega:

- modo rapido e modo profissional
- modo rapido em formato de wizard
- matriz de modelos por `event_type_family`, `style_skin` e `behavior_profile`
- `draft_version` / `published_version`
- autosave
- preview link compartilhavel
- restore previous version
- presets por organizacao
- temas e tokens
- blocos oficiais e variantes orientadas a casamento, 15 anos e corporativo
- inspector contextual
- public gallery configuravel

## P2 - IA guardrailed com diff aplicavel

Objetivo:

- prompt -> `3` variacoes seguras
- preview de diff
- aplicacao parcial
- historico de execucao
- catalogo de prompt para builder

Entrega:

- assistente IA seguro para usuario leigo
- diff por camada
- sem abertura para codigo arbitrario

## P3 - editor visual mais livre, se ainda fizer sentido

Objetivo:

- avaliar `Puck` como camada de authoring

Entrega possivel:

- drag-and-drop visual;
- slots mais livres;
- plugins adicionais.

Condicao:

- so faz sentido depois que schema, blocos e guardrails estiverem muito claros;
- e so se houver demanda real por nesting/authoring mais livre que nao caiba no builder atual.

---

## Permissoes e ownership

Recomendacao minima:

- reutilizar `gallery.manage` para editar builder

Recomendacao melhor:

- criar `gallery.builder.manage`

Motivo:

- separa curadoria de midia da configuracao da experiencia publica;
- deixa a matriz de acesso mais clara.

---

## Testes obrigatorios

## Backend

- feature test para `GET/PATCH /events/{event}/gallery/settings`
- feature test para `POST /events/{event}/gallery/autosave`
- feature test para `POST /events/{event}/gallery/publish`
- feature test para `GET /events/{event}/gallery/revisions`
- feature test para `POST /events/{event}/gallery/revisions/{revision}/restore`
- feature test para `GET/POST /gallery/presets`
- feature test para payload publico com `experience`
- feature test para contrato de `responsive_sources`
- feature test para preview compartilhavel
- unit test do `GalleryBuilderPresetRegistry`
- unit test da matriz `event_type_family` + `style_skin` + `behavior_profile`
- unit test da normalizacao de `theme_tokens`
- unit test da validacao de contraste e motion tokens
- unit test da validacao do schema gerado por IA
- unit test da aplicacao parcial de patch por camada
- teste de compatibilidade aditiva do contrato publico atual

## Frontend

- `GalleryBuilderPage.test.tsx`
- `GalleryRenderer.test.tsx`
- `PublicGalleryPage.test.tsx` cobrindo presets e video
- `GalleryAiVariationsPanel.test.tsx`
- testes do wizard de modo rapido
- testes de modo rapido/profissional
- testes de inspector contextual
- testes de restore/autosave/publish status
- testes de photo lightbox e video modal
- testes de `responsive_sources`, `srcset` e `sizes`
- testes de contraste/reduced motion na preview
- testes de preview mobile/desktop
- testes de contratos de budget mobile e prioridade visual

---

## Checklist de implementacao

- [ ] ampliar `Gallery` com settings e presets
- [ ] criar `event_gallery_settings`
- [ ] criar `event_gallery_revisions`
- [ ] criar `gallery_presets`
- [ ] criar registry de layouts/temas/blocos
- [ ] estender payload publico da galeria
- [ ] trocar renderer publico para layout dedicado
- [ ] suportar video de forma visivel no frontend publico
- [ ] criar contrato de `responsive_sources`, `srcset` e `sizes`
- [ ] separar o contrato em `theme_tokens`, `page_schema` e `media_behavior`
- [ ] transformar presets em matriz `event_type_family` + `style_skin` + `behavior_profile`
- [ ] criar editor administrativo da galeria
- [ ] criar modo rapido como wizard e modo profissional como inspector completo
- [ ] criar autosave
- [ ] criar `draft_version` e `published_version`
- [ ] criar preview link compartilhavel
- [ ] criar restore previous version
- [ ] criar save/load de presets por organizacao
- [ ] integrar branding efetivo do evento ao tema default
- [ ] desenhar schema JSON guardrailed para IA
- [ ] fazer IA responder com variacoes aplicaveis e diff parcial
- [ ] validar contraste minimo e reduced motion
- [ ] explicitar budgets mobile de Web Vitals desde o inicio do rollout
- [ ] definir gatilho explicito para renderer otimizado/virtualizacao
- [ ] copiar padrao de prompt/schema/historico de `MediaIntelligence`
- [ ] adicionar testes backend
- [ ] adicionar testes frontend
- [ ] atualizar `README.md` do modulo `Gallery`
- [ ] atualizar `docs/modules/module-map.md`
- [ ] revisar fluxo correspondente em `docs/flows/` se a galeria publica ganhar nova narrativa

---

## Validacao externa usada nesta recomendacao

As referencias abaixo foram revisadas em `2026-04-11` para validar as opcoes externas e evitar recomendacao desatualizada.

### `Puck`

- GitHub oficial: https://github.com/puckeditor/puck
- Docs: https://puckeditor.com/docs
- `slot`: https://puckeditor.com/docs/api-reference/fields/slot
- plugin API: https://puckeditor.com/docs/extending-puck/plugins
- pricing / AI cloud: https://puckeditor.com/pricing

Leitura pratica:

- excelente fit tecnico;
- melhor opcao externa se o produto realmente precisar de editor visual React-first;
- IA oficial existe, mas nao substitui a infraestrutura interna que o Eventovivo ja tem.

### `Easyblocks`

- site oficial: https://easyblocks.io/
- docs: https://docs.easyblocks.io/

Leitura pratica:

- muito bom para guardrails e no-code components;
- precisa atencao especial ao modelo de licenciamento.

### `GrapesJS`

- docs oficiais: https://grapesjs.com/docs/
- Studio SDK overview: https://app.grapesjs.com/docs-sdk/overview/getting-started
- React renderer announcement: https://grapesjs.com/blog/react-renderer-release

Leitura pratica:

- poderoso;
- mas menos alinhado ao caminho schema-first + React component-first do repo.

### Renderizacao da galeria

- React Photo Album: https://react-photo-album.com/
- Documentacao: https://react-photo-album.com/documentation
- PhotoSwipe: https://photoswipe.com/
- PhotoSwipe React example: https://photoswipe.com/react-image-gallery/
- PhotoSwipe getting started: https://photoswipe.com/getting-started/
- PhotoSwipe data sources: https://photoswipe.com/data-sources/
- PhotoSwipe custom content: https://photoswipe.com/custom-content/
- react-photoswipe-gallery: https://github.com/dromru/react-photoswipe-gallery

Leitura pratica:

- `react-photo-album` oferece `rows`, `columns`, `masonry`, `breakpoints` e render overrides;
- a propria doc indica `columns` como encaixe pior para infinite scroll;
- `PhotoSwipe` e excelente para foto quando `width`/`height` ja existem, mas video pede cautela e fallback proprio.

### Virtualizacao

- TanStack Virtual: https://tanstack.com/virtual/latest
- TanStack Virtual intro: https://tanstack.com/virtual/latest/docs/introduction
- masonic: https://github.com/jaredLunde/masonic

Leitura pratica:

- usar somente quando o volume justificar;
- nao precisa travar a primeira entrega.

### Acessibilidade e performance

- WCAG contrast minimum: https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum
- WCAG non-text contrast: https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html
- web.dev LCP: https://web.dev/articles/lcp
- web.dev INP: https://web.dev/articles/inp
- web.dev CLS: https://web.dev/articles/cls
- MDN responsive images: https://developer.mozilla.org/en-US/docs/Web/HTML/Guides/Responsive_images
- MDN prefers-reduced-motion: https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion
- MDN content-visibility: https://developer.mozilla.org/en-US/docs/Web/CSS/content-visibility
- MDN Intersection Observer API: https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
- MDN lazy loading: https://developer.mozilla.org/en-US/docs/Web/Performance/Lazy_loading
- MDN HTMLImageElement.loading: https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/loading

Leitura pratica:

- contraste minimo nao deve ser opcional no builder;
- reduced motion precisa virar token/comportamento suportado pelo renderer;
- `LCP <= 2.5s`, `INP <= 200ms` e `CLS <= 0.1` no p75 segmentado por mobile/desktop sao budgets corretos para a V1 mobile-first;
- `srcset` e `sizes` devem virar contrato de API, nao detalhe opcional do frontend;
- lazy loading, `IntersectionObserver` e `content-visibility` ajudam, mas precisam entrar com criterio de UX e nao so como "otimizacao generica".

---

## Veredito final

### O Eventovivo deve criar um construtor de galeria?

Sim.

### O repo ja tem base suficiente?

Sim.

### O caminho certo e partir para `Puck` agora?

Nao.

### Qual o melhor caminho agora?

1. ampliar `Gallery` seguindo o padrao do `Hub` em registry, defaults, validacao e preview;
2. separar a configuracao em `theme_tokens`, `page_schema` e `media_behavior`;
3. transformar a entrada do produto em uma matriz de modelos por tipo de evento, estilo e comportamento;
4. endurecer o renderer publico primeiro, com foto e video tratados de forma diferente e contrato de imagem responsiva;
5. colocar draft/publish, autosave, preview compartilhavel e restore como partes obrigatorias do produto;
6. introduzir IA como engine de propostas e diff aplicavel, nao como gerador livre de pagina;
7. avaliar `Puck` apenas quando o schema e o produto estiverem maduros e houver demanda real por authoring mais livre.

Em resumo:

**a V1 nao deve ser tratada como page builder generico de galeria. Ela deve ser um sistema de modelos de galeria por tipo de evento, mobile-first, rapido, seguro, versionado e altamente customizavel dentro de guardrails.**
