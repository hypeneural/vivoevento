# WhatsApp Module

## Responsabilidade

Core funcional do domínio WhatsApp — provider-agnostic, com Z-API como primeiro adapter.

Gerencia:
- Instâncias WhatsApp (CRUD, lifecycle, conexão via QR)
- Envio de mensagens (texto, imagem, áudio, reação, carrossel, PIX)
- Menções em grupos (@mentions)
- Recebimento de webhooks (inbound normalizado)
- Chats: listagem paginada, pin/unpin
- Grupos: criar, atualizar (nome/foto/descrição/settings), convidar, participantes, admin, sair
- Binding de grupo ↔ evento
- Logs de dispatch (auditoria)
- Automação de reações

## Arquitetura Provider-Agnostic

```
Controller → Service → ProviderResolver → ProviderAdapter → HTTP API do Provider
                                              ↑
                                        WhatsAppProviderInterface
                                              ↑
                              ┌───────────────┼───────────────┐
                        ZApiProvider    EvolutionProvider   (futuro)
```

O sistema nunca sabe qual provider está usando. O `WhatsAppProviderResolver`
resolve o adapter correto pela `provider_key` da instância.

## Entidades

| Model | Tabela | Descrição |
|-------|--------|-----------|
| WhatsAppProvider | whatsapp_providers | Catálogo de providers |
| WhatsAppInstance | whatsapp_instances | Instância conectada à org |
| WhatsAppChat | whatsapp_chats | Chat conhecido |
| WhatsAppMessage | whatsapp_messages | Mensagem inbound/outbound |
| WhatsAppInboundEvent | whatsapp_inbound_events | Payload bruto webhook |
| WhatsAppDispatchLog | whatsapp_dispatch_logs | Log de envio |
| WhatsAppGroupBinding | whatsapp_group_bindings | Grupo ↔ evento |

## Rotas

### Webhook (sem auth)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | /webhooks/whatsapp/{provider}/{instanceKey}/inbound | Webhook inbound |
| POST | /webhooks/whatsapp/{provider}/{instanceKey}/status | Status update |
| POST | /webhooks/whatsapp/{provider}/{instanceKey}/delivery | Delivery receipt |

### Instâncias

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/instances | Listar instâncias |
| POST | /whatsapp/instances | Criar instância |
| GET | /whatsapp/instances/{id} | Detalhes |
| PATCH | /whatsapp/instances/{id} | Atualizar |
| DELETE | /whatsapp/instances/{id} | Remover |

### Conexão

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/instances/{id}/status | Status da conexão |
| GET | /whatsapp/instances/{id}/qr-code | QR (bytes) |
| GET | /whatsapp/instances/{id}/qr-code/image | QR (base64 image) |
| POST | /whatsapp/instances/{id}/phone-code | Código por telefone |
| POST | /whatsapp/instances/{id}/disconnect | Desconectar |
| POST | /whatsapp/instances/{id}/sync-status | Sincronizar status |

### Chats (Provider)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/chats | Listar chats (paginado via provider) |
| POST | /whatsapp/chats/modify | Fixar/desafixar chat (pin/unpin) |

### Mensageria

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/messages | Listar mensagens |
| GET | /whatsapp/messages/{id} | Detalhes |
| POST | /whatsapp/messages/text | Enviar texto (suporta `mentioned`) |
| POST | /whatsapp/messages/image | Enviar imagem |
| POST | /whatsapp/messages/audio | Enviar áudio |
| POST | /whatsapp/messages/reaction | Enviar reação |
| POST | /whatsapp/messages/remove-reaction | Remover reação |
| POST | /whatsapp/messages/carousel | Enviar carrossel |
| POST | /whatsapp/messages/pix | Enviar botão PIX |

### Group Management (CRUD direto no WhatsApp)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | /whatsapp/group-management/create | Criar grupo |
| POST | /whatsapp/group-management/{groupId}/update-name | Atualizar nome |
| POST | /whatsapp/group-management/{groupId}/update-photo | Atualizar foto |
| POST | /whatsapp/group-management/{groupId}/update-description | Atualizar descrição |
| POST | /whatsapp/group-management/{groupId}/update-settings | Configurações admin |
| GET | /whatsapp/group-management/{groupId}/invitation-link | Obter link de convite |
| POST | /whatsapp/group-management/{groupId}/add-participants | Adicionar participantes |
| POST | /whatsapp/group-management/{groupId}/remove-participants | Remover participantes |
| POST | /whatsapp/group-management/{groupId}/promote-admin | Promover a admin |
| POST | /whatsapp/group-management/{groupId}/leave | Sair do grupo |

### Groups / Bindings (vinculação grupo ↔ evento)

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/groups | Listar bindings |
| POST | /whatsapp/groups/{groupId}/bind-event | Vincular grupo |
| PATCH | /whatsapp/groups/{groupId}/binding | Atualizar binding |
| DELETE | /whatsapp/groups/{groupId}/binding | Desvincular |

### Logs

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | /whatsapp/logs/dispatch | Logs de envio |
| GET | /whatsapp/logs/inbound | Logs de inbound |

## Filas

| Fila | Prioridade | Uso |
|------|------------|-----|
| whatsapp-inbound | ALTA | Processamento de webhooks |
| whatsapp-send | MÉDIA-ALTA | Envio de mensagens |
| whatsapp-sync | BAIXA | Polling de status/QR |

## DTOs

### Envio (Outbound)
| DTO | Uso |
|-----|-----|
| SendTextData | Texto + menções (@mentions) |
| SendImageData | Imagem com caption |
| SendAudioData | Áudio com waveform |
| SendReactionData | Reação a mensagem |
| RemoveReactionData | Remoção de reação |
| SendCarouselData | Carrossel de cards |
| SendPixButtonData | Botão PIX com chave |

### Grupos
| DTO | Uso |
|-----|-----|
| CreateGroupData | Criar grupo com participantes |
| GroupParticipantsData | Add/remove/promote participantes |
| UpdateGroupData | Nome/foto/descrição do grupo |
| UpdateGroupSettingsData | Permissões admin do grupo |

### Chats
| DTO | Uso |
|-----|-----|
| ModifyChatData | Pin/unpin chat |

### Provider Responses
| DTO | Uso |
|-----|-----|
| ProviderStatusData | Status da conexão |
| ProviderQrCodeData | QR code bytes/base64 |
| ProviderActionResultData | Resultado de ação genérica |
| ProviderSendMessageResultData | Resultado de envio |
| ProviderGroupCreatedData | Grupo criado (ID + invite link) |
| ProviderChatsPageData | Lista paginada de chats |

## Dependências

- Organizations (multi-tenant via HasOrganization)
- Events (binding grupo ↔ evento)
- Users (created_by)
- InboundMedia (pipeline de mídia via listener)
- MediaProcessing (downstream da pipeline)

## Pré-requisitos de Infraestrutura

- **APP_KEY** configurado e estável (encrypted casts)
- Workers do Supervisor/Horizon para filas `whatsapp-*`
- Log channel `whatsapp` configurado no `config/logging.php`
