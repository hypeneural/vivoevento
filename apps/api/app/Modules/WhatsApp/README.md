# WhatsApp Module

## Responsabilidade

Core funcional do dominio WhatsApp, provider-agnostic, com adapters para Z-API e Evolution API.

Gerencia:
- Instancias WhatsApp, lifecycle e conexao por QR/pairing.
- Envio de mensagens e reacoes.
- Recebimento de webhooks com payload normalizado.
- Chats remotos via provider.
- Grupos remotos via provider e bindings internos grupo -> evento.
- Logs de dispatch e auditoria operacional.

## Arquitetura Provider-Agnostic

```text
Controller -> Service -> ProviderResolver -> ProviderAdapter -> HTTP API do Provider
                                       ^
                                 WhatsAppProviderInterface
                                       ^
                       +---------------+---------------+
                       |                               |
                  ZApiProvider                EvolutionProvider
```

O modulo nao conhece os detalhes do provider no controller. A resolucao sempre parte da `provider_key` da instancia.

## Escopo atual por provider

### Z-API

- Cobertura completa do contrato atual do modulo: conexao, mensageria, chats e grupos.

### Evolution API

- Conexao: status, detalhes da sessao, QR code, pairing por telefone e disconnect.
- Mensageria: texto, media, audio, reacao e remocao de reacao.
- Chats: listagem remota via provider e busca remota de mensagens por chat.
- Grupos: catalogo remoto, participantes, criar, atualizar nome/foto/descricao, convite, adicionar/remover/promover participantes, aplicar settings suportados e sair do grupo.
- Limitacoes atuais do contrato: `carousel`, `pix` e `pin/unpin` nao possuem paridade direta na documentacao oficial atual da Evolution dentro deste adapter.
- Settings sem paridade direta no provider (`require_admin_approval` e `admin_only_add_member`) retornam erro explicito.

## Entidades

| Model | Tabela | Descricao |
|-------|--------|-----------|
| WhatsAppProvider | `whatsapp_providers` | Catalogo de providers |
| WhatsAppInstance | `whatsapp_instances` | Instancia conectada a org |
| WhatsAppChat | `whatsapp_chats` | Chat conhecido |
| WhatsAppMessage | `whatsapp_messages` | Mensagem inbound/outbound |
| WhatsAppInboundEvent | `whatsapp_inbound_events` | Payload bruto de webhook |
| WhatsAppDispatchLog | `whatsapp_dispatch_logs` | Log de envio |
| WhatsAppGroupBinding | `whatsapp_group_bindings` | Grupo -> evento |

## Rotas

### Webhook

| Metodo | Rota | Descricao |
|--------|------|-----------|
| POST | `/webhooks/whatsapp/{provider}/{instanceKey}/inbound` | Webhook inbound |
| POST | `/webhooks/whatsapp/{provider}/{instanceKey}/status` | Status update |
| POST | `/webhooks/whatsapp/{provider}/{instanceKey}/delivery` | Delivery receipt |

### Instancias

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/instances` | Listar instancias |
| POST | `/whatsapp/instances` | Criar instancia |
| GET | `/whatsapp/instances/{id}` | Detalhes |
| PATCH | `/whatsapp/instances/{id}` | Atualizar |
| DELETE | `/whatsapp/instances/{id}` | Remover |
| POST | `/whatsapp/instances/{id}/test-connection` | Testar integracao |
| POST | `/whatsapp/instances/{id}/set-default` | Definir instancia padrao |

### Conexao

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/instances/{id}/status` | Status bruto do provider |
| GET | `/whatsapp/instances/{id}/connection-state` | Estado unificado para frontend |
| GET | `/whatsapp/instances/{id}/qr-code` | QR em bytes/string |
| GET | `/whatsapp/instances/{id}/qr-code/image` | QR pronto para renderizacao |
| POST | `/whatsapp/instances/{id}/phone-code` | Codigo por telefone |
| POST | `/whatsapp/instances/{id}/disconnect` | Desconectar sessao |
| POST | `/whatsapp/instances/{id}/sync-status` | Enfileirar sync de status |

### Chats

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/chats` | Listar chats remotos do provider |
| POST | `/whatsapp/chats/find-messages` | Buscar mensagens remotas de um chat |
| POST | `/whatsapp/chats/modify` | Acao de chat dependente do provider |

### Mensageria

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/messages` | Listar mensagens da organizacao |
| GET | `/whatsapp/messages/{id}` | Detalhes da mensagem |
| POST | `/whatsapp/messages/text` | Enviar texto |
| POST | `/whatsapp/messages/image` | Enviar imagem/media |
| POST | `/whatsapp/messages/audio` | Enviar audio |
| POST | `/whatsapp/messages/reaction` | Enviar reacao |
| POST | `/whatsapp/messages/remove-reaction` | Remover reacao |
| POST | `/whatsapp/messages/carousel` | Enviar carrossel |
| POST | `/whatsapp/messages/pix` | Enviar botao PIX |

### Group Management

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/group-management/catalog` | Catalogo remoto de grupos |
| POST | `/whatsapp/group-management/create` | Criar grupo remoto |
| POST | `/whatsapp/group-management/{groupId}/update-name` | Atualizar nome |
| POST | `/whatsapp/group-management/{groupId}/update-photo` | Atualizar foto |
| POST | `/whatsapp/group-management/{groupId}/update-description` | Atualizar descricao |
| POST | `/whatsapp/group-management/{groupId}/update-settings` | Atualizar settings |
| GET | `/whatsapp/group-management/{groupId}/invitation-link` | Obter link de convite |
| GET | `/whatsapp/group-management/{groupId}/participants` | Listar participantes remotos |
| POST | `/whatsapp/group-management/{groupId}/add-participants` | Adicionar participantes |
| POST | `/whatsapp/group-management/{groupId}/remove-participants` | Remover participantes |
| POST | `/whatsapp/group-management/{groupId}/promote-admin` | Promover a admin |
| POST | `/whatsapp/group-management/{groupId}/leave` | Sair do grupo |

### Group Bindings

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/groups` | Listar bindings |
| POST | `/whatsapp/groups/{groupId}/bind-event` | Vincular grupo |
| PATCH | `/whatsapp/groups/{groupId}/binding` | Atualizar binding |
| DELETE | `/whatsapp/groups/{groupId}/binding` | Desvincular |

### Logs

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/whatsapp/logs/dispatch` | Logs de envio |
| GET | `/whatsapp/logs/inbound` | Logs de inbound |

## Filas

| Fila | Prioridade | Uso |
|------|------------|-----|
| `whatsapp-inbound` | Alta | Processamento de webhooks |
| `whatsapp-send` | Media-alta | Envio de mensagens |
| `whatsapp-sync` | Baixa | Polling de status/QR |

## DTOs principais

### Envio

| DTO | Uso |
|-----|-----|
| `SendTextData` | Texto e mencoes |
| `SendImageData` | Imagem/media com caption |
| `SendAudioData` | Audio |
| `SendReactionData` | Reacao a mensagem |
| `RemoveReactionData` | Remocao de reacao |
| `SendCarouselData` | Carrossel |
| `SendPixButtonData` | Botao PIX |

### Grupos

| DTO | Uso |
|-----|-----|
| `CreateGroupData` | Criar grupo |
| `GroupParticipantsData` | Add/remove/promote participantes |
| `UpdateGroupData` | Nome/foto/descricao |
| `UpdateGroupSettingsData` | Settings de grupo |

### Provider responses

| DTO | Uso |
|-----|-----|
| `ProviderStatusData` | Status da conexao |
| `ProviderConnectionDetailsData` | Perfil e device conectados |
| `ProviderQrCodeData` | QR code bytes/base64/value |
| `ProviderHealthCheckData` | Resultado de health check |
| `ProviderActionResultData` | Resultado de acao generica |
| `ProviderSendMessageResultData` | Resultado de envio |
| `ProviderGroupCreatedData` | Grupo criado |
| `ProviderChatsPageData` | Lista paginada de chats |
| `ProviderChatMessagesData` | Lista remota de mensagens por chat |
| `ProviderGroupCatalogData` | Catalogo remoto de grupos |
| `ProviderGroupParticipantsData` | Participantes remotos de grupo |

## Dependencias

- Organizations para escopo multi-tenant.
- Events para bindings grupo -> evento.
- Users para `created_by` e `updated_by`.
- InboundMedia e MediaProcessing para pipeline posterior.

## Idempotencia de inbound

- `whatsapp_inbound_events` continua servindo como trilha bruta de auditoria.
- `whatsapp_messages` protege o processamento canonico com unique constraint em
  `instance_id + direction + provider_message_id`.
- `WhatsAppInboundRouter` faz lookup rapido e refetch seguro em caso de
  corrida de insert.

## Requisitos de infraestrutura

- `APP_KEY` estavel para encrypted casts.
- Workers para filas `whatsapp-*`.
- Canal de log `whatsapp` configurado em `config/logging.php`.
