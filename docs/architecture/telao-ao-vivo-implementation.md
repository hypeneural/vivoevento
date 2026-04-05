# Implementacao do Telao ao Vivo

## Objetivo

Este documento traduz a visao de produto do telao ao vivo para uma implementacao concreta no `eventovivo`.

Ele cobre:

- arquitetura alvo do modulo `Wall`;
- runtime do player;
- fluxo realtime e WebSocket;
- uso de filas;
- configuracao administrativa em `/events/:id/wall`;
- simulacao, diagnostico e observabilidade.

Este documento parte de um principio central:

- o backend define identidade, elegibilidade, configuracao e diagnostico agregado;
- o player decide o proximo slide em runtime;
- o painel administrativo deve ser preset-first, com progressive disclosure.

---

## Escopo

## Dentro do escopo

- fairness por remetente com `sender_key` estavel;
- selector configuravel por preset e por regras simples;
- runtime resiliente com `assetStatus`, cache e fallback;
- comportamento adaptativo por volume e fase do evento;
- simulacao e resumo operacional no painel;
- diagnostico de runtime para operacao;
- fluxo realtime entre pipeline de midia, player e manager.

## Fora do escopo imediato

- pausa por WhatsApp como requisito de v1;
- controles muito finos por pesos matematicos no modo simples;
- politicas muito sofisticadas por `source_type` no launch inicial;
- orquestracao automatica de fase do evento baseada em agenda ou sensores externos.

## Estado implementado nesta fase

Ja entrou no codigo:

- `POST /public/wall/{wallCode}/heartbeat` com payload tipado do runtime do player;
- `GET /events/{event}/wall/diagnostics` com `summary`, `players` e `updated_at`;
- `POST /events/{event}/wall/simulate` usando `draft settings + fila real atual`;
- canal privado `event.{eventId}.wall` no manager para `wall.settings.updated`, `wall.status.changed`, `wall.expired` e `wall.diagnostics.updated`;
- cards de diagnostico operacional e bloco de simulacao no manager;
- comandos operacionais do player para limpar cache, revalidar assets e reinicializar engine;
- agregacao assincrona via `RecalculateWallDiagnosticsJob` e limpeza via `PruneWallRuntimeSnapshotsJob`.

---

## Principios de Produto e UX

1. Preset-first. O admin comeca por `balanced`, `live`, `inclusive` ou `editorial`.
2. Progressive disclosure. O modo simples resolve 80% dos casos; o modo avancado expande o restante.
3. Simulacao antes de salvar. O admin nao deve configurar a fila no escuro.
4. Diagnostico claro. O operador precisa entender se o telao esta vivo, atrasado ou degradado.
5. Social-first. O padrao deve ser calibrado para festa real, nao para ambiente editorial puro.
6. Guardrails ativos. O produto nao deve deixar o operador quebrar a experiencia sem friccao.

---

## Experiencia Alvo do Admin

O gestor precisa controlar 4 areas diferentes:

1. estado operacional do telao;
2. comportamento da fila;
3. experiencia visual;
4. saude e previsibilidade do runtime.

Hoje a tela atual cobre melhor o item 1 e parte do 3. O salto de produto esta em cobrir melhor o item 2 e o item 4.

## Organizacao recomendada da pagina `/events/:id/wall`

### Aba 1. Geral

Blocos:

- Operacao do telao
- Modo do telao
- Visual da exibicao

Campos e informacoes principais:

- status atual;
- iniciar, pausar, parar, encerrar e resetar;
- codigo do telao;
- link publico;
- ultima sincronizacao;
- estado da conexao realtime;
- preset atual;
- tempo por slide;
- layout;
- transicao;
- QR;
- branding;
- overlay basico.

### Aba 2. Fila e Justica

Blocos:

- fairness por remetente;
- replay;
- recencia;
- burst control;
- adaptacao por volume.

Campos principais:

- `selection_mode`
- `max_consecutive_per_sender`
- `avoid_same_sender_if_alternative_exists`
- `sender_cooldown_seconds`
- `sender_window_limit`
- `sender_window_minutes`
- `max_eligible_items_per_sender`
- `prefer_unseen_senders`
- `fairness_strength`
- `replay_enabled`
- `max_replays_per_item`
- `min_repeat_interval_minutes`
- `adaptive_replay_enabled`
- `freshness_boost_enabled`
- `freshness_boost_minutes`
- `freshness_weight`
- `adaptive_volume_mode`
- `burst_control_enabled`
- `burst_sender_limit`
- `burst_window_minutes`
- `burst_strategy`

### Aba 3. Destaques

Blocos:

- lane editorial;
- regras de destaque;
- chamada visual opcional.

Campos principais:

- `featured_lane_enabled`
- `featured_every_n_slides`
- `featured_max_consecutive`
- `featured_priority_level`
- `show_featured_badge`
- `show_neon`
- `neon_text`
- `neon_color`

### Aba 4. Performance

Blocos:

- runtime e cache;
- fallback;
- diagnostico ao vivo.

Campos e controles:

- `prefetch_enabled`
- `stale_fallback_enabled`
- `persistent_cache_enabled`
- `network_saver_mode`
- prefetch agressivo;
- manter item atual se o proximo asset falhar;
- limpar cache local;
- revalidar assets;
- reinicializar engine.

Informacoes ao vivo:

- conexao realtime;
- ultima sincronizacao;
- latencia media;
- itens `ready/loading/error/stale`;
- cache ativo;
- persistencia local ativa;
- ultimo fallback aplicado.

### Aba 5. Tela de Espera

Blocos:

- mensagem de espera;
- orientacao de envio;
- QR e branding;
- rotacao de mensagens.

Campos principais:

- `idle_message`
- `idle_submessage`
- `idle_show_qr`
- `idle_rotate_messages`
- `idle_messages`
- `idle_show_branding`
- `idle_return_delay_seconds`

---

## Simulacao e Resumo Operacional

O painel precisa mostrar, antes de salvar ou ao menos ao revisar:

- resumo textual do comportamento;
- simulacao da ordem provavel;
- resumo de saude da fila.

Exemplos de saida:

- "Este telao esta configurado para distribuir fotos de forma equilibrada, evitar que um convidado domine a tela e inserir destaques com baixa frequencia."
- "Com 9 remetentes ativos e 28 midias prontas, a primeira aparicao media estimada e de 42s."
- "Chance de repeticao do mesmo remetente: baixa."

Exemplo de sequencia fake:

- Ana -> Pedro -> Carla -> Ana -> Joao -> Pedro

---

## Modelo de Dados Alvo no Backend

## Base existente

Manter `event_wall_settings` como entidade principal do modulo.

Campos existentes importantes continuam valendo:

- `wall_code`
- `is_enabled`
- `status`
- `layout`
- `transition_effect`
- `interval_ms`
- `queue_limit`
- `show_qr`
- `show_branding`
- `show_neon`
- `neon_text`
- `neon_color`
- `show_sender_credit`
- `background_image_path`
- `partner_logo_path`
- `instructions_text`
- `expires_at`

## Campos novos recomendados

Adicionar configuracao estruturada, sem explodir a tabela com dezenas de colunas pequenas.

Recomendacao:

- `selection_mode` string
- `event_phase` string
- `selector_config_json` jsonb
- `editorial_config_json` jsonb
- `idle_config_json` jsonb
- `runtime_config_json` jsonb
- `diagnostics_config_json` jsonb opcional

Exemplo de `selector_config_json`:

```json
{
  "max_consecutive_per_sender": 1,
  "avoid_same_sender_if_alternative_exists": true,
  "sender_cooldown_seconds": 60,
  "sender_window_limit": 3,
  "sender_window_minutes": 10,
  "max_eligible_items_per_sender": 8,
  "prefer_unseen_senders": true,
  "fairness_strength": "medium",
  "freshness_boost_enabled": true,
  "freshness_boost_minutes": 3,
  "freshness_weight": "medium",
  "adaptive_volume_mode": "enabled",
  "burst_control_enabled": true,
  "burst_sender_limit": 10,
  "burst_window_minutes": 3,
  "burst_strategy": "throttle",
  "replay_enabled": true,
  "max_replays_per_item": 2,
  "min_repeat_interval_minutes": 12,
  "adaptive_replay_enabled": true
}
```

Exemplo de `editorial_config_json`:

```json
{
  "featured_lane_enabled": true,
  "featured_every_n_slides": 7,
  "featured_max_consecutive": 1,
  "featured_priority_level": "medium",
  "show_featured_badge": false
}
```

Exemplo de `idle_config_json`:

```json
{
  "idle_message": "Envie sua foto e apareca no telao em tempo real.",
  "idle_submessage": "Aponte a camera para o QR e participe.",
  "idle_show_qr": true,
  "idle_rotate_messages": true,
  "idle_messages": [
    "Envie sua foto e apareca no telao em tempo real.",
    "Aponte a camera para o QR e participe.",
    "Compartilhe seu momento com todos aqui."
  ],
  "idle_return_delay_seconds": 15
}
```

Exemplo de `runtime_config_json`:

```json
{
  "prefetch_enabled": true,
  "stale_fallback_enabled": true,
  "persistent_cache_enabled": true,
  "network_saver_mode": false,
  "prefetch_strategy": "balanced",
  "keep_current_if_next_fails": true
}
```

---

## Data Objects, Requests e Services Recomendados

## Data objects

- `WallSelectorConfigData`
- `WallEditorialConfigData`
- `WallIdleConfigData`
- `WallRuntimeConfigData`
- `WallDiagnosticsSummaryData`
- `WallSimulationResultData`

## Requests

- `UpdateWallSettingsRequest`
  - passa a validar tambem os blocos novos
- `SimulateWallSelectorRequest`
- `StoreWallHeartbeatRequest`

## Services e Support

- `WallSenderKeyResolver`
  - gera `sender_key` estavel
- `WallDuplicateClusterResolver`
  - define `duplicate_cluster_key` quando houver suporte
- `WallDiagnosticsService`
  - agrega saude do player por wall
- `WallSimulationService`
  - calcula resumo e ordem simulada
- `WallSelectorDefaultsFactory`
  - aplica presets e defaults sociais

## Queries

- `GetWallDiagnosticsQuery`
- `GetWallSimulationPreviewQuery` opcional

---

## Contrato HTTP Alvo

## GET `/events/{event}/wall/settings`

Deve devolver:

- estado operacional;
- settings visuais;
- selector config;
- editorial config;
- idle config;
- runtime config;
- diagnostico agregado;
- links publicos;
- options do painel.

Shape recomendado:

```json
{
  "id": 1,
  "event_id": 1,
  "wall_code": "ABCD1234",
  "status": "live",
  "status_label": "Ao Vivo",
  "public_url": "https://...",
  "operation": {
    "is_enabled": true,
    "last_runtime_heartbeat_at": "2026-04-02T21:00:00-03:00",
    "realtime_state": "connected"
  },
  "settings": {
    "visual": {},
    "selector": {},
    "editorial": {},
    "idle": {},
    "runtime": {}
  },
  "diagnostics": {
    "ready_items": 28,
    "loading_items": 2,
    "error_items": 0,
    "stale_items": 1,
    "active_senders": 9,
    "estimated_first_appearance_seconds": 42,
    "last_sync_at": "2026-04-02T21:00:10-03:00",
    "runtime_health": "healthy"
  }
}
```

## PATCH `/events/{event}/wall/settings`

Deve aceitar atualizacao parcial por blocos.

Exemplo:

```json
{
  "selection_mode": "balanced",
  "event_phase": "flow",
  "selector": {
    "max_consecutive_per_sender": 1,
    "sender_cooldown_seconds": 60,
    "sender_window_limit": 3,
    "sender_window_minutes": 10,
    "max_eligible_items_per_sender": 8,
    "prefer_unseen_senders": true,
    "burst_control_enabled": true,
    "burst_strategy": "throttle",
    "replay_enabled": true
  },
  "editorial": {
    "featured_lane_enabled": true,
    "featured_every_n_slides": 7
  },
  "runtime": {
    "prefetch_enabled": true,
    "stale_fallback_enabled": true
  }
}
```

## POST `/events/{event}/wall/simulate`

Objetivo:

- receber configuracao atual ou draft;
- devolver resumo previsivel da politica de fila.

Resposta recomendada:

```json
{
  "summary": {
    "first_appearance_eta_seconds": 55,
    "monopolization_risk": "low",
    "freshness_level": "medium",
    "fairness_level": "high"
  },
  "sequence_preview": [
    "ana",
    "pedro",
    "carla",
    "ana",
    "joao",
    "pedro"
  ],
  "explanation": "Este modo distribui melhor entre convidados e reduz a chance de monopolizacao."
}
```

## GET `/events/{event}/wall/diagnostics`

Objetivo:

- abastecer a aba Performance;
- permitir polling ou invalidacao seletiva.

## POST `/public/wall/{wallCode}/heartbeat`

Objetivo:

- player publica saude do runtime;
- backend agrega diagnostico para o admin;
- nao depende de WebSocket bidirecional do player para telemetria.

Payload implementado:

```json
{
  "player_instance_id": "player-alpha",
  "runtime_status": "playing",
  "connection_status": "connected",
  "current_item_id": "media_10",
  "current_sender_key": "whatsapp:5511999999999",
  "ready_count": 28,
  "loading_count": 2,
  "error_count": 0,
  "stale_count": 1,
  "cache_enabled": true,
  "persistent_storage": "indexeddb",
  "cache_usage_bytes": 1048576,
  "cache_quota_bytes": 8388608,
  "cache_hit_count": 12,
  "cache_miss_count": 3,
  "cache_stale_fallback_count": 1,
  "last_sync_at": "2026-04-02T21:00:10-03:00",
  "last_fallback_reason": null
}
```

---

## Realtime e WebSocket

## Canais

### Canal publico do player

- `wall.{wallCode}`

Responsavel por:

- novas midias;
- atualizacao de midias;
- exclusao de midias;
- settings;
- status;
- expiracao.

### Canal privado do manager

- `event.{eventId}.wall`

Responsavel por:

- atualizacao administrativa do estado do wall;
- invalidacao leve do painel;
- resumo de diagnostico;
- notificacoes de simulacao ou mudanca de preset, se necessario.

Observacao importante:

- o manager do evento nao deve depender apenas do canal publico `wall.{wallCode}`;
- o painel deve usar o canal privado por evento para informacao administrativa e diagnostico.

## Eventos publicos mantidos

- `wall.media.published`
- `wall.media.updated`
- `wall.media.deleted`
- `wall.settings.updated`
- `wall.status.changed`
- `wall.expired`

## Eventos administrativos implementados

- `wall.diagnostics.updated`

## Regras de reconnect

### Player

- ao reconectar, faz `boot/state` de resync;
- nunca depende apenas do buffer do WebSocket;
- reaplica selector local com base no snapshot mais recente.

### Manager

- ao reconectar, invalida `wall.byEvent(eventId)`;
- invalida `wall.diagnostics(eventId)`;
- reidrata cards de status, fila e performance.

## O que nao fazer

- nao usar WebSocket para heartbeat detalhado de cada player a cada slide;
- nao mandar diagnostico bruto do player no canal publico do wall;
- nao usar a fila `broadcasts` para telemetria agregada.

---

## Filas e Processamento

## Regra principal

O selector roda no player. Ele nao vira job de backend.

O backend continua dono de:

- publish de midia;
- payloads;
- configuracao;
- diagnostico agregado;
- observabilidade.

## Filas recomendadas

### `broadcasts`

Uso:

- eventos de midia do wall;
- eventos administrativos broadcastaveis;
- atualizacoes de status e settings.

Regras:

- continua isolada das filas pesadas de processamento;
- manter `tries`, `timeout` e `backoff` explicitos;
- medir backlog e latencia em producao.

### `analytics`

Uso:

- agregacao de heartbeats do player;
- consolidacao de diagnostico do wall;
- historico leve de saude e fila.

Regras:

- heartbeat HTTP do player nao deve fazer agregacao pesada inline;
- a persistencia agregada deve ser assincorna.

### `media-process` e `media-publish`

Uso:

- continuam como base do pipeline de midia.

Observacao:

- o wall so funciona bem se o pipeline continuar entregando assets prontos rapidamente;
- qualquer gargalo em variantes e publish aparece como "fila vazia" ou "loading demais" no player.

## Jobs e agregacao implementados

- `RecalculateWallDiagnosticsJob`
- `PruneWallRuntimeSnapshotsJob`
- `WallDiagnosticsService`

---

## Runtime do Player

## Estrutura recomendada

```text
apps/web/src/modules/wall/player/
  api.ts
  types.ts
  hooks/
    useWallPlayer.ts
    useWallRealtime.ts
    useWallEngine.ts
  engine/
    selectors.ts
    reducer.ts
    cache.ts
    storage.ts
    isEligibleNow.ts
    selectBestItemWithinSender.ts
    computeSenderScore.ts
    applyPreset.ts
```

## Responsabilidades do runtime

- manter `items` com `assetStatus`;
- distinguir `loading`, `ready`, `error` e `stale`;
- prefetch do item atual, do proximo e de um pequeno buffer;
- persistir estado minimo localmente;
- aplicar fairness por remetente;
- aplicar backlog gradual por remetente;
- aplicar anti-sequencia parecida quando houver `duplicate_cluster_key`;
- enviar heartbeat periodico para diagnostico.

## Status visual recomendado

- `booting`
- `idle`
- `playing`
- `paused`
- `stopped`
- `expired`
- `error`
- `degraded` opcional

`degraded` pode ser usado quando o wall segue operando com cache `stale` ou com reconnect instavel.

## Heartbeat do player

Frequencia sugerida:

- a cada 15 a 30 segundos em `playing`;
- ao mudar para `paused`, `idle`, `error` ou `degraded`;
- ao aplicar fallback importante;
- apos resync bem-sucedido.

---

## Configuracao de Selector no Front

## Campos principais recomendados

### Config principal

- `selection_mode`
- `slide_duration_seconds`
- `layout_mode`
- `transition_mode`

### Justica da fila

- `max_consecutive_per_sender`
- `avoid_same_sender_if_alternative_exists`
- `sender_cooldown_seconds`
- `sender_window_limit`
- `sender_window_minutes`
- `max_eligible_items_per_sender`
- `prefer_unseen_senders`
- `fairness_strength`

### Ao vivo e recencia

- `freshness_boost_enabled`
- `freshness_boost_minutes`
- `freshness_weight`
- `adaptive_volume_mode`

### Anti-rajada

- `burst_control_enabled`
- `burst_sender_limit`
- `burst_window_minutes`
- `burst_strategy`

Valores sugeridos para `burst_strategy`:

- `throttle`
- `soften`
- `strict`

### Replay

- `replay_enabled`
- `max_replays_per_item`
- `min_repeat_interval_minutes`
- `adaptive_replay_enabled`

### Destaques

- `featured_lane_enabled`
- `featured_every_n_slides`
- `featured_max_consecutive`
- `featured_priority_level`
- `show_featured_badge`

### Visual

- `show_qr_code`
- `show_brand_logo`
- `show_sender_name`
- `show_caption`
- `qr_position`
- `overlay_opacity`
- `theme_variant`

### Idle

- `idle_message`
- `idle_submessage`
- `idle_show_qr`
- `idle_rotate_messages`

### Performance

- `prefetch_enabled`
- `stale_fallback_enabled`
- `persistent_cache_enabled`
- `network_saver_mode`

---

## Guardrails

Mesmo no modo avancado, certos guardrails devem continuar protegidos ou ao menos ter alto atrito para desligar:

- evitar o mesmo remetente em sequencia se houver alternativa;
- item precisa estar `ready` para concorrer;
- limite de repeticao do mesmo item;
- anti-rajada basico;
- lane editorial limitada.

A interface pode permitir override, mas nao como configuracao casual de primeiro nivel.

---

## Criticas de Sucesso da Implementacao

A implementacao deve ser considerada bem-sucedida quando:

1. o wall deixa de ser carrossel linear e passa a selecionar com fairness;
2. o admin entende o comportamento do telao por preset, resumo e simulacao;
3. o player continua operando em rede degradada com cache e fallback;
4. o manager consegue diagnosticar estado da fila e saude do runtime;
5. o fluxo realtime continua previsivel e observavel sem congestionar `broadcasts`;
6. o produto fica melhor para evento social antes de ficar mais sofisticado em cenarios editoriais.
