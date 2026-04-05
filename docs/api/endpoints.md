# API Endpoints — Evento Vivo

## Auth
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| POST | `/api/v1/auth/login` | LoginController@login | Autenticar |
| POST | `/api/v1/auth/logout` | LoginController@logout | Encerrar sessão |

## Users
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/me` | MeController@show | Usuário autenticado |
| PATCH | `/api/v1/me` | MeController@update | Atualizar perfil |
| GET | `/api/v1/users` | UserController@index | Listar usuários |
| GET | `/api/v1/users/{id}` | UserController@show | Detalhes do user |

## Organizations
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/organizations` | OrganizationController@index | Listar |
| POST | `/api/v1/organizations` | OrganizationController@store | Criar |
| GET | `/api/v1/organizations/{id}` | OrganizationController@show | Detalhes |
| PATCH | `/api/v1/organizations/{id}` | OrganizationController@update | Atualizar |
| DELETE | `/api/v1/organizations/{id}` | OrganizationController@destroy | Remover |

## Roles
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/roles` | RoleController@index | Listar roles |
| GET | `/api/v1/roles/{id}` | RoleController@show | Detalhes |

## Events
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events` | EventController@index | Listar eventos |
| POST | `/api/v1/events` | EventController@store | Criar evento |
| GET | `/api/v1/events/{id}` | EventController@show | Detalhes |
| PATCH | `/api/v1/events/{id}` | EventController@update | Atualizar |
| DELETE | `/api/v1/events/{id}` | EventController@destroy | Remover |
| POST | `/api/v1/events/{id}/publish` | EventStatusController@publish | Publicar |
| POST | `/api/v1/events/{id}/archive` | EventStatusController@archive | Arquivar |

## Channels
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/event-channels` | EventChannelController@index | Listar |
| POST | `/api/v1/event-channels` | EventChannelController@store | Criar |
| GET | `/api/v1/event-channels/{id}` | EventChannelController@show | Detalhes |
| DELETE | `/api/v1/event-channels/{id}` | EventChannelController@destroy | Remover |

## Webhooks (Público — sem auth)
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| POST | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/inbound` | WhatsAppWebhookController@inbound | Webhook inbound de mensagens do WhatsApp, incluindo fotos |
| POST | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/status` | WhatsAppWebhookController@status | Webhook de status do provider/instância |
| POST | `/api/v1/webhooks/whatsapp/{provider}/{instanceKey}/delivery` | WhatsAppWebhookController@delivery | Webhook de delivery/read receipts |
| POST | `/api/v1/webhooks/telegram` | TelegramWebhookController@handle | Webhook Telegram |

## Media
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events/{id}/media` | EventMediaController@index | Listar mídias |
| GET | `/api/v1/media/{id}` | EventMediaController@show | Detalhes |
| POST | `/api/v1/media/{id}/approve` | EventMediaController@approve | Aprovar |
| POST | `/api/v1/media/{id}/reject` | EventMediaController@reject | Rejeitar |
| DELETE | `/api/v1/media/{id}` | EventMediaController@destroy | Remover |

## Gallery
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events/{id}/gallery` | GalleryMediaController@index | Galeria admin |
| POST | `/api/v1/events/{id}/gallery/{media}/feature` | GalleryMediaController@feature | Toggle destaque |
| DELETE | `/api/v1/events/{id}/gallery/{media}` | GalleryMediaController@remove | Ocultar |
| GET | `/api/v1/public/events/{slug}/gallery` | PublicGalleryController@index | **Público** |

## Wall
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events/{id}/wall/settings` | EventWallController@show | Config |
| PATCH | `/api/v1/events/{id}/wall/settings` | EventWallController@update | Atualizar |
| GET | `/api/v1/public/events/{slug}/wall` | PublicWallController@boot | **Público** |

## Play
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events/{id}/play/settings` | EventPlayController@show | Config |
| PATCH | `/api/v1/events/{id}/play/settings` | EventPlayController@update | Atualizar |
| GET | `/api/v1/public/events/{slug}/play` | PublicPlayController@manifest | **Público** |

## Hub
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/events/{id}/hub/settings` | EventHubController@show | Config |
| PATCH | `/api/v1/events/{id}/hub/settings` | EventHubController@update | Atualizar |
| GET | `/api/v1/public/events/{slug}/hub` | PublicHubController@index | **Público** |

## Plans
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/plans` | PlanController@index | Listar planos |
| GET | `/api/v1/plans/{id}` | PlanController@show | Detalhes |

## Billing
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/subscriptions` | SubscriptionController@index | Listar |
| GET | `/api/v1/subscriptions/{id}` | SubscriptionController@show | Detalhes |

## Analytics
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/analytics/events/{id}` | AnalyticsController@eventOverview | Métricas do evento |
| GET | `/api/v1/analytics/platform` | AnalyticsController@platformOverview | Visão geral |

## Audit
| Método | Rota | Controller | Descrição |
|--------|------|-----------|-----------|
| GET | `/api/v1/audit` | AuditController@index | Logs de auditoria |
