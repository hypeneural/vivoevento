# Wall Module

## Responsabilidade
Telão/slideshow realtime com layouts, overlays e exibição em tempo real via WebSocket (Pusher).

## Entidades
- **EventWallSetting** — configurações do wall por evento (1:1 com Event)

## Enums
| Enum | Valores |
|------|---------|
| `WallStatus` | draft, live, paused, stopped, expired |
| `WallLayout` | auto, polaroid, fullscreen, split, cinematic |
| `WallTransition` | fade, slide, zoom, flip, none |

## Broadcasting (ShouldBroadcastNow)
Canal público: `wall.{wallCode}` — sem autenticação (o wall_code é o secret).

| Evento | Quando | Payload |
|--------|--------|---------|
| `WallMediaPublished` | Nova mídia publicada | {id, url, type, sender_name, caption, is_featured, created_at} |
| `WallMediaUpdated` | Variante gerada | Mesmo payload (URL diferente) |
| `WallMediaDeleted` | Mídia removida | {id} |
| `WallSettingsUpdated` | Admin altera config | {interval_ms, layout, transition_effect, ...} |
| `WallStatusChanged` | Status muda | {status, reason, updated_at} |
| `WallExpired` | Wall expirado | {reason, expired_at} |

## Actions
| Action | Descrição |
|--------|-----------|
| `StartWallAction` | Ativa wall → live + broadcast |
| `StopWallAction` | Para wall → paused/stopped + broadcast |
| `ExpireWallAction` | Expira wall (terminal) + broadcast |
| `ResetWallAction` | Reseta tudo + novo wall_code |

## Rotas

### Admin (auth:sanctum)
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /events/{id}/wall/settings | Config do wall |
| PATCH | /events/{id}/wall/settings | Atualizar config |
| POST | /events/{id}/wall/start | Iniciar wall (live) |
| POST | /events/{id}/wall/stop | Pausar wall |
| POST | /events/{id}/wall/pause | Pausar wall (alias) |
| POST | /events/{id}/wall/full-stop | Parar wall completamente |
| POST | /events/{id}/wall/expire | Expirar wall |
| POST | /events/{id}/wall/reset | Resetar wall |
| POST | /events/{id}/wall/upload-background | Upload background |
| POST | /events/{id}/wall/upload-logo | Upload logo parceiro |
| GET | /wall/options | Opções de layout/status/transição |

### Público (sem auth — via wall_code)
| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /public/wall/{wallCode}/boot | Bootstrap do telão |
| GET | /public/wall/{wallCode}/state | Estado atual |

## Services
- **WallBroadcasterService** — Centralizador de broadcasts com payload builders e verificação de elegibilidade

## Listeners (integração com MediaProcessing)
| Listener | Evento escutado |
|----------|----------------|
| `BroadcastWallOnMediaPublished` | `media.published` |
| `BroadcastWallOnMediaUpdated` | `media.variants.generated` |
| `BroadcastWallOnMediaDeleted` | `media.deleted`, `media.rejected` |

## Dependências
- Events (Model Event)
- MediaProcessing (EventMedia, EventMediaVariant para resolução de URL)

## Fluxo: Foto → Telão

```
1. MediaProcessing publica mídia (publication_status = published)
2. Dispara evento `media.published`
3. BroadcastWallOnMediaPublished escuta
4. WallBroadcasterService verifica elegibilidade:
   - Wall está live?
   - Mídia está approved + published?
   - Tipo é image/video?
5. Se elegível: event(new WallMediaPublished(wallCode, payload))
6. Pusher entrega ao wall player via WebSocket (instantâneo!)
```
