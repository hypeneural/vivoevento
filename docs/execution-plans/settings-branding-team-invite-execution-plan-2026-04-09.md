# Settings, Branding and Team Invitation Execution Plan

## Objetivo

Executar a evolucao de `/settings` em duas frentes complementares, sem perder coerencia de dominio:

1. transformar `Equipe` em um fluxo real de convite com link, aceite e envio opcional por WhatsApp;
2. evoluir `Branding` de um CRUD administrativo basico para uma camada reutilizavel entre painel, organizacao e futuras superficies publicas.

## Escopo

Este plano cobre:

- backend do convite de equipe;
- frontend autenticado da aba `Equipe`;
- onboarding publico de aceite do convite;
- scoping da instancia WhatsApp da organizacao;
- identidade unica do usuario da plataforma;
- reuso do mesmo usuario em multiplas organizacoes e eventos;
- contexto ativo de workspace para usuarios multi-organizacao;
- auditoria e estados do convite;
- branding da organizacao em `/settings`;
- heranca organizacao -> evento na evolucao de branding.

Este plano nao cobre:

- reescrever o cadastro publico inteiro;
- custom domain com DNS/SSL completo;
- white-label completo de todas as superficies;
- transferir ownership no mesmo fluxo generico de equipe.

## Estado Atual Validado

### O que ja existe

- `/settings` ja persiste dados basicos da organizacao.
- `/settings` ja persiste cores e upload de logo da organizacao.
- `/settings` ja persiste preferencias do usuario autenticado.
- `/settings` ja lista, adiciona e remove membros da organizacao atual.
- labels de papel no frontend ja foram parcialmente traduzidos.
- remocao de membro ja exige confirmacao no frontend.
- `Nome`, `WhatsApp` e `Perfil` ja sao obrigatorios no dialog atual.

### O que foi provado pelos testes de caracterizacao

- `POST /organizations/current/team` cria membership `active` imediatamente.
- o fluxo generico atual ainda permite criar proprietario adicional.
- `POST /auth/register/verify-otp` cria organizacao nova ao concluir o cadastro.
- o envio de OTP atual usa a instancia de autenticacao configurada, nao uma instancia escopada por organizacao convidante.

### Implicacoes tecnicas

- convite de equipe precisa de um dominio proprio.
- aceite de convite precisa de um ramo proprio de onboarding.
- entrega por WhatsApp precisa de um resolver especifico por organizacao.
- ownership precisa sair do fluxo generico de equipe.
- telefone e e-mail do convidado identificam um usuario da plataforma, nao um cadastro isolado por parceiro.
- usuarios multi-organizacao exigem contexto ativo explicito; `currentOrganization()` sozinho nao resolve o produto.

## Decisoes de Arquitetura Ja Travadas

### 0. Identidade unica da plataforma

Uma pessoa = um `User`.

Memberships e convites sao vinculos dessa identidade com:

- organizacoes
- eventos

Consequencias:

- nao criar usuario duplicado quando o WhatsApp ja existir;
- nao bloquear convite so porque o DJ ja trabalha com outro parceiro;
- nao criar nova organizacao no aceite de convite;
- recuperar senha continua sendo responsabilidade do auth global, por WhatsApp ou e-mail.

### 1. Modulo backend

O trabalho fica dentro de `Organizations`, com um subdominio explicito de convites.

Estrutura recomendada:

- `Models/OrganizationMemberInvitation.php`
- `Enums/OrganizationMemberInvitationStatus.php`
- `Actions/CreateOrganizationMemberInvitationAction.php`
- `Actions/ResendOrganizationMemberInvitationAction.php`
- `Actions/RevokeOrganizationMemberInvitationAction.php`
- `Actions/AcceptOrganizationMemberInvitationAction.php`
- `Queries/ListOrganizationMemberInvitationsQuery.php`
- `Services/OrganizationInvitationWhatsAppDeliveryService.php`
- `Support/OrganizationWhatsAppInvitationInstanceResolver.php`
- `Http/Controllers/CurrentOrganizationTeamInvitationController.php`
- `Http/Controllers/PublicOrganizationInvitationController.php`
- `Http/Requests/*`
- `Http/Resources/*`

Tambem precisaremos de infraestrutura de sessao/contexto:

- expandir `MeResource` para listar workspaces disponiveis;
- criar endpoint de troca de contexto organizacional;
- manter compatibilidade com o painel atual durante a migracao.

### 2. Ownership

`partner-owner` nao entra no fluxo generico de convite.

Ownership deve virar fluxo proprio, posterior, com endpoint dedicado e auditoria reforcada.

### 3. Scoping de WhatsApp

Convite de equipe deve usar:

- `default connected instance` da organizacao;
- fallback para unica instancia conectada da mesma organizacao;
- sem fallback para instancia global de autenticacao.

### 4. Frontend

O painel autenticado continua em `src/modules/settings`.

O onboarding publico do convite deve nascer como modulo proprio no web:

- `src/modules/team-invitations/`

### 5. Branding

Branding continua pertencendo a `Organization`, mas precisa evoluir em trilha separada do convite.

Primeiro objetivo de branding:

- tornar a UX de `/settings` consciente de entitlement;
- introduzir heranca organizacao -> evento;
- preparar mais ativos sem quebrar o CRUD atual.

### 6. Matriz simples no frontend

O fluxo padrao da cerimonial deve usar presets, nao matriz tecnica.

Perfis alvo:

- `Gerente / Secretaria`
- `Financeiro`
- `Operar evento`
- `Moderar midias`
- `Ver midias`

Ownership e configuracoes avancadas ficam fora do fluxo comum.

## Contratos TDD Ja Criados

### Backend

- `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`
- `apps/api/tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`

### Frontend

- `apps/web/src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx`

Esses testes cumprem dois papeis:

- congelar o comportamento atual que precisa ser substituido;
- explicitar o comportamento futuro antes da implementacao.

## Validacao Pre-Execucao de Onboarding, Cadastro e Notificacoes

### Cadastro e acesso ja estruturados

O que ja esta pronto no produto hoje:

- login com e-mail ou WhatsApp;
- cadastro novo via WhatsApp OTP;
- jornada `single_event_checkout` para cliente direto/noiva;
- reset de senha por WhatsApp;
- reset de senha por e-mail;
- envio de mensagem via Z-API no auth;
- autenticacao de canal privado de notificacoes do usuario.

Evidencias em testes:

- `tests/Feature/Auth/RegisterOtpTest.php`
- `tests/Feature/Auth/PasswordResetOtpTest.php`
- `tests/Feature/Auth/LoginTest.php`
- `tests/Feature/Notifications/NotificationRealtimeAuthorizationTest.php`

### Onde a estrutura ainda para no meio

O que ainda nao esta pronto para convite organizacional e event-scoped:

- aceitar convite sem criar nova organizacao;
- reusar o mesmo onboarding publico com `users.id` existente;
- bootstrapar `workspaces` e `active_context` em `/auth/me`;
- redirecionar usuario event-only para `/my-events`;
- mostrar inbox real no header do painel.

### Implicacao pratica para a execucao

O projeto nao precisa reinventar login, OTP ou reset.

O que precisa ser construído agora e:

- contexto de sessao multi-workspace;
- convite de organizacao;
- convite de evento;
- inbox real no frontend quando a fase de notificacoes entrar.

### Template UX recomendado para convite e aceite

Para cerimonial, noiva e DJ:

- formularios curtos com labels em portugues;
- presets simples como `Operar evento`, `Moderar midias` e `Ver midias`;
- cards com parceiro, evento, data e resumo do acesso;
- CTA principal claro por tela;
- nada de matriz tecnica, tabela de permissions ou menus escondidos em excesso.

## Fase 0 — Baseline e Contratos

### Status

- [x] Revisar o fluxo atual de `settings`, `branding`, `team`, `auth/register` e `whatsapp`.
- [x] Confirmar via teste que o fluxo atual cria membership ativo imediatamente.
- [x] Confirmar via teste que o fluxo atual ainda permite novo owner pelo CRUD generico.
- [x] Confirmar via teste que o cadastro OTP atual cria organizacao nova.
- [x] Confirmar via teste que o envio OTP atual usa sender global/configurado de autenticacao.
- [x] Criar contratos TDD backend e frontend para o fluxo futuro.
- [x] Confirmar via teste que o mesmo usuario pode ser reutilizado em mais de uma organizacao pelo mesmo WhatsApp.
- [x] Confirmar via teste que `/auth/me` colapsa usuario multi-organizacao em uma unica organizacao atual.

### Gate de saida

- comportamento atual comprovado por teste;
- comportamento futuro descrito por contrato;
- sem ambiguidade sobre o que pode ser reaproveitado e o que precisa de trilha propria.

## Fase 1 — Dominio de Convite de Equipe

### Objetivo

Parar de provisionar membership imediatamente e passar a trabalhar com convite persistido, tokenizado e auditavel.

### Tasks backend

- [ ] Criar migration `organization_member_invitations`.
- [ ] Definir colunas minimas:
  - `id`
  - `organization_id`
  - `invited_by`
  - `invitee_name`
  - `invitee_email nullable`
  - `invitee_phone`
  - `role_key`
  - `delivery_channel nullable`
  - `delivery_status`
  - `delivery_error nullable`
  - `whatsapp_instance_id nullable`
  - `token`
  - `token_expires_at`
  - `status`
  - `accepted_user_id nullable`
  - `accepted_at nullable`
  - `revoked_at nullable`
  - `last_sent_at nullable`
  - timestamps
- [ ] Criar model, factory e enum de status.
- [ ] Criar resource para listagem administrativa.
- [ ] Criar request de criacao de convite.
- [ ] Criar action `CreateOrganizationMemberInvitationAction`.
- [ ] Deduplicar convite pendente por `organization_id + invitee_phone + role_key`.
- [ ] Validar que `role_key` permitido exclui `partner-owner`, `super-admin` e `platform-admin`.
- [ ] Reutilizar `users.id` existente quando `invitee_phone` ou `invitee_email` ja pertencer a um usuario da plataforma.
- [ ] Registrar explicitamente no convite se o destinatario ja existe:
  - `existing_user_id nullable`
  - ou campo derivado equivalente no resource
- [ ] Registrar activity logs:
  - `organization.team.invitation.created`
  - `organization.team.invitation.resent`
  - `organization.team.invitation.revoked`
  - `organization.team.invitation.accepted`
  - `organization.team.invitation.delivery_failed`

### Tasks de rota

- [ ] `GET /organizations/current/team/invitations`
- [ ] `POST /organizations/current/team/invitations`
- [ ] `POST /organizations/current/team/invitations/{invitation}/resend`
- [ ] `POST /organizations/current/team/invitations/{invitation}/revoke`

### Tasks de compatibilidade

- [ ] Manter `GET /organizations/current/team` apenas para memberships ativos.
- [ ] Decidir se `POST /organizations/current/team` passa a delegar internamente para o fluxo novo ou se sera descontinuado.
- [ ] Preferencia recomendada: descontinuar semanticamente e mover a UI para `/team/invitations`.

### Testes obrigatorios

- [ ] convite cria registro `pending` sem `joined_at`.
- [ ] convite retorna `token`, `invitation_url` e status de entrega.
- [ ] convite nao cria `organization_members` ativo no ato.
- [ ] convite rejeita roles globais e `partner-owner`.
- [ ] convite deduplica pendencias equivalentes.
- [ ] convite para WhatsApp ja existente nao falha por "telefone em uso".
- [ ] convite para usuario existente nao cria nova linha em `users`.

### Gate de saida

- nenhum convite novo gera membership ativo direto;
- painel passa a receber metadados de convite;
- ownership deixa de passar pelo CRUD generico.

## Fase 2 — Entrega por WhatsApp Escopada por Organizacao

### Objetivo

Enviar o link pelo WhatsApp correto, com identidade operacional da organizacao convidante.

### Tasks backend

- [ ] Criar `OrganizationWhatsAppInvitationInstanceResolver`.
- [ ] Resolver instancia na ordem:
  - default conectada da organizacao;
  - unica conectada da organizacao;
  - sem instancia elegivel => retornar `null`
- [ ] Criar `OrganizationInvitationWhatsAppDeliveryService`.
- [ ] Reaproveitar `WhatsAppMessagingService::sendText`.
- [ ] Persistir metadados de entrega no convite.
- [ ] Nao usar `AuthOtpDeliveryService` nesse fluxo.
- [ ] Adicionar template inicial da mensagem.

### Tasks de comportamento

- [ ] `send_via_whatsapp = true` tenta envio.
- [ ] sem instancia elegivel o convite ainda deve ser criado.
- [ ] resposta deve indicar:
  - `delivery_channel`
  - `delivery_status`
  - `delivery_error`
  - `invitation_url`
- [ ] `resend` deve reutilizar o mesmo token enquanto convite estiver valido, salvo decisao posterior contraria.

### Testes obrigatorios

- [ ] usa a instancia default conectada da organizacao.
- [ ] usa a unica instancia conectada da organizacao quando nao houver default.
- [ ] nao usa instancia de outra organizacao.
- [ ] nao usa instancia global de autenticacao.
- [ ] sem instancia conectada ainda retorna link manual.
- [ ] `resend` atualiza `last_sent_at` e activity log.

### Gate de saida

- link pode ser enviado por WhatsApp sem romper scoping;
- convite continua utilizavel mesmo sem sender conectado.

## Fase 3 — Aceite Publico do Convite

### Objetivo

Permitir que o convidado conclua o onboarding sem criar nova organizacao.

### Tasks backend

- [ ] Criar endpoints publicos:
  - `GET /public/team-invitations/{token}`
  - `POST /public/team-invitations/{token}/request-otp`
  - `POST /public/team-invitations/{token}/verify-otp`
  - `POST /public/team-invitations/{token}/accept`
- [ ] Criar action de aceite para usuario autenticado.
- [ ] Criar action de aceite para usuario autenticado apos OTP.
- [ ] Criar ramo proprio de onboarding por convite.
- [ ] Nao chamar `CreateOrganizationAction` nesse fluxo.
- [ ] Vincular usuario existente por telefone e/ou email quando aplicavel.
- [ ] Criar membership `active` somente no aceite.
- [ ] Invalidar convite apos aceite.
- [ ] Se o usuario ja estiver autenticado, permitir aceite direto sem repetir onboarding completo.
- [ ] Se o usuario existir mas nao estiver autenticado, usar OTP ou recuperacao de acesso sem criar outra conta.

### Integracao com OTP

- [ ] Reaproveitar a experiencia de OTP onde fizer sentido.
- [ ] Nao reaproveitar `RegisterWithWhatsAppOtpAction` como fluxo final de aceite.
- [ ] Opcao recomendada:
  - criar `RequestInvitationOtpAction`
  - criar `VerifyInvitationOtpAction`
  - criar `AcceptOrganizationMemberInvitationAction`

### Testes obrigatorios

- [ ] token valido retorna contexto do convite.
- [ ] token expirado retorna erro consistente.
- [ ] token revogado retorna erro consistente.
- [ ] usuario novo aceita convite sem gerar organizacao nova.
- [ ] usuario existente aceita convite e vira membro da organizacao correta.
- [ ] aceite nao duplica membership existente.
- [ ] usuario existente aceita convite sem duplicar `users`.
- [ ] usuario existente passa a ter mais de um workspace quando aplicavel.

### Gate de saida

- onboarding de equipe existe de ponta a ponta;
- nenhuma organizacao nova e criada ao aceitar convite;
- membership nasce no aceite, nao na emissao.

## Fase 4B â€” Contexto Ativo e Workspace Selector

### Objetivo

Permitir que um unico usuario opere em multiplas organizacoes e acessos event-scoped sem colapsar a sessao para o primeiro membership.

### Status consolidado em `2026-04-10 14:32:00 -03:00`

- [x] `GET /auth/me` ja devolve `workspaces.organizations`, `workspaces.event_accesses` e `active_context`.
- [x] `POST /auth/context/organization` ja troca o workspace organizacional ativo.
- [x] `POST /auth/context/event` ja fixa o contexto event-scoped ativo.
- [x] o frontend ja redireciona usuario event-only para `/my-events` ou `/my-events/{eventId}`.
- [x] o layout event-scoped ja esconde a navegacao organizacional.
- [x] `GET /access/presets` ja existe para evitar hardcode da matriz no frontend.
- [x] o dominio inicial de convite por evento ja persiste convites pendentes com `existing_user_id` quando houver usuario existente.
- [x] leitura publica do convite por evento ja existe.
- [x] aceite publico do convite por evento ja cria usuario sem gerar organizacao nova quando necessario.
- [x] aceite autenticado do convite por evento ja reutiliza o mesmo `users.id`.
- [x] a rota publica do web `/convites/eventos/:token` ja existe para concluir o aceite.
- [x] reenvio/revogacao do convite por evento ja existem com rotacao de token, refresh de expiracao, activity log e invalidacao imediata do link revogado.
- [x] o envio do convite por WhatsApp do evento/organizacao ja existe com resolver escopado, priorizando a instancia padrao conectada do evento e fallback seguro para a organizacao.
- [x] a UI `/events/:eventId/access` ja existe com equipe ativa, convites pendentes, criacao, copia de link, reenvio, revogacao e remocao de acesso.
- [x] a aba `Equipe` de `/settings` ja foi migrada para o mesmo padrao de convites, com separacao entre membros ativos e convites pendentes.

### Tasks backend

- [ ] Expandir `GET /auth/me` com:
  - `workspaces.organizations`
  - `workspaces.event_accesses`
  - `active_context`
- [ ] Para cada `event_access`, retornar:
  - `event_id`
  - `event_title`
  - `event_date`
  - `organization_id`
  - `organization_name`
  - `role_key`
  - `role_label`
  - `capabilities`
  - `entry_path`
- [ ] Definir fonte de verdade do contexto ativo:
  - preferencia persistida do usuario
  - ou contexto por token/sessao
- [ ] Criar `POST /auth/context/organization`.
- [ ] Opcionalmente criar `POST /auth/context/event`.
- [ ] Parar de depender implicitamente de `organizations()->first()` como estrategia principal.
- [ ] Criar endpoint de presets, por exemplo `GET /access/presets`, para a UI nao hardcodar a matriz.

### Tasks frontend

- [ ] Adicionar seletor de workspace quando o usuario tiver mais de uma organizacao.
- [ ] Se o usuario for apenas event-scoped, entrar direto no evento.
- [ ] Exibir claramente o contexto atual no header/shell.
- [ ] Criar home `/my-events` para DJ/noivos/terceiros sem acesso organizacional.
- [ ] Agrupar cards de eventos por parceiro/organizacao.
- [ ] Mostrar acoes por evento usando `capabilities`, nao role hardcoded.
- [ ] Esconder sidebar organizacional quando `active_context.type = event`.

### Testes obrigatorios

- [ ] `/auth/me` devolve lista de workspaces para usuario multi-organizacao.
- [ ] DJ com 4 eventos de 3 parceiros recebe 4 `event_accesses` agrupaveis.
- [ ] troca de workspace altera o escopo dos endpoints organizacionais.
- [ ] usuario event-scoped nao enxerga organizacoes nao vinculadas.
- [ ] frontend mostra somente acoes permitidas pelo preset/capability daquele evento.

### Gate de saida

- DJ recorrente e secretaria multi-organizacao conseguem usar a mesma conta sem ambiguidade de contexto.

## Fase 4 — UI de Equipe em `/settings`

### Objetivo

Trocar o conceito visual de "adicionar membro" por "emitir convite", com rastreabilidade e estados claros.

### Tasks frontend

- [x] Renomear CTA principal para `Convidar pessoa`.
- [x] Remover `partner-owner` das opcoes do select generico.
- [x] Adicionar checkbox `Enviar convite pelo WhatsApp`.
- [x] Enviar payload com preferencia explicita de entrega.
- [x] Mostrar `invitation_url` para copia manual.
- [x] Mostrar status de entrega:
  - `link_gerado`
  - `whatsapp_enviado`
  - `falha_no_envio`
- [x] Separar UI em duas secoes:
  - membros ativos
  - convites pendentes
- [x] Adicionar acoes em convite pendente:
  - copiar link
  - reenviar
  - revogar
- [x] Ajustar textos e labels integralmente para PT-BR com linguagem mais humana.
- [x] Manter confirmacao modal para remocao de membro ativo.

### Tasks de dados

- [x] Criar queries dedicadas:
  - `listCurrentOrganizationTeam`
  - `listCurrentOrganizationTeamInvitations`
- [x] Invalidate correto apos criar/reenviar/revogar/aceitar.
- [x] Atualizar tipos locais do modulo.

### Testes obrigatorios

- [x] exibe toggle de envio por WhatsApp.
- [x] envia `send_via_whatsapp` no payload.
- [x] renderiza convites pendentes separados da equipe ativa.
- [x] exibe link manual quando necessario.
- [x] revoga convite pendente com feedback visual.
- [x] owner nao aparece no fluxo generico de convite.

### Gate de saida

- a aba `Equipe` deixa de ser CRUD imediato de membership;
- o usuario entende claramente quando houve emissao, envio, falha, reenvio e aceite.

## Fase 5 — Branding V1 Funcional

### Objetivo

Sair do branding administrativo minimo e preparar uma base util ao produto inteiro.

### Tasks backend

- [ ] Criar snapshot/config coerente de branding da organizacao.
- [ ] Adicionar campos/ativos conforme prioridade:
  - `logo_dark_path nullable`
  - `favicon_path nullable`
  - `watermark_path nullable`
  - `cover_path nullable`
- [ ] Validar quais ativos dependem de entitlement.
- [ ] Criar action de upload por ativo, sem sobrecarregar o endpoint atual de logo.

### Tasks frontend

- [ ] Mostrar preview real do branding da organizacao.
- [ ] Exibir gates de entitlement:
  - white-label
  - custom domain
  - branding expandido
- [ ] Esconder ou desabilitar campos nao disponiveis no plano.
- [ ] Mostrar motivo da restricao com CTA de upgrade quando aplicavel.

### Testes obrigatorios

- [ ] assets permitidos sobem corretamente.
- [ ] assets bloqueados por entitlement retornam 403/422 coerente.
- [ ] UI reage ao entitlement real vindo de `/auth/me`.
- [ ] preview reflete branding salvo.

### Gate de saida

- `/settings` para branding passa a ser guiado por entitlement;
- o usuario entende o que o plano libera e o que nao libera.

## Fase 6 — Branding V2: Heranca Organizacao -> Evento

### Objetivo

Reduzir duplicacao operacional e criar fallback coerente entre organizacao e evento.

### Tasks backend

- [ ] Definir politica de heranca:
  - evento novo recebe defaults da organizacao;
  - evento pode sobrescrever branding proprio;
  - evento pode optar por `inherit_branding = true`
- [ ] Ajustar create/update de evento.
- [ ] Ajustar resources publicos quando evento nao tiver ativo proprio.

### Tasks frontend

- [ ] Exibir opcao `Herdar branding da organizacao` na configuracao do evento.
- [ ] Exibir preview do fallback.

### Testes obrigatorios

- [ ] evento novo herda branding default.
- [ ] evento com override mantem branding proprio.
- [ ] superficies publicas usam fallback coerente quando o evento nao tiver asset proprio.

### Gate de saida

- branding da organizacao deixa de ser apenas administrativo;
- eventos passam a ter fallback previsivel.

## Ownership — Fluxo Separado

### Decisao

Transferencia de ownership sai deste escopo operacional e ganha trilha propria.

### Trabalho futuro recomendado

- [ ] criar endpoint especifico de ownership transfer;
- [ ] exigir confirmacao do owner atual;
- [ ] registrar trilha forte de auditoria;
- [ ] impedir multiplos owners se essa for a regra definitiva do produto.

## Observabilidade e Auditoria

### Eventos de activity recomendados

- `organization.team.invitation.created`
- `organization.team.invitation.sent`
- `organization.team.invitation.delivery_failed`
- `organization.team.invitation.resent`
- `organization.team.invitation.revoked`
- `organization.team.invitation.accepted`
- `organization.team.member.removed`
- `organization.branding.asset_uploaded`
- `organization.branding.updated`

### Metricas recomendadas

- convites emitidos por dia
- taxa de entrega WhatsApp
- taxa de aceite
- tempo medio entre emissao e aceite
- convites expirados
- convites revogados

## Riscos e Mitigacoes

### Risco 1 — Misturar convite com cadastro publico existente

Mitigacao:

- criar actions proprias para convite;
- nao reutilizar diretamente `RegisterWithWhatsAppOtpAction` como aceite.

### Risco 2 — Vazamento de identidade operacional no WhatsApp

Mitigacao:

- resolver sender por organizacao;
- proibir fallback para instancia global.

### Risco 3 — Ownership continuar no CRUD generico

Mitigacao:

- remover `partner-owner` do formulario e do request novo;
- planejar fluxo dedicado depois.

### Risco 4 — Branding crescer sem modelo de fallback

Mitigacao:

- separar Branding V1 administrativo de Branding V2 com heranca organizacao -> evento.

## Bateria de Testes Recomendada por Etapa

### Backend

- `tests/Feature/Organizations/OrganizationTest.php`
- `tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- `tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`
- `tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
- `tests/Feature/Auth/RegisterOtpTest.php`
- `tests/Feature/Auth/MeTest.php`
- `tests/Feature/WhatsApp/WhatsAppInstanceManagementTest.php`

### Frontend

- `src/modules/settings/SettingsPage.test.tsx`
- `src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx`
- `src/app/layouts/AppSidebar.test.tsx`

### Gate tecnico recorrente

- `php artisan test ...`
- `npx vitest run ...`
- `npm run type-check`

## Validacao executada antes da implementacao

Backend:

- `php artisan test tests/Feature/Auth/RegisterOtpTest.php tests/Feature/Auth/PasswordResetOtpTest.php tests/Feature/Auth/LoginTest.php tests/Feature/Auth/MeTest.php tests/Feature/Notifications/NotificationRealtimeAuthorizationTest.php tests/Feature/Notifications/NotificationCenterReadinessTest.php tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- resultado: `42 passed`, `308 assertions`

Contratos backend:

- `php artisan test tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php`
- resultado: `5 passed`, `19 todo`, `23 assertions`

Frontend:

- `npx vitest run src/modules/auth/LoginPage.test.tsx src/modules/auth/login-navigation.test.ts src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/moderation/moderation-event-scope.contract.test.ts src/app/layouts/AppHeader.characterization.test.tsx`
- resultado: `4 passed`, `27 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao complementar apos workspaces + convites pendentes por evento

Backend:

- `php artisan test tests/Feature/Auth/EventOnlySessionCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php`
- resultado: `59 passed`, `6 todo`, `702 assertions`

Frontend:

- `npx vitest run src/modules/auth/MyEventsPage.test.tsx src/modules/auth/workspace-utils.test.ts src/app/layouts/AppSidebar.test.tsx src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
- resultado: `10 passed`, `19 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

Rotas novas desta rodada:

- `GET /api/v1/events/{event}/access/invitations`
- `POST /api/v1/events/{event}/access/invitations`
- `POST /api/v1/events/{event}/access/invitations/{invitation}/resend`
- `POST /api/v1/events/{event}/access/invitations/{invitation}/revoke`
- `GET /api/v1/public/event-invitations/{token}`
- `POST /api/v1/public/event-invitations/{token}/accept`
- `POST /api/v1/event-invitations/{token}/accept`
- `/convites/eventos/:token`
- `/events/:eventId/access`

## Ordem Recomendada de Execucao

1. Fase 1 — dominio de convite
2. Fase 2 — entrega por WhatsApp escopada
3. Fase 3 — aceite publico do convite
4. Fase 4 — UI de equipe
5. Fase 5 — branding V1
6. Fase 6 — branding V2

## Leitura Final

O slice organizacional de `/settings > Equipe` deixou de ser CRUD imediato de membership.

Agora a trilha correta esta implementada:

- `POST /organizations/current/team` emite `OrganizationMemberInvitation` pendente;
- a equipe ativa continua em `GET /organizations/current/team`;
- convites pendentes saem por `GET /organizations/current/team/invitations`;
- reenvio e revogacao existem com rotacao de token;
- aceite publico e aceite autenticado reutilizam o mesmo `users.id` quando o usuario ja existe;
- o onboarding da equipe organizacional nao cria organizacao nova;
- envio por WhatsApp usa apenas instancia conectada da propria organizacao.

O backlog real deste plano ficou concentrado em:

- ownership transfer dedicado;
- branding V1 expandido por entitlement;
- heranca organizacao -> evento.

Atualizacao de dependencia compartilhada em `2026-04-10 18:25:00 -03:00`:

- [x] o slice event-scoped ja cobre emissao, aceite publico, aceite autenticado, reenvio e revogacao de convite;
- [x] o slice event-scoped ja cobre envio por WhatsApp do evento/organizacao sem fallback para instancia global do sistema;
- [x] o slice event-scoped ja cobre a superficie administrativa `/events/:eventId/access` no web;
- [x] o slice organizacional em `/settings` agora usa a mesma semantica de convite pendente, aceite posterior, reenvio/revogacao e reutilizacao de `users.id`.

Validacao complementar do slice organizacional em `2026-04-10 22:40:00 -03:00`:

Backend:

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php tests/Feature/Organizations/OrganizationTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php`
  - `33 passed`, `303 assertions`

Frontend:

- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-team/EventAccessPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/app/layouts/AppSidebar.test.tsx`
  - `29 passed`

Type-check:

- `npm run type-check`
  - `ok`

Rotas organizacionais confirmadas nesta rodada:

- `GET /api/v1/organizations/current/team/invitations`
- `POST /api/v1/organizations/current/team`
- `POST /api/v1/organizations/current/team/invitations/{invitation}/resend`
- `POST /api/v1/organizations/current/team/invitations/{invitation}/revoke`
- `GET /api/v1/public/organization-invitations/{token}`
- `POST /api/v1/public/organization-invitations/{token}/accept`
- `POST /api/v1/organization-invitations/{token}/accept`
- `/convites/equipe/:token`

Atualizacao de fechamento em `2026-04-10 23:07:23 -03:00`:

- [x] erro real `SQLSTATE[42P01] relation "organization_member_invitations" does not exist` corrigido aplicando as migrations pendentes no banco local.
- [x] ownership saiu definitivamente do convite generico e ganhou endpoint dedicado `POST /api/v1/organizations/current/team/ownership-transfer`.
- [x] transferencia de titularidade exige owner atual ou admin global, rebaixa o owner anterior para `partner-manager`, promove o novo titular para `partner-owner` e registra activity log.
- [x] `/settings > Equipe` ganhou acao separada `Tornar titular` com modal de confirmacao; o fluxo comum de convite continua sem expor `partner-owner`.
- [x] branding V1 expandido ganhou colunas de organizacao: `logo_dark_path`, `favicon_path`, `watermark_path`, `cover_path`.
- [x] branding V1 expandido ganhou endpoint dedicado `POST /api/v1/organizations/current/branding/assets`, sem sobrecarregar o endpoint legado de logo.
- [x] dominio proprio passou a respeitar entitlement `branding.custom_domain`.
- [x] assets premium passaram a respeitar entitlement `branding.expanded_assets` e `branding.watermark`, derivados do plano ativo.
- [x] `/auth/me` e access matrix agora expõem os novos entitlements e URLs de ativos de branding da organizacao.
- [x] eventos ganharam `inherit_branding = true` por padrao e `effective_branding` nos resources.
- [x] `EventBrandingResolver` aplica fallback organizacao -> evento sem copiar dados desnecessariamente para cada evento.
- [x] superficies publicas principais de upload, busca facial publica e Play passam a usar o branding efetivo quando o evento nao tiver asset proprio.
- [x] foi corrigido um bug antigo em `GET /events?organization_id=...`: a query string vinha como string e era comparada estritamente com `currentOrganizationId` inteiro, gerando `403` indevido.

Validacao executada nesta rodada:

Backend:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationOwnershipTransferTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Events/EventBrandingUploadTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php`
  - `59 passed`, `469 assertions`

Frontend:

- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx`
  - `23 passed`

Type-check:

- `npm run type-check`
  - `ok`

Rotas novas confirmadas nesta rodada:

- `POST /api/v1/organizations/current/team/ownership-transfer`
- `POST /api/v1/organizations/current/branding/assets`

Pendencias reais para fechar 100% da trilha visual:

- [ ] adicionar no editor de evento a opcao explicita `Herdar branding da organizacao`.
- [ ] mostrar preview do `effective_branding` no editor/detalhe do evento, nao apenas no payload.
- [ ] criar CTA comercial de upgrade quando o usuario tenta usar dominio proprio ou asset premium sem entitlement.
- [ ] definir operacao completa de DNS/SSL para `custom_domain`, fora do escopo desta V1.

Atualizacao visual do branding em `2026-04-10 23:55:00 -03:00`:

- [x] o editor de evento agora mostra a opcao explicita `Herdar branding da organizacao`.
- [x] o payload do editor deixou de forcar cor propria por default; campos vazios agora preservam o fallback organizacao -> evento quando `inherit_branding=true`.
- [x] o editor agora calcula preview local do `effective_branding`, reaproveitando capa, logo e cores da organizacao quando o evento nao tiver override proprio.
- [x] o detalhe do evento agora mostra preview visual do `effective_branding`, com origem atual (`Evento`, `Organizacao` ou `Evento + organizacao`) e explicacao legivel.
- [x] os contratos do front (`types.ts` e `api-types.ts`) agora conhecem `inherit_branding` e `effective_branding`.

Validacao executada nesta rodada:

Backend:

- `php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php`
  - `18 passed`, `160 assertions`

Frontend:

- `npx vitest run src/modules/events/branding.test.ts src/modules/events/EventDetailPage.test.tsx`
  - `6 passed`

Type-check:

- `npm run type-check`
  - `ok`

Pendencias reais apos esta rodada:

- [x] adicionar no editor de evento a opcao explicita `Herdar branding da organizacao`.
- [x] mostrar preview do `effective_branding` no editor/detalhe do evento, nao apenas no payload.
- [ ] criar CTA comercial de upgrade quando o usuario tenta usar dominio proprio ou asset premium sem entitlement.
- [ ] definir operacao completa de DNS/SSL para `custom_domain`, fora do escopo desta V1.

Atualizacao de UX/UI e linguagem guiada em `2026-04-11 00:37:52 -03:00`:

- [x] a aba `Branding` de `/settings` passou a se apresentar como `Identidade visual`, reduzindo jargao em ingles para usuario iniciante.
- [x] o bloco organizacional de dominio proprio agora usa linguagem mais simples (`Dominio da sua marca`) e explica o bloqueio sem CTA comercial.
- [x] o bloco de assets premium foi reescrito como `Arquivos extras da sua marca`, removendo `white-label`, `depende do plano` e outros termos tecnicos do front.
- [x] os estados bloqueados de branding premium continuam visiveis, mas agora com texto neutro e explicativo; o CTA comercial ficou pausado por decisao desta rodada.
- [x] a secao de branding recebeu polida visual com icones mais claros, textos de apoio curtos e tooltips posicionados nos pontos de maior duvida.
- [x] o editor de evento passou a falar em `Identidade visual do evento`, `Usar visual da organizacao` e `Preview do visual aplicado`.
- [x] o detalhe do evento passou a exibir `Visual aplicado` e `Como o visual e montado`, removendo referencia tecnica literal a `effective_branding`, `logo_path` e `cover_image_path`.
- [x] o fluxo de DNS/SSL de `custom_domain` continua deliberadamente fora do escopo desta etapa.

Validacao executada nesta rodada:

Backend:

- `php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php tests/Feature/Organizations/OrganizationTest.php`
  - `33 passed`, `252 assertions`

Frontend:

- `npx vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/events/branding.test.ts src/modules/events/EventDetailPage.test.tsx`
  - `29 passed`

Type-check:

- `npm run type-check`
  - `ok`

Pendencias reais apos esta rodada:

- [ ] CTA comercial de upgrade para branding premium permanece pausado nesta iteracao.
- [ ] operacao completa de DNS/SSL para `custom_domain` permanece fora do escopo atual.
