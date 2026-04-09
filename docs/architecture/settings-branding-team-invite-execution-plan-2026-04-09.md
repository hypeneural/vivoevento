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

## Decisoes de Arquitetura Ja Travadas

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

## Contratos TDD Ja Criados

### Backend

- `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`

### Frontend

- `apps/web/src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx`

Esses testes cumprem dois papeis:

- congelar o comportamento atual que precisa ser substituido;
- explicitar o comportamento futuro antes da implementacao.

## Fase 0 — Baseline e Contratos

### Status

- [x] Revisar o fluxo atual de `settings`, `branding`, `team`, `auth/register` e `whatsapp`.
- [x] Confirmar via teste que o fluxo atual cria membership ativo imediatamente.
- [x] Confirmar via teste que o fluxo atual ainda permite novo owner pelo CRUD generico.
- [x] Confirmar via teste que o cadastro OTP atual cria organizacao nova.
- [x] Confirmar via teste que o envio OTP atual usa sender global/configurado de autenticacao.
- [x] Criar contratos TDD backend e frontend para o fluxo futuro.

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

### Gate de saida

- onboarding de equipe existe de ponta a ponta;
- nenhuma organizacao nova e criada ao aceitar convite;
- membership nasce no aceite, nao na emissao.

## Fase 4 — UI de Equipe em `/settings`

### Objetivo

Trocar o conceito visual de "adicionar membro" por "emitir convite", com rastreabilidade e estados claros.

### Tasks frontend

- [ ] Renomear CTA principal para `Convidar membro`.
- [ ] Remover `partner-owner` das opcoes do select generico.
- [ ] Adicionar checkbox `Enviar convite pelo WhatsApp`.
- [ ] Enviar payload com preferencia explicita de entrega.
- [ ] Mostrar `invitation_url` para copia manual.
- [ ] Mostrar status de entrega:
  - `link_gerado`
  - `whatsapp_enviado`
  - `falha_no_envio`
- [ ] Separar UI em duas secoes:
  - membros ativos
  - convites pendentes
- [ ] Adicionar acoes em convite pendente:
  - copiar link
  - reenviar
  - revogar
- [ ] Ajustar textos e labels integralmente para PT-BR.
- [ ] Manter confirmacao modal para remocao de membro ativo.

### Tasks de dados

- [ ] Criar queries dedicadas:
  - `listCurrentOrganizationTeam`
  - `listCurrentOrganizationTeamInvitations`
- [ ] Invalidate correto apos criar/reenviar/revogar/aceitar.
- [ ] Atualizar tipos locais do modulo.

### Testes obrigatorios

- [ ] exibe toggle de envio por WhatsApp.
- [ ] envia `send_via_whatsapp` no payload.
- [ ] renderiza convites pendentes separados da equipe ativa.
- [ ] exibe link manual quando necessario.
- [ ] revoga convite pendente com feedback visual.
- [ ] owner nao aparece no fluxo generico de convite.

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

## Ordem Recomendada de Execucao

1. Fase 1 — dominio de convite
2. Fase 2 — entrega por WhatsApp escopada
3. Fase 3 — aceite publico do convite
4. Fase 4 — UI de equipe
5. Fase 5 — branding V1
6. Fase 6 — branding V2

## Leitura Final

O proximo passo correto nao e continuar mexendo no `POST /organizations/current/team` atual.

O proximo passo correto e:

- introduzir `OrganizationMemberInvitation`;
- mover a aba `Equipe` para um fluxo real de convite;
- separar ownership;
- tratar branding em trilha propria, com heranca e entitlement-aware UX.

Enquanto isso nao acontecer, o sistema continuara funcionando, mas com semantica errada:

- "convite" que na verdade provisiona acesso;
- onboarding que cria organizacao nova quando deveria apenas aceitar membership;
- scoping de WhatsApp errado para identidade organizacional;
- branding ainda insuficiente para governar as superficies do produto.
