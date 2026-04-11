# Access, branding and admin remaining scope validation - 2026-04-11

## Objetivo

Consolidar, com base no codigo e na bateria de testes executada em `2026-04-11`, o que:

- ja esta entregue e nao precisa ser reaberto;
- ainda precisa de melhoria de UX, cobertura ou endurecimento;
- continua fora do escopo atual.

Este documento fecha a leitura combinada de:

- `settings-branding-team-invite-execution-plan-2026-04-09.md`
- `partners-admin-crud-access-plan.md`
- `event-access-granularity-security-plan-2026-04-09.md`

com foco nos pontos abaixo:

1. CTA comercial de upgrade para branding premium
2. convite organizacional/event-scoped
3. ownership transfer
4. reenvio/revogacao
5. reuse de `users.id`
6. heranca de branding
7. entitlement enforcement
8. partner admin scope
9. access matrix e `/auth/me`

---

## Veredito executivo

O nucleo funcional desta trilha esta entregue e validado.

Hoje, o que realmente continua aberto fica concentrado em tres blocos:

1. `CTA comercial de upgrade para branding premium` no frontend
2. `custom_domain` com operacao completa de DNS/SSL
3. endurecimento de UX e cobertura em `workspace selector` / `/my-events` / `EventEditorPage`

O resto dos pontos pedidos nesta revisao ja esta implementado e coberto por testes de feature e UI.

Em outras palavras:

- **nao ha backlog funcional relevante aberto** para convite organizacional/event-scoped, ownership transfer, reenvio/revogacao, reuse de `users.id`, heranca de branding, entitlement enforcement, partner admin scope e access matrix basica;
- **ha backlog de acabamento e clareza** em CTA comercial, UX de workspaces e cobertura explicita de algumas superficies do front.

---

## Validacao executada nesta pesquisa

### Rotas confirmadas

Organizacao atual:

- `GET /api/v1/organizations/current`
- `PATCH /api/v1/organizations/current`
- `PATCH /api/v1/organizations/current/branding`
- `POST /api/v1/organizations/current/branding/assets`
- `POST /api/v1/organizations/current/branding/logo`
- `GET /api/v1/organizations/current/team`
- `POST /api/v1/organizations/current/team`
- `GET /api/v1/organizations/current/team/invitations`
- `POST /api/v1/organizations/current/team/invitations/{invitation}/resend`
- `POST /api/v1/organizations/current/team/invitations/{invitation}/revoke`
- `POST /api/v1/organizations/current/team/ownership-transfer`
- `DELETE /api/v1/organizations/current/team/{member}`

Convites organizacionais:

- `GET /api/v1/public/organization-invitations/{token}`
- `POST /api/v1/public/organization-invitations/{token}/accept`
- `POST /api/v1/organization-invitations/{token}/accept`

Convites event-scoped:

- `GET /api/v1/public/event-invitations/{token}`
- `POST /api/v1/public/event-invitations/{token}/accept`
- `POST /api/v1/event-invitations/{token}/accept`
- `GET /api/v1/events/{event}/access/invitations`
- `POST /api/v1/events/{event}/access/invitations`
- `POST /api/v1/events/{event}/access/invitations/{invitation}/resend`
- `POST /api/v1/events/{event}/access/invitations/{invitation}/revoke`

Branding de evento:

- `PATCH /api/v1/events/{event}/branding`
- `POST /api/v1/events/branding-assets`

### Backend

Bateria ampliada:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationOwnershipTransferTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php tests/Feature/Organizations/OrganizationAdminScopeCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Events/CreateEventTest.php tests/Feature/Events/EventBrandingUploadTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php tests/Feature/Partners/PartnerStatsProjectionQueueTest.php tests/Feature/Billing/AdminQuickEventTest.php tests/Feature/WhatsApp/WhatsAppOrganizationScopeTest.php`
  - resultado: `116 passed`, `1039 assertions`

Bateria focada no recorte desta doc:

- `php artisan test tests/Feature/Organizations/OrganizationTest.php tests/Feature/Organizations/OrganizationOwnershipTransferTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/AccessMatrixTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Partners/PartnerAdminCrudContractTest.php`
  - resultado: `78 passed`, `704 assertions`

### Frontend

- `npx.cmd vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-team/EventAccessPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/modules/partners/PartnersPage.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - resultado: `51 passed`

### Type-check

- `npm run type-check`
  - resultado: `ok`

---

## Matriz de status por tema

## 1. CTA comercial de upgrade para branding premium

Status:

- `pendente real de frontend`

Ja existe:

- entitlement real em `/auth/me`;
- enforcement real no backend para `custom_domain`, `expanded_assets` e `watermark`;
- bloqueio visual no frontend quando o recurso nao esta habilitado;
- copy explicativa em portugues para estado bloqueado.

O que falta:

- card ou CTA contextual em `/settings` apontando para `/plans` quando `custom_domain=false` ou `expanded_assets=false`;
- opcionalmente, CTA equivalente no editor/detalhe do evento quando a organizacao ainda nao tem entitlement;
- copy comercial curta e coerente com usuario leigo.

Leitura:

- este e o unico item desta lista que continua claramente aberto no front por decisao deliberada, nao por falha tecnica.

## 2. Convite organizacional/event-scoped

Status:

- `entregue`

Ja existe:

- emissao de convite pendente;
- aceite publico;
- aceite autenticado;
- reenvio;
- revogacao;
- links publicos dedicados;
- telas administrativas separadas para organizacao e evento;
- envio opcional por WhatsApp;
- separacao entre membros ativos e convites pendentes.

O que realmente falta:

- nenhum bloqueio funcional desta trilha.

Melhorias opcionais:

- smoke E2E de jornada completa no navegador;
- convergencia visual maior entre `/settings > Equipe` e `/events/:eventId/access`.

## 3. Ownership transfer

Status:

- `entregue`

Ja existe:

- endpoint dedicado `POST /api/v1/organizations/current/team/ownership-transfer`;
- regra de autorizacao para owner atual ou admin global;
- validacao de alvo pertencente a organizacao atual;
- rebaixamento do owner anterior para `partner-manager`;
- promocao do novo titular para `partner-owner`;
- modal dedicado no frontend.

O que realmente falta:

- nenhum bloqueio funcional.

Melhorias opcionais:

- expor activity log desta acao em uma UI administrativa;
- notificacao explicita para owner antigo e novo owner.

## 4. Reenvio/revogacao

Status:

- `entregue`

Ja existe:

- reenvio organizacional;
- revogacao organizacional;
- reenvio event-scoped;
- revogacao event-scoped;
- rotacao de token;
- bloqueio do token revogado;
- cobertura de frontend e backend.

O que realmente falta:

- nenhum bloqueio funcional.

Melhorias opcionais:

- mostrar historico de convites revogados/reemitidos na UI;
- expor status de entrega mais detalhado quando houver falha do WhatsApp.

## 5. Reuse de `users.id`

Status:

- `entregue`

Ja existe:

- convites organizacionais reutilizam usuario existente;
- convites event-scoped reutilizam usuario existente;
- aceite autenticado nao duplica cadastro;
- aceite publico cria usuario novo apenas quando necessario.

O que realmente falta:

- nenhum bloqueio funcional.

Melhorias opcionais:

- mensagem mais explicita no frontend quando o convite bater com usuario ja existente;
- consolidar testes redundantes ou TODOs antigos que ainda mencionam essa duvida como se fosse backlog.

## 6. Heranca de branding

Status:

- `entregue no nucleo`

Ja existe:

- `inherit_branding=true` por padrao;
- `effective_branding` no backend;
- fallback organizacao -> evento;
- preview no detalhe do evento;
- preview no editor do evento;
- uso do branding efetivo nas superficies publicas principais.

O que realmente falta:

- nenhum bloqueio logico no backend desta trilha;
- `custom_domain` operacional completo continua fora deste item, porque e outra camada.

Melhorias reais:

- adicionar teste de UI dedicado para `EventEditorPage` cobrindo toggle de heranca e preview;
- manter smoke nas superficies publicas mais sensiveis ao branding efetivo.

## 7. Entitlement enforcement

Status:

- `entregue`

Ja existe:

- resolucao de entitlements no backend;
- exposicao em `/auth/me`;
- access matrix refletindo feature flags;
- bloqueio de `custom_domain`;
- bloqueio de assets premium;
- front reagindo ao estado bloqueado.

O que realmente falta:

- nenhum bloqueio logico;
- o que continua aberto aqui e apenas o CTA comercial.

## 8. Partner admin scope

Status:

- `entregue`

Ja existe:

- listagem global restrita a admin global;
- bloqueio para partner-owner ver ou mutar outros parceiros;
- CRUD administrativo completo;
- filtros, detalhe, subrecursos e activity log;
- cobertura de projection queue e fallback sem tabelas derivadas.

O que realmente falta:

- nenhum bloqueio funcional neste recorte.

Melhorias opcionais:

- smoke mais amplo de UX no detalhe administrativo, se esta trilha voltar a ser foco.

## 9. Access matrix e `/auth/me`

Status:

- `entregue no essencial`, com `melhorias de workspace` ainda abertas

Ja existe:

- `active_context`;
- `workspaces.organizations`;
- `workspaces.event_accesses`;
- entitlements de branding;
- permissions e modules na access matrix;
- redirecionamento seguro para usuario event-only;
- sidebar reagindo a workspaces event-scoped.

O que realmente falta:

- fechar os contratos TODO de `workspace selector` e `/my-events` que ainda estao abertos em teste, principalmente:
  - agrupar eventos por organizacao parceira para DJ/usuario com varios eventos;
  - detalhar filtros, ordenacao e empty states em `/my-events`;
  - reforcar por teste a selecao de contexto do evento para moderation/wall/play;
  - transformar os TODOs atuais em testes verdes ou remover o que ficou redundante.

Leitura:

- isso nao bloqueia o que ja foi entregue;
- mas hoje ainda e o principal ponto de endurecimento residual em `access matrix / /auth/me`.

---

## O que realmente precisa entrar no proximo escopo

## Escopo A - necessario agora

1. CTA comercial de upgrade para branding premium
   - `/settings > Organizacao`
   - `/settings > Identidade visual`
   - opcional no editor de evento quando fizer sentido

2. Fechar a lacuna de cobertura do `EventEditorPage`
   - teste de UI para `Usar visual da organizacao`
   - teste de preview do visual aplicado

3. Fechar os TODOs de workspace que ainda representam comportamento esperado
   - `apps/api/tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
   - `apps/web/src/modules/auth/workspace-selector.contract.test.tsx`
   - `apps/web/src/modules/auth/my-events-page.contract.test.tsx`

## Escopo B - melhoria importante, mas nao bloqueante

1. convergir mais a UX entre convite organizacional e convite event-scoped
2. mostrar historico de revogacao/reenvio na UI
3. mostrar sinalizacao mais amigavel quando o convite reaproveita um usuario existente
4. expor audit trail de ownership transfer no front

## Escopo C - fora do escopo atual

1. operacao completa de `custom_domain` com DNS/SSL
2. automacao operacional completa de provisionamento e monitoramento de dominio

---

## O que nao deve ser reaberto

Nao faz sentido reabrir agora como se ainda fossem backlog funcional:

- convite organizacional pendente;
- convite event-scoped pendente;
- ownership transfer;
- reenvio/revogacao;
- reuse de `users.id`;
- heranca de branding;
- enforcement de entitlement;
- partner admin scope;
- access matrix basica e bootstrap de `/auth/me`.

Esses itens ja estao implementados e cobertos.

---

## Conclusao

O novo escopo correto nao e "terminar convites, branding e access matrix".

O novo escopo correto e:

1. fechar o upsell/CTA comercial que ficou pausado;
2. endurecer a UX e os testes de workspace `/my-events` e `EventEditorPage`;
3. manter `custom_domain` completo explicitamente fora da fase atual.

Em uma frase:

**o backlog real ja nao esta mais na logica central de acesso e branding; ele esta no acabamento comercial, na UX multi-workspace e na cobertura explicita de algumas superficies do front.**
