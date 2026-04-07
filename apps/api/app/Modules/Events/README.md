# Events Module

## Responsibility
Manage Evento Vivo events as the main product aggregate, including:

- event base data
- commercial state resolved for the event
- enabled modules
- public links
- canonical intake channel configuration

## Entities
- `Event` - main event with branding, status, dates, and intake defaults
- `EventModule` - enabled modules for the event (`live`, `wall`, `play`, `hub`)
- `EventBanner` - promotional banners for the event
- `EventChannel` - canonical intake channels configured for the event

## Main use cases
- create event
- update event
- publish event
- archive event
- manage public links
- configure media intake channels

## Intake contract in Event CRUD

`POST /api/v1/events`, `PATCH /api/v1/events/{id}`, and
`GET /api/v1/events/{id}` support the blocks below:

```json
{
  "intake_defaults": {
    "whatsapp_instance_id": 10,
    "whatsapp_instance_mode": "shared"
  },
  "intake_channels": {
    "whatsapp_groups": {
      "enabled": true,
      "groups": [
        {
          "group_external_id": "120363425796926861-group",
          "group_name": "Evento vivo 1",
          "is_active": true,
          "auto_feedback_enabled": true
        }
      ]
    },
    "whatsapp_direct": {
      "enabled": true,
      "media_inbox_code": "CODIGO-DO-EVENTO",
      "session_ttl_minutes": 120
    },
    "public_upload": {
      "enabled": true
    },
    "telegram": {
      "enabled": false
    }
  }
}
```

## Current backend rules

- the intake block is optional on create/update
- `whatsapp_instance_mode` accepts `shared` or `dedicated`
- `whatsapp_groups.groups[*].group_external_id` is required when groups are
  sent
- `event_channels` is the canonical persisted representation
- `whatsapp_group_bindings` is kept as operational legacy state synced from the
  event
- the backend enforces event entitlements before save:
  - maximum number of WhatsApp groups
  - `whatsapp_direct` availability
  - `public_upload` availability
  - `telegram` availability
  - dedicated WhatsApp instance availability
  - dedicated instance exclusivity across events

## Routes
| Method | Route | Controller | Description |
|--------|------|-----------|-----------|
| GET | `/api/v1/events` | `EventController@index` | List events |
| POST | `/api/v1/events` | `EventController@store` | Create event |
| GET | `/api/v1/events/{id}` | `EventController@show` | Show event details |
| PATCH | `/api/v1/events/{id}` | `EventController@update` | Update event |
| DELETE | `/api/v1/events/{id}` | `EventController@destroy` | Delete event |
| POST | `/api/v1/events/{id}/publish` | `EventStatusController@publish` | Publish event |
| POST | `/api/v1/events/{id}/archive` | `EventStatusController@archive` | Archive event |
| GET | `/api/v1/events/{id}/share-links` | `EventQrController@shareLinks` | Public links and identifiers |
| PATCH | `/api/v1/events/{id}/public-links` | `EventQrController@updateIdentifiers` | Update slug and upload slug |
| POST | `/api/v1/events/{id}/public-links/regenerate` | `EventQrController@regenerateIdentifiers` | Regenerate slug, upload slug, and wall code |

## Dependencies
- Organizations
- Users
- Billing / Entitlements
- Channels
- WhatsApp
- MediaProcessing
- Gallery, Wall, Play, Hub
