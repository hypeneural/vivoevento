# Access, branding and invitation UX hardening execution plan - 2026-04-11

## Objetivo

Transformar a base ja entregue de acesso, convites, workspaces e branding em uma jornada mais clara, guiada e resistente a regressao para usuario leigo.

Este plano parte do diagnostico consolidado em:

- `access-branding-admin-remaining-scope-2026-04-11.md`
- `settings-branding-team-invite-execution-plan-2026-04-09.md`
- `partners-admin-crud-access-plan.md`
- `event-access-granularity-security-plan-2026-04-09.md`

Foco desta execucao:

1. convite -> login/reset -> aceite
2. workspace selector + `/my-events` + `active_context`
3. branding com heranca, sobrescrita e preview didatico
4. cobertura de front para as costuras que hoje ainda nao estao provadas como contrato

---

## Veredito executivo

O backend principal ja esta bem resolvido.

O que ainda precisa ser feito agora nao e "mais regra de negocio de acesso". O que precisa ser feito e:

1. reduzir friccao de convite e autenticacao;
2. fechar a experiencia multi-workspace como produto, nao apenas como resposta correta de `/auth/me`;
3. tornar branding autoexplicativo para quem esta iniciando na plataforma;
4. endurecer os testes que ainda estao apoiados em TODOs ou em interacoes pouco fieis.

---

## Fora do escopo desta fase

Estes itens nao entram neste plano:

1. operacao completa de `custom_domain` com DNS/SSL
2. automacao operacional completa de provisionamento e monitoramento de dominio
3. retomada do CTA comercial de upgrade para branding premium

Podem voltar depois, mas nao devem contaminar esta execucao.

---

## Validacao oficial das libs e guias

### React

O React oficial recomenda extrair logica de estado complexa para `reducer` quando varios handlers e booleans comecam a se acumular.

Fonte oficial:

- `https://react.dev/learn/extracting-state-logic-into-a-reducer`

Aplicacao neste plano:

- o fluxo `convite + login + reset + aceite + redirect` deve sair de estado fragmentado e ir para um `useReducer` ou maquina de estados equivalente;
- o reducer deve ser testado isoladamente.

### React Router

O React Router oficial valida `MemoryRouter` para testes simples e `createRoutesStub` / `Testing` para testes com mais fidelidade de redirect, `loader`, `action` e contexto.

Fontes oficiais:

- `https://reactrouter.com/api/declarative-routers/MemoryRouter`
- `https://reactrouter.com/start/data/testing`
- `https://reactrouter.com/api/utils/createRoutesStub`

Aplicacao neste plano:

- testes simples podem continuar com `MemoryRouter`;
- testes do fluxo completo de convite com `returnTo` devem subir para abordagem mais fiel quando necessario.

### Testing Library

O Testing Library oficial recomenda `user-event` para interacoes mais proximas do uso real e prioriza queries acessiveis, como `getByRole` e `getByLabelText`.

Fontes oficiais:

- `https://testing-library.com/docs/user-event/intro/`
- `https://testing-library.com/docs/queries/about/`
- `https://testing-library.com/docs/queries/bylabeltext/`

Aplicacao neste plano:

- `LoginPage.test.tsx` e novos testes de convite devem migrar do eixo `fireEvent + placeholder` para `userEvent + labels/roles`.

### Vitest

O Vitest oficial recomenda fake timers para fluxos com countdown, timeout e resend deterministico.

Fonte oficial:

- `https://vitest.dev/guide/mocking/timers`

Aplicacao neste plano:

- countdown de `resend_in` do OTP deve ser coberto com fake timers.

### OWASP

O OWASP reforca mensagens genericas em autenticacao/recuperacao para evitar enumeracao de conta e consistencia da politica de senha em registro, troca e reset.

Fontes oficiais:

- `https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html`
- `https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html`

Aplicacao neste plano:

- a refacao de UX nao pode trocar clareza por vazamento de existencia de conta;
- o fluxo deve ficar mais facil de entender sem abrir respostas mais especificas no `forgot password`.

---

## Estado atual validado

### Entregue no nucleo

1. convite organizacional e event-scoped
2. aceite publico e autenticado
3. reenvio e revogacao
4. reuse de `users.id`
5. ownership transfer
6. entitlement enforcement
7. heranca de branding
8. partner admin scope
9. `active_context` + `workspaces` em `/auth/me`

### Gaps confirmados por codigo

1. as paginas publicas de convite ainda nao tem costura completa com `Esqueci a senha`
2. `LoginPage.test.tsx` ainda usa `fireEvent` e `placeholder` como base predominante
3. `workspace selector` e `/my-events` ja tem contratos verdes, mas ainda precisam de fechamento de UX
4. `EventEditorPage` ja tem teste de UI dedicado, mas branding ainda nao deixa explicito onde cada arquivo aparece e se esta herdado ou sobrescrito

---

## Arquitetura alvo

## 1. Fluxo unico de convite + auth

Criar um fluxo explicito, com estado centralizado, algo como:

- `invite_context_loaded`
- `needs_existing_login`
- `needs_new_account`
- `otp_requested`
- `otp_verified`
- `password_defined`
- `invite_accepted`
- `context_selected`
- `redirect_ready`

Implementacao recomendada:

- `apps/web/src/modules/auth/hooks/useInvitationAuthFlow.ts`
- `apps/web/src/modules/auth/support/invitationAuthFlowReducer.ts`
- `apps/web/src/modules/auth/support/invitationAuthFlowTypes.ts`

Regra:

- convite organizacional e convite event-scoped devem compartilhar a mesma base de fluxo;
- o que muda e apenas o contexto exibido e a API usada.

## 2. Componentes compartilhados de convite/auth

Componentes recomendados:

- `InvitationContextCard`
- `AuthIdentityInput`
- `OtpDeliveryStatus`
- `OtpCodeStep`
- `PasswordSetupStep`
- `ExistingAccountHint`
- `AuthFlowFooter`

Local sugerido:

- `apps/web/src/modules/auth/components/invitation-flow/`

## 3. Componentes de workspace

Componentes recomendados:

- `WorkspaceSelectorDialog`
- `WorkspaceGroupList`
- `MyEventsGroupedList`
- `ActiveContextBadge`

Local sugerido:

- `apps/web/src/modules/auth/components/workspaces/`

## 4. Componentes de branding

Componentes recomendados:

- `BrandingAssetCard`
- `BrandingSurfacePreview`
- `BrandingOriginBadge`
- `BrandingAssetGuidelines`
- `BrandingInheritanceToggle`

Locais sugeridos:

- `apps/web/src/modules/settings/components/branding/`
- `apps/web/src/modules/events/components/branding/`

---

## Estrategia de TDD

Regra desta execucao:

1. primeiro caracterizar o comportamento esperado em teste
2. depois ajustar contrato ou UI
3. por fim refatorar para componentes compartilhados

Prioridade de teste:

1. testes de contrato e jornada
2. testes de componente/fluxo
3. testes utilitarios
4. type-check

Nao abrir implementacao grande antes de ter, no minimo, os contratos vermelhos ou `todo` promovidos a testes reais.

---

## Fase 0 - characterization e contratos

Status:

- `concluida em 2026-04-11`

### Objetivo

Transformar as expectativas difusas do proximo escopo em contratos explicitos.

### Entregas executadas

1. `PublicOrganizationInvitationResource` e `PublicEventTeamInvitationResource` passaram a expor `invited_by.name`
2. `MultiOrganizationWorkspaceContractTest.php` teve os `todo` relevantes substituidos por testes reais
3. `workspace-selector.contract.test.tsx` e `my-events-page.contract.test.tsx` passaram a ter contratos verdes
4. `EventEditorPage.test.tsx` foi criado para cobrir heranca e preview
5. `LoginPage.test.tsx` ganhou cobertura explicita de `returnTo` e countdown deterministico de reenvio

### Validacao executada

1. `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
   - resultado: `26 passed`, `307 assertions`
2. `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/events/components/EventEditorPage.test.tsx`
   - resultado: `14 passed`
3. `npm run type-check`
   - resultado: `ok`

### Backend

Tarefas:

1. promover gaps reais de convite para testes de contrato
2. fechar os TODOs de `/auth/me` que ainda representam comportamento esperado

Subtarefas:

1. em `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`
   - adicionar teste que exige `invited_by` resumido no payload publico
2. em `apps/api/tests/Feature/EventTeam/EventTeamInvitationContractTest.php`
   - adicionar teste equivalente para convite event-scoped
3. em `apps/api/tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
   - substituir os `todo` por testes reais para:
   - attach de usuario existente por WhatsApp/e-mail
   - agrupamento por organizacao parceira sem acesso organizacional
   - escopo de moderation/wall/play pelo `active_context`

### Frontend

Tarefas:

1. fechar contratos de convite e auth
2. fechar contratos de workspace
3. abrir contrato de `EventEditorPage`

Subtarefas:

1. em `apps/web/src/modules/auth/LoginPage.test.tsx`
   - migrar gradualmente para `userEvent`
   - parar de depender do placeholder do campo principal
   - adicionar testes para loading, erro de OTP, sessao expirada, reenvio e preservacao de `returnTo`
2. em `apps/web/src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx`
   - testar CTA de `Esqueci a senha`
   - testar mensagem de retorno ao proprio convite
   - testar exibicao de `quem convidou`
3. em `apps/web/src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
   - repetir os mesmos contratos para convite event-scoped
4. em `apps/web/src/modules/auth/workspace-selector.contract.test.tsx`
   - substituir todos os `todo` por testes reais
5. em `apps/web/src/modules/auth/my-events-page.contract.test.tsx`
   - substituir todos os `todo` por testes reais
6. criar `apps/web/src/modules/events/components/EventEditorPage.test.tsx`
   - cobrir `Usar visual da organizacao`
   - cobrir preview do `effective_branding`

### Criterio de aceite

1. nenhum `todo` relevante restante nos arquivos acima
2. `LoginPage` com cobertura explicita para resend e `returnTo`
3. `EventEditorPage` com teste de UI dedicado
4. todos os itens acima foram atendidos nesta rodada

---

## Fase 1 - contrato backend de convite e contexto

Status:

- `concluida antecipadamente junto da Fase 0`

### Objetivo

Entregar o minimo de contrato backend necessario para a primeira dobra da UX recomendada.

### Tarefas

1. expor resumo seguro de `quem convidou`
2. manter compatibilidade com o fluxo atual
3. nao abrir dados desnecessarios do contexto

### Subtarefas

1. atualizar `PublicOrganizationInvitationResource`
   - adicionar `invited_by` resumido
   - sugerido: `name` e, se fizer sentido, `role_label`
2. atualizar `PublicEventTeamInvitationResource`
   - adicionar `invited_by` resumido
3. revisar controllers se alguma relacao precisar ser eager loaded
4. validar que payload continua sem expor dados organizacionais nao necessarios

### Testes obrigatorios

1. feature tests de convite organizacional
2. feature tests de convite event-scoped
3. smoke dos endpoints publicos

### Criterio de aceite

1. a UI consegue mostrar `quem convidou`
2. nenhum payload publico passa a expor dados fora do contexto do convite
3. ambos os itens acima ja foram atendidos nesta rodada

---

## Fase 2 - fluxo unificado de convite + auth

Status:

- `em andamento`

### Objetivo

Fazer o usuario entender claramente:

1. onde ele esta entrando
2. se esta usando conta existente ou criando nova
3. qual o proximo passo
4. que ele voltara para o proprio convite
5. para onde sera redirecionado ao final

### Tarefas

1. centralizar estado do fluxo
2. compartilhar a base visual entre convites organizacionais e event-scoped
3. costurar login/reset/aceite com contexto preservado

### Subtarefas

1. criar reducer e tipos do fluxo
   - `invite_context_loaded`
   - `needs_existing_login`
   - `needs_new_account`
   - `otp_requested`
   - `otp_verified`
   - `password_defined`
   - `invite_accepted`
   - `context_selected`
   - `redirect_ready`
2. criar componentes compartilhados da jornada
3. integrar `PublicOrganizationInvitationPage.tsx`
4. integrar `PublicEventInvitationPage.tsx`
5. adicionar CTA `Esqueci a senha` quando `requires_existing_login=true`
6. adicionar copy:
   - `essa mesma conta pode ser usada em varios eventos e convites`
   - `voce voltara para este convite apos entrar`
7. manter resposta generica de seguranca no `forgot password`
8. revisar `login-navigation.ts` e fluxo de `returnTo`

### Testes obrigatorios

1. convite com conta existente
2. convite com nova conta
3. esqueci a senha dentro do convite
4. retorno ao proprio convite
5. aceite automatico com contexto preservado
6. redirecionamento final correto

### Entregas executadas ate agora

1. paginas publicas de convite organizacional e event-scoped agora mostram `quem convidou`, CTA primario de login, CTA secundario de `Esqueci a senha` e copy explicita de continuidade do convite
2. `login-navigation.ts` passou a entender `flow=forgot` e a construir `returnTo` seguro para os links de convite
3. `LoginPage` passou a abrir diretamente em `Esqueci a senha` quando o convite chama esse fluxo
4. `LoginPage` agora retorna explicitamente para o `returnTo` apos reset de senha, sem depender de `window.location.reload()`
5. os campos principais de login e forgot/reset ganharam identificacao acessivel suficiente para sair da dependencia exclusiva de `placeholder`
6. paginas publicas de convite agora permitem `Usar outra conta` quando o usuario ja esta autenticado e precisa trocar de sessao antes do aceite
7. `LoginPage.test.tsx` agora prova a volta ao mesmo convite apos reset bem-sucedido
8. `PublicOrganizationInvitationPage.test.tsx` e `PublicEventInvitationPage.test.tsx` agora provam o fluxo de troca de conta antes do aceite
9. o fluxo principal do `LoginPage` agora usa `useInvitationAuthFlow` + `invitationAuthFlowReducer`, em vez de depender apenas de `setStep` espalhado
10. a maquina de estados inicial da jornada ficou testada em `invitationAuthFlowReducer.test.ts`
11. os blocos visuais repetidos das paginas publicas foram convergidos em componentes compartilhados:
   - `InvitationContextCard`
   - `InvitationExistingLoginActions`
   - `AuthenticatedInvitationActions`
12. os passos internos de `OTP`, `nova senha` e `rodape/ajuda` do `LoginPage` agora tambem foram convergidos em componentes compartilhados:
   - `AuthFlowFooter`
   - `AuthHelpHint`
   - `OtpCodeStep`
   - `PasswordSetupStep`
13. o `LoginPage` deixou de repetir markup e regras visuais entre:
   - login -> criar conta
   - cadastro -> validacao por OTP
   - forgot -> validacao por OTP
   - forgot -> definicao de nova senha
14. `LoginPage.test.tsx` agora prova a navegacao entre `Entrar` e `Criar conta` via rodape compartilhado
15. `LoginPage.test.tsx` agora prova explicitamente o passo de cadastro que avanca para `Valide seu WhatsApp`

### Validacao executada nesta rodada

1. `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
   - resultado: `13 passed`
2. `npm run type-check`
   - resultado: `ok`
3. `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Auth/PasswordResetOtpTest.php`
   - resultado: `24 passed`, `262 assertions`
4. `npx.cmd vitest run src/modules/auth/support/invitationAuthFlowReducer.test.ts src/modules/auth/LoginPage.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
   - resultado: `16 passed`
5. `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx src/modules/auth/support/invitationAuthFlowReducer.test.ts src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
   - resultado: `18 passed`
6. `npm run type-check`
   - resultado: `ok`
7. `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Auth/PasswordResetOtpTest.php`
   - resultado: `24 passed`, `262 assertions`

### Pendencias reais dentro da Fase 2

1. aprofundar o reducer para reduzir ainda mais estados auxiliares e transicoes espalhadas fora da camada de fluxo
2. migrar progressivamente os testes principais de `fireEvent` para `user-event`
3. subir os testes de rota mais sensiveis para uma abordagem mais fiel quando `returnTo`, guard e redirect passarem a pesar mais na jornada
4. decidir se a proxima iteracao converte tambem o passo inicial de `Esqueci a senha` e o estado de sucesso final em componentes compartilhados

### Criterio de aceite

1. usuario existente completa convite sem perder o contexto
2. usuario novo cria conta e cai no contexto correto
3. usuario com varios workspaces aceita primeiro e escolhe contexto depois, se necessario

---

## Fase 3 - workspace selector, /my-events e active_context

### Objetivo

Fazer o usuario entender sempre:

1. em nome de qual organizacao ou evento esta agindo
2. quais eventos pertencem a qual parceira
3. quais acoes ele realmente pode executar

### Tarefas

1. endurecer a leitura de `active_context`
2. melhorar a navegacao de `/my-events`
3. fechar o selector como produto

### Subtarefas

1. extrair `ActiveContextBadge`
2. extrair `WorkspaceGroupList` e `MyEventsGroupedList`
3. revisar `MyEventsPage.tsx`
   - filtros claros
   - ordenacao previsivel
   - empty state `sem eventos`
   - empty state `sem acesso neste filtro`
4. revisar `EventWorkspaceLayout.tsx`
   - deixar mais claro o contexto ativo
   - reforcar o nome da organizacao e do evento
5. esconder navegacao organizacional quando sessao for somente event-scoped
6. garantir links coerentes por capacidade:
   - media
   - moderation
   - wall
   - play

### Testes obrigatorios

1. agrupamento por organizacao parceira
2. usuario com quatro eventos em diferentes parceiras
3. usuario event-only com um evento vai direto para o workspace
4. usuario event-only com varios eventos vai para `/my-events`
5. selected context escopa moderation/wall/play
6. UI nao mostra navegacao organizacional em sessao event-only

### Criterio de aceite

1. o usuario entende de imediato qual evento esta abrindo
2. o usuario entende qual organizacao parceira controla aquele evento
3. a navegacao nao oferece acoes fora das capacidades liberadas

---

## Fase 4 - branding UX hardening

### Objetivo

Explicar o branding sem exigir que o usuario entenda termos tecnicos.

### Tarefas

1. mostrar onde cada arquivo aparece
2. mostrar se o arquivo esta herdado ou sobrescrito
3. orientar formato e proporcao

### Subtarefas

1. criar `BrandingSurfacePreview`
   - pagina publica
   - tela administrativa
   - favicon
   - tela de telao, quando aplicavel
   - watermark, quando aplicavel
2. criar `BrandingOriginBadge`
   - `Herdado da organizacao`
   - `Personalizado neste evento`
3. criar `BrandingAssetGuidelines`
   - formato
   - proporcao
   - uso recomendado
4. revisar copy em `/settings`
5. revisar `EventEditorPage.tsx`
6. revisar `EventDetailPage.tsx`

### Testes obrigatorios

1. `EventEditorPage.test.tsx`
2. testes do preview no detalhe do evento
3. testes de estados herdado x sobrescrito

### Criterio de aceite

1. usuario entende onde cada arquivo aparece
2. usuario entende o que veio da organizacao e o que foi customizado no evento
3. usuario recebe orientacao minima para evitar tentativa e erro de upload

---

## Fase 5 - polimento importante, nao bloqueante

### Tarefas

1. convergencia visual entre convite organizacional e event-scoped
2. historico de convite reemitido/revogado
3. status de entrega WhatsApp mais humano
4. audit trail de ownership transfer no front

### Regra

Esta fase so entra depois de Fase 0 a Fase 4 verdes.

---

## Bateria de testes obrigatoria por entrega

### Backend

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php`
- `php artisan test tests/Feature/EventTeam/EventTeamInvitationContractTest.php`
- `php artisan test tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
- `php artisan test tests/Feature/Auth/PasswordResetOtpTest.php`
- `php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php`

### Frontend

- `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx`
- `npx.cmd vitest run src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx`
- `npx.cmd vitest run src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
- `npx.cmd vitest run src/modules/auth/MyEventsPage.test.tsx`
- `npx.cmd vitest run src/modules/auth/workspace-utils.test.ts`
- `npx.cmd vitest run src/modules/auth/workspace-selector.contract.test.tsx`
- `npx.cmd vitest run src/modules/auth/my-events-page.contract.test.tsx`
- `npx.cmd vitest run src/modules/events/components/EventEditorPage.test.tsx`
- `npx.cmd vitest run src/modules/events/EventDetailPage.test.tsx`

### Qualidade geral

- `npm run type-check`

---

## Checklist de aceite final

1. convite mostra contexto, papel, quem convidou e proximo passo
2. usuario existente consegue entrar, redefinir senha e voltar ao convite
3. usuario novo cria conta e aceita convite sem perder contexto
4. `/my-events` agrupa eventos por organizacao parceira e mostra acoes coerentes
5. `active_context` fica visivel nas superfices sensiveis
6. branding explica heranca, sobrescrita e superficie de uso
7. nenhum `todo` relevante restante nas areas deste plano
8. bateria de testes do plano fica verde

---

## Conclusao

O foco correto desta execucao nao e inventar mais regras de permissao.

O foco correto e tornar obvio o que ja esta certo tecnicamente:

1. convite com contexto
2. autenticacao com retorno seguro
3. workspace com orientacao clara
4. branding com comunicacao visual correta

Se esta execucao ficar verde, a base passa de "funciona" para "produto claro e dificil de quebrar".
