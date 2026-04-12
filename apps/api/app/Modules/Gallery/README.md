# Gallery Module

## Responsabilidade
Galeria publica, curadoria administrativa e builder versionado da experiencia de galeria por evento.

## Camadas atuais

- catalogo admin e acoes de curadoria;
- payload publico `event + experience + media`;
- builder backend com settings por evento;
- revisions para `draft/publish/autosave/restore`;
- presets reutilizaveis por organizacao;
- preview compartilhavel baseado em token e revisao draft.
- assistente de IA guardrailed com `3` variacoes aplicaveis em JSON.
- telemetry operacional do builder persistida em `analytics_events`.

## Rollout da V1

- o acesso ao builder segue controlado apenas por permissao `gallery.builder.manage`;
- a V1 nao abre feature flag adicional para `Gallery`;
- a publicacao da galeria continua independente da IA: o fluxo de preset, ajustes manuais, autosave, preview e publish funciona sem `gallery/ai/proposals`.

## Budgets e operacao da V1

- budget mobile da experiencia publica e do preview admin:
  - `LCP <= 2500ms`
  - `INP <= 200ms`
  - `CLS <= 0.1`
- o boot do builder expõe `optimized_renderer_trigger` como fonte de verdade do threshold para `render_mode = standard|optimized`;
- o renderer usa virtualizacao reaproveitando o padrao de `MediaVirtualFeed` quando o volume do evento cruza o threshold;
- a coleta de Web Vitals da Sprint 5 fica limitada ao builder admin e entra como telemetry leve, sem dashboard novo nesta fase.

## Rotas

### Curadoria/admin

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/api/v1/gallery` | Catalogo admin da galeria com filtros |
| GET | `/api/v1/events/{id}/gallery` | Listar galeria do evento |
| POST | `/api/v1/events/{id}/gallery/{media}/publish` | Publicar item aprovado |
| POST | `/api/v1/events/{id}/gallery/{media}/feature` | Alternar destaque |
| DELETE | `/api/v1/events/{id}/gallery/{media}` | Ocultar/remover da galeria |

### Builder

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/api/v1/events/{id}/gallery/settings` | Boot do builder com settings normalizados |
| PATCH | `/api/v1/events/{id}/gallery/settings` | Atualizar `theme_tokens`, `page_schema` e `media_behavior` |
| POST | `/api/v1/events/{id}/gallery/autosave` | Gerar revisao `autosave` e apontar draft atual |
| POST | `/api/v1/events/{id}/gallery/publish` | Gerar revisao `published` e promover para publico |
| GET | `/api/v1/events/{id}/gallery/revisions` | Listar historico de revisoes |
| POST | `/api/v1/events/{id}/gallery/revisions/{revision}/restore` | Restaurar revisao anterior para o draft |
| POST | `/api/v1/events/{id}/gallery/preview-link` | Gerar token/link de preview draft |
| POST | `/api/v1/events/{id}/gallery/hero-image` | Upload direto da imagem principal do hero |
| POST | `/api/v1/events/{id}/gallery/banner-image` | Upload direto da imagem do banner/interstitial |
| POST | `/api/v1/events/{id}/gallery/ai/proposals` | Gerar `3` propostas guardrailed e aplicaveis de IA |
| POST | `/api/v1/events/{id}/gallery/telemetry` | Registrar telemetry operacional do builder (`preset`, `IA`, `vitals`) |
| GET | `/api/v1/gallery/presets` | Listar presets da organizacao ativa |
| POST | `/api/v1/gallery/presets` | Salvar preset reutilizavel da organizacao |

### Publico

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/api/v1/public/events/{slug}/gallery` | Boot da galeria publica com `experience` |
| GET | `/api/v1/public/events/{slug}/gallery/media` | Feed incremental de midia publica |
| GET | `/api/v1/public/gallery-previews/{token}` | Preview publico de uma revisao draft |

## Modelos principais

- `EventGallerySetting`
- `EventGalleryRevision`
- `GalleryPreset`
- `GalleryBuilderPromptRun`

## Dependencias

- `Events`
- `MediaProcessing`
- `Analytics`
- `Organizations`
- `Users`

## Observacoes de arquitetura

- a fonte de verdade do builder fica separada em `theme_tokens`, `page_schema` e `media_behavior`;
- publico usa apenas `published_version`; draft fica isolado para preview compartilhavel;
- preview tokenizado resolve uma revisao autosalva especifica;
- `GalleryBuilderPresetRegistry` centraliza defaults, matriz de modelos e normalizacao;
- `GalleryRevisionManager` garante versionamento monotonicamente crescente por evento.
- a IA do builder retorna JSON validado por schema e nunca HTML, CSS ou JSX livre.
- `current_preset_origin_json` em `event_gallery_settings` preserva a origem persistida do preset/atalho atual e alimenta o feedback operacional da UI;
- `GalleryBuilderOperationalFeedbackResolver` agrega `current_preset_origin`, `last_ai_application`, `last_publish` e `last_restore` para o boot do builder;
- `AnalyticsTracker` registra eventos do builder em `analytics_events` com `channel = gallery_builder`:
  - `gallery.builder_preset_applied`
  - `gallery.builder_ai_applied`
  - `gallery.builder_published`
  - `gallery.builder_restored`
  - `gallery.builder_vitals_sample`
