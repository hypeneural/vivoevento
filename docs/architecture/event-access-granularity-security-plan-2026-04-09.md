# Event Access Granularity and Security Plan

## Objetivo

Definir um modelo seguro de acesso para dois tipos de colaboracao:

- equipe da organizacao parceira, com alcance organizacional;
- convidados operacionais de um evento especifico, com alcance restrito ao evento.

Casos principais:

- cerimonialista parceira convida sua secretaria com acesso amplo da organizacao;
- cerimonialista convida DJ apenas para operacao e moderacao de um evento;
- cerimonialista convida noivos apenas para visualizar as midias de um evento.

## Resumo Executivo

Hoje o sistema ja tem duas pecas importantes:

- `organization_members` para acesso da organizacao;
- `event_team_members` para acesso por evento.

Mas a autorizacao efetiva ainda nao usa essas duas camadas corretamente.

O resultado atual e:

- acesso por organizacao continua dominante;
- `EventTeam` existe, mas nao participa da decisao real de acesso;
- ha vazamentos de escopo em listagem, midias, moderacao e gerenciamento de equipe do evento.

Conclusao:

- nao devemos resolver isso com mais roles globais;
- nao devemos migrar o problema para wildcard permissions dinamicas por evento;
- o melhor caminho e um RBAC hibrido:
  - `OrganizationMember` para equipe interna da empresa;
  - `EventTeamMember` para acesso restrito ao evento;
  - `EventAccessService` reescrito como unica fonte de verdade do escopo.

## Estado Atual Validado

### Organizacao

- `OrganizationMember` governa o acesso atual de painel e operacao da conta.
- roles principais:
  - `partner-owner`
  - `partner-manager`
  - `event-operator`
  - `viewer`

### Evento

- existe modulo `EventTeam` com roles:
  - `manager`
  - `operator`
  - `moderator`
  - `viewer`

Referencias:

- `apps/api/app/Modules/EventTeam/README.md`
- `apps/api/app/Modules/EventTeam/Http/Controllers/EventTeamController.php`
- `apps/api/app/Modules/EventTeam/Models/EventTeamMember.php`

### Onde o escopo quebra hoje

`EventAccessService` hoje exige:

1. permissao global no usuario;
2. membership ativo na organizacao do evento.

Ele nao consulta `event_team_members`.

Referencia:

- `apps/api/app/Shared/Support/EventAccessService.php`

## Identidade da Plataforma

Cada pessoa deve ser tratada como um unico `User` da plataforma.

Isso significa:

- um DJ recorrente nao vira um usuario novo a cada convite;
- uma secretaria nao vira outro cadastro quando entra em uma segunda organizacao;
- noivos, DJ, fotografo e cerimonial usam a mesma identidade para logar em todos os contextos a que tiverem acesso;
- login e recuperacao de acesso continuam centrados em `email`, `phone` e `password`.

Estado atual validado:

- `users.email` e unico;
- `users.phone` tambem esta sob unicidade;
- ja existe recuperacao de senha por WhatsApp ou e-mail;
- o fluxo generico atual de equipe ja reaproveita usuario existente por e-mail ou telefone.

Implicacao:

- convite por evento e convite por organizacao nao devem falhar porque o telefone "ja existe";
- eles devem localizar o usuario da plataforma e apenas criar um novo vinculo:
  - `organization_members`
  - ou `event_team_members`

Referencias:

- `apps/api/database/migrations/0001_01_01_000000_create_users_table.php`
- `apps/api/database/migrations/2026_03_31_235900_add_unique_index_to_users_phone.php`
- `apps/api/tests/Feature/Auth/PasswordResetOtpTest.php`
- `apps/api/app/Modules/Organizations/Actions/InviteCurrentOrganizationTeamMemberAction.php`

## O que os testes provaram

### Caracterizacao backend

Arquivo:

- `apps/api/tests/Feature/Events/EventScopedAccessCharacterizationTest.php`

Resultados comprovados:

- um `event-operator` da organizacao consegue acessar midias de outro evento da mesma organizacao;
- um `viewer` da organizacao tambem consegue visualizar midias de outro evento da mesma organizacao;
- um usuario alocado em `event_team_members` sem membership organizacional continua sem acesso ao evento;
- um owner autenticado consegue manipular `EventTeam` de evento de outra organizacao;
- um usuario com role global `viewer` e sem `currentOrganization()` consegue listar eventos sem filtro organizacional.

### Caracterizacao adicional de identidade e contexto

Arquivos:

- `apps/api/tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php`
- `apps/api/tests/Feature/Auth/MeTest.php`

Resultados comprovados:

- um mesmo usuario da plataforma ja pode ser reutilizado em multiplas organizacoes pelo mesmo WhatsApp;
- o problema principal nao e o telefone duplicado, e sim o contexto ativo depois que o usuario pertence a mais de um workspace;
- `/auth/me` hoje colapsa um usuario multi-organizacao em uma unica `currentOrganization()`;
- `currentOrganization()` hoje usa `organizations()->first()`, sem uma selecao explicita de workspace.

### Contratos futuros

Arquivos:

- `apps/api/tests/Feature/Events/EventScopedAccessContractTest.php`
- `apps/web/src/modules/moderation/moderation-event-scope.contract.test.ts`

Esses contratos travam o comportamento futuro desejado:

- moderador por evento opera apenas o evento atribuido;
- viewer de evento ve apenas aquele evento;
- usuarios event-scoped nao consomem feed ou canal organizacional;
- gerenciamento de equipe do evento respeita escopo e ownership corretos.

## Validacao das libs e docs oficiais

## Laravel Authorization

Doc oficial:

- https://laravel.com/docs/authorization

Pontos relevantes:

- policies devem centralizar autorizacao por recurso;
- `before()` pode fazer pre-autorizacao para super admins;
- `authorize()` e `can()` sao o caminho nativo para controllers e models.

Implicacao para o projeto:

- o bypass de `super-admin` e `platform-admin` deve sair do espalhamento manual e ir para `Gate::before` ou `Policy::before`;
- a logica de evento precisa continuar concentrada em um servico/policy compartilhado.

## Laravel Broadcasting

Doc oficial:

- https://laravel.com/docs/12.x/broadcasting

Pontos relevantes:

- canais privados existem exatamente para autenticacao e autorizacao por canal;
- o canal deve refletir o recurso protegido.

Implicacao para o projeto:

- usuario event-scoped nao deve escutar `organization.{id}.moderation`;
- precisa usar canal por evento, por exemplo `event.{eventId}.moderation`.

## Spatie Laravel Permission

Docs oficiais:

- introducao: https://spatie.be/docs/laravel-permission/v7/introduction
- super admin: https://spatie.be/docs/laravel-permission/v7/basic-usage/super-admin
- teams permissions: https://spatie.be/docs/laravel-permission/v7/basic-usage/teams-permissions
- wildcard permissions: https://spatie.be/docs/laravel-permission/v6/basic-usage/wildcard-permissions

Pontos relevantes:

- o pacote integra com Laravel Gate e o uso recomendado e `can()`;
- Spatie recomenda `Gate::before` para super admin;
- `teams` depende de `team_id` global ativo por middleware e troca de contexto;
- wildcard permissions exigem criar as permissoes/padroes antes de atribuir ou checar.

Decisao:

- nao usar `teams` para este problema;
- nao usar wildcard dinamico por evento como base principal.

Motivo:

- hoje o sistema ja tem `organization_members` e `event_team_members` como fontes de verdade do dominio;
- `teams` introduziria contexto global mutavel por request e conflito conceitual entre organizacao e evento;
- wildcard por evento explodiria o numero de permissions e complicaria cache, seed e UI.

## React Router

Doc oficial:

- https://reactrouter.com/v6/start/overview

Pontos relevantes:

- nested routes e nested layouts sao o caminho natural para separar contextos de navegacao;
- loaders nao sao obrigatorios para este caso, mas a arvore de rotas precisa refletir os contextos protegidos;
- redirecionamento por estado de sessao pode ser tratado no bootstrap da aplicacao.

Implicacao para o projeto:

- o painel precisa separar layout organizacional de layout event-scoped;
- `/my-events` deve ser uma entrada propria, nao um atalho improvisado dentro do menu atual;
- rotas como `/my-events/:eventId/media` e `/my-events/:eventId/moderation` devem viver sob um layout proprio, sem sidebar organizacional.

## TanStack Query

Doc oficial:

- https://tanstack.com/query/latest/docs/framework/react/guides/query-keys

Pontos relevantes:

- query keys precisam incluir todas as variaveis das quais o fetch depende;
- cache e invalidacao ficam incorretos quando o contexto nao aparece na chave.

Implicacao para o projeto:

- toda query de evento precisa carregar `eventId` na key;
- toda query organizacional precisa carregar `organizationId` ou contexto equivalente;
- nao podemos reutilizar a mesma key para feeds de moderacao org-wide e event-scoped.

## shadcn/ui e Radix

Docs oficiais:

- card: https://ui.shadcn.com/docs/components/radix/card
- command: https://ui.shadcn.com/docs/components/command
- dialog: https://ui.shadcn.com/docs/components/dialog
- tabs: https://ui.shadcn.com/docs/components/tabs
- radix primitives: https://www.radix-ui.com/primitives/docs/overview/introduction

Pontos relevantes:

- `Card` atende bem discovery, agrupamento e CTA primario;
- `Command` atende bem busca e troca rapida entre workspaces e eventos;
- `Dialog` atende confirmacoes e acoes focadas sem trocar de contexto;
- `Tabs` funcionam melhor para separar secoes simples do que permissao bruta em tabela;
- Radix prioriza acessibilidade, foco, teclado e papeis ARIA.

Implicacao para o projeto:

- para noiva, DJ e parceiro, a V1 deve preferir cards, tabs simples, dialogs e resumo textual;
- o seletor de workspace pode usar `CommandDialog` para busca rapida sem criar menu tecnico;
- evitar grids densos, matrizes tecnicas e tabelas como tela principal para usuario leigo;
- botoes precisam usar labels explicitas e linguagem operacional, nao jargao tecnico.

## Laravel Password Reset e Notifications

Docs oficiais:

- passwords: https://laravel.com/docs/12.x/passwords
- notifications: https://laravel.com/docs/12.x/notifications

Pontos relevantes:

- reset de senha e notificacoes ja sao dominios separados no ecossistema Laravel;
- canais de notificacao e estados de entrega podem evoluir sem acoplar toda a autenticacao.

Implicacao para o projeto:

- recuperacao de senha pode continuar no auth global, por WhatsApp ou e-mail;
- convite de organizacao e convite por evento devem virar dominios proprios, nao extensoes improvisadas do signup publico;
- inbox em tempo real pode reaproveitar a infraestrutura de notificacoes, mas o header do painel ainda nao representa isso.

## Validacao Pre-Execucao de UX e Onboarding

### Onboarding do parceiro e da noiva

O que ja esta estruturado hoje:

- parceiro novo pode iniciar cadastro por WhatsApp OTP;
- noiva/cliente direto pode iniciar o fluxo `single_event_checkout` por WhatsApp OTP;
- login suporta e-mail ou WhatsApp;
- reset de senha ja funciona por WhatsApp e por e-mail;
- envio por WhatsApp via Z-API ja existe na infraestrutura de auth.

O que nao esta estruturado ainda:

- aceite de convite por organizacao;
- aceite de convite por evento;
- reuso de onboarding publico sem criar organizacao nova;
- escolha de workspace/contexto para usuarios com acessos multiplos.

### Notificacoes

O que ja esta estruturado hoje:

- autenticacao de canal privado de notificacoes do usuario;
- sinais de dashboard que conseguem alimentar uma inbox futura;
- backend com base para snapshot inicial de notificacoes.

O que continua incompleto:

- `AppHeader` ainda usa `mockNotifications`;
- nao existe central de notificacoes real no frontend;
- `financeiro` ainda nao recebe `notifications.view`, mesmo sendo candidato natural para alertas de cobranca.

### Template recomendado para usuario leigo

Padrao visual recomendado:

- cards para descoberta de eventos e acessos;
- resumo textual do papel em portugues;
- CTA principal visivel por card;
- tabs curtas para separar `Midias`, `Moderacao`, `Wall` e `Play`;
- `CommandDialog` para troca rapida de workspace ou evento quando houver muitos acessos;
- dialogs apenas para confirmacao ou convite, nunca para despejar matriz de permissao.

Padrao visual a evitar:

- tabela densa como entrada principal;
- permission strings no frontend;
- listas sem parceiro/data/papel destacados;
- menus organizacionais visiveis para usuarios apenas event-scoped.

## Modelo Recomendado

### Camada 1 - Capacidade global

Roles e permissions globais continuam definindo o teto da capacidade.

Exemplos:

- `partner-owner`
- `partner-manager`
- `viewer`
- `media.view`
- `media.moderate`
- `events.view`

### Camada 2 - Escopo

O escopo define onde a capacidade vale.

Escopos:

- organizacao inteira
- evento especifico

### Camada 3 - Fonte do vinculo

- `organization_members` => colaborador interno da empresa
- `event_team_members` => colaborador de um evento especifico

### Camada 4 - Contexto ativo de sessao

Depois que um usuario passa a existir em mais de uma organizacao e possivelmente em mais de um evento, a sessao precisa carregar um contexto ativo explicito.

O modelo alvo deve diferenciar:

- identidade do usuario da plataforma;
- workspaces organizacionais disponiveis;
- acessos event-scoped disponiveis;
- contexto atualmente selecionado no painel.

Recomendacao:

- manter `User` como identidade unica;
- expor no bootstrap da sessao os workspaces disponiveis;
- permitir troca explicita de organizacao ativa;
- quando o acesso for apenas por evento, entrar diretamente no contexto do evento e esconder o restante do painel.

Sem isso, o modelo de convite para DJ recorrente ou fornecedor multi-cerimonial continua tecnicamente inseguro e operacionalmente confuso.

## Workspace e Sessao Ativa

### Problema real

Hoje o backend inteiro assume `currentOrganization()` como fonte de escopo.

Isso funciona para:

- parceiro com uma unica organizacao;
- secretaria com uma unica organizacao;
- operacao simples de parceiro pequeno.

Mas quebra quando:

- o DJ trabalha para mais de uma cerimonial;
- o mesmo usuario e convidado como `EventTeamMember` em eventos de organizacoes diferentes;
- um gerente tem mais de uma organizacao associada.

### Direcao recomendada

Adicionar um conceito formal de workspace/contexto ativo.

Payload alvo de sessao:

- `user`
- `active_context`
- `organizations[]`
- `event_accesses[]`

Onde:

- `active_context.type` = `organization` ou `event`
- `active_context.organization_id` = organizacao selecionada
- `active_context.event_id` = evento selecionado quando o acesso for event-scoped

Endpoints recomendados:

- `GET /api/v1/auth/me`
  - passa a retornar `workspaces.organizations` e `workspaces.event_accesses`
- `POST /api/v1/auth/context/organization`
  - troca a organizacao ativa
- opcionalmente `POST /api/v1/auth/context/event`
  - fixa um contexto de evento quando o usuario tiver acesso apenas pontual

Regra de produto:

- parceiro interno navega por organizacao;
- DJ/noivos entram por contexto de evento;
- super admin pode trocar contexto com liberdade;
- usuario comum nao escolhe uma organizacao que nao esteja em seus memberships.

## Caso Critico: DJ Com 4 Eventos

Exemplo:

- DJ Joao trabalha no evento A da Cerimonial Alfa;
- DJ Joao trabalha no evento B da Cerimonial Beta;
- DJ Joao trabalha no evento C da Cerimonial Beta;
- DJ Joao trabalha no evento D da Cerimonial Gama.

Regra correta:

- existe apenas um `users.id` para o DJ;
- existem quatro linhas em `event_team_members`, uma por evento;
- ele nao ganha `organization_members` em Alfa, Beta ou Gama;
- ele nao ve clientes, financeiro, equipe, planos, WhatsApp ou auditoria dessas organizacoes;
- ele ve uma tela inicial com seus eventos permitidos.

### Visualizacao recomendada para o DJ

Ao logar, se o usuario nao tiver organizacao ativa mas tiver acessos por evento, o painel deve abrir uma home event-scoped.

Layout recomendado:

- header com `Meus eventos`;
- cards agrupados por parceiro/organizacao;
- em cada card:
  - nome do evento;
  - data;
  - parceiro responsavel;
  - papel do DJ naquele evento;
  - acoes permitidas.

Exemplo de grupos:

- `Cerimonial Alfa`
  - `Casamento Ana e Pedro`
    - papel: `Operar evento`
    - acoes: `Midias`, `Moderacao`, `Telao`, `Play`
- `Cerimonial Beta`
  - `Formatura Turma 2026`
    - papel: `Moderar midias`
    - acoes: `Midias`, `Moderacao`
  - `Casamento Laura e Caio`
    - papel: `Operar evento`
    - acoes: `Midias`, `Moderacao`, `Telao`, `Play`
- `Cerimonial Gama`
  - `Aniversario Clara`
    - papel: `Ver midias`
    - acoes: `Midias`

### Rotas do front para event-scoped

O ideal e nao reutilizar diretamente a navegacao org-wide.

Rotas recomendadas:

- `/my-events`
  - lista os eventos acessiveis pelo usuario
- `/my-events/:eventId`
  - resumo do evento permitido
- `/my-events/:eventId/media`
  - midias daquele evento
- `/my-events/:eventId/moderation`
  - moderacao daquele evento, se o papel permitir
- `/my-events/:eventId/wall`
  - wall daquele evento, se o papel permitir
- `/my-events/:eventId/play`
  - play daquele evento, se o papel permitir

Essas rotas podem compartilhar componentes com as telas atuais, mas devem usar query keys e endpoints event-scoped.

Regra de seguranca:

- nao mostrar sidebar completa da organizacao;
- nao chamar endpoints org-wide;
- nao assinar canais realtime de organizacao;
- nao cachear dados event-scoped na mesma query key de dados org-wide.

## Paginas Necessarias no Front

Para o fluxo funcionar 100%, precisamos separar o painel organizacional do painel event-scoped.

### 1. `/my-events`

Pagina inicial para usuarios que possuem acessos por evento.

Publico:

- DJ;
- noivos;
- fotografo freelancer;
- celebrante;
- operador convidado para um ou mais eventos;
- usuario que tambem pode ter organizacao, mas quer entrar em um contexto de evento.

Fonte de dados V1:

- `GET /auth/me`
- campo `workspaces.event_accesses`

Fonte de dados futura, se o volume crescer:

- `GET /auth/workspaces/events`

O que exibe:

- cards dos eventos permitidos;
- eventos agrupados por parceiro/organizacao;
- badge do papel no evento;
- badge de status do evento;
- data e local;
- acoes permitidas por capabilities;
- empty state quando nao houver acessos ativos.

Filtros:

- busca por nome do evento;
- busca por nome do parceiro;
- parceiro/organizacao;
- status do evento;
- periodo/data;
- papel/preset;
- capacidade disponivel:
  - midias;
  - moderacao;
  - telao;
  - play.

Ordenacao:

- proximos primeiro;
- ativos hoje;
- mais recentes;
- parceiro A-Z;
- titulo A-Z.

Tabs recomendadas:

- `Ativos hoje`;
- `Proximos`;
- `Encerrados`;
- `Todos`.

Template V1 recomendado:

- cards simples, nunca tabela como entrada;
- topo com busca e CTA de filtros;
- parceiro, data e papel visiveis acima das acoes;
- badges curtas para `Operar evento`, `Moderar midias`, `Ver midias`;
- CTA principal por card conforme o preset.

Regras:

- nunca mostrar eventos fora de `workspaces.event_accesses`;
- nunca listar clientes, financeiro, WhatsApp ou equipe da organizacao;
- se houver apenas um evento e nenhum workspace organizacional, pode redirecionar direto para `/my-events/{eventId}`.

### 2. `/my-events/:eventId`

Home segura do evento para usuario event-scoped.

O que exibe:

- resumo do evento;
- parceiro responsavel;
- papel do usuario;
- acoes permitidas;
- links para midias, moderacao, wall e play conforme capabilities;
- ultimas atividades visiveis naquele evento;
- indicadores simples do evento quando permitidos.

Regras:

- se o usuario nao tiver acesso ao evento, retornar `403` ou redirecionar para `/my-events`;
- nao renderizar configuracoes sensiveis da organizacao;
- todos os cards usam `active_context.event_id`.

### 3. `/my-events/:eventId/media`

Midias do evento em modo event-scoped.

Para `media-viewer`:

- visualizacao somente leitura;
- sem acoes de aprovar/rejeitar;
- sem detalhes tecnicos de moderacao;
- preferencialmente exibir apenas midias aprovadas/publicadas, salvo decisao futura de produto.

Para `moderator`, `operator` e `manager`:

- visualizacao de fila completa conforme capability;
- acoes de moderacao se `media.moderate` existir.

Filtros:

- tipo de midia;
- canal de origem;
- periodo;
- busca por legenda/remetente;
- favoritos/destaques, quando aplicavel;
- status somente para quem tem `media.moderate`.

### 4. `/my-events/:eventId/moderation`

Fila de moderacao event-scoped.

Publico:

- `event.moderator`;
- `event.operator`;
- `event.manager`.

Filtros:

- status de moderacao;
- tipo de midia;
- canal;
- remetente;
- periodo;
- status de IA/safety/VLM, quando aplicavel;
- duplicadas;
- orientacao.

Regras:

- bulk actions sempre filtradas pelo evento;
- realtime sempre em `private-event.{eventId}.moderation`;
- query key sempre event-scoped.

### 5. `/my-events/:eventId/wall`

Operacao do telao daquele evento.

Publico:

- `event.operator`;
- `event.manager`;
- opcionalmente `event.viewer` futuro para leitura.

O que exibe:

- controle operacional do wall;
- QR/link publico;
- status de exibicao;
- playlist/midias do evento;
- configuracoes permitidas para o papel.

Regras:

- nao expor branding ou configuracoes globais da organizacao;
- nao permitir trocar dados de outros eventos.

### 6. `/my-events/:eventId/play`

Operacao dos jogos daquele evento.

Publico:

- `event.operator`;
- `event.manager`.

O que exibe:

- jogos habilitados;
- status da rodada;
- ranking;
- controles permitidos.

Regras:

- esconder se o evento nao tiver entitlement/modulo ativo;
- esconder se o papel nao tiver capability.

### 7. `/events/:eventId/access`

Pagina ou aba para membros da organizacao gerenciarem acessos daquele evento.

Publico:

- `partner-owner`;
- `partner-manager`;
- `event.manager`, se a politica permitir.

O que faz:

- lista equipe event-scoped;
- convida DJ/noivos/terceiros;
- altera preset de acesso;
- revoga acesso;
- mostra convites pendentes;
- mostra auditoria basica de aceite/revogacao.

Filtros:

- status do convite/acesso;
- papel/preset;
- busca por nome, WhatsApp ou e-mail;
- origem do acesso:
  - convite;
  - adicionado manualmente;
  - importado.

Regras:

- nao misturar com `/settings > Equipe`, que e equipe da organizacao inteira;
- `partner-owner` nao deve ser opcao nesse fluxo;
- owner/manager estrangeiro nunca gerencia equipe de evento alheio.

## Layouts do Front

### `OrganizationLayout`

Uso atual do admin.

Mostra:

- dashboard;
- eventos;
- clientes;
- midias org-wide;
- settings;
- billing;
- parceiros/admin, quando aplicavel.

Requer:

- `active_context.type = organization`;
- ou super admin/platform admin.

### `EventWorkspaceLayout`

Novo layout event-scoped.

Mostra:

- seletor de evento;
- nome do parceiro;
- papel do usuario naquele evento;
- menu curto:
  - Visao geral;
  - Midias;
  - Moderacao, se permitido;
  - Telao, se permitido;
  - Play, se permitido.

Nao mostra:

- clientes da organizacao;
- financeiro;
- planos;
- WhatsApp da organizacao;
- settings;
- audit global;
- equipe da organizacao.

Template recomendado:

- navegacao horizontal curta para `Midias`, `Moderacao`, `Telao`, `Jogos`;
- esconder por completo qualquer aba nao permitida;
- preferir CTA por contexto em vez de menus densos;
- manter parceira e evento sempre visiveis no topo para reduzir erro operacional.

### `WorkspaceSelector`

Componente transversal.

Regras:

- se houver mais de uma organizacao, permite trocar organizacao;
- se houver mais de um evento event-scoped, permite trocar evento;
- se o usuario tiver organizacao e eventos pontuais, mostra os dois grupos separados;
- nunca mostra workspace sem vinculo autorizado.

## Ultima caracterizacao antes da implementacao de `active_context + workspaces`

### Backend

O teste `EventOnlySessionCharacterizationTest` provou que hoje um usuario apenas event-scoped:

- recebe `organization = null` em `/auth/me`;
- nao recebe `workspaces`;
- nao recebe `active_context`;
- ainda recebe `accessible_modules` derivados das permissions globais do role.

Implicacao:

- a sessao atual nao sabe bootstrapar contexto de evento;
- se nao corrigirmos isso primeiro, o frontend vai continuar inferindo navegacao a partir de permissions globais soltas.

### Frontend

O teste `AppSidebar.test.tsx` provou que hoje a navegacao continua organizacional:

- existe `Dashboard`, `Eventos` e `Midias`;
- nao existe entrada dedicada `Meus eventos`;
- nao ha separacao de layout entre contexto organizacional e contexto event-scoped.

Inspecao de rotas em `App.tsx` confirmou:

- nao existe rota `/my-events`;
- todas as rotas autenticadas relevantes continuam sob `AdminLayout`;
- `LoginRoute` ainda redireciona para `/` por padrao, sem logica de workspace selector.

Conclusao:

- `active_context + workspaces` precisa entrar antes de qualquer endurecimento real de UX event-scoped;
- `/my-events` precisa nascer junto com um layout proprio;
- `AdminLayout` nao pode continuar sendo o shell padrao para usuarios apenas event-scoped.

## Contrato de Sessao Alvo

`GET /api/v1/auth/me` deve expor informacao suficiente para o front montar a tela sem inferir permissao por conta propria.

Payload conceitual:

```json
{
  "user": {},
  "organization": null,
  "active_context": {
    "type": "event",
    "organization_id": 10,
    "event_id": 55,
    "role_key": "event.operator"
  },
  "workspaces": {
    "organizations": [],
    "event_accesses": [
      {
        "event_id": 55,
        "event_title": "Casamento Ana e Pedro",
        "event_date": "2026-06-20",
        "organization_id": 10,
        "organization_name": "Cerimonial Alfa",
        "role_key": "event.operator",
        "role_label": "Operar evento",
        "capabilities": [
          "events.view",
          "media.view",
          "media.moderate",
          "wall.manage",
          "play.manage"
        ],
        "entry_path": "/my-events/55"
      }
    ]
  }
}
```

Notas:

- `organization` pode continuar existindo para compatibilidade, mas nao deve ser a unica fonte de contexto;
- `active_context` deve guiar modulo, sidebar, query keys e canais realtime;
- `capabilities` devem vir do backend como resultado da matriz, nao da tela.

## Organizacao das Permissoes Para a View

A view nao deve alterar permissoes granulares diretamente.

Ela deve alterar um preset seguro, e o backend deve traduzir esse preset para capabilities.

Modelo recomendado:

- frontend exibe `access_preset`;
- backend persiste `event_team_members.role`;
- backend calcula `capabilities`;
- frontend renderiza acoes permitidas a partir de `capabilities`.

### Presets para evento

| Preset na UI | Role persistida | Uso |
|---|---|---|
| Gerenciar evento | `manager` | coordenador do evento |
| Operar evento | `operator` | DJ, operador de telao, operador de jogos |
| Moderar midias | `moderator` | pessoa que aprova/rejeita conteudo |
| Ver midias | `media-viewer` | casal/noivos/cliente final |

### Presets para organizacao

| Preset na UI | Role persistida | Uso |
|---|---|---|
| Proprietaria | `partner-owner` | dona da organizacao |
| Gerente / Secretaria | `partner-manager` | acesso amplo sem ownership |
| Financeiro | `financeiro` | cobrancas e relatorios |
| Leitura | `viewer` | consulta organizacional |

### Endpoint recomendado para presets

- `GET /api/v1/access/presets`

Resposta conceitual:

```json
{
  "organization": [
    {
      "key": "partner-manager",
      "label": "Gerente / Secretaria",
      "description": "Acessa eventos e operacao da organizacao, sem ownership.",
      "capabilities": ["events.view", "events.create", "media.view"]
    }
  ],
  "event": [
    {
      "key": "operator",
      "label": "Operar evento",
      "description": "Opera midias, moderacao, telao e jogos deste evento.",
      "capabilities": ["media.view", "media.moderate", "wall.manage", "play.manage"]
    }
  ]
}
```

Esse endpoint evita hardcode no front e permite evoluir a matriz sem reescrever formulários.

## Mapa de papeis recomendado

### Papeis organizacionais

- `partner-owner`
  - acesso total da organizacao
- `partner-manager`
  - acesso amplo da organizacao, sem ownership
- `partner-admin`
  - opcional; alternativa para secretaria com quase tudo da organizacao
- `financeiro`
  - billing e relatorios
- `viewer`
  - leitura organizacional, se ainda fizer sentido manter

### Papeis por evento

- `event.manager`
  - configura evento, equipe e operacao daquele evento
- `event.operator`
  - wall, play, galeria e moderacao daquele evento
- `event.moderator`
  - visualiza e modera midias daquele evento
- `event.media-viewer`
  - visualiza midias daquele evento

## Matriz simples para a cerimonial

O erro mais comum aqui e expor uma matriz tecnica demais.

Para a operacao da cerimonial, a UI nao deve pedir:

- permission strings;
- abilities individuais por modulo;
- diferenca tecnica entre policy, role e scope.

Ela deve pedir duas decisoes simples:

1. esse acesso e da empresa inteira ou so deste evento?
2. qual perfil pronto melhor representa essa pessoa?

### Bloco 1 - Acesso da empresa

Perfis prontos:

- `Proprietaria`
  - ownership total da organizacao
- `Gerente / Secretaria`
  - acesso amplo da organizacao, sem ownership
- `Financeiro`
  - billing, planos, cobrancas e leitura operacional limitada
- `Leitura`
  - consulta, sem acoes destrutivas

### Bloco 2 - Acesso somente ao evento

Perfis prontos:

- `Gerenciar evento`
  - `event.manager`
- `Operar evento`
  - `event.operator`
- `Moderar midias`
  - `event.moderator`
- `Ver midias`
  - `event.media-viewer`

### Regras de UX

- DJ deve aparecer por padrao em `Operar evento`;
- casal/noivos deve aparecer por padrao em `Ver midias`;
- secretaria deve ser convidada por `Gerente / Secretaria`;
- a UI deve mostrar um resumo simples:
  - `Vai acessar toda a organizacao`
  - ou `Vai acessar apenas o evento Casamento Ana e Pedro`
- modo avancado de permissoes, se existir, deve ficar escondido para super admin ou configuracao futura, nao para a cerimonial no fluxo padrao.

## Casos de negocio

### Secretaria da assessora

Deve ser `OrganizationMember`.

Motivo:

- ela trabalha para a empresa, nao para um unico evento;
- precisa de conta propria para trilha de auditoria;
- faz sentido que veja multiplos eventos da organizacao.

Observacao importante:

- ela deve usar a mesma conta da plataforma mesmo que depois entre em outra organizacao.

### DJ

Deve ser `EventTeamMember`.

Papel recomendado:

- `event.operator` se operar wall/play + moderacao;
- `event.moderator` se for apenas moderacao e triagem.

Observacao importante:

- o mesmo DJ pode trabalhar para varias cerimoniais;
- o convite deve reutilizar a conta ja existente pelo WhatsApp ou e-mail;
- o que muda e o vinculo do evento, nao o cadastro base do usuario.

### Noivos

Devem ser `EventTeamMember`.

Papel recomendado:

- `event.media-viewer`

Nao devem entrar como `OrganizationMember`.

Observacao importante:

- o casal pode ja ter conta da plataforma por outro evento ou por compra direta;
- o aceite do convite deve anexar apenas o novo acesso ao evento.

## Reescrita recomendada do EventAccessService

### Regra alvo

`EventAccessService::can($user, $event, $permission)` deve responder:

1. super admin / platform admin:
  - sempre `true` por `Gate::before` ou `Policy::before`
2. usuario com membership organizacional relevante:
  - acesso conforme permissions globais da role organizacional
3. usuario com `event_team_member`:
  - acesso apenas se o papel do evento permitir aquela ability
4. qualquer outro caso:
  - `false`

### Matriz sugerida por papel do evento

- `manager`
  - `events.view`
  - `events.update`
  - `media.view`
  - `media.moderate`
  - `gallery.view`
  - `gallery.manage`
  - `wall.view`
  - `wall.manage`
  - `play.view`
  - `play.manage`
- `operator`
  - `events.view`
  - `media.view`
  - `media.moderate`
  - `gallery.view`
  - `gallery.manage`
  - `wall.view`
  - `wall.manage`
  - `play.view`
  - `play.manage`
- `moderator`
  - `events.view`
  - `media.view`
  - `media.moderate`
  - `gallery.view`
- `media-viewer`
  - `events.view`
  - `media.view`
  - `gallery.view`

## Gaps de seguranca que precisam ser corrigidos

### G1 - EventTeamController sem policy real

Hoje ele aceita `auth:sanctum`, mas nao valida escopo do evento.

Impacto:

- usuario de uma organizacao pode alterar equipe de evento estrangeiro.

### G2 - EventAccessService ignora EventTeam

Impacto:

- DJ/noivos nao conseguem funcionar corretamente sem virar membro da organizacao;
- se virarem membro da organizacao, recebem escopo amplo demais.

### G3 - Index de eventos sem fallback seguro

Quando `currentOrganization()` e `null`, o index pode ficar sem filtro organizacional.

Impacto:

- vazamento de listagem.

### G4 - Feeds organizacionais de midia e moderacao

`/media`, `/media/feed`, `/media/feed/stats` continuam org-wide.

Impacto:

- nao servem para usuarios com acesso restrito a um unico evento.

### G5 - Realtime de moderacao por organizacao

Frontend usa `private-organization.{organizationId}.moderation`.

Impacto:

- usuario event-scoped nao pode entrar nesse canal.

## Fases de Execucao

## Fase 0 - Hardening imediato

Objetivo:

- fechar vazamentos graves sem depender da granularidade final.

Tarefas:

- [ ] criar `EventTeamPolicy` e aplicar `authorize()` em `EventTeamController`
- [ ] validar que `EventTeamController` sempre resolve o `Event` real antes de operar no member
- [ ] impedir list, create, update e delete de equipe em evento de outra organizacao
- [ ] endurecer `/events` para negar acesso quando `currentOrganization()` for nulo e nao houver escopo explicito permitido
- [ ] documentar no modulo `EventTeam` que ele ainda nao concede acesso ate a Fase 2

Subtarefas:

- [ ] trocar parametros primitivos do controller por binding consistente de `Event $event`
- [ ] garantir que `EventTeamMember` pertence ao `Event` informado na rota antes de atualizar/remover
- [ ] criar teste de feature para owner estrangeiro nao gerenciar equipe de evento alheio
- [ ] criar teste de feature para `viewer` sem `currentOrganization()` nao listar eventos globais

Criterios de aceite:

- [ ] `POST /events/{event}/team` falha com `403` fora do escopo
- [ ] `PATCH /events/{event}/team/{member}` falha com `404` ou `403` quando o member nao pertence ao evento
- [ ] `GET /events` nao devolve eventos globais para usuario autenticado sem escopo valido

Dependencias:

- nenhuma. Esta fase deve entrar antes das demais.

## Fase 1 - Modelo de acesso por evento

Objetivo:

- formalizar o vocabulário e a matriz de abilities do acesso por evento.

Tarefas:

- [ ] criar enum ou resolver de role do evento
- [ ] mapear abilities por role do evento
- [ ] padronizar nomes:
  - `manager`
  - `operator`
  - `moderator`
  - `media-viewer`
- [ ] decidir migracao de `viewer` atual do evento para `media-viewer`

Subtarefas:

- [ ] criar tabela de abilities alvo por role no README do modulo
- [ ] definir se `event.manager` pode gerenciar apenas equipe do evento ou tambem configuracoes sensiveis
- [ ] separar abilities de leitura de midia (`media.view`) das de moderacao (`media.moderate`)
- [ ] validar se `wall.manage` e `play.manage` entram em `operator` ou apenas `manager`

Criterios de aceite:

- [ ] matriz de papel por evento documentada e referenciada pelos testes
- [ ] nomenclatura de role sem ambiguidade entre viewer organizacional e viewer de evento

Dependencias:

- Fase 0 concluida.

## Fase 2 - Reescrever EventAccessService

Objetivo:

- transformar `EventAccessService` na unica fonte de verdade do escopo por evento.

Tarefas:

- [ ] consultar `event_team_members`
- [ ] preservar org-wide para `OrganizationMember`
- [ ] responder por ability e escopo
- [ ] adicionar testes unitarios e feature para matriz completa

Subtarefas:

- [ ] decidir assinatura alvo do servico:
  - `can(User $user, Event|int $event, string $ability): bool`
  - opcionalmente `scope(User $user, Builder $query, string $ability): Builder`
- [ ] implementar bypass de `super-admin` e `platform-admin` via `Gate::before` ou `Policy::before`
- [ ] manter compatibilidade com acessos organizacionais existentes
- [ ] consultar membership do evento antes de negar acesso quando nao houver membership organizacional

Criterios de aceite:

- [ ] usuario somente em `event_team_members` consegue acessar o evento atribuido conforme sua role
- [ ] usuario event-scoped nao acessa evento nao atribuido da mesma organizacao
- [ ] usuario organizacional continua com acesso amplo dentro da propria organizacao

Dependencias:

- Fase 1 concluida.

## Fase 3 - Endpoints e policies

Objetivo:

- aplicar a nova regra de escopo em todos os pontos de entrada sensiveis.

Tarefas:

- [ ] aplicar nova regra em `EventPolicy`
- [ ] aplicar nova regra nos controllers de:
  - eventos
  - midias
  - moderacao
  - gallery
  - wall
  - play
  - face search
  - content moderation
  - media intelligence
- [ ] revisar bulk actions para nao operar fora do evento permitido

Subtarefas:

- [ ] revisar cada endpoint que aceita `event_id` por query string
- [ ] negar bulk update se o payload misturar itens de eventos fora do escopo permitido
- [ ] alinhar resources para nao exporem eventos nao permitidos em filtros auxiliares
- [ ] revisar endpoints derivados como stats, insights e exports

Criterios de aceite:

- [ ] todas as rotas sensiveis usam policy ou `EventAccessService`
- [ ] nenhuma acao bulk atravessa eventos nao autorizados
- [ ] contrato backend `EventScopedAccessContractTest` sai de `todo` para `pass`

Dependencias:

- Fase 2 concluida.

## Fase 4 - Realtime e feeds

Objetivo:

- alinhar feed e realtime ao mesmo escopo do backend.

Tarefas:

- [ ] criar modo event-scoped no frontend de moderacao
- [ ] trocar canal organizacional por canal de evento para usuarios limitados
- [ ] esconder eventos nao permitidos nos filtros
- [ ] impedir consumo de feed org-wide por usuario event-scoped

Subtarefas:

- [ ] revisar `useModerationRealtime`
- [ ] criar estrategia para selecionar canal:
  - `private-event.{eventId}.moderation`
  - `private-organization.{organizationId}.moderation`
- [ ] adaptar o backend de channel authorization
- [ ] revisar cache/query keys para nao misturar feed org-wide com feed event-scoped
- [ ] criar query keys separadas:
  - `moderation.feed.organization`
  - `moderation.feed.event`
  - `media.catalog.organization`
  - `media.catalog.event`
- [ ] criar uma entrada `/my-events/:eventId/moderation` que sempre exija contexto de evento

Criterios de aceite:

- [ ] usuario event-scoped nao assina canal organizacional
- [ ] usuario event-scoped so ve midias do evento permitido
- [ ] contrato frontend `moderation-event-scope.contract.test.ts` sai de `todo` para `pass`

Dependencias:

- Fase 3 concluida.

## Fase 5 - UX e onboarding

Objetivo:

- dar suporte operacional para convidar e gerenciar acessos por evento sem confundir com equipe da organizacao.

Tarefas:

- [ ] criar tela de equipe do evento dentro de `/events/{id}`
- [ ] criar convite de membro por evento
- [ ] criar `/my-events` para usuarios com apenas acessos por evento
- [ ] criar selector de evento quando o usuario tiver mais de um evento permitido
- [ ] agrupar eventos por organizacao/parceiro na home event-scoped
- [ ] revisar rotulos PT-BR coerentes
- [ ] explicitar no UI quando acesso e:
  - da organizacao
  - de um evento

Subtarefas:

- [ ] diferenciar convites da secretaria org-wide versus DJ/noivos event-scoped
- [ ] exibir escopo e abilities no formulario de convite
- [ ] usar presets de acesso em vez de matriz tecnica
- [ ] buscar presets pelo backend ou por contrato versionado
- [ ] impedir escolha de role inadequada para terceiros
- [ ] revisar mensagens de ajuda e telas de empty state

Criterios de aceite:

- [ ] operador entende visualmente se o acesso concedido e organizacional ou apenas do evento
- [ ] DJ e noivos nao sao convidados pelo fluxo de equipe da organizacao
- [ ] DJ com 4 eventos ve somente os 4 cards/eventos permitidos, agrupados por parceiro
- [ ] noivos com `media-viewer` nao veem moderação, wall, play ou configurações

Dependencias:

- Fase 4 concluida.

## Fase 6 - Auditoria

Objetivo:

- garantir rastreabilidade por usuario e por tipo de vinculo.

Tarefas:

- [ ] registrar no activity log a origem do escopo
- [ ] diferenciar nos logs:
  - `organization_member`
  - `event_team_member`
- [ ] manter conta separada da secretaria como regra operacional

Subtarefas:

- [ ] padronizar properties de log:
  - `scope_type`
  - `scope_event_id`
  - `scope_organization_id`
  - `acting_membership_type`
- [ ] revisar auditoria de moderacao, wall, play e configuracoes do evento
- [ ] validar exibicao futura desses logs no painel

Criterios de aceite:

- [ ] acao feita pela secretaria fica separada da acao da assessora
- [ ] acao feita por DJ/noivos mostra o evento de origem no log

Dependencias:

- Fase 5 concluida.

## Ordem de implementacao recomendada

1. Fase 0
2. Fase 1
3. Fase 2
4. Fase 3
5. Fase 4
6. Fase 6
7. Fase 5

Motivo:

- o convite e a UX so devem ser implementados depois que o escopo tecnico estiver seguro;
- auditoria pode caminhar em paralelo ao fechamento do backend;
- o hardening precisa entrar primeiro porque hoje ha vazamento real de permissao.

## Plano de Implementacao V1

Esta e a ordem pragmatica para transformar o desenho em produto utilizavel sem abrir brechas de seguranca.

### Status consolidado em `2026-04-10 14:32:00 -03:00`

- [x] `active_context` e `workspaces` ja saem de `/auth/me`.
- [x] `POST /auth/context/organization` e `POST /auth/context/event` ja persistem troca de contexto.
- [x] `MeResponse`, `AuthProvider` e o redirect pos-login ja respeitam sessao event-scoped.
- [x] `/my-events`, `EventWorkspaceLayout`, `/my-events/:eventId` e placeholders seguros por modulo ja existem no front.
- [x] `EventAccessService` ja consulta `event_team_members`.
- [x] `GET /events` ja respeita o escopo event-scoped quando nao existe `currentOrganization()`.
- [x] `GET /access/presets` ja expoe presets simples de organizacao e evento.
- [x] `EventTeamController` agora usa autorizacao por `events.manage_team`, binding forte de `Event` e validacao de pertencimento do membro ao evento.
- [x] `PATCH /events/{event}/team/{member}` ja aceita `preset_key` e preserva o escopo do evento.
- [x] convites pendentes por evento ja existem em `event_team_invitations`.
- [x] criacao de convite por evento ja reutiliza `existing_user_id` quando o usuario da plataforma ja existe por WhatsApp/e-mail.
- [x] listagem de convites pendentes por evento ja esta separada da equipe ativa.
- [ ] aceite publico do convite por evento ainda nao foi implementado.
- [ ] reenvio/revogacao do convite por evento ainda nao foi implementado.
- [ ] envio do convite por WhatsApp do evento/organizacao ainda nao foi implementado.
- [ ] a UI administrativa `/events/:eventId/access` ainda nao foi criada.

### Etapa 1 - `active_context` e `workspaces` em `/auth/me`

Objetivo:

- fazer o frontend parar de inferir contexto por `organization` unico.

Backend:

- [ ] criar um builder de workspaces da sessao;
- [ ] retornar `workspaces.organizations`;
- [ ] retornar `workspaces.event_accesses`;
- [ ] retornar `active_context`;
- [ ] manter `organization` legado para compatibilidade temporaria;
- [ ] criar `POST /auth/context/organization`;
- [ ] opcionalmente criar `POST /auth/context/event`;
- [ ] garantir que o contexto ativo escolhido pertence ao usuario.

Frontend:

- [ ] atualizar `MeResponse`;
- [ ] atualizar `AuthProvider`;
- [ ] criar helpers:
  - `isOrganizationContext`;
  - `isEventContext`;
  - `eventCapabilities`;
  - `canInEvent`.
- [ ] atualizar redirect pos-login:
  - org user -> `/`;
  - event-only user com 1 evento -> `/my-events/{eventId}`;
  - event-only user com varios eventos -> `/my-events`.

Testes:

- [ ] `MultiOrganizationWorkspaceContractTest`;
- [ ] `workspace-selector.contract.test.tsx`;
- [ ] type-check.

### Etapa 2 - `/my-events` no front

Objetivo:

- entregar uma home segura para usuarios event-scoped.

Frontend:

- [ ] criar modulo `apps/web/src/modules/my-events`;
- [ ] criar `MyEventsPage.tsx`;
- [ ] criar `MyEventHomePage.tsx`;
- [ ] criar `EventWorkspaceLayout.tsx`;
- [ ] registrar rotas:
  - `/my-events`;
  - `/my-events/:eventId`;
  - `/my-events/:eventId/media`;
  - `/my-events/:eventId/moderation`;
  - `/my-events/:eventId/wall`;
  - `/my-events/:eventId/play`.
- [ ] criar filtros de `/my-events`:
  - busca;
  - parceiro;
  - status;
  - periodo;
  - papel;
  - capacidade;
  - ordenacao.
- [ ] agrupar cards por parceiro/organizacao.

Backend:

- [ ] nenhum endpoint novo obrigatorio se `workspaces.event_accesses` vier completo;
- [ ] se volume crescer, criar `GET /auth/workspaces/events`.

Testes:

- [ ] contratos de `workspace-selector`;
- [ ] teste de pagina para agrupamento, filtros e acoes por capability.

### Etapa 3 - `EventAccessService` lendo `event_team_members`

Objetivo:

- permitir que DJ/noivos acessem apenas o evento atribuido.

Backend:

- [ ] criar resolver de role/capabilities do evento;
- [ ] consultar `event_team_members` no `EventAccessService`;
- [ ] manter org-wide para `organization_members`;
- [ ] aplicar `Gate::before` ou `Policy::before` para super admin;
- [ ] corrigir `EventTeamController` com policy;
- [ ] corrigir `GET /events` sem escopo.

Testes:

- [ ] tirar TODOs de `EventScopedAccessContractTest`;
- [ ] manter caracterizacao antiga ate o comportamento mudar, depois atualizar/remover.

### Etapa 4 - Presets backend em `GET /access/presets`

Objetivo:

- permitir que a view monte formulários de acesso sem hardcode perigoso.

Backend:

- [ ] criar endpoint `GET /access/presets`;
- [ ] retornar presets de organizacao;
- [ ] retornar presets de evento;
- [ ] incluir label, descricao, scope, role persistida e capabilities;
- [ ] bloquear presets sensiveis conforme quem esta convidando.

Frontend:

- [ ] carregar presets no dialog de convite;
- [ ] exibir presets simples;
- [ ] mostrar resumo textual do que a pessoa podera fazer;
- [ ] esconder modo avancado para cerimonial.

Testes:

- [ ] tirar TODOs de `EventPermissionPresetContractTest`;
- [ ] contrato frontend de presets simples.

### Etapa 5 - Convite por evento usando o mesmo `users.id`

Objetivo:

- convidar DJ/noivos por evento sem criar usuario duplicado e sem organization-wide access.

Backend:

- [ ] criar modelo de convite por evento ou generalizar invitation com `scope_type`;
- [ ] localizar usuario por WhatsApp/e-mail;
- [ ] se usuario existe, anexar `existing_user_id`;
- [ ] se usuario nao existe, criar somente no aceite;
- [ ] criar `event_team_members` somente no aceite;
- [ ] enviar link por WhatsApp da organizacao/evento quando solicitado;
- [ ] registrar auditoria.

Frontend:

- [ ] criar `/events/:eventId/access`;
- [ ] criar dialog `Convidar para este evento`;
- [ ] exigir nome, WhatsApp e preset;
- [ ] checkbox `Enviar convite pelo WhatsApp`;
- [ ] mostrar link manual;
- [ ] listar convites pendentes e acessos ativos.

Testes:

- [ ] contratos de convite existentes;
- [ ] novos testes de evento access invitation;
- [ ] testes frontend de `/events/:eventId/access`.

Gate de conclusao da V1:

- DJ com 4 eventos em 3 parceiros ve somente esses 4 eventos em `/my-events`;
- DJ consegue operar/moderar conforme preset de cada evento;
- casal com `Ver midias` nao acessa moderacao, wall, play, financeiro ou settings;
- secretaria continua em contexto organizacional com auditoria propria;
- nenhum fluxo cria usuario duplicado por WhatsApp ja existente.

## Testes obrigatorios

### Caracterizacao ja criada

- `apps/api/tests/Feature/Events/EventScopedAccessCharacterizationTest.php`
- `apps/api/tests/Feature/Events/EventScopedAccessContractTest.php`
- `apps/web/src/modules/moderation/moderation-event-scope.contract.test.ts`

### Backend futuros

- [ ] moderator por evento aprova/rejeita apenas no evento atribuido
- [ ] media-viewer por evento consegue listar midias apenas do evento atribuido
- [ ] event-scoped user nao ve outros eventos no index
- [ ] event-scoped user nao assina canal organizacional
- [ ] owner/manager da organizacao continua com acesso amplo
- [ ] owner estrangeiro nao gerencia equipe de evento alheio
- [ ] DJ com quatro `event_team_members` recebe quatro `event_accesses` no bootstrap da sessao
- [ ] presets de evento sao expostos pelo backend sem depender de strings hardcoded no front

### Frontend futuros

- [ ] filtros de moderacao mostram apenas eventos permitidos
- [ ] realtime usa canal do evento quando aplicavel
- [ ] bulk actions ficam limitadas ao evento
- [ ] convite/gestao de equipe do evento respeita escopo
- [ ] `/my-events` mostra cards agrupados por parceiro
- [ ] sidebar organizacional some para usuario apenas event-scoped
- [ ] permissoes aparecem como presets simples, nao como matriz tecnica

## Decisao Final

O modelo correto para o Evento Vivo e:

- `OrganizationMember` para equipe interna da empresa parceira;
- `EventTeamMember` para terceiros ou clientes com acesso a um evento especifico;
- `EventAccessService` como orquestrador unico do escopo;
- `Gate::before` ou `Policy::before` para super admin;
- canais privados por evento para usuarios event-scoped.

Nao recomendo:

- usar `Spatie teams` como eixo principal desta refatoracao;
- usar wildcard permission dinamica por evento como fonte de verdade;
- continuar convidando DJ/noivos pelo fluxo de equipe da organizacao.

## Validacao executada nesta rodada

Backend:

- `php artisan test tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php`
- resultado: `19 passed`, `6 todos`, `262 assertions`

Frontend:

- `npx vitest run src/modules/moderation/moderation-architecture.test.ts src/modules/moderation/moderation-event-scope.contract.test.ts src/modules/moderation/components/ModerationReviewPanel.test.tsx src/modules/moderation/services/moderation.service.test.ts`
- resultado: `7 passed`, `3 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao pre-execucao especifica de `active_context + /my-events`

Backend:

- `php artisan test tests/Feature/Auth/EventOnlySessionCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php`
- resultado: `21 passed`, `19 todos`, `167 assertions`

Frontend:

- `npx vitest run src/app/layouts/AppSidebar.test.tsx src/app/layouts/AppHeader.characterization.test.tsx src/modules/auth/LoginPage.test.tsx src/modules/auth/login-navigation.test.ts src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx`
- resultado: `7 passed`, `19 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao adicional do plano `/my-events`

Backend:

- `php artisan test tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php`
- resultado: `13 todos`, sem falhas

Frontend:

- `npx vitest run src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx`
- resultado: `19 todos`, sem falhas

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao adicional de identidade, workspace e presets

Backend:

- `php artisan test tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php`
- resultado: `25 passed`, `17 todos`, `195 assertions`

Frontend:

- `npx vitest run src/modules/auth/workspace-selector.contract.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/moderation/moderation-event-scope.contract.test.ts`
- resultado: `13 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao pre-execucao de onboarding, notificacoes e template

Backend:

- `php artisan test tests/Feature/Auth/RegisterOtpTest.php tests/Feature/Auth/PasswordResetOtpTest.php tests/Feature/Auth/LoginTest.php tests/Feature/Auth/MeTest.php tests/Feature/Notifications/NotificationRealtimeAuthorizationTest.php tests/Feature/Notifications/NotificationCenterReadinessTest.php tests/Feature/Organizations/OrganizationTeamInvitationCharacterizationTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php`
- resultado: `47 passed`, `19 todos`, `331 assertions`

Frontend:

- `npx vitest run src/modules/auth/LoginPage.test.tsx src/modules/auth/login-navigation.test.ts src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx src/modules/settings/SettingsTeamInvitationFlow.contract.test.tsx src/modules/moderation/moderation-event-scope.contract.test.ts src/app/layouts/AppHeader.characterization.test.tsx`
- resultado: `4 passed`, `27 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

## Validacao executada nesta implementacao

Backend:

- `php artisan test tests/Feature/Auth/EventOnlySessionCharacterizationTest.php tests/Feature/Auth/MeTest.php tests/Feature/Auth/MultiOrganizationWorkspaceContractTest.php tests/Feature/EventTeam/EventPermissionPresetContractTest.php tests/Feature/EventTeam/EventTeamInvitationContractTest.php tests/Feature/Events/EventScopedAccessCharacterizationTest.php tests/Feature/Events/EventScopedAccessContractTest.php tests/Feature/Events/ListEventsTest.php tests/Feature/MediaProcessing/ModerationMediaTest.php`
- resultado: `56 passed`, `7 todo`, `656 assertions`

Frontend:

- `npx vitest run src/modules/auth/MyEventsPage.test.tsx src/modules/auth/workspace-utils.test.ts src/app/layouts/AppSidebar.test.tsx src/modules/auth/workspace-selector.contract.test.tsx src/modules/auth/my-events-page.contract.test.tsx`
- resultado: `8 passed`, `19 todo`

Type-check:

- `npm run type-check`
- resultado: `ok`

O que essa rodada fechou:

- sessao multi-workspace e entrada `/my-events` deixaram de ser apenas contrato e viraram implementacao real;
- `EventTeam` deixou de aceitar mutacao fora do escopo do evento;
- a troca de role por preset agora esta validada por teste;
- o dominio inicial de convite por evento ja existe sem duplicar usuario da plataforma;
- o aceite publico ainda segue como proxima slice porque o OTP atual continua acoplado a criacao de organizacao nova.

Rotas novas confirmadas:

- `GET /api/v1/events/{event}/access/invitations`
- `POST /api/v1/events/{event}/access/invitations`
