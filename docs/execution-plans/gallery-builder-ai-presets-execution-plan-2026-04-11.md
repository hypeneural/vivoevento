# Gallery builder, presets and AI execution plan - 2026-04-11

## Objetivo

Transformar `docs/architecture/gallery-builder-ai-presets-analysis-2026-04-11.md` em um plano de execucao tecnico, detalhado e orientado por TDD para entregar:

- galeria publica de fotos e videos melhor;
- builder administrativo por evento;
- presets por organizacao;
- matriz de modelos por tipo de evento, estilo e comportamento;
- configuracao separada em `theme_tokens`, `page_schema` e `media_behavior`;
- contrato mobile-first com budget de Web Vitals;
- contrato de imagem responsiva com `responsive_sources`, `srcset` e `sizes`;
- draft/publish/autosave/restore/preview compartilhavel;
- assistente de IA guardrailed com propostas aplicaveis;
- base pronta para avaliar `Puck` apenas depois da V1.

Este plano responde:

1. qual e a ordem real de implementacao;
2. o que entra em cada sprint;
3. quais tarefas e subtarefas backend/frontend precisam acontecer;
4. quais contratos precisam ser congelados antes de codar;
5. qual e a bateria TDD obrigatoria por fase;
6. qual e a definicao de pronto da V1.

Documento base e fonte de verdade tecnica:

- `docs/architecture/gallery-builder-ai-presets-analysis-2026-04-11.md`

Referencias internas obrigatorias para a trilha:

- `apps/api/app/Modules/Gallery/routes/api.php`
- `apps/api/tests/Feature/Gallery/PublicGalleryAvailabilityTest.php`
- `apps/api/tests/Feature/Gallery/GalleryAdminWorkflowTest.php`
- `apps/api/tests/Feature/MediaProcessing/EventMediaListTest.php`
- `apps/api/app/Modules/Hub/Support/HubBuilderPresetRegistry.php`
- `apps/api/app/Modules/Hub/Models/EventHubSetting.php`
- `apps/api/app/Modules/Hub/Models/HubPreset.php`
- `apps/web/src/modules/gallery/PublicGalleryPage.tsx`
- `apps/web/src/modules/media/components/MediaVirtualFeed.tsx`
- `apps/web/src/modules/events/branding.ts`
- `apps/web/src/modules/wall/player/runtime-profile.ts`
- `apps/web/src/modules/qr-code/support/qrReadability.ts`
- `apps/web/src/modules/ai/MediaAutomaticRepliesPage.tsx`
- `apps/api/app/Modules/MediaIntelligence/Services/OpenAiCompatibleVisualReasoningPayloadFactory.php`

---

## Status da execucao em 2026-04-11

Sprint 0 foi iniciada e concluida em `2026-04-12`.

Sprint 1 foi iniciada e concluida em `2026-04-12`.

Sprint 2 foi iniciada e concluida em `2026-04-12`.

Sprint 3 foi iniciada e concluida em `2026-04-12`.

Sprint 4 foi iniciada e concluida em `2026-04-12`.

Sprint 5 foi iniciada e concluida em `2026-04-12`.

O que ja esta validado antes de iniciar:

- a galeria publica/admin atual esta verde;
- o `Hub` builder atual esta verde;
- o contrato operacional de midia entrega `preview_url`, `original_url`, dimensoes e metadados suficientes;
- o frontend ja tem precedente para contraste, `prefers-reduced-motion` e feed virtualizado;
- ate o inicio da Sprint 1, o repo ainda nao instalava `react-photo-album`, `PhotoSwipe`, `@tanstack/react-virtual`, `masonic`, `Puck`, `Easyblocks` ou `GrapesJS`.

Revalidacao complementar em `2026-04-12`:

- `PublicGalleryAvailabilityTest`, `GalleryAdminWorkflowTest` e `EventMediaListTest` seguiram verdes;
- `PublicGalleryPage`, `ModerationMediaSurface`, `qrReadability` e `runtime-profile` seguiram verdes;
- o repo ainda nao expoe `responsive_sources`/`srcset`/`sizes` no payload publico;
- a galeria publica continua usando `<img src={thumbnail_url} loading="lazy">` simples, entao a trilha mobile-first e responsiva precisa entrar cedo, nao so no hardening final.

Leitura pratica:

- a trilha nasce sobre base estavel;
- o risco maior esta em produto/contrato e nao em pipeline de midia;
- o primeiro valor vem de melhorar renderer e experience, nao de arrastar bloco visualmente.

Implementacao Sprint 0 em `2026-04-12`:

- contratos compartilhados criados em `packages/shared-types/src/gallery-builder.ts`;
- contratos frontend e fixtures criados em `apps/web/src/modules/gallery/gallery-builder.ts`;
- shell inicial do builder criada em `/events/:id/gallery/builder`;
- `gallery.builder.manage` adicionada ao backend, frontend e mocks;
- `queryKeys.gallery` estendido para `settings`, `presets`, `revisions` e `preview`;
- registries/guards de contrato criados em `apps/api/app/Modules/Gallery/Support`;
- bateria TDD de Sprint 0 criada e executada.

Implementacao Sprint 1 em `2026-04-12`:

- `react-photo-album` e `photoswipe` instalados em `apps/web`;
- wrapper proprio adotado para PhotoSwipe, sem adicionar `react-photoswipe-gallery` na V1;
- payload publico da galeria estendido com `event`, `experience` e `responsive_sources`;
- rota publica incremental `GET /api/v1/public/events/{slug}/gallery/media` adicionada para preparar separacao entre boot da experience e feed longo;
- `GalleryResponsiveSourceBuilder`, `PublicGalleryMediaResource` e `BuildPublicGalleryPayloadAction` criados no backend;
- `GalleryRenderer`, `GalleryPhotoLightbox` e `GalleryVideoModal` criados no frontend;
- `PublicGalleryPage` passou a usar hero com branding, renderer dedicado, primeira faixa eager e restante lazy;
- foto e video foram separados como regra de UX: foto abre em PhotoSwipe, video abre em modal/player proprio;
- bateria TDD de Sprint 1 criada e executada.

Implementacao Sprint 2 core backend em `2026-04-12`:

- migrations `event_gallery_settings`, `event_gallery_revisions` e `gallery_presets` criadas;
- models/factories `EventGallerySetting`, `EventGalleryRevision` e `GalleryPreset` adicionados;
- `GalleryBuilderPresetRegistry` e `GalleryRevisionManager` criados para defaults, normalizacao e versionamento;
- endpoints admin de `settings`, `autosave`, `publish`, `revisions`, `restore`, `preview-link` e `presets` adicionados ao modulo `Gallery`;
- endpoints `POST /events/{event}/gallery/hero-image` e `POST /events/{event}/gallery/banner-image` adicionados para assets do builder;
- `GET /api/v1/public/gallery-previews/{token}` adicionado para preview compartilhavel por revisao draft;
- `BuildPublicGalleryPayloadAction` passou a respeitar `published_version` quando existir e a isolar draft em preview tokenizado;
- `page_schema.blocks.hero` e `page_schema.blocks.banner_strip` passaram a suportar `image_path` persistido e `image_url` resolvido no payload;
- `README.md` do modulo `Gallery` e `docs/modules/module-map.md` atualizados;
- bateria TDD de Sprint 2 foi ampliada para cobrir uploads de assets e executada com regressao do modulo.

Implementacao Sprint 5 em `2026-04-12`:

- `GalleryBuilderController@show` passou a expor `optimized_renderer_trigger` e `operational_feedback`;
- `POST /api/v1/events/{event}/gallery/telemetry` foi adicionado para `preset_applied`, `ai_applied` e `vitals_sample`;
- `event_gallery_settings.current_preset_origin_json` passou a persistir a origem atual do preset/atalho;
- publish e restore passaram a registrar analytics operacionais via `AnalyticsTracker` em `analytics_events`;
- `GalleryBuilderPromptRun.selected_variation_id` passou a ser preenchido no fluxo de `ai_applied`;
- o builder admin ganhou `role="status"`, `role="alert"`, reduced motion real, `render_mode` explicito e telemetry de vitals no frontend;
- a preview passou a expor `standard|optimized`, `data-reduced-motion` e a reaproveitar `MediaVirtualFeed` quando o threshold do renderer e ultrapassado;
- o detalhe do evento ganhou CTA explicito para `/events/:id/gallery/builder` respeitando `gallery.builder.manage`;
- `apps/api/app/Modules/Gallery/README.md`, `docs/modules/module-map.md` e `docs/flows/gallery-builder-operations.md` foram atualizados para rollout, budgets, telemetry e runbook.

Leitura pratica:

- com a Sprint 5 concluida, a V1 planejada neste documento ficou fechada;
- o builder terminou a trilha como superficie acessivel, mensuravel, versionada e operavel sem depender de conhecimento implicito.

---

## Veredito executivo

O caminho mais seguro para produzir uma V1 forte e este:

1. Sprint 0: contratos, permissao, baseline e fundacao do dominio;
2. Sprint 1: endurecer a galeria publica e o payload `experience`;
3. Sprint 2: backend do builder, settings, revisions e presets;
4. Sprint 3: builder administrativo com modo rapido/profissional, preview central e versionamento;
5. Sprint 4: IA guardrailed com propostas, diff e aplicacao parcial;
6. Sprint 5: hardening de acessibilidade, performance, rollout e analytics operacional;
7. Sprint 6: avaliar `Puck` apenas se ainda houver necessidade real de authoring visual mais livre.

Leitura pratica:

- `Sprint 1` entrega valor visivel para o usuario final;
- `Sprint 2` e `Sprint 3` transformam isso em produto de verdade;
- `Sprint 4` entra em cima de schema e guardrails ja maduros;
- `Sprint 5` fecha risco operacional e de UX;
- `Sprint 6` fica explicitamente fora da V1.

Se a equipe trabalhar em sprints de duas semanas, a recomendacao e:

- juntar `Sprint 0 + Sprint 1`;
- juntar `Sprint 2 + Sprint 3`;
- manter `Sprint 4` e `Sprint 5` separadas;
- tratar `Sprint 6` como spike futuro, nao como compromisso da V1.

---

## Decisoes tecnicas fixadas antes da implementacao

Estas decisoes ficam congeladas para evitar drift:

1. O ownership continua em `Gallery`, nao em modulo transversal de builder generico.
2. O frontend continua em `apps/web/src/modules/gallery`.
3. O backend continua em `apps/api/app/Modules/Gallery`.
4. O contrato primario nao nasce como um `builder_config` gigante.
5. A fonte de verdade sera separada em:
   - `theme_tokens`
   - `page_schema`
   - `media_behavior`
6. Pode existir um envelope agregado `experience` no payload publico, mas ele nao substitui as tres camadas.
7. A V1 nasce com:
   - `draft_version`
   - `published_version`
   - autosave
   - preview link compartilhavel
   - restore previous version
8. A entrada principal do produto deixa de ser "preset tecnico" e passa a ser uma matriz:
   - `event_type_family`
   - `style_skin`
   - `behavior_profile`
9. A V1 e mobile-first por contrato:
   - `LCP <= 2.5s`
   - `INP <= 200ms`
   - `CLS <= 0.1`
   - medidos no percentil 75 e segmentados por mobile e desktop
10. O payload publico de midia precisa evoluir para expor `responsive_sources`, `srcset` e `sizes`.
11. Foto e video nao seguem exatamente o mesmo fluxo de exibicao:
   - foto usa lightbox
   - video usa `poster_only`, `poster_to_modal` ou `inline_preview`
   - mobile default: `poster_to_modal`
12. `react-photo-album` e a base do renderer.
13. `PhotoSwipe` entra apenas para foto.
14. `columns` nao entra como layout principal de galerias longas; priorizar `masonry` e `rows`.
15. O frontend deve ser desenhado desde o inicio para suportar separacao entre `boot da experience` e `feed de midia`.
16. A IA propoe patches aplicaveis; ela nao gera pagina livre, JSX, HTML ou CSS.
17. A IA devolve `3` variacoes seguras, com aplicacao total ou parcial.
18. A V1 cria permissao propria `gallery.builder.manage`.
19. `Puck` nao entra na V1 e so sera reavaliado se houver demanda real por nesting/authoring livre que nao caiba no builder atual.

---

## Escopo real da V1

## Entra na V1

- payload publico `experience` aditivo;
- contrato de `responsive_sources`, `srcset` e `sizes`;
- hero simples por evento;
- renderer publico novo com imagem e video tratados separadamente;
- matriz de modelos por tipo de evento, estilo e comportamento;
- `event_gallery_settings`;
- `event_gallery_revisions`;
- `gallery_presets`;
- builder administrativo por evento;
- `theme_tokens`, `page_schema`, `media_behavior`;
- budget mobile de Web Vitals como criterio de aceite;
- modo rapido e modo profissional;
- preview central mobile/desktop;
- autosave;
- draft/publish;
- preview link compartilhavel;
- restore previous version;
- presets por organizacao;
- assistente de IA com propostas e diff aplicavel;
- guardrails de contraste, reduced motion e banners/interstitials.

## Fica fora da V1

- drag-and-drop livre;
- editor HTML/CSS livre;
- custom CSS;
- posicionamento totalmente livre;
- `Puck`;
- `Easyblocks`;
- `GrapesJS`;
- `masonic` por padrao;
- virtualizacao dedicada antes de volume real justificar;
- IA gerando layout arbitrario.

---

## Estrutura alvo da implementacao

## Backend

```text
apps/api/app/Modules/Gallery/
  Actions/
    CreateGalleryPresetAction.php
    UpdateEventGallerySettingsAction.php
    BuildPublicGalleryPayloadAction.php
    PublishEventGalleryDraftAction.php
    RestoreEventGalleryRevisionAction.php
    AutosaveEventGalleryDraftAction.php
    RunGalleryBuilderPromptAction.php
  Data/
    EventGallerySettingsData.php
    EventGalleryRevisionData.php
    GalleryPresetData.php
    GalleryAiProposalData.php
  Http/
    Controllers/
      GalleryBuilderController.php
      GalleryPresetController.php
      PublicGalleryController.php
    Requests/
      ShowEventGallerySettingsRequest.php
      UpdateEventGallerySettingsRequest.php
      StoreGalleryPresetRequest.php
      RunGalleryBuilderPromptRequest.php
    Resources/
      EventGallerySettingsResource.php
      EventGalleryRevisionResource.php
      GalleryPresetResource.php
      PublicGalleryExperienceResource.php
  Models/
    EventGallerySetting.php
    EventGalleryRevision.php
    GalleryPreset.php
    GalleryBuilderPromptRun.php
  Queries/
    ListGalleryPresetsQuery.php
    ListEventGalleryRevisionsQuery.php
  Support/
    GalleryModelMatrixRegistry.php
    GalleryBuilderPresetRegistry.php
    GalleryThemeTokenResolver.php
    GalleryBuilderSchemaRegistry.php
    GalleryRevisionManager.php
    GalleryResponsiveSourceBuilder.php
    GalleryAiPatchApplier.php
    GalleryAccessibilityGuardService.php
  README.md
  routes/
    api.php
```

## Frontend

```text
apps/web/src/modules/gallery/
  GalleryPage.tsx
  GalleryBuilderPage.tsx
  PublicGalleryPage.tsx
  api.ts
  gallery-builder.ts
  hooks/
    useGalleryBuilderSettings.ts
    useGalleryPresets.ts
    useGalleryRevisions.ts
    useGalleryAiProposals.ts
  components/
    GalleryRenderer.tsx
    GalleryModeSwitch.tsx
    GalleryQuickStartWizard.tsx
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
    GalleryPhotoLightbox.tsx
    GalleryVideoModal.tsx
    blocks/
      WeddingHero.tsx
      QuinceHero.tsx
      CorporateHero.tsx
      GalleryStreamBlock.tsx
      GalleryBannerBlock.tsx
      SponsorsStrip.tsx
      FindMeCta.tsx
      TimelineMoments.tsx
      CeremonyInfo.tsx
      GiftTableInfo.tsx
      PhotoHighlightRail.tsx
      LiveMomentsTicker.tsx
      GalleryQuoteBlock.tsx
      GalleryInfoBlock.tsx
      GalleryFooterBlock.tsx
```

Arquivos transversais que a trilha precisa tocar:

- `apps/web/src/App.tsx`
- `apps/web/src/app/routing/route-preload.ts`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/lib/api-types.ts`
- `apps/web/src/shared/auth/permissions.ts`
- `apps/api/database/seeders/RolesAndPermissionsSeeder.php`
- `docs/modules/module-map.md`

---

## Contrato e rotas alvo da V1

## Rotas admin

- `GET /api/v1/events/{event}/gallery/settings`
- `PATCH /api/v1/events/{event}/gallery/settings`
- `POST /api/v1/events/{event}/gallery/autosave`
- `POST /api/v1/events/{event}/gallery/publish`
- `GET /api/v1/events/{event}/gallery/revisions`
- `POST /api/v1/events/{event}/gallery/revisions/{revision}/restore`
- `POST /api/v1/events/{event}/gallery/preview-link`
- `GET /api/v1/gallery/presets`
- `POST /api/v1/gallery/presets`
- `POST /api/v1/events/{event}/gallery/hero-image`
- `POST /api/v1/events/{event}/gallery/banner-image`
- `POST /api/v1/events/{event}/gallery/ai/proposals`

## Rotas publicas

- manter `GET /api/v1/public/events/{slug}/gallery` com payload aditivo `experience`
- adicionar `GET /api/v1/public/events/{slug}/gallery/media` para o feed longo quando o boot da experience precisar ficar mais leve
- adicionar `GET /api/v1/public/gallery-previews/{token}` para preview compartilhavel

## Rotas frontend

- manter `/e/:slug/gallery` para publico
- adicionar `/events/:id/gallery/builder` para admin

---

## Dependencias e caminho critico

Sequencia critica obrigatoria:

1. congelar contrato de `theme_tokens`, `page_schema` e `media_behavior`;
2. congelar matriz `event_type_family` + `style_skin` + `behavior_profile`;
3. congelar budget mobile e contrato de `responsive_sources`;
4. melhorar renderer publico e payload `experience`;
5. criar settings, revisions e presets no backend;
6. abrir builder admin com preview e versionamento;
7. plugar IA em cima do schema pronto;
8. endurecer acessibilidade, performance e rollout.

Bloqueadores reais:

- decisao sobre permissao `gallery.builder.manage`;
- decisao sobre budget mobile e metodo de medicao;
- contrato de revisao e restore;
- shape versionado das tres camadas;
- shape de `responsive_sources`;
- politica de preview compartilhavel;
- regra de video separada da foto.

Paralelizacao segura:

- frontend pode evoluir `GalleryRenderer` enquanto backend fecha `experience` publico;
- backend pode abrir `event_gallery_settings` e `gallery_presets` enquanto frontend desenvolve a shell do builder com fixtures;
- IA pode ser desenvolvida sobre fixtures e schema antes do provider real.

---

## Regra de TDD obrigatoria

Neste plano, `TDD` significa:

1. escrever o teste da fatia antes da implementacao;
2. ver o teste falhar pelo motivo certo;
3. implementar o minimo para ficar verde;
4. refatorar sem quebrar o contrato;
5. rodar a regressao do modulo adjacente.

Checklist padrao para qualquer task:

- teste de contrato/feature primeiro;
- implementacao minima;
- regressao de `Gallery`, `Hub` e `MediaProcessing` quando aplicavel;
- `type-check` frontend quando houver mudanca web;
- atualizar docs do modulo quando o ownership mudar.

---

## Sprint 0 - Contratos, permissao e baseline do dominio

### Objetivo da sprint

Abrir a fundacao da trilha antes de mexer no renderer publico ou no editor:

- contrato congelado;
- permissao definida;
- rotas e tipos planejados;
- TDD de contrato criado.

### Status da Sprint 0 em 2026-04-12

- [x] `S0-T1` contrato das tres camadas criado em shared-types e espelhado no frontend/backend
- [x] `S0-T1.1` contrato de midia responsiva congelado com `responsive_sources`, `srcset`, `sizes` e variantes por largura
- [x] `S0-T2` permissao `gallery.builder.manage` adicionada em seeder, constantes frontend e mocks
- [x] `S0-T3` rota `/events/:id/gallery/builder`, preload e query keys planejadas/criadas
- [x] `S0-T4` fixtures de experience e matriz de modelos criadas
- [x] `S0-T5` budget mobile e gatilho inicial de renderer otimizado congelados em contrato
- [x] bateria TDD da Sprint 0 executada com regressao de `Gallery`, `Hub`, `MediaProcessing`, `qrReadability`, `runtime-profile` e `MediaAutomaticReplies`

Arquivos principais entregues:

- `packages/shared-types/src/gallery-builder.ts`
- `apps/web/src/modules/gallery/gallery-builder.ts`
- `apps/web/src/modules/gallery/GalleryBuilderPage.tsx`
- `apps/api/app/Modules/Gallery/Support/GalleryBuilderSchemaRegistry.php`
- `apps/api/app/Modules/Gallery/Support/GalleryModelMatrixRegistry.php`
- `apps/api/app/Modules/Gallery/Support/GalleryThemeTokenResolver.php`
- `apps/api/app/Modules/Gallery/Support/GalleryAccessibilityGuardService.php`

### Tarefas da sprint

### `S0-T1` Congelar o contrato das tres camadas

Subtarefas:

- criar `packages/shared-types/src/gallery-builder.ts`;
- definir matriz versionada de entrada:
  - `event_type_family`
  - `style_skin`
  - `behavior_profile`
- definir enums fechados para:
  - `layout_key`
  - `theme_key`
  - `block_key`
  - `video_mode`
  - `density`
  - `interstitial_policy`
- definir shape versionado de:
  - `theme_tokens`
  - `page_schema`
  - `media_behavior`
- definir envelope agregado `experience` do payload publico.

### `S0-T1.1` Congelar contrato de midia responsiva

Subtarefas:

- definir `responsive_sources` no contrato publico;
- definir `srcset`, `sizes` e lista de variantes por largura;
- decidir shape especifico para:
  - imagens
  - poster de video
  - lightbox/foto
  - grid/mobile.

### `S0-T2` Fechar permissao e trilha de acesso

Subtarefas:

- adicionar `gallery.builder.manage` no backend;
- atualizar seeders e roles iniciais;
- adicionar constante no frontend;
- manter compatibilidade temporaria com `gallery.manage` apenas durante rollout, se necessario;
- decidir quem acessa o builder na V1:
  - `super-admin`
  - `platform-admin`
  - `partner-owner`
  - `partner-manager`
  - `event-operator`

### `S0-T3` Planejar rotas e query keys

Subtarefas:

- adicionar `routeImports.galleryBuilder` em `route-preload.ts`;
- registrar `/events/:id/gallery/builder` em `App.tsx`;
- estender `queryKeys.gallery` para:
  - `settings`
  - `presets`
  - `revisions`
  - `preview`

### `S0-T4` Criar fixtures e contratos TDD

Subtarefas:

- criar fixtures de `experience`;
- criar fixtures da matriz de modelos:
  - `wedding + romantic + story`
  - `wedding + premium + light`
  - `quince + modern + live`
  - `corporate + clean + sponsors`
- criar fixtures de IA para patches parciais.

### `S0-T5` Congelar budget mobile e politica de escala

Subtarefas:

- documentar budgets:
  - `LCP <= 2.5s`
  - `INP <= 200ms`
  - `CLS <= 0.1`
- travar que a leitura oficial sera no percentil `75`, segmentada por mobile e desktop;
- definir como medir:
  - synthetic/lab para regressao
  - RUM/campo para aceite real;
- definir gatilho explicito para renderer otimizado/virtualizacao;
- definir desde ja que o frontend suporta separacao entre `boot da experience` e `feed de midia`.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Unit/Gallery/GalleryBuilderSchemaRegistryTest.php`
- `apps/api/tests/Unit/Gallery/GalleryModelMatrixRegistryTest.php`
- `apps/api/tests/Unit/Gallery/GalleryThemeTokenResolverTest.php`
- `apps/api/tests/Unit/Gallery/GalleryAccessibilityGuardServiceTest.php`

Frontend:

- `apps/web/src/modules/gallery/gallery-builder.contract.test.ts`
- `apps/web/src/modules/gallery/gallery-builder.fixtures.test.ts`
- `apps/web/src/modules/gallery/public-gallery-responsive-contract.test.ts`
- `apps/web/src/modules/gallery/gallery-mobile-budget.contract.test.ts`

Resultado executado em `2026-04-12`:

- backend Sprint 0: `13` testes passaram, `103` assertions;
- frontend Sprint 0: `5` arquivos passaram, `11` testes passaram;
- regressao backend `Gallery + MediaProcessing + Hub`: `30` testes passaram, `313` assertions;
- regressao frontend adjacente: `6` arquivos passaram, `25` testes passaram;
- `npm run type-check`: passou.

### Definicao de pronto da sprint

- contrato versionado existe;
- matriz de modelos existe e esta congelada;
- permissao foi definida;
- rotas e query keys ficaram fechadas;
- budget mobile e gatilho de escala foram escritos;
- fixtures e contratos TDD existem antes da implementacao funcional.

---

## Sprint 1 - P0 real: endurecer a galeria publica

### Objetivo da sprint

Entregar valor visivel primeiro:

- sair do CSS columns puro;
- enriquecer o payload publico;
- tratar foto e video corretamente;
- preparar a base do preview futuro.

### Status da Sprint 1 em 2026-04-12

- [x] `S1-T1` `react-photo-album` e `photoswipe` instalados; wrapper proprio escolhido para manter controle do contrato e evitar dependencia extra de wrapper.
- [x] `S1-T2` payload publico estendido com `event`, `experience`, `responsive_sources`, `srcset`, `sizes` e rota incremental `/gallery/media`.
- [x] `S1-T3` renderer publico substituido por `GalleryRenderer` com `react-photo-album`, priorizando `masonry` e `rows`; `columns` ficou apenas suportado como layout menor/editorial.
- [x] `S1-T4` foto e video separados no frontend publico: foto com PhotoSwipe, video com badge e modal/player dedicado.
- [x] `S1-T5` hero com branding, CTA de face search preservado, primeira faixa de midias eager, restante lazy e respeitando reduced motion.
- [ ] `S1-T6` medicao real de LCP/INP/CLS em ambiente de referencia ainda pendente; contrato, renderer e testes ja deixam a pagina pronta para essa medicao sem bloquear a implementacao funcional da Sprint 1.

Arquivos principais entregues:

- `apps/api/app/Modules/Gallery/Actions/BuildPublicGalleryPayloadAction.php`
- `apps/api/app/Modules/Gallery/Http/Resources/PublicGalleryMediaResource.php`
- `apps/api/app/Modules/Gallery/Support/GalleryResponsiveSourceBuilder.php`
- `apps/web/src/modules/gallery/components/GalleryRenderer.tsx`
- `apps/web/src/modules/gallery/components/GalleryPhotoLightbox.tsx`
- `apps/web/src/modules/gallery/components/GalleryVideoModal.tsx`
- `apps/web/src/modules/gallery/PublicGalleryPage.tsx`

### Tarefas da sprint

### `S1-T1` Instalar e travar libs de renderer

Subtarefas:

- instalar `react-photo-album`;
- instalar `photoswipe`;
- avaliar se `react-photoswipe-gallery` entra ou se a equipe prefere wrapper proprio;
- validar import e compatibilidade com `React 18.3.1` e `Vite 5.4.19`.

### `S1-T2` Estender o payload publico da galeria

Subtarefas:

- manter `GET /public/events/{slug}/gallery`;
- preparar `GET /public/events/{slug}/gallery/media` para o feed longo;
- adicionar campos:
  - `event`
  - `experience.version`
  - `experience.theme_key`
  - `experience.layout_key`
  - `experience.theme_tokens`
  - `experience.page_schema`
  - `experience.media_behavior`
- adicionar por item:
  - `responsive_sources.sizes`
  - `responsive_sources.srcset`
  - `responsive_sources.variants[]`
- garantir compatibilidade aditiva do contrato atual.

### `S1-T3` Substituir o renderer publico

Subtarefas:

- criar `GalleryRenderer.tsx`;
- usar `react-photo-album`;
- priorizar `masonry` e `rows`;
- deixar `columns` apenas como preset menor/editorial;
- aplicar defaults por familia:
  - `wedding` -> `masonry` ou `rows`
  - `quince` -> `masonry` ou `rows`
  - `corporate` -> `rows`
- usar breakpoints;
- renderizar badges e overlays via render overrides.

### `S1-T4` Separar foto e video no frontend publico

Subtarefas:

- foto:
  - thumb
  - lightbox com `PhotoSwipe`
- video:
  - poster
  - badge
  - CTA claro
  - modal/player dedicado
- formalizar modos:
  - `poster_only`
  - `poster_to_modal`
  - `inline_preview`
- manter `poster_to_modal` como default mobile;
- manter `thumbnail_url` e `preview_url` coerentes com o backend atual.

### `S1-T5` Melhorar hero, loading e guardrails basicos

Subtarefas:

- criar hero simples com branding do evento;
- manter CTA de face search;
- manter hero e primeira faixa de cards como prioridade visual;
- aplicar `loading="lazy"` abaixo da primeira dobra;
- manter hero/LCP sem lazy;
- manter videos com poster apenas no primeiro paint;
- aplicar `prefers-reduced-motion`;
- validar contraste minimo antes de montar tokens finais do preview.

### `S1-T6` Validar o contrato mobile-first na pratica

Subtarefas:

- medir LCP, INP e CLS em build de referencia;
- verificar efeito de hero, skeleton, lazy loading e chunking;
- confirmar que o renderer ja aceita evoluir para `boot da experience` + `feed de midia`.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/Gallery/PublicGalleryExperiencePayloadTest.php`
- `apps/api/tests/Feature/Gallery/PublicGalleryResponsiveSourcesTest.php`
- `apps/api/tests/Feature/Gallery/PublicGalleryAvailabilityTest.php` atualizado para o contrato aditivo

Frontend:

- `apps/web/src/modules/gallery/PublicGalleryPage.test.tsx`
- `apps/web/src/modules/gallery/GalleryRenderer.test.tsx`
- `apps/web/src/modules/gallery/GalleryPhotoLightbox.test.tsx`
- `apps/web/src/modules/gallery/GalleryVideoModal.test.tsx`
- `apps/web/src/modules/gallery/public-gallery-responsive-sources.test.tsx`
- `apps/web/src/modules/gallery/public-gallery-loading-priority.test.tsx`
- `apps/web/src/modules/gallery/public-gallery-accessibility.test.tsx`

Regressao recomendada:

- `apps/web/src/modules/wall/player/runtime-profile.test.ts`
- `apps/web/src/modules/qr-code/support/qrReadability.test.ts`

Resultado executado em `2026-04-12`:

- backend Sprint 1: `4` testes passaram, `43` assertions;
- frontend Sprint 1: `7` arquivos passaram, `10` testes passaram;
- regressao backend `Gallery + Unit/Gallery + Hub + EventMediaList`: `47` testes passaram, `459` assertions;
- regressao frontend `Gallery + Hub + Journey + IA + qrReadability + runtime-profile`: `18` arquivos passaram, `45` testes passaram;
- `npm run type-check`: passou.

### Definicao de pronto da sprint

- o publico ja percebe a galeria como experiencia melhor, nao so feed;
- payload `experience` existe sem quebrar cliente atual;
- `responsive_sources` existe com contrato claro;
- foto e video estao claramente separados;
- hero, loading e motion basicos estao corretos;
- a trilha mobile-first ja esta ancorada em medicao, nao so em promessa.

---

## Sprint 2 - Backend do builder: settings, revisions e presets

### Objetivo da sprint

Criar a infraestrutura do builder no backend, ainda sem IA:

- settings por evento;
- revisions restauraveis;
- presets por organizacao;
- autosave/publish/restore;
- preview compartilhavel.

### Status da Sprint 2 em `2026-04-12`

- [x] `S2-T1` scaffolding do builder adicionado em `Gallery` com actions, queries, requests, resources e README do modulo
- [x] `S2-T2` persistencia criada com `event_gallery_settings`, `event_gallery_revisions` e `gallery_presets`
- [x] `S2-T3` registries, normalizacao e validacao forte conectados ao contrato separado do builder
- [x] `S2-T4` endpoints admin expostos para settings, autosave, publish, revisions, restore, preview-link, presets, `hero-image` e `banner-image`
- [x] `S2-T5` preview compartilhavel publico entregue em `GET /api/v1/public/gallery-previews/{token}`
- [x] bateria TDD da Sprint 2 executada com cobertura complementar de upload de assets

Arquivos principais entregues:

- `apps/api/app/Modules/Gallery/Models/EventGallerySetting.php`
- `apps/api/app/Modules/Gallery/Models/EventGalleryRevision.php`
- `apps/api/app/Modules/Gallery/Models/GalleryPreset.php`
- `apps/api/app/Modules/Gallery/Actions/UploadEventGalleryAssetAction.php`
- `apps/api/app/Modules/Gallery/Http/Controllers/GalleryBuilderController.php`
- `apps/api/app/Modules/Gallery/Support/GalleryBuilderPresetRegistry.php`
- `apps/api/app/Modules/Gallery/Support/GalleryRevisionManager.php`
- `apps/api/app/Modules/Gallery/Support/GalleryBuilderAssetUrlResolver.php`

### Tarefas da sprint

### `S2-T1` Scaffolding do builder dentro de `Gallery`

Subtarefas:

- criar actions, queries, requests e resources do builder;
- criar `README.md` do modulo `Gallery` com a nova camada builder;
- atualizar `docs/modules/module-map.md`.

### `S2-T2` Criar persistencia

Subtarefas:

- migration `event_gallery_settings`;
- migration `event_gallery_revisions`;
- migration `gallery_presets`;
- campos minimos em `event_gallery_settings`:
  - `event_id`
  - `is_enabled`
  - `event_type_family`
  - `style_skin`
  - `behavior_profile`
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
- campos minimos em `event_gallery_revisions`:
  - `event_id`
  - `version_number`
  - `kind`
  - `theme_tokens_json`
  - `page_schema_json`
  - `media_behavior_json`
  - `change_summary_json`
  - `created_by`
- campos minimos em `gallery_presets`:
  - `organization_id`
  - `source_event_id`
  - `name`
  - `slug`
  - `description`
  - `event_type_family`
  - `style_skin`
  - `behavior_profile`
  - `layout_key`
  - `theme_key`
  - `theme_tokens_json`
  - `page_schema_json`
  - `media_behavior_json`
  - `derived_preset_key`

### `S2-T3` Implementar registry, normalizacao e validacao

Subtarefas:

- criar `GalleryBuilderPresetRegistry`;
- criar `GalleryModelMatrixRegistry`;
- criar `GalleryBuilderSchemaRegistry`;
- criar `GalleryThemeTokenResolver`;
- criar `GalleryRevisionManager`;
- validar:
  - contraste minimo
  - reduced motion
  - maximo de banners/interstitials
  - blocos obrigatorios
  - enums fechados

### `S2-T4` Expor endpoints admin

Subtarefas:

- `GET/PATCH /events/{event}/gallery/settings`
- `POST /events/{event}/gallery/autosave`
- `POST /events/{event}/gallery/publish`
- `GET /events/{event}/gallery/revisions`
- `POST /events/{event}/gallery/revisions/{revision}/restore`
- `POST /events/{event}/gallery/preview-link`
- `GET/POST /gallery/presets`
- `POST /events/{event}/gallery/hero-image`
- `POST /events/{event}/gallery/banner-image`

### `S2-T5` Expor preview compartilhavel

Subtarefas:

- implementar `GET /api/v1/public/gallery-previews/{token}`;
- token resolve uma revisao draft;
- resposta segue mesmo shape do payload publico;
- token com expiracao e revogacao por novo publish/restoration quando necessario.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/Gallery/EventGallerySettingsTest.php`
- `apps/api/tests/Feature/Gallery/EventGalleryRevisionLifecycleTest.php`
- `apps/api/tests/Feature/Gallery/GalleryPresetManagementTest.php`
- `apps/api/tests/Feature/Gallery/PublicGalleryPreviewTokenTest.php`
- `apps/api/tests/Feature/Gallery/EventGalleryAssetUploadTest.php`
- `apps/api/tests/Unit/Gallery/GalleryModelMatrixRegistryTest.php`
- `apps/api/tests/Unit/Gallery/GalleryBuilderPresetRegistryTest.php`
- `apps/api/tests/Unit/Gallery/GalleryRevisionManagerTest.php`
- `apps/api/tests/Unit/Gallery/GalleryAccessibilityGuardServiceTest.php`

Regressao recomendada:

- `apps/api/tests/Feature/Gallery/GalleryAdminWorkflowTest.php`
- `apps/api/tests/Feature/Hub`

Resultado executado em `2026-04-12`:

- backend Sprint 2: `18` testes passaram, `209` assertions;
- regressao backend `Gallery + Unit/Gallery + Hub + EventMediaList`: `58` testes passaram, `618` assertions.

### Definicao de pronto da sprint

- backend do builder existe e persiste;
- draft/publish/autosave/restore funcionam;
- presets existem por organizacao;
- preview compartilhavel resolve uma revisao real;
- validacao forte ja impede configuracao fragil.

---

## Sprint 3 - Frontend do builder: modo rapido, profissional e preview central

### Objetivo da sprint

Entregar o builder administrativo utilizavel por operador e por usuario leigo.

### Status da Sprint 3 em `2026-04-12`

- [x] `S3-T1` rota e shell do builder entregues em `/events/:id/gallery/builder`, com carregamento real de settings, presets, revisions e media do evento
- [x] `S3-T2` modo rapido entregue com `GalleryModeSwitch`, `GalleryQuickStartWizard`, `GalleryQuickSetupRail` e atalhos de vibe orientados pela matriz do contrato
- [x] `S3-T3` modo profissional entregue com `GalleryPresetRail`, `GalleryThemePanel`, `GalleryBlocksPanel` e `GalleryContextInspector`
- [x] `S3-T4` preview central entregue com `GalleryPreviewFrame`, `GalleryPreviewToolbar`, alternancia mobile/desktop e reuse do `GalleryRenderer` publico
- [x] `S3-T5` revisoes e versionamento integrados no frontend com `GalleryRevisionPanel`, visibilidade de `draft_version`/`published_version`, restore, publish, autosave e preview link compartilhavel
- [x] bateria TDD da Sprint 3 executada com regressao de `Gallery`, `Hub`, `Journey`, `MediaAutomaticReplies`, `qrReadability`, `runtime-profile` e `type-check`

Arquivos principais entregues:

- `apps/web/src/modules/gallery/GalleryBuilderPage.tsx`
- `apps/web/src/modules/gallery/api.ts`
- `apps/web/src/modules/gallery/gallery-builder.ts`
- `apps/web/src/modules/gallery/hooks/useGalleryBuilderSettings.ts`
- `apps/web/src/modules/gallery/hooks/useGalleryPresets.ts`
- `apps/web/src/modules/gallery/hooks/useGalleryRevisions.ts`
- `apps/web/src/modules/gallery/components/GalleryPreviewFrame.tsx`
- `apps/web/src/modules/gallery/components/GalleryRevisionPanel.tsx`

### Tarefas da sprint

### `S3-T1` Abrir rota e shell do builder

Subtarefas:

- criar `GalleryBuilderPage.tsx`;
- registrar `/events/:id/gallery/builder` em `App.tsx`;
- adicionar preload em `route-preload.ts`;
- preparar entrada no detalhe do evento e/ou `GalleryPage`.

### `S3-T2` Criar o modo rapido

Subtarefas:

- `GalleryModeSwitch`;
- `GalleryQuickStartWizard`;
- `GalleryQuickSetupRail`;
- `GalleryVibeShortcuts`;
- fluxo guiado:
  - escolher tipo do evento
  - escolher vibe
  - aplicar `Comecar com base do evento`
  - ajustar capa/logo
  - revisar cores sugeridas
  - revisar preview mobile
  - publicar

### `S3-T3` Criar o modo profissional

Subtarefas:

- `GalleryPresetRail`;
- `GalleryThemePanel`;
- `GalleryBlocksPanel`;
- `GalleryContextInspector`;
- expor variantes orientadas a dominio:
  - `WeddingHero`
  - `QuinceHero`
  - `CorporateHero`
  - `SponsorsStrip`
  - `FindMeCta`
  - `TimelineMoments`
- exposicao de ordem de blocos, densidade, video, interstitials e CTA;
- inspector contextual por bloco/area clicada.

### `S3-T4` Tornar o preview o protagonista

Subtarefas:

- criar `GalleryPreviewFrame`;
- criar `GalleryPreviewToolbar`;
- alternancia mobile/desktop;
- preview sempre visivel;
- preview usando o mesmo `GalleryRenderer` do publico;
- mostrar estado de autosave, draft e publish.

### `S3-T5` Integrar revisoes e versionamento no frontend

Subtarefas:

- criar `GalleryRevisionPanel`;
- mostrar `draft_version` e `published_version`;
- acao de restore;
- acao de gerar preview link;
- diff simples entre versoes em metadata e camadas alteradas.

### TDD obrigatorio da sprint

Frontend:

- `apps/web/src/modules/gallery/GalleryBuilderPage.test.tsx`
- `apps/web/src/modules/gallery/GalleryModeSwitch.test.tsx`
- `apps/web/src/modules/gallery/GalleryQuickStartWizard.test.tsx`
- `apps/web/src/modules/gallery/GalleryQuickSetupRail.test.tsx`
- `apps/web/src/modules/gallery/GalleryContextInspector.test.tsx`
- `apps/web/src/modules/gallery/GalleryPreviewFrame.test.tsx`
- `apps/web/src/modules/gallery/GalleryRevisionPanel.test.tsx`
- `apps/web/src/modules/gallery/gallery-builder-route.test.tsx`

Regressao recomendada:

- `apps/web/src/modules/hub/PublicHubPage.test.tsx`
- `apps/web/src/modules/events/event-media-flow-builder-architecture-characterization.test.ts`

Resultado executado em `2026-04-12`:

- frontend Sprint 3: `8` arquivos passaram, `10` testes passaram;
- regressao frontend `Gallery`: `20` arquivos passaram, `31` testes passaram;
- regressao frontend `Hub + Journey + IA + qrReadability + runtime-profile`: `5` arquivos passaram, `23` testes passaram;
- `npx.cmd tsc --noEmit`: passou.

### Definicao de pronto da sprint

- builder admin existe em rota propria;
- modo rapido e profissional funcionam;
- preview central usa renderer real;
- autosave, draft/publish e restore ficam visiveis e usaveis;
- a UX ja reduz o medo de "estragar".

---

## Sprint 4 - IA guardrailed com propostas, diff e aplicacao parcial

### Objetivo da sprint

Plugar IA depois que schema, presets e versionamento ja existem.

### Status da Sprint 4 em `2026-04-12`

- [x] `S4-T1` persistencia de execucoes de IA criada com `gallery_builder_prompt_runs`, model `GalleryBuilderPromptRun` e factory dedicada
- [x] `S4-T2` backend de propostas entregue com `RunGalleryBuilderPromptAction`, `GalleryAiPatchApplier`, schema JSON guardrailed e payload OpenAI-compatible com `json_schema`
- [x] `S4-T3` UI entregue com `GalleryAiVariationsPanel`, exibicao de `3` variacoes, apply parcial por camada e autosave no draft
- [x] `S4-T4` guardrails endurecidos para bloquear HTML/CSS/JSX, campos fora do catalogo, contraste inseguro e publish sem preview compartilhavel depois de apply de IA
- [x] bateria TDD da Sprint 4 executada com regressao backend/frontend do modulo e checagem adjacente

Arquivos principais entregues:

- `apps/api/app/Modules/Gallery/Actions/RunGalleryBuilderPromptAction.php`
- `apps/api/app/Modules/Gallery/Models/GalleryBuilderPromptRun.php`
- `apps/api/app/Modules/Gallery/Support/GalleryAiPatchApplier.php`
- `apps/api/app/Modules/Gallery/Support/GalleryBuilderPromptSchemaFactory.php`
- `apps/api/app/Modules/Gallery/Support/GalleryAiProposalGenerator.php`
- `apps/web/src/modules/gallery/components/GalleryAiVariationsPanel.tsx`
- `apps/web/src/modules/gallery/hooks/useGalleryAiProposals.ts`
- `apps/web/src/modules/gallery/gallery-ai-partial-apply.test.tsx`

### Tarefas da sprint

### `S4-T1` Criar persistencia de execucoes de IA

Subtarefas:

- migration `gallery_builder_prompt_runs`;
- model `GalleryBuilderPromptRun`;
- campos minimos:
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

### `S4-T2` Criar backend de propostas de IA

Subtarefas:

- criar `RunGalleryBuilderPromptAction`;
- criar `GalleryAiPatchApplier`;
- reaproveitar padrao de `MediaIntelligence`;
- montar payload OpenAI-compatible com `json_schema`;
- incluir no contexto:
  - draft atual
  - branding do evento
  - tipo de evento
  - `event_type_family`
  - `style_skin`
  - `behavior_profile`
  - persona
  - modelos disponiveis
  - guardrails
- gerar `3` variacoes seguras;
- validar patch por camada.

### `S4-T3` Entregar UI de IA

Subtarefas:

- criar `GalleryAiVariationsPanel`;
- exibir `3` variacoes com `summary`;
- exibir diff/preview por variacao;
- permitir aplicar:
  - so paleta
  - so hero + textos
  - so media behavior
  - tudo
- salvar aplicacao como revisao/autosave.

### `S4-T4` Endurecer guardrails de IA

Subtarefas:

- bloquear HTML/CSS/JSX livre;
- bloquear campos fora do catalogo;
- bloquear excesso de banners/interstitials;
- bloquear tokens sem contraste minimo;
- garantir preview antes de publish quando patch vier da IA.

### TDD obrigatorio da sprint

Backend:

- `apps/api/tests/Feature/Gallery/GalleryAiProposalsTest.php`
- `apps/api/tests/Unit/Gallery/GalleryAiPatchApplierTest.php`
- `apps/api/tests/Unit/Gallery/GalleryBuilderPromptSchemaTest.php`

Frontend:

- `apps/web/src/modules/gallery/GalleryAiVariationsPanel.test.tsx`
- `apps/web/src/modules/gallery/gallery-ai-partial-apply.test.tsx`
- `apps/web/src/modules/gallery/GalleryBuilderPage.test.tsx` cobrindo apply IA + autosave

Observacao:

- se houver smoke test de provider real, ele deve nascer como opt-in, nunca como dependencia da suite comum.

Resultado executado em `2026-04-12`:

- backend Sprint 4: `6` testes passaram, `57` assertions;
- regressao backend `Gallery + Unit/Gallery`: `43` testes passaram, `443` assertions;
- regressao backend `Hub + EventMediaList`: `21` testes passaram, `232` assertions;
- frontend Sprint 4: `3` arquivos passaram, `6` testes passaram;
- regressao frontend `Gallery`: `22` arquivos passaram, `34` testes passaram;
- regressao frontend `Hub + Journey + IA + qrReadability + runtime-profile`: `5` arquivos passaram, `23` testes passaram;
- `npx.cmd tsc --noEmit`: passou.

### Definicao de pronto da sprint

- IA propoe, nao gera pagina livre;
- `3` variacoes seguras aparecem com diff;
- aplicacao parcial funciona;
- patchs ruins morrem no backend antes de afetar o draft.

---

## Sprint 5 - Hardening de acessibilidade, performance, rollout e analytics operacional

### Objetivo da sprint

Fechar a V1 como superficie confiavel de produto, nao so como builder funcional.

### Status da Sprint 5 em `2026-04-12`

- [x] `S5-T1` acessibilidade endurecida com `role="status"`, `role="alert"`, labels/regions explicitas, restore visivel e reduced motion efetivo no builder e na preview
- [x] `S5-T2` performance endurecida com `optimized_renderer_trigger` no boot, `render_mode = standard|optimized`, `content-visibility` real, reuse de `MediaVirtualFeed` e telemetry de vitals
- [x] `S5-T3` rollout/documentacao/operacao fechados com README do modulo, module map, flow operacional e CTA explicito a partir do detalhe do evento
- [x] `S5-T4` analytics operacional fechada com telemetry autenticada, tracking backend de publish/restore e feedback persistido de preset/IA/revisoes
- [x] bateria TDD da Sprint 5 executada com regressao final backend/frontend e `type-check`

Arquivos principais entregues:

- `apps/api/app/Modules/Gallery/Http/Controllers/GalleryBuilderController.php`
- `apps/api/app/Modules/Gallery/Http/Requests/StoreGalleryBuilderTelemetryRequest.php`
- `apps/api/app/Modules/Gallery/Http/Resources/EventGallerySettingsResource.php`
- `apps/api/app/Modules/Gallery/Support/GalleryBuilderOperationalFeedbackResolver.php`
- `apps/api/database/migrations/2026_04_12_210000_add_current_preset_origin_to_event_gallery_settings_table.php`
- `apps/web/src/modules/gallery/GalleryBuilderPage.tsx`
- `apps/web/src/modules/gallery/components/GalleryPreviewFrame.tsx`
- `apps/web/src/modules/gallery/components/GalleryPreviewToolbar.tsx`
- `apps/web/src/modules/gallery/components/GalleryRevisionPanel.tsx`
- `apps/web/src/modules/gallery/components/GalleryRenderer.tsx`
- `apps/web/src/modules/gallery/hooks/useGalleryReducedMotion.ts`
- `apps/web/src/modules/events/EventDetailPage.tsx`
- `docs/flows/gallery-builder-operations.md`

### Tarefas da sprint

### `S5-T1` Endurecer acessibilidade e motion

Subtarefas:

- validar contraste de texto normal, grande e UI;
- respeitar `prefers-reduced-motion`;
- auditar foco, dialog, drawer e preview mobile/desktop;
- manter restore sempre acessivel.

### `S5-T2` Endurecer performance

Subtarefas:

- medir eventos grandes em rows e masonry;
- medir `LCP`, `INP` e `CLS` por mobile e desktop;
- usar `content-visibility: auto` onde fizer sentido;
- revisar lazy loading, decode e poster/video startup;
- confirmar threshold real para renderer otimizado/virtualizacao;
- se necessario, reaproveitar padrao de `MediaVirtualFeed` antes de adicionar dependencia nova.

### `S5-T3` Fechar rollout, docs e operacao

Subtarefas:

- atualizar `README.md` do modulo `Gallery`;
- atualizar `docs/modules/module-map.md`;
- atualizar `docs/flows/` se a galeria publica ganhar narrativa oficial nova;
- documentar runbook de:
  - publish
  - restore
  - preview compartilhavel
  - fallback sem IA

### `S5-T4` Analytics operacional do builder

Subtarefas:

- registrar eventos de admin:
  - preset aplicado
  - IA aplicada
  - publish executado
  - restore executado
- expor feedback minimo no builder:
  - ultimo publish
  - ultima IA aplicada
  - origem do preset atual

### TDD obrigatorio da sprint

Frontend:

- `apps/web/src/modules/gallery/gallery-builder-accessibility.test.tsx`
- `apps/web/src/modules/gallery/gallery-builder-performance.contract.test.ts`
- `apps/web/src/modules/gallery/GalleryPreviewFrame.test.tsx` cobrindo reduced motion

Backend:

- `apps/api/tests/Feature/Gallery/GalleryBuilderOperationalAnalyticsTest.php`
- `apps/api/tests/Feature/Gallery/EventGalleryRevisionLifecycleTest.php` ampliado para restore/publish final

Resultado executado em `2026-04-12`:

- backend Sprint 5 direcionado: `4` testes passaram, `100` assertions;
- regressao backend `Gallery + Unit/Gallery`: `46` testes passaram, `501` assertions;
- regressao backend `Hub + EventMediaList`: `21` testes passaram, `232` assertions;
- regressao frontend `Gallery`: `24` arquivos passaram, `40` testes passaram;
- regressao frontend `Hub + Journey + IA + qrReadability + runtime-profile`: `5` arquivos passaram, `23` testes passaram;
- `npx.cmd tsc --noEmit`: passou.

### Definicao de pronto da sprint

- a V1 esta acessivel, performatica e operavel;
- rollout pode ser controlado por permissao/feature flag se necessario;
- docs e runbook estao atualizados;
- o time consegue suportar a feature sem conhecimento implicito.

---

## Sprint 6 - Spike futuro: authoring visual com `Puck`

### Objetivo da sprint

Avaliar `Puck` apenas se a V1 ja tiver mostrado demanda real por drag-and-drop mais livre.

### Regras dessa fase

- nao mudar o contrato do dominio;
- manter `theme_tokens`, `page_schema` e `media_behavior`;
- usar `Puck` como authoring layer, nao como fonte de verdade;
- sair facilmente se o custo de integracao for maior do que o ganho.

Criterio minimo para abrir essa fase:

- backlog real de nesting/authoring mais livre que o builder atual nao resolva;
- prova de que wizard, inspector contextual e matriz de modelos nao bastam;
- manutencao do contrato interno sem vendor lock-in.

### TDD minimo da fase

- caracterizacao do roundtrip `Puck -> schema interno -> preview`;
- testes de nao regressao do builder atual;
- testes de bloqueio de saida fora do catalogo.

### Definicao de pronto da fase

- ou `Puck` prova valor sem quebrar o dominio;
- ou fica descartado com decisao documentada.

---

## Bateria TDD consolidada

## Backend

Suite nova esperada:

```text
apps/api/tests/Feature/Gallery/
  PublicGalleryExperiencePayloadTest.php
  PublicGalleryResponsiveSourcesTest.php
  EventGalleryAssetUploadTest.php
  EventGallerySettingsTest.php
  EventGalleryRevisionLifecycleTest.php
  GalleryPresetManagementTest.php
  PublicGalleryPreviewTokenTest.php
  GalleryAiProposalsTest.php
  GalleryBuilderOperationalAnalyticsTest.php

apps/api/tests/Unit/Gallery/
  GalleryModelMatrixRegistryTest.php
  GalleryBuilderPresetRegistryTest.php
  GalleryBuilderSchemaRegistryTest.php
  GalleryThemeTokenResolverTest.php
  GalleryRevisionManagerTest.php
  GalleryAiPatchApplierTest.php
  GalleryBuilderPromptSchemaTest.php
  GalleryAccessibilityGuardServiceTest.php
```

## Frontend

Suite nova esperada:

```text
apps/web/src/modules/gallery/
  PublicGalleryPage.test.tsx
  GalleryRenderer.test.tsx
  GalleryPhotoLightbox.test.tsx
  GalleryVideoModal.test.tsx
  GalleryBuilderPage.test.tsx
  GalleryModeSwitch.test.tsx
  GalleryQuickStartWizard.test.tsx
  GalleryQuickSetupRail.test.tsx
  GalleryContextInspector.test.tsx
  GalleryPreviewFrame.test.tsx
  GalleryRevisionPanel.test.tsx
  GalleryAiVariationsPanel.test.tsx
  gallery-builder.contract.test.ts
  gallery-builder.fixtures.test.ts
  public-gallery-responsive-contract.test.ts
  public-gallery-responsive-sources.test.tsx
  public-gallery-loading-priority.test.tsx
  gallery-mobile-budget.contract.test.ts
  gallery-builder-route.test.tsx
  gallery-builder-accessibility.test.tsx
  gallery-builder-performance.contract.test.ts
  gallery-ai-partial-apply.test.tsx
```

## Comandos de regressao que devem acompanhar a trilha

Backend:

```bash
cd apps/api
php artisan test tests/Feature/Gallery tests/Unit/Gallery
php artisan test tests/Feature/Hub tests/Feature/MediaProcessing/EventMediaListTest.php
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/gallery
npx.cmd vitest run src/modules/hub/PublicHubPage.test.tsx src/modules/events/event-media-flow-builder-architecture-characterization.test.ts src/modules/ai/MediaAutomaticRepliesPage.test.tsx src/modules/qr-code/support/qrReadability.test.ts src/modules/wall/player/runtime-profile.test.ts
npx.cmd tsc --noEmit
```

---

## Ordem recomendada de execucao dentro da sprint

Para qualquer sprint desta trilha:

1. escrever testes de contrato da fatia;
2. congelar tipos, fixtures e normalizadores;
3. implementar backend ou renderer minimo;
4. implementar UI por cima de contrato ja verde;
5. rodar regressao de `Gallery`, `Hub`, `MediaProcessing` e acessibilidade adjacente;
6. atualizar docs e README.

Regra pratica:

- nunca implementar IA antes de versionamento e restore;
- nunca abrir drag-and-drop antes de schema maduro;
- nunca tratar video como se fosse foto em lightbox;
- nunca permitir configuracao livre fora do catalogo.

---

## Checklist de homologacao da V1

- [x] payload publico `experience` existe e e compativel
- [x] `responsive_sources`, `srcset` e `sizes` existem
- [x] galeria publica usa renderer novo
- [x] foto usa lightbox e video usa modal/player proprio
- [x] `event_gallery_settings` existe
- [x] `event_gallery_revisions` existe
- [x] `gallery_presets` existe
- [x] builder admin em `/events/:id/gallery/builder`
- [x] `theme_tokens`, `page_schema` e `media_behavior` separados
- [x] matriz `event_type_family` + `style_skin` + `behavior_profile` existe
- [x] modo rapido e modo profissional existem
- [x] modo rapido existe como wizard guiado
- [x] autosave funciona
- [x] `draft_version` e `published_version` estao visiveis
- [x] preview link compartilhavel funciona
- [x] restore previous version funciona
- [x] IA gera `3` variacoes seguras e aplicaveis
- [x] contraste minimo e reduced motion estao endurecidos
- [x] budgets mobile de Web Vitals estao medidos
- [x] bateria TDD esta verde
- [x] docs do modulo e runbook foram atualizados

---

## Definicao de pronto da V1

A V1 pode ser considerada pronta quando:

1. a galeria publica deixou de ser percebida como grade tecnica de thumbs e passou a ser experiencia editorial configuravel;
2. o produto opera por matriz de modelos de evento, estilo e comportamento, e nao por presets tecnicos opacos para o usuario;
3. um operador consegue montar, revisar, publicar e restaurar a experiencia sem medo de quebrar o que ja estava publicado;
4. uma noiva ou operador leigo consegue usar o modo rapido em formato de wizard com seguranca;
5. a IA acelera a configuracao sem abrir risco estrutural;
6. a equipe consegue evoluir o produto sem depender de page builder externo.

Em uma frase:

**a V1 fecha quando a galeria deixa de ser apenas um feed publicado e vira um sistema mobile-first de modelos de galeria, seguro, versionado, responsivo e facil de operar.**
