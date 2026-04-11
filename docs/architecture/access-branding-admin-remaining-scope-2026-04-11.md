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

e nas camadas complementares que afetam a experiencia real do usuario:

10. notificacao de convite por WhatsApp
11. login, senha e redefinicao de senha
12. UX/UI do branding

---

## Veredito executivo

O nucleo funcional desta trilha continua entregue e validado.

O backlog real agora esta concentrado em quatro frentes de produto, nao em regra de negocio basica:

1. `convite -> login/reset -> aceite` ainda nao esta liso para usuario leigo;
2. `workspace selector`, `/my-events` e `active_context` parecem corretos tecnicamente, mas ainda nao estao fechados como experiencia de produto;
3. `branding` ja funciona, mas ainda nao ensina bem heranca, sobrescrita e onde cada arquivo aparece;
4. a cobertura de front ainda nao prova explicitamente algumas costuras que o time ja espera como contrato.

Leitura objetiva da stack atual:

- **backend e contratos principais**: bem resolvidos;
- **frontend funcional**: suficiente;
- **frontend de produto**: ainda precisa amadurecer;
- **testes**: bons no nucleo, mas ainda faltam fechar as costuras mais frageis.

`CTA comercial de upgrade para branding premium` continua pausado por decisao de escopo.

`custom_domain` com DNS/SSL completo continua explicitamente fora desta fase.

Em uma frase:

- **o proximo salto nao e mais feature de base; e transformar fluxos corretos em jornadas obvias, guiadas e dificeis de quebrar.**

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

Validacao adicional desta rodada:

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Auth/PasswordResetOtpTest.php`
  - resultado: `23 passed`, `246 assertions`

- `php artisan test tests/Feature/Events/EventBrandingInheritanceTest.php tests/Feature/Organizations/OrganizationBrandingEntitlementTest.php`
  - resultado: `7 passed`, `50 assertions`

### Frontend

- `npx.cmd vitest run src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-team/EventAccessPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx src/modules/events/EventDetailPage.test.tsx src/app/layouts/AppSidebar.test.tsx src/modules/partners/PartnersPage.test.tsx src/app/guards/ModuleGuard.test.tsx`
  - resultado: `51 passed`

- `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx src/modules/settings/SettingsPage.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/team-invitations/PublicOrganizationInvitationPage.test.tsx src/modules/event-team/EventAccessPage.test.tsx src/modules/event-invitations/PublicEventInvitationPage.test.tsx`
  - resultado: `31 passed`

- `npx.cmd vitest run src/modules/settings/SettingsPage.test.tsx src/modules/events/branding.test.ts src/modules/events/EventDetailPage.test.tsx`
  - resultado: `24 passed`

### Type-check

- `npm run type-check`
  - resultado: `ok`

### Validacao complementar desta rodada

Frontend focado em jornada e workspace:

- `npx.cmd vitest run src/modules/auth/LoginPage.test.tsx src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/events/components/EventEditorPage.test.tsx`
  - resultado: `14 passed`

Backend focado em convites, reset e workspace:

- `php artisan test tests/Feature/Organizations/OrganizationTeamInvitationContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php`
  - resultado: `26 passed`, `307 assertions`

### Fase 0 executada nesta rodada

Fechamentos reais convertidos em contrato:

- `PublicOrganizationInvitationResource` e `PublicEventTeamInvitationResource` agora expõem `invited_by.name` de forma segura;
- os `todo` relevantes de `MultiOrganizationWorkspaceContractTest.php` foram substituídos por testes reais de attach, agrupamento por parceiro e escopo por `active_context`;
- os `todo` relevantes de `workspace-selector.contract.test.tsx` e `my-events-page.contract.test.tsx` viraram testes verdes;
- `EventEditorPage.test.tsx` foi criado para cobrir herança e preview do `effective_branding`;
- `LoginPage.test.tsx` passou a cobrir explicitamente `returnTo` e countdown determinístico de reenvio.

---

## Validacao oficial das libs e guias

As recomendacoes abaixo foram revisadas nas documentacoes oficiais em `2026-04-11` e sustentam o recorte proposto para o proximo escopo.

### React

O React documenta que componentes com muitas atualizacoes de estado espalhadas em varios handlers ficam dificeis de manter e recomenda consolidar essa logica em um `reducer`.

Fonte oficial:

- `React - Extracting State Logic into a Reducer`: https://react.dev/learn/extracting-state-logic-into-a-reducer

Leitura aplicada:

- o fluxo `convite + login + reset + aceite + redirect` ja passou do ponto em que varios booleans soltos continuam saudaveis;
- a proxima iteracao deve tratar essa jornada como uma maquina de estados explicita, ou no minimo um `useReducer` dedicado;
- o proprio React tambem reforca que reducers podem ser testados isoladamente, o que encaixa bem no TDD desta trilha.

### React Router

O React Router oficial valida duas abordagens complementares:

- `MemoryRouter` e apropriado para testes simples de navegacao em memoria;
- `createRoutesStub` e o guia de `Testing` servem melhor quando a fidelidade de rota, `loader`, `action`, redirect e contexto precisam ficar mais proximos da aplicacao real.

Fontes oficiais:

- `React Router - MemoryRouter`: https://reactrouter.com/api/declarative-routers/MemoryRouter
- `React Router - Testing`: https://reactrouter.com/start/data/testing
- `React Router - createRoutesStub`: https://reactrouter.com/api/utils/createRoutesStub

Leitura aplicada:

- `MemoryRouter` continua suficiente para boa parte dos testes atuais de `LoginPage`;
- se o fluxo de convite passar a depender mais de `returnTo`, redirect e guard de rota, vale subir alguns testes para helper mais fiel de rotas.

### Testing Library

O Testing Library oficial recomenda `user-event` para interacoes mais proximas do uso real e prioriza queries acessiveis, como `getByRole` e `getByLabelText`, em vez de depender de `placeholder`.

Fontes oficiais:

- `Testing Library - user-event`: https://testing-library.com/docs/user-event/intro/
- `Testing Library - About Queries`: https://testing-library.com/docs/queries/about/
- `Testing Library - ByLabelText`: https://testing-library.com/docs/queries/bylabeltext/

Leitura aplicada:

- os testes atuais de `LoginPage` continuam uteis, mas ainda usam `fireEvent` e `placeholder` como dependencia principal;
- isso reduz um pouco a confianca em foco, digitacao, submit por Enter e acessibilidade do formulario;
- esse e um ponto real de endurecimento do proximo escopo.

### Vitest

O Vitest documenta `fake timers` como abordagem correta para codigo com `timeout` e `interval`, inclusive com `vi.useFakeTimers()` e avancos deterministas de tempo.

Fonte oficial:

- `Vitest - Timers`: https://vitest.dev/guide/mocking/timers

Leitura aplicada:

- o `resend_in` do OTP deve ganhar teste deterministico com countdown;
- isso evita teste lento, flaky e dependente de tempo real.

### OWASP

O OWASP documenta duas regras especialmente relevantes aqui:

1. respostas de login/recuperacao devem ser genericas para nao vazar existencia de conta;
2. reset de senha deve reaplicar a mesma politica de senha usada no restante da aplicacao.

Fontes oficiais:

- `OWASP Authentication Cheat Sheet`: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- `OWASP Forgot Password Cheat Sheet`: https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html

Leitura aplicada:

- a resposta generica atual de `forgot password` esta no caminho certo e nao deve ser perdida na refacao de UX;
- a tela pode ficar mais clara para o usuario sem abrir brecha de enumeracao;
- a jornada de redefinicao deve continuar consistente com a mesma politica de senha aplicada em cadastro e troca normal.

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

- `entregue no nucleo`, com `melhorias de UX` ainda abertas

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

- nenhum bloqueio funcional do fluxo base;
- o resumo de `quem convidou` ja esta disponivel no payload publico;
- o que continua aberto agora e a costura de UX da primeira dobra:
  - CTA mais claro de `Entrar para aceitar convite`;
  - CTA secundario de `Esqueci a senha` quando `requires_existing_login=true`;
  - explicacao curta de que a mesma conta pode servir para varios convites e eventos.

Melhorias opcionais:

- smoke E2E de jornada completa no navegador;
- convergencia visual maior entre `/settings > Equipe` e `/events/:eventId/access`.

Fluxo real de notificacao hoje:

- o formulario administrativo exige `nome + WhatsApp + perfil` como base do convite;
- quando `send_via_whatsapp=false`, o backend ja deixa o `invitation_url` pronto para compartilhamento manual;
- quando `send_via_whatsapp=true`, o backend tenta usar a instancia conectada do escopo correto e retorna um `delivery_status` coerente para a UI;
- quando nao existe instancia conectada, o convite continua valido com `manual link fallback`, sem impedir a operacao.

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

- nenhum gap de contrato basico restante nesta trilha;
- o que continua aberto agora e endurecimento de experiencia:
  - tornar `active_context` mais visivel para o usuario;
  - deixar mais obvio em nome de qual organizacao/evento ele esta agindo;
  - polir agrupamento, filtros, ordenacao e empty states como produto, nao como contrato.
- endurecer a leitura de produto sobre `active_context`, deixando mais claro para o usuario em nome de quem ele esta agindo e onde aquele contexto afeta navegacao e permissoes.

Leitura:

- isso nao bloqueia o que ja foi entregue;
- mas hoje ainda e o principal ponto de endurecimento residual em `access matrix / /auth/me`.

## 10. Notificacao de convite por WhatsApp

Status:

- `entregue no caminho principal`, com `melhoria real de comunicacao` ainda aberta

Validacao objetiva:

- convite organizacional usa a instancia conectada da propria organizacao;
- a resolucao privilegia a `default instance` da organizacao e, na falta dela, aceita a unica instancia conectada do mesmo escopo;
- convite event-scoped usa a instancia padrao do evento quando existir e faz fallback para a organizacao quando aplicavel;
- o envio cria `whatsapp_messages` e despacha job assincrono;
- se o provider conectado for `zapi`, o adaptador do provider usa o `send-text` correspondente;
- se nao houver instancia conectada, o sistema nao falha a criacao do convite: ele marca `delivery_status=unavailable` e entrega o link manual.

O que isso significa na stack atual:

- o produto ja trata convite como fluxo `WhatsApp-first`, nao como fluxo `e-mail-first`;
- isso e coerente com os formularios atuais, porque o WhatsApp e obrigatorio no convite administrativo;
- logo, a falta de fallback por e-mail nao e bug de contrato hoje, e sim uma decisao funcional da V1.

O que ainda falta ou pode melhorar:

- mostrar `delivery_error` com copy mais humana quando o envio falhar;
- mostrar historico simples de `reenviado`, `revogado`, `enviado manualmente` na UI;
- decidir se vale abrir fallback por e-mail no futuro, mas tratar isso como expansao de canal, nao como reparo da V1.

## 11. Login, senha e redefinicao de senha

Status:

- `entregue no nucleo`, com `melhoria estrutural de fluxo` ainda aberta

Validacao objetiva:

- login principal aceita `WhatsApp ou e-mail + senha`;
- criacao de conta publica hoje e `WhatsApp-first` com OTP;
- `forgot password` aceita `WhatsApp` ou `e-mail` e devolve resposta generica por seguranca;
- o reset exige verificacao previa do OTP;
- o backend ja cobre envio de OTP por WhatsApp e por e-mail;
- quando configurado, o envio de autenticacao pode usar um sender `zapi send-text` proprio ou uma instancia de autenticacao dedicada.

Decisao importante da arquitetura atual:

- o envio de OTP de autenticacao nao depende da instancia da organizacao ou do evento do convite;
- ele usa um `auth sender` dedicado;
- isso evita acoplar login/reset ao contexto ativo da organizacao, o que esta correto para a stack atual.

O que ja esta bem resolvido no front:

- `LoginPage` ja cobre entrada, cadastro via OTP, recuperacao por OTP e troca de senha;
- paginas publicas de convite ja distinguem `usuario existente precisa fazer login` de `usuario novo precisa criar senha`.

O que ainda falta ou pode melhorar:

- consolidar a jornada em um fluxo unico, preferencialmente com estado explicito do tipo:
  - `invite_context_loaded`
  - `needs_existing_login`
  - `needs_new_account`
  - `otp_requested`
  - `otp_verified`
  - `password_defined`
  - `invite_accepted`
  - `context_selected`
  - `redirect_ready`
- adicionar atalho claro de `Esqueci a senha` nas paginas publicas de convite quando `requires_existing_login=true`;
- preservar de forma mais visivel o contexto do convite durante login/reset, com mensagens como:
  - `voce voltara para este convite apos entrar`
  - `essa mesma conta pode ser usada em varios eventos e convites`
- explicar melhor, na UX do convite, que a mesma conta da plataforma pode servir para varios convites e varios eventos;
- alinhar melhor o texto entre `Entrar para aceitar convite`, `Criar conta e aceitar convite` e `Redefinir senha` para reduzir duvida de usuario iniciante;
- manter a evolucao dos testes de `LoginPage` para sair progressivamente de `fireEvent + placeholder` e subir ainda mais a confianca em:
  - loading state;
  - erro de request do OTP;
  - erro de validacao do OTP;
  - sessao OTP expirada;
  - reenvio com countdown;
  - retorno ao proprio convite via `returnTo`.

Leitura:

- a maior melhoria aqui nao e de regra de negocio isolada; e de modelo de fluxo;
- hoje a costura ainda parece correta tecnicamente, mas repartida demais entre estados visuais e paginas;
- isso aumenta risco de regressao quando o fluxo crescer.

## 12. Branding UX/UI

Status:

- `entregue na base`, com `backlog real de orientacao visual e cobertura de UI`

O que ja existe:

- `/settings` com linguagem mais simples em portugues;
- icones e tooltips em `Identidade visual`;
- upload real de `logo`, `cover`, `logo_dark`, `favicon` e `watermark` conforme entitlement;
- preview do `effective_branding` no detalhe do evento;
- toggle visual de heranca no editor do evento;
- bloqueio visual quando o recurso premium nao esta habilitado, sem expor termo tecnico de entitlement.

O que ainda falta ou pode melhorar:

1. preview mais didatico de onde cada arquivo aparece, por superficie real:
   - `logo`
   - `logo_dark`
   - `cover`
   - `favicon`
   - `watermark`
2. recomendacoes de formato, proporcao e tamanho por ativo para evitar tentativa e erro;
3. badges mais explicitos de origem, como `Herdado da organizacao` e `Personalizado neste evento`;
4. revisar microcopys que ainda soam tecnicos, por exemplo trocar `ativo de marca` por `arquivo visual` ou `arquivo da marca`.

Leitura:

- a falha principal aqui ja nao parece tecnica; parece de comunicacao;
- o usuario precisa bater o olho e entender onde o arquivo aparece, se esta herdado ou sobrescrito e qual o impacto da mudanca naquele evento.

---

## 13. Gaps residuais confirmados por codigo e testes

Os pontos abaixo continuam como gap residual confirmado apos a execucao da Fase 0:

1. as paginas publicas de convite ainda nao costuram `login/reset/aceite` da forma mais clara para usuario leigo
   - o backend agora ja entrega `invited_by.name`;
   - o que falta e refletir isso melhor na primeira dobra e no caminho de `requires_existing_login=true`.

2. `LoginPage` ainda depende de estrategia de teste menos fiel do que o ideal
   - os testes atuais agora cobrem `returnTo` e countdown de reenvio;
   - mas ainda usam `fireEvent` e `placeholder` como base;
   - isso precisa ser endurecido com `userEvent`, `getByRole` e `getByLabelText`.

3. `workspace selector` e `/my-events` ja tem contratos verdes, mas ainda pedem fechamento de experiencia
   - agrupamento, filtros e badges ja estao provados;
   - o que falta e lapidar a orientacao de contexto ativo e a clareza de acao por modulo.

4. `EventEditorPage` ja possui teste de UI dedicado, mas o branding ainda nao ensina bem onde cada arquivo aparece
   - heranca e preview ja estao cobertos;
   - o backlog real virou preview didatico por superficie, origem e guideline por ativo.

### Fechamentos adicionais executados na Fase 2

Desde a abertura desta doc, os seguintes pontos ja deixaram de ser gap:

1. `LoginPage` agora respeita o `returnTo` tambem apos reset de senha, sem depender de reload da pagina
2. os campos principais de login e forgot/reset deixaram de depender apenas de `placeholder` para identificacao em teste
3. as paginas publicas de convite organizacional e event-scoped agora permitem `Usar outra conta` quando o usuario ja esta autenticado com a sessao errada
4. os testes de front agora provam explicitamente:
   - reset com retorno ao mesmo convite
   - troca de conta antes do aceite em convite organizacional
   - troca de conta antes do aceite em convite event-scoped
5. o fluxo principal de `LoginPage` agora saiu do `setStep` solto e passou a usar `useInvitationAuthFlow` + `invitationAuthFlowReducer`
6. a primeira camada de componentes compartilhados de convite/auth ja existe e esta integrada nas paginas publicas:
   - `InvitationContextCard`
   - `InvitationExistingLoginActions`
   - `AuthenticatedInvitationActions`
7. a maquina de estados inicial do fluxo ficou coberta por teste unitario dedicado
8. o `LoginPage` agora tambem compartilha os passos internos de `OTP`, `nova senha` e `rodape/ajuda` por meio de:
   - `AuthFlowFooter`
   - `AuthHelpHint`
   - `OtpCodeStep`
   - `PasswordSetupStep`
9. o front agora prova explicitamente:
   - troca entre `Entrar` e `Criar conta` usando o rodape compartilhado
   - cadastro que avanca para `Valide seu WhatsApp`
10. o `LoginPage` deixou de repetir blocos visuais equivalentes entre cadastro por OTP e reset por OTP

Leitura pratica:

- o maior gap residual de Fase 2 nao e mais a volta ao convite apos reset;
- o maior gap residual agora ficou concentrado em aumentar a fidelidade dos testes de interacao, reduzir mais estados auxiliares do fluxo e decidir se o passo inicial de forgot/sucesso final tambem entram na camada compartilhada.

---

## O que realmente precisa entrar no proximo escopo

## Escopo A - necessario agora

1. Endurecer o fluxo `convite + auth + aceite` no nivel de interacao
   - migrar o nucleo de `LoginPage.test.tsx` para `userEvent`;
   - cobrir erro de request do OTP, erro de validacao do OTP e sessao expirada;
   - cobrir loading/desabilitacao de botoes durante request;
   - subir parte dos testes de rota para helper mais fiel quando `returnTo` e redirect pesarem mais.

2. Fechar as superficies de front ainda esperadas, mas nao totalmente provadas
   - `workspace selector`;
   - `/my-events`;
   - badge e leitura de `active_context` em modulos sensiveis.

3. Melhorar o entendimento visual do branding
   - preview real por ativo;
   - badges de origem;
   - dicas de formato;
   - microcopy menos tecnica.

4. Decidir a convergencia final da camada compartilhada de auth
   - avaliar se `Esqueci a senha` passo inicial vira componente compartilhado;
   - avaliar se o estado de sucesso final tambem entra nessa base;
   - manter `LoginPage` como orquestrador, nao como acumulador de markup.

## Escopo B - melhoria importante, mas nao bloqueante

1. convergir mais a UX entre convite organizacional e convite event-scoped
2. mostrar historico de revogacao/reenvio na UI
3. mostrar sinalizacao mais amigavel quando o convite reaproveita um usuario existente
4. expor status de entrega WhatsApp com copy mais humana
5. expor audit trail de ownership transfer no front
6. retomar o CTA comercial de upgrade para branding premium quando voltar a ser prioridade comercial
7. avaliar fallback por e-mail para convites apenas se o produto decidir abrir um segundo canal de entrega

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

1. tratar `convite + auth + aceite` como uma jornada unica, nao como telas soltas;
2. endurecer a UX e os testes de workspace `/my-events`, `active_context` e `EventEditorPage`;
3. tornar o branding mais autoexplicativo com previews, badges de heranca e dicas de uso;
4. manter CTA premium pausado ate decisao comercial e `custom_domain` completo fora da fase atual.

Em uma frase:

**o backlog real ja nao esta mais na logica central de acesso e branding; ele esta na costura entre convite e autenticacao, na UX multi-workspace, na didatica do branding e na cobertura explicita das costuras mais frageis do front.**
