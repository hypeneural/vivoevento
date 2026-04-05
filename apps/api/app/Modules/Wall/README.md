# Wall Module

## Responsabilidade

Telao/slideshow realtime com layouts, overlays, bootstrap publico por `wall_code` e sincronizacao em tempo real via Reverb.

## Entidade Principal

- `EventWallSetting` - configuracao do wall por evento (relacao 1:1 com `Event`)

## Enums

| Enum | Valores |
|------|---------|
| `WallStatus` | `draft`, `live`, `paused`, `stopped`, `expired` |
| `WallLayout` | `auto`, `polaroid`, `fullscreen`, `split`, `cinematic` |
| `WallTransition` | `fade`, `slide`, `zoom`, `flip`, `none` |

## Canais Realtime

### Publico

- `wall.{wallCode}`
  - Uso: player publico do telao
  - Auth: nao exige autenticacao interativa
  - Observacao: o `wall_code` funciona como segredo curto do player

### Privados por evento

- `event.{eventId}.wall`
  - Permissao: `wall.view`
- `event.{eventId}.gallery`
  - Permissao: `gallery.view`
- `event.{eventId}.moderation`
  - Permissao: `media.moderate`
- `event.{eventId}.play`
  - Permissao: `play.view`

Todos os canais privados validam:

1. permissao especifica do modulo
2. vinculacao ativa do usuario a organizacao do evento
3. excecao para roles globais (`super-admin`, `platform-admin`)

## Eventos Broadcastaveis

### Eventos de midia

Usam `ShouldBroadcast`, `ShouldDispatchAfterCommit` e fila `broadcasts`.

- `WallMediaPublished`
- `WallMediaUpdated`
- `WallMediaDeleted`

### Eventos operacionais

Usam broadcast imediato com `ShouldDispatchAfterCommit`.

- `WallSettingsUpdated`
- `WallStatusChanged`
- `WallExpired`

## Actions

| Action | Descricao |
|--------|-----------|
| `StartWallAction` | Coloca o wall em `live` e notifica os players |
| `StopWallAction` | Pausa ou para o wall |
| `ExpireWallAction` | Expira o wall e encerra o ciclo do player |
| `ResetWallAction` | Reseta defaults e gera um novo `wall_code` |

## Endpoints

### Admin (`auth:sanctum`)

| Metodo | Rota | Regra |
|--------|------|-------|
| `GET` | `/events/{event}/wall/settings` | `viewWall` |
| `PATCH` | `/events/{event}/wall/settings` | `manageWall` |
| `POST` | `/events/{event}/wall/start` | `manageWall` |
| `POST` | `/events/{event}/wall/stop` | `manageWall` |
| `POST` | `/events/{event}/wall/pause` | `manageWall` |
| `POST` | `/events/{event}/wall/full-stop` | `manageWall` |
| `POST` | `/events/{event}/wall/expire` | `manageWall` |
| `POST` | `/events/{event}/wall/reset` | `manageWall` |
| `POST` | `/events/{event}/wall/upload-background` | `manageWall` |
| `POST` | `/events/{event}/wall/upload-logo` | `manageWall` |
| `GET` | `/wall/options` | permissao `wall.view` |

### Publico

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/public/wall/{wallCode}/boot` | bootstrap inicial do player |
| `GET` | `/public/wall/{wallCode}/state` | estado publico atual do wall |

## Integracao com MediaProcessing

O modulo Wall nao escuta mais strings como `media.published`. Ele consome eventos tipados do pipeline:

- `MediaPublished`
- `MediaVariantsGenerated`
- `MediaDeleted`
- `MediaRejected`

Listeners atuais:

| Listener | Evento de dominio |
|----------|-------------------|
| `BroadcastWallOnMediaPublished` | `MediaPublished` |
| `BroadcastWallOnMediaUpdated` | `MediaVariantsGenerated` |
| `BroadcastWallOnMediaDeleted` | `MediaDeleted`, `MediaRejected` |

## Servicos

- `WallBroadcasterService`
  - Estado atual: orquestra o disparo de eventos broadcastaveis do wall
- `WallEligibilityService`
  - Responsavel por decidir se uma midia pode aparecer no telao
- `WallPayloadFactory`
  - Responsavel por montar payloads de midia, settings e status
- `MediaAssetUrlService`
  - Responsavel por resolver URLs publicas de variantes/originais da midia

## Fluxo Atual

1. `MediaProcessing` publica ou atualiza uma midia.
2. O pipeline emite evento de dominio tipado.
3. O listener do Wall resolve a `EventMedia`.
4. `WallBroadcasterService` verifica elegibilidade do wall e da midia.
5. O modulo Wall emite evento broadcastavel para o canal do player.
6. O player do telao recebe o payload via Reverb.

## Contrato Compartilhado

O contrato TypeScript consumido pelo player fica em `packages/shared-types/src/wall.ts`.

Ele centraliza:

- payloads HTTP do boot e do state publico
- payloads dos eventos realtime
- nomes canonicos dos eventos do Wall
- status publicos, incluindo `disabled`
