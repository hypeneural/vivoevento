These fixtures are based on real Z-API webhook payloads captured by Evento Vivo
on 2026-04-05 and then anonymized for automated replay tests.

Source mapping:

- `group-image-with-caption.json`
  - official webhook: `ReceivedCallback`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`
- `group-text.json`
  - official webhook: `ReceivedCallback`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`
- `group-notification.json`
  - official webhook: `ReceivedCallback` with `notification=*`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`
- `reaction.json`
  - official webhook: `ReceivedCallback` with `reaction`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`
- `message-status.json`
  - official webhook: `MessageStatusCallback`
  - doc: `https://developer.z-api.io/webhooks/on-whatsapp-message-status-changes`
- `dm-text.json`
  - official webhook: `ReceivedCallback`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`
- `dm-image-with-caption.json`
  - official webhook: `ReceivedCallback`
  - doc: `https://developer.z-api.io/webhooks/on-message-received`

Anonymization rules applied:

- phone numbers, lids, group ids, names and image URLs were replaced
- provider shape, field names and message structures were preserved
- real callback formats were kept intact to protect parser and routing tests
