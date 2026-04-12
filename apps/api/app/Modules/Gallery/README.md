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
