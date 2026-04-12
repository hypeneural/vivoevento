# Gallery Builder Operations Flow

## Objetivo

Consolidar o fluxo operacional minimo da V1 do builder da galeria para suporte, rollout e troubleshooting.

Escopo desta V1:

- publish do draft atual;
- restore de revisoes anteriores;
- preview compartilhavel da revisao draft;
- fallback sem IA;
- leitura basica da telemetry operacional do builder.

## Rollout e acesso

- o builder admin fica em `/events/:id/gallery/builder`;
- o acesso e controlado apenas por `gallery.builder.manage`;
- a V1 nao usa feature flag separada para liberar ou esconder a superficie.

## Fluxo normal de publicacao

1. O operador abre o builder do evento.
2. O boot carrega `settings`, `revisions`, `mobile_budget`, `optimized_renderer_trigger` e `operational_feedback`.
3. O operador ajusta a experiencia por atalho, preset, wizard, modo profissional ou IA.
4. O draft e salvo via `PATCH /gallery/settings` + `POST /gallery/autosave`.
5. O operador gera um preview compartilhavel quando precisa revisar o draft fora do painel.
6. O operador publica com `POST /gallery/publish`.
7. O publico continua lendo apenas `published_version`.

## Preview compartilhavel

- rota: `POST /api/v1/events/{event}/gallery/preview-link`
- resultado: token temporario para `GET /api/v1/public/gallery-previews/{token}`
- o preview sempre representa uma revisao draft/autosave, nunca muda o publicado por si so
- depois de aplicar uma variacao de IA, a UI exige gerar preview antes do publish

## Restore de revisao

- rota: `POST /api/v1/events/{event}/gallery/revisions/{revision}/restore`
- o restore cria nova revisao do tipo `restore` e reposiciona o draft
- o restore nao muda `published_version` imediatamente
- a UI deve continuar mostrando:
  - ultima restauracao
  - versao restaurada
  - origem persistida do preset atual

## Fallback sem IA

Todo o fluxo essencial da V1 continua suportado sem `POST /gallery/ai/proposals`.

O operador ainda consegue:

- aplicar atalhos de vibe;
- usar wizard rapido;
- editar `theme_tokens`, `page_schema` e `media_behavior`;
- autosalvar;
- gerar preview compartilhavel;
- publicar;
- restaurar revisoes.

Se a IA estiver indisponivel:

- o builder nao deve bloquear o draft;
- apenas a acao de gerar propostas fica degradada;
- a UI deve manter feedback claro sem esconder publish/restore.

## Telemetry operacional

### Endpoint

- `POST /api/v1/events/{event}/gallery/telemetry`

### Eventos aceitos

- `preset_applied`
- `ai_applied`
- `vitals_sample`

### Persistencia

- store oficial: `analytics_events`
- canal: `gallery_builder`

### Nomes rastreados

- `gallery.builder_preset_applied`
- `gallery.builder_ai_applied`
- `gallery.builder_published`
- `gallery.builder_restored`
- `gallery.builder_vitals_sample`

## Leitura basica dos analytics

O minimo a verificar em suporte operacional:

1. Se o operador aplicou preset e qual foi a origem persistida.
2. Se uma variacao de IA foi aplicada e qual `variation_id` foi marcada em `gallery_builder_prompt_runs.selected_variation_id`.
3. Quando aconteceu o ultimo publish.
4. Quando aconteceu o ultimo restore.
5. Se o builder esta amostrando `LCP`, `INP`, `CLS`, `viewport`, `item_count` e `render_mode`.

## Sinais de feedback no boot do builder

`operational_feedback` deve expor:

- `current_preset_origin`
- `last_ai_application`
- `last_publish`
- `last_restore`

O boot tambem deve expor:

- `optimized_renderer_trigger`

Esses dados devem permanecer estaveis entre reloads e resyncs do builder.

## Troubleshooting rapido

### Publish bloqueado apos IA

- esperado quando uma variacao de IA acabou de ser aplicada sem preview novo
- acao: gerar preview compartilhavel e tentar publicar novamente

### Origem do preset sumiu apos reload

- verificar `event_gallery_settings.current_preset_origin_json`
- verificar resposta de `GET /gallery/settings`

### Variacao de IA aplicada sem rastro

- verificar `analytics_events` com `channel = gallery_builder`
- verificar `gallery_builder_prompt_runs.selected_variation_id`

### Builder pesado em eventos grandes

- verificar `item_count` no boot e o `optimized_renderer_trigger`
- confirmar que o preview entrou em `render_mode = optimized`
- revisar amostras de `gallery.builder_vitals_sample`
