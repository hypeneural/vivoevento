# Event people identity and relations execution plan - 2026-04-10

## Objetivo

Transformar a estrategia de `event-people-identity-relations-aws-strategy-2026-04-10.md` em um plano de execucao implementavel, sem perder contexto de produto, sem deixar a AWS no hot path de navegacao e sem abrir uma trilha cara de manutencao.

Este plano responde 10 perguntas:

1. o que entra no escopo real da V1;
2. o que fica explicitamente fora da V1;
3. em qual modulo backend a feature deve nascer;
4. qual deve ser a fronteira entre `FaceSearch`, `EventPeople` e AWS;
5. quais tabelas, read models e endpoints precisam existir;
6. quais telas e fluxos de UX precisam entrar primeiro;
7. quais jobs e filas precisam ser criados;
8. quais testes devem travar comportamento antes da implementacao;
9. quais criterios de aceite precisam existir por fase;
10. qual e a definicao de pronto para a camada de `EventPeople`.

Documento base:

- `docs/architecture/event-people-identity-relations-aws-strategy-2026-04-10.md`

---

## Resultado dos testes executados antes do plano

## Backend

Comando:

```bash
cd apps/api
php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch
```

Resultado:

- `156 passed`
- `7 skipped`
- `1141 assertions`

Leitura pratica:

- a base de `FaceSearch` esta verde;
- indexacao, search, fallback, shadow, `users`, crop-first, readiness, queue hardening e telemetria continuam estaveis;
- o novo plano pode nascer por cima dessa trilha sem reabrir arquitetura de busca facial.

## Frontend

Comando:

```bash
cd apps/web
npx.cmd vitest run src/modules/face-search/components/FaceSearchSearchPanel.test.tsx src/modules/face-search/components/EventFaceSearchSearchCard.test.tsx src/modules/face-search/PublicFaceSearchPage.test.tsx src/modules/media/MediaPage.test.tsx src/modules/events/face-search-status.test.ts src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/EventDetailPage.test.tsx
```

Resultado:

- `8 files passed`
- `20 tests passed`

Leitura pratica:

- a UX atual de ativacao do evento e busca por selfie continua verde;
- o card avancado AWS e o detalhe do evento continuam cobertos;
- o novo plano deve aproveitar essas superfícies, nao substitui-las.

---

## Decisoes ja fechadas

As decisoes abaixo saem do plano de estrategia e entram aqui como restricoes de implementacao:

- a feature nasce em um modulo novo backend: `EventPeople`;
- `FaceSearch` continua tecnico e provider-aware;
- `EventPeople` vira o dominio canonico de pessoa e relacao no evento;
- AWS nao entra no hot path de navegacao, filtros ou relacoes;
- `SearchFacesByImage` e `SearchUsersByImage` continuam para selfie search e sugestao, nao para navegacao do catalogo;
- `EventPerson` e a identidade do produto; `AWS User` e um acelerador tecnico;
- relacao declarada e coocorrencia inferida precisam ficar separadas no modelo e na UI;
- a V1 nasce em imagem, nao em video;
- a V1 nasce em PostgreSQL + Laravel + React, sem banco de grafo dedicado;
- a V1 privilegia confirmacao guiada, nao cadastro manual vazio;
- read models quentes da V1 devem nascer como **tabelas projetadas incrementais**, nao como materialized views puras;
- materialized views ficam reservadas para agregados mais pesados e tardios, se a medicao real justificar;
- observabilidade entra na `Fase 0` e na `Fase 1`, nao como endurecimento tardio;
- a trilha principal de jobs de `EventPeople` deve usar a conexao `redis`, porque a validacao local mostra `after_commit=true` nela e `false` nas demais;
- jobs amplos de rebuild, replay e reconciliacao devem ser unicos;
- jobs com payload sensivel de rosto/pessoa devem ser criptografados;
- React deve usar `useDeferredValue` e `useTransition` nas superfícies quentes, sem desenhar a V1 em cima de Suspense para data fetching administrativo;
- a governanca de regiao, retention e privacidade AWS faz parte do plano de execucao, nao so do runbook final;
- o produto nao deve parar em "organizar pessoas"; o norte comercial passa a incluir cobertura importante, grupos do evento, momentos e entregas por relacao.

---

## Escopo real da V1

## Entra na V1

- modulo `EventPeople` no backend;
- entidade canonica `EventPerson`;
- atribuicao `rosto -> pessoa`;
- lista de pessoas do evento;
- detalhe da pessoa;
- filtro local por pessoa;
- overlay com rostos nomeaveis dentro da foto;
- caixa de revisao de sugestoes;
- criacao de pessoa a partir da confirmacao;
- representantes curados por pessoa para sync AWS;
- presets por tipo de evento;
- relacoes manuais basicas;
- read models locais para performance operacional;
- jobs assíncronos para sync AWS e recomputacoes incrementais;
- observabilidade minima da feature.

## Fica fora da V1

Observacao:

- grupos sociais, coverage intelligence, modo cerimonialista e trilha guest-facing por relacao nao entram na V1-base, mas entram como V1.5 / P1 de produto.

- banco de grafo dedicado;
- visualizacao premium do grafo;
- video face search operacional no fluxo de pessoas;
- inferencia automatica de parentesco como verdade;
- UX publica avançada por relacao;
- recomendador forte de grupos/nucleos familiares;
- analytics sociais premium;
- automacao de merge/split sem revisao humana.

---

## Arquitetura alvo da V1

## Modulo backend novo

Criar:

- `apps/api/app/Modules/EventPeople`

Estrutura inicial:

```text
EventPeople/
├── Actions/
├── DTOs/
├── Enums/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Models/
├── Policies/
├── Queries/
├── Services/
├── Support/
├── routes/
│   └── api.php
├── Providers/
│   └── EventPeopleServiceProvider.php
└── README.md
```

## Fronteira entre modulos

### `FaceSearch`

Responsavel por:

- detectar rostos;
- indexar rostos;
- manter `EventMediaFace`;
- orquestrar AWS/local;
- selfie search;
- sync tecnico de provider records e user vectors.

### `EventPeople`

Responsavel por:

- pessoa canonica do evento;
- revisão humana;
- atribuicao de rosto para pessoa;
- relacoes entre pessoas;
- read models de consulta local;
- filtros por pessoa;
- inbox operacional;
- grafo local do evento.

### `Events`

Continua responsavel por:

- configuracao simples no CRUD;
- status operacional resumido;
- links de entrada para a experiencia de pessoas.

---

## Modelo de dados recomendado

## Tabelas transacionais

### 1. `event_people`

Campos:

- `id`
- `event_id`
- `display_name`
- `slug`
- `type`
- `side`
- `avatar_media_id`
- `avatar_face_id`
- `importance_rank`
- `notes`
- `status`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Indices:

- `event_id, status`
- `event_id, type`
- `event_id, importance_rank desc`
- `event_id, slug`

### 2. `event_person_face_assignments`

Campos:

- `id`
- `event_id`
- `event_person_id`
- `event_media_face_id`
- `source`
- `confidence`
- `status`
- `reviewed_by`
- `reviewed_at`
- `created_at`
- `updated_at`

Indices:

- `event_id, event_person_id, status`
- `event_id, event_media_face_id`
- unique parcial obrigatorio para impedir dois `confirmed` para o mesmo `event_media_face_id`
- indice parcial para itens `status in ('suggested', 'confirmed')` quando a consulta quente pedir esse subconjunto

### 3. `event_person_relations`

Campos:

- `id`
- `event_id`
- `person_a_id`
- `person_b_id`
- `person_pair_key`
- `relation_type`
- `directionality`
- `source`
- `confidence`
- `strength`
- `is_primary`
- `notes`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Indices:

- `event_id, person_a_id`
- `event_id, person_b_id`
- `event_id, relation_type`
- unique por `event_id + person_pair_key + relation_type`

### 4. `event_person_cooccurrences`

Campos:

- `id`
- `event_id`
- `person_a_id`
- `person_b_id`
- `person_pair_key`
- `co_photo_count`
- `solo_photo_count_a`
- `solo_photo_count_b`
- `average_face_distance`
- `weighted_score`
- `last_seen_together_at`
- `created_at`
- `updated_at`

Indices:

- `event_id, person_a_id, weighted_score desc`
- `event_id, person_b_id, weighted_score desc`
- unique por `event_id + person_pair_key`

## Read models projetados da V1

Decisao para a primeira entrega:

- usar **tabelas projetadas incrementais** via jobs;
- nao depender de `REFRESH MATERIALIZED VIEW` como mecanismo principal da V1.

Motivo:

- confirmacoes humanas pedem atualizacao incremental;
- `review queue` precisa ser rapida e previsivel;
- evitar refresh total em telas quentes;
- facilitar idempotencia por `event_person_id` e `event_media_id`.

Disciplina obrigatoria:

- cada partial index quente deve casar com o `WHERE` real das queries da V1;
- nada de predicados genericos que o planner nao consiga reaproveitar;
- qualquer consulta parametrizada que precise usar partial index deve ser escrita de forma a preservar o predicado esperado;
- pares e coocorrencias devem sempre usar chave normalizada para evitar duplicidade logica.

### 5. `event_person_media_stats`

Uso:

- contadores da pessoa;
- total de fotos solo;
- total de fotos com outros;
- total de fotos publicadas;
- total de fotos pendentes.

### 6. `event_person_pair_scores`

Uso:

- pares mais frequentes;
- filtro `X + Y`;
- sugestoes de conexao forte.

### 7. `event_person_review_queue`

Uso:

- inbox priorizada;
- itens com maior frequencia;
- conflitos de identidade;
- sugestoes aguardando revisao.

Indices minimos:

- parcial para `status in ('pending', 'conflict')`;
- `event_id, priority desc, last_signal_at desc`.

### 8. `event_person_representative_faces`

Uso:

- conjunto curado para AWS;
- status do sync;
- rank de representatividade;
- controle do limite pratico de faces por pessoa.

Campos adicionais recomendados:

- `rank_score`
- `quality_score`
- `pose_bucket`
- `context_hash`
- `sync_status`
- `last_synced_at`

### 9. `event_person_name_search`

Uso:

- autocomplete;
- busca textual por nome e alias;
- lookup rapido no painel.

Indices minimos:

- `event_id, normalized_name`
- indice especifico para alias normalizado;
- evitar depender apenas de `ILIKE` sobre `event_people`.

## Extensoes de dominio recomendadas depois da V1-base

Essas estruturas nao precisam bloquear a primeira entrega, mas fazem sentido no plano porque sustentam o diferencial comercial.

### 10. `event_person_groups`

Uso:

- grupos sociais do evento;
- filtros por nucleo;
- albuns por grupo;
- slideshow tematico por grupo.

### 11. `event_person_group_memberships`

Uso:

- vincular pessoa a grupo;
- permitir grupos como `familia da noiva`, `padrinhos`, `amigos da faculdade`, `equipe do buffet`.

### 12. `event_coverage_targets`

Uso:

- definir o que e cobertura importante no evento;
- registrar alvos como `noiva + pai`, `casal + padrinhos`, `aniversariante + avos`.

### 13. `event_must_have_pairs`

Uso:

- pares obrigatorios por preset ou por operador;
- atalhos de curadoria e entrega.

### 14. `event_coverage_alerts`

Uso:

- alertas de cobertura insuficiente;
- backlog de momentos importantes ainda sem boa foto;
- assistente de priorizacao para fotografo, cerimonial ou operador.

---

## Contratos e endpoints da V1

## Leitura

### `GET /api/v1/events/{event}/people`

Lista pessoas do evento com:

- avatar;
- stats;
- tipo e lado;
- status;
- indicadores de revisao.

### `GET /api/v1/events/{event}/people/{person}`

Detalhe da pessoa com:

- dados principais;
- galerias derivadas;
- relacoes declaradas;
- pares recorrentes;
- sugestoes e conflitos.

### `GET /api/v1/events/{event}/people/review-queue`

Inbox de revisao ordenada por prioridade.

### `GET /api/v1/events/{event}/people/search`

Busca local por nome, alias e filtros de tipo/lado/status.

### `GET /api/v1/events/{event}/people/{person}/media`

Fotos da pessoa, com filtros:

- solo;
- com outra pessoa;
- publicadas;
- pendentes;
- favoritas;
- melhores.

### `GET /api/v1/events/{event}/media/{media}/people`

Retorna as faces detectadas da foto, com sugestoes de pessoa e atribuicoes atuais.

## Escrita

### `POST /api/v1/events/{event}/people`

Criacao manual opcional de pessoa.

Importante:

- nao e o fluxo principal da V1;
- existe para excecoes e refinamentos.

### `PATCH /api/v1/events/{event}/people/{person}`

Atualiza nome, tipo, lado, notas, avatar e status.

### `POST /api/v1/events/{event}/people/assignments`

Cria ou confirma atribuicao `rosto -> pessoa`.

Payload base:

- `event_media_face_id`
- `event_person_id` ou `create_person`
- `source`
- `status=confirmed`

### `POST /api/v1/events/{event}/people/review-queue/{item}/confirm`

Confirma sugestao.

### `POST /api/v1/events/{event}/people/review-queue/{item}/split`

Separa grupo sugerido.

### `POST /api/v1/events/{event}/people/review-queue/{item}/merge`

Mescla pessoas ou sugestoes.

### `POST /api/v1/events/{event}/people/review-queue/{item}/ignore`

Ignora item da fila.

### `POST /api/v1/events/{event}/people/relations`

Cria relacao manual.

### `PATCH /api/v1/events/{event}/people/relations/{relation}`

Edita relacao.

### `DELETE /api/v1/events/{event}/people/relations/{relation}`

Remove relacao.

---

## Contrato assincrono obrigatorio

Toda escrita que mexe em pessoa, atribuicao ou relacao segue a mesma ordem:

1. gravacao local;
2. commit da transacao;
3. atualizacao dos read models minimos;
4. sync AWS por fora;
5. reconciliacao pesada apenas por job.

Regras fixas:

- jobs disparados por escrita humana usam `afterCommit`;
- fila principal do modulo fica em `redis`;
- recalculos sensiveis usam `WithoutOverlapping` por `event_id` ou `event_person_id`;
- sync AWS usa `RateLimited`;
- rebuilds amplos usam `ShouldBeUnique`;
- jobs com payload sensivel usam `ShouldBeEncrypted`.

Consequencia pratica:

- a UI confirma primeiro no banco local;
- a pessoa aparece no produto antes de qualquer roundtrip com a AWS;
- erro de sync remoto nunca bloqueia a operacao humana.

---

## UX alvo da V1

## 1. Entrada no evento

No detalhe do evento, adicionar CTA claro:

- `Organizar pessoas do evento`

Esse CTA so aparece quando:

- `face_search.enabled = true`
- ha acervo suficiente para iniciar a revisao

## 2. Caixa de revisao como fluxo principal

Unidade de trabalho:

- `Quem e esta pessoa?`

Acao primaria:

- confirmar ou corrigir rapidamente.

Evitar:

- formulario vazio como primeira experiencia;
- linguagem tecnica.

## 3. Overlay na foto

Ao abrir uma foto:

- exibir caixas dos rostos detectados;
- permitir clique no rosto;
- mostrar drawer/popover com:
  - sugestao principal
  - pessoa existente
  - criar nova pessoa
  - irrelevante
  - ignorar

## 4. Lista de pessoas

Ordenacao default:

1. pessoas com maior volume confirmado;
2. pessoas sem nome mas com alta prioridade de revisao;
3. pessoas importantes do preset;
4. restante.

## 5. Detalhe da pessoa

Tabs sugeridas:

- `Fotos`
- `Com quem aparece`
- `Relacoes`
- `Revisao`

## 6. Modo cerimonialista

Essa camada entra cedo na experiencia, mesmo que a modelagem de grupos e coverage venha depois.

Fluxo guiado recomendado:

1. confirmar pessoas principais;
2. relacionar familiares e papeis importantes;
3. revisar sugestoes prioritarias;
4. acompanhar cobertura importante;
5. aprovar momentos e entregas.

CTAs sugeridos:

- `Organizar pessoas`
- `Cobertura importante`
- `Grupos do evento`
- `Momentos do evento`
- `Entregas prontas`

## 7. Guest-facing relacional

Depois da base local de pessoas e relacoes estabilizar, a camada guest-facing pode crescer acima dela.

Entradas futuras recomendadas:

- `Veja suas fotos com o casal`
- `Veja fotos com sua familia`
- `Veja fotos do seu grupo`
- `Receba quando aparecer uma nova foto sua`

---

## Regras de responsividade do frontend

Essas regras entram como restricao de implementacao, nao como sugestao.

### Inputs e autocomplete

- estado do input fica isolado em subcomponente;
- `useDeferredValue` entra na busca por pessoa e no autocomplete quando a lista depender do texto digitado;
- nenhuma tentativa de usar `Transition` para controlar o valor do input.

### Navegacao lateral e filtros

- `useTransition` entra em troca de aba, ordenacao, filtros `X + Y`, abertura de painel lateral e troca de pessoa selecionada;
- updates apos `await` precisam ser remarcados com `startTransition`;
- loading states continuam explicitos e previsiveis.

### Suspense

- a V1 nao nasce `Suspense-first` para data fetching administrativo comum;
- usar cache local, estados de loading/error e transicoes nao bloqueantes;
- so considerar Suspense para dados se a fonte de dados adotada no modulo for explicitamente compativel.

### Critico para review queue

- confirmar uma pessoa nao pode travar digitacao nem scroll da fila;
- o ack local da acao precisa aparecer antes do sync AWS;
- a UI deve priorizar sensacao de continuidade sobre refetch bruto.

---

## Presets por tipo de evento

## V1

Implementar presets simples no backend e frontend.

### Casamento

Pessoas sugeridas:

- noiva
- noivo
- mae da noiva
- pai da noiva
- mae do noivo
- pai do noivo
- padrinhos
- madrinhas
- cerimonial
- fotografo

### Corporativo

Pessoas sugeridas:

- host
- speaker
- executivo
- equipe
- patrocinador
- imprensa

### Show / festival

Pessoas sugeridas:

- artista
- banda
- producao
- equipe tecnica
- patrocinador

---

## Filas e jobs da V1

## Principio operacional

- salvar primeiro localmente;
- refletir na UI rapidamente;
- sincronizar depois;
- recalcular por delta;
- manter AWS fora do request-response.

## Jobs novos sugeridos

### Alta prioridade

#### `ProjectEventPersonReviewQueueJob`

Responsavel por:

- atualizar inbox minima depois de confirmacao;
- recalcular prioridade dos itens afetados.

#### `ProjectEventPersonMediaStatsJob`

Responsavel por:

- atualizar contadores minimos por pessoa.

#### `ProjectEventPeopleOperationalCountersJob`

Responsavel por:

- atualizar contadores de backlog;
- refletir sinais minimos da review queue;
- alimentar badges e resumos do detalhe do evento.

## Media prioridade

#### `ProjectEventPersonPairScoresJob`

Responsavel por:

- recalcular pares impactados por confirmacao, merge ou split.

#### `ProjectEventPersonCooccurrenceDeltaJob`

Responsavel por:

- recalcular apenas o delta de coocorrencia afetado.

## Baixa prioridade

#### `SyncEventPersonRepresentativesToAwsJob`

Responsavel por:

- curar representatives;
- sincronizar AWS user;
- respeitar rate limit e overlap.

#### `RefreshEventPeopleLongTailReadModelsJob`

Responsavel por:

- recomputar agregados mais pesados em lote;
- preencher ou reconciliar projeções tardias.

## Middlewares recomendados

- `afterCommit`
- `WithoutOverlapping`
- `RateLimited` ou `RateLimitedWithRedis`
- `ShouldBeUnique`
- `ShouldBeEncrypted`

## Fila sugerida

Seguindo a topologia atual, usar filas dedicadas:

- `event-people-high`
- `event-people-medium`
- `event-people-low`

Se a topologia inicial nao puder crescer agora, usar:

- `face-index`
- `default`

mas com naming separado e supervisores dedicados assim que a V1 entrar em homolog.

### Horizon e operacao

Criar desde cedo:

- supervisors dedicados por fila `event-people-*`;
- tags Horizon por `event:{id}` e `person:{id}`;
- thresholds de espera por fila quente;
- alarmes para backlog e fila lenta;
- `horizon:snapshot` mantido no schedule operacional.

Leitura da configuracao atual:

- `apps/api/config/queue.php` ja deixa `after_commit=true` apenas na conexao `redis`;
- `apps/api/routes/console.php` ja agenda `horizon:snapshot`;
- `apps/api/config/horizon.php` ja tem `waits`, `silenced_tags` e supervisor dedicado para `face-index`.

Implicacao:

- `EventPeople` deve nascer na trilha `redis`;
- qualquer desvio de conexao precisa ser intencional e justificado;
- observabilidade da nova fila nao comeca do zero, mas precisa ser estendida.

---

## Decisao pratica sobre representatives AWS

Para a V1, usar:

- piso de `5` faces representativas por pessoa quando houver acervo suficiente;
- teto operacional inicial de `15` faces representativas por pessoa;
- rank por:
  - qualidade
  - frontalidade
  - diversidade de yaw/pitch
  - variacao de contexto
  - ausencia de duplicata quase identica

Politica formal do seletor:

- descartar face abaixo do gate minimo antes mesmo de entrar na disputa;
- maximizar diversidade de pose antes de maximizar quantidade;
- evitar sequencias quase identicas da mesma midia;
- privilegiar contextos diferentes quando a qualidade for equivalente;
- manter um conjunto menor e curado, nunca um espelho completo do acervo local;
- registrar `ExternalImageId` e metadata suficiente para reconciliar face local x face remota.

Nao usar:

- todas as faces confirmadas da pessoa;
- sync imediato a cada clique.

Governanca operacional:

- definir regiao AWS unica por tenant ou evento, sem mistura casual;
- explicitar politica de retention para collections, face vectors e user vectors;
- revisar opt-out organizacional aplicavel aos servicos de IA usados;
- documentar limpeza de evento encerrado como etapa obrigatoria do runbook.

---

## Fases de execucao

## Fase 0 - Fundacao e contratos

Objetivo:

- preparar o modulo novo, travar os contratos e endurecer a base operacional antes de UX rica.

### EP0-T1 - criar o modulo `EventPeople`

Subtarefas:

- criar estrutura de pastas do modulo;
- registrar `ServiceProvider`;
- registrar rotas do modulo;
- criar `README.md` do modulo;
- registrar no mapa de modulos, se aplicavel.

### EP0-T2 - criar migrations iniciais

Subtarefas:

- `event_people`
- `event_person_face_assignments`
- `event_person_relations`
- `event_person_cooccurrences`
- `event_person_media_stats`
- `event_person_pair_scores`
- `event_person_review_queue`
- `event_person_representative_faces`
- `event_person_name_search`

### EP0-T3 - criar enums e policies

Subtarefas:

- `EventPersonType`
- `EventPersonSide`
- `EventPersonStatus`
- `EventPersonAssignmentSource`
- `EventPersonAssignmentStatus`
- `EventPersonRelationType`
- `EventPersonPolicy`

### EP0-T4 - criar factories e seeds minimos

Subtarefas:

- factory de `EventPerson`
- factory de `EventPersonFaceAssignment`
- factory de `EventPersonRelation`
- factory de `EventPersonCooccurrence`

### EP0-T5 - travar contratos de API

Subtarefas:

- definir payloads de listagem;
- definir payload de detalhe;
- definir payload do review queue;
- definir payload de overlay em foto.

### EP0-T6 - endurecer contrato assincrono e topologia de filas

Subtarefas:

- fixar `redis` como conexao principal do modulo;
- marcar jobs de escrita com `afterCommit`;
- definir onde entra `WithoutOverlapping`;
- definir onde entra `RateLimited`;
- definir quais jobs precisam de `ShouldBeUnique`;
- definir quais jobs precisam de `ShouldBeEncrypted`.

### EP0-T7 - observabilidade baseline

Subtarefas:

- definir tags Horizon por evento e pessoa;
- definir thresholds de espera das filas `event-people-*`;
- definir metricas minimas de review queue;
- definir mapeamento basico para metricas Rekognition no CloudWatch;
- definir logs estruturados minimos de confirmacao, merge, split e sync.

### EP0-T8 - governanca AWS e privacidade

Subtarefas:

- definir estrategia de regiao por tenant ou evento;
- definir retention de collections e vectors;
- definir limpeza de evento encerrado;
- revisar necessidade de opt-out organizacional;
- alinhar linguagem de consentimento com produto e juridico.

### Testes obrigatorios da fase 0

Backend:

- feature test de rotas base do modulo;
- contract test de resources;
- unit tests de enums e rules;
- migration smoke;
- test de config/contrato de fila;
- test de tags ou metadata minima dos jobs.

Aceite da fase:

- modulo nasce isolado e coerente com o monorepo;
- banco suporta o dominio minimo;
- contratos basicos estao congelados em teste;
- observabilidade minima e contrato assincrono ficam definidos antes da tela rica.

## Fase 1 - Escrita local e confirmacao guiada

Objetivo:

- criar o fluxo humano principal sem depender da AWS em tempo real.

### EP1-T1 - criar action de confirmacao de rosto

Subtarefas:

- confirmar sugestao existente;
- criar pessoa a partir do rosto;
- mover rosto para pessoa existente;
- marcar irrelevante;
- rejeitar sugestao.

### EP1-T2 - criar endpoints da review queue

Subtarefas:

- listar inbox;
- confirmar item;
- ignorar item;
- split;
- merge.

### EP1-T3 - projetar `event_person_review_queue`

Subtarefas:

- criterios de prioridade;
- itens sem nome recorrentes;
- conflitos de identidade;
- agrupamentos com alta confianca.

### EP1-T3.1 - garantir resposta local e consistencia eventual

Subtarefas:

- projetar inbox minima apos commit;
- nao depender do sync AWS para refletir confirmacao;
- manter idempotencia por rosto e pessoa;
- devolver ack local antes de qualquer reconciliacao pesada.

### EP1-T4 - integrar `EventMediaFace` ao fluxo novo

Subtarefas:

- endpoint por foto para listar rostos detectados;
- devolver bbox, suggestion e assignment atual;
- garantir leitura scoped por `event_id`.

### Testes obrigatorios da fase 1

Backend:

- feature tests de `confirm`, `ignore`, `merge`, `split`;
- unit tests das actions;
- teste de idempotencia nas confirmacoes;
- teste de escopo por evento.

Frontend:

- teste do inbox de revisao;
- teste do overlay por rosto;
- teste da criacao de pessoa a partir da confirmacao;
- teste de feedback local antes do sync remoto.

Aceite da fase:

- operador consegue transformar rostos em pessoas sem formulario vazio;
- o fluxo inteiro responde localmente e rapido;
- nenhum clique da revisao depende da AWS;
- a confirmacao aparece no produto antes da reconciliacao remota.

## Fase 2 - Lista de pessoas, detalhe e filtros

Objetivo:

- entregar navegação operacional por pessoa.

### EP2-T1 - criar queries e resources de pessoa

Subtarefas:

- listagem;
- detalhe;
- midias da pessoa;
- filtros por tipo, lado e status.

### EP2-T2 - projetar `event_person_media_stats`

Subtarefas:

- total de midias;
- solo vs acompanhado;
- publicadas vs pendentes;
- melhor foto/ultima foto.

### EP2-T3 - projetar `event_person_name_search`

Subtarefas:

- indice de nome;
- alias futuros;
- autocomplete local.

### EP2-T4 - filtros na UI

Subtarefas:

- filtro por pessoa no catalogo;
- entrada de autocomplete;
- pagina de pessoas;
- detalhe da pessoa.

### EP2-T5 - endurecer responsividade percebida

Subtarefas:

- isolar estado dos inputs de busca;
- aplicar `useDeferredValue` em autocomplete e busca local;
- aplicar `useTransition` em troca de aba, filtros e drawers;
- evitar Suspense como base do fetching administrativo;
- garantir loading states previsiveis.

### Testes obrigatorios da fase 2

Backend:

- feature tests de listagem/detalhe/media;
- unit tests das queries;
- tests de `event_person_media_stats`.

Frontend:

- tests de pagina de pessoas;
- tests de detalhe da pessoa;
- tests de filtro local por pessoa;
- tests de busca/autocomplete sem bloquear input;
- tests de transicao nao bloqueante em troca de aba ou filtro.

Aceite da fase:

- pessoa vira unidade navegavel do produto;
- buscar por nome nao toca AWS;
- abrir pessoa e filtrar por pessoa fica estavel e rapido;
- a digitacao continua fluida mesmo com a lista carregada.

## Fase 3 - Relacoes manuais e presets

Objetivo:

- estruturar o nucleo social do evento com baixa friccao.

### EP3-T1 - presets por tipo de evento

Subtarefas:

- casamento;
- corporativo;
- show/festival.

### EP3-T2 - CRUD de relacoes

Subtarefas:

- criar;
- editar;
- apagar;
- destacar relacao principal.

### EP3-T3 - UI de relacoes

Subtarefas:

- editor simples;
- cards de relacoes confirmadas;
- sugerir papeis iniciais do preset.

### EP3-T4 - filtros `X + Y`

Subtarefas:

- fotos da pessoa com outra pessoa;
- filtro por par;
- combinacoes simples no detalhe.

### Testes obrigatorios da fase 3

Backend:

- feature tests de relacoes;
- unit tests de validacao de direcionalidade e unicidade;
- tests de filtros por par.

Frontend:

- tests do editor de relacoes;
- tests dos presets;
- tests de filtros `X + Y`.

Aceite da fase:

- casamento e corporativo ja conseguem modelar as principais pessoas sem trabalho excessivo;
- relacoes declaradas ficam separadas de sugestoes inferidas.

## Fase 3.5 - Grupos e coverage intelligence

Objetivo:

- transformar a base de pessoas em assistente de cobertura importante.

### EP3.5-T1 - grupos sociais do evento

Subtarefas:

- criar `event_person_groups`;
- criar `event_person_group_memberships`;
- presets iniciais de grupos por tipo de evento;
- filtros e listagens por grupo.

### EP3.5-T2 - coverage targets

Subtarefas:

- criar `event_coverage_targets`;
- criar `event_must_have_pairs`;
- permitir targets sugeridos por preset;
- permitir ajustes manuais do operador.

### EP3.5-T3 - coverage alerts

Subtarefas:

- criar `event_coverage_alerts`;
- destacar pessoas e pares sem boa cobertura;
- priorizar pares e grupos mais importantes.

### EP3.5-T4 - UI de cobertura importante

Subtarefas:

- painel `Cobertura importante`;
- cards de pares e grupos obrigatorios;
- status `ok`, `baixo`, `faltando`.

### Testes obrigatorios da fase 3.5

Backend:

- feature tests de grupos e memberships;
- tests de targets e alerts;
- unit tests de score de cobertura.

Frontend:

- tests do painel `Cobertura importante`;
- tests de filtros por grupo;
- tests de priorizacao de pares obrigatorios.

Aceite da fase:

- o produto consegue responder "o que ainda falta fotografar bem?";
- grupos sociais viram entidade navegavel e editavel;
- cobertura importante passa a orientar a operacao humana.

## Fase 4 - Coocorrencia e sugestoes locais

Objetivo:

- introduzir inteligencia local reutilizavel sem inventar parentesco.

### EP4-T1 - projetar `event_person_pair_scores`

Subtarefas:

- score por par;
- ranking por peso;
- combinacao com co-photo count.

### EP4-T2 - projetar `event_person_cooccurrences`

Subtarefas:

- pares por evento;
- ultima aparicao conjunta;
- peso incremental por midia.

### EP4-T3 - sugestoes operacionais

Subtarefas:

- quem aparece mais com quem;
- possiveis grupos recorrentes;
- possiveis conflitos;
- cobertura de pessoas importantes.

### EP4-T4 - UI de conexoes sugeridas

Subtarefas:

- bloco separado de relacoes confirmadas;
- bloco de conexoes sugeridas;
- acao `transformar em relacao`.

### Testes obrigatorios da fase 4

Backend:

- unit tests de score incremental;
- tests de escopo por evento;
- tests de projeção por delta.

Frontend:

- tests de bloco de conexoes sugeridas;
- tests de separacao visual entre confirmado e sugerido.

Aceite da fase:

- produto passa a responder `quem aparece junto com quem` sem chamar AWS;
- sugestao nao e confundida com verdade.

## Fase 4.5 - Momentos e entregas emocionais

Objetivo:

- converter pessoas, grupos e relacoes em memorias prontas para entrega.

### EP4.5-T1 - colecoes por relacao e grupo

Subtarefas:

- melhores fotos com `X`;
- melhores fotos com `X + Y`;
- melhores fotos do grupo;
- colecoes de cobertura ja pronta.

### EP4.5-T2 - guest-facing relacional

Subtarefas:

- ver fotos com o casal;
- ver fotos com sua familia;
- ver fotos do seu grupo;
- preparar base para notificacao futura.

### EP4.5-T3 - modo cerimonialista

Subtarefas:

- fluxo guiado por etapas;
- checklist de cobertura;
- entrada unica para entregas prontas.

### Testes obrigatorios da fase 4.5

Backend:

- feature tests de colecoes derivadas;
- unit tests de selecao de melhores momentos;
- tests de escopo e privacidade das colecoes guest-facing.

Frontend:

- tests do fluxo guiado de cerimonialista;
- tests das telas de momentos e entregas;
- tests das entradas guest-facing novas, se habilitadas.

Aceite da fase:

- o produto ja nao entrega so busca de pessoa, mas momentos prontos por vinculo;
- a experiencia guest-facing usa o dominio local, sem depender de AWS por clique.

## Fase 5 - Sync AWS curado e endurecimento operacional

Objetivo:

- sincronizar representatives com a AWS de forma barata, segura e observável.

### EP5-T1 - projetar `event_person_representative_faces`

Subtarefas:

- rank de qualidade;
- diversidade de pose;
- status de sync;
- face ativa/inativa.

### EP5-T2 - criar job de sync AWS

Subtarefas:

- lote por pessoa;
- rate limit;
- sem hot path;
- retry/backoff;
- overlap protection.

### EP5-T3 - observabilidade expandida e governanca

Subtarefas:

- logs por etapa;
- counters de sync;
- falhas por motivo;
- tempo para primeira confirmacao;
- backlog de review queue;
- alarmes CloudWatch para Rekognition;
- retention e limpeza de collections.

### EP5-T4 - runbook operacional

Subtarefas:

- como reprocessar pessoa;
- como ressincronizar evento;
- como lidar com conflito de user;
- como degradar para local sem quebrar UX;
- como limpar evento encerrado e encerrar retention.

### Testes obrigatorios da fase 5

Backend:

- unit tests do selector de representatives;
- unit tests do job de sync;
- tests de idempotencia;
- tests de throttling e overlap.

Frontend:

- tests de status operacional minimo na futura UI de pessoas, se exibido.

Aceite da fase:

- AWS continua fora do hot path;
- sync fica observável e retry-safe;
- representatives obedecem politica de custo e qualidade.

## Fase 6 - Visualizacao premium opcional

Objetivo:

- habilitar wow factor sem contaminar a base operacional.

### EP6-T1 - mapa de conexoes

### EP6-T2 - visualizacao familiar

### EP6-T3 - timeline de relacoes

### EP6-T4 - insights sociais

Essa fase so comeca quando:

- fases 1 a 5 estiverem estaveis;
- inbox de revisao estiver adotada;
- pessoas e relacoes estiverem consolidadas.

---

## Decisao detalhada sobre read models

## V1

Usar tabelas projetadas incrementais para:

- `event_person_review_queue`
- `event_person_media_stats`
- `event_person_pair_scores`
- `event_person_representative_faces`
- `event_person_name_search`

## Reserva para V1.5 / V2

Avaliar materialized views apenas se:

- relatorios ficarem pesados demais;
- ranking de coocorrencia exigir refresh em lote;
- medicao real mostrar que tabela projetada incremental virou custo maior que `refresh`.

Essa escolha e a mais coerente com:

- confirmacao humana frequente;
- necessidade de delta local;
- UX que nao pode esperar refresh global.

---

## Criterios operacionais alvo da V1

Esses alvos sao operacionais e podem ser calibrados em homolog, mas ja entram no plano como referencia.

### UX percebida

- ack local de confirmacao visivel em ate `250ms` percebidos;
- tempo para primeira confirmacao util medido por sessao;
- fluxo principal responde sem travar digitacao ou scroll.

### Leituras locais

- `review queue` e listagem de pessoas com p95 abaixo de `500ms` em homolog;
- autocomplete local com resposta previsivel e sem roundtrip AWS;
- filtros `X + Y` servidos por read model local.

### Consistencia assincrona

- read models minimos convergem poucos segundos apos a confirmacao;
- sync AWS atrasado nao bloqueia a operacao humana;
- rebuild amplo nao duplica job nem disputa lock do mesmo evento.

### Operacao

- tempo de espera por `event-people-high` monitorado desde o inicio;
- alarmes para backlog e throttling AWS;
- metricas de `tempo para primeira confirmacao`, `pessoas confirmadas por sessao` e `abandono da review queue` entram no acompanhamento da feature.

---

## Bateria de testes obrigatoria por milestone

## Milestone A - Fundacao pronta

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople tests/Unit/EventPeople
```

Backend extra:

```bash
cd apps/api
php artisan test tests/Unit/EventPeople/EventPeopleQueueContractTest.php tests/Unit/EventPeople/EventPeopleObservabilityContractTest.php
```

## Milestone B - Fluxo de confirmacao guiada

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PeopleReviewQueueTest.php tests/Feature/EventPeople/PersonFaceAssignmentTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/PeopleReviewQueuePage.test.tsx src/modules/event-people/components/MediaFaceOverlay.test.tsx
```

## Milestone C - Lista e detalhe de pessoas

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PeopleCatalogTest.php tests/Feature/EventPeople/PersonDetailTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/PeoplePage.test.tsx src/modules/event-people/PersonDetailPage.test.tsx src/modules/media/MediaPage.test.tsx
```

Frontend extra:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/PeopleSearchAutocomplete.test.tsx src/modules/event-people/components/PersonTabsTransition.test.tsx
```

## Milestone D - Relacoes e filtros por par

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PersonRelationsTest.php tests/Feature/EventPeople/PersonPairFilterTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/PersonRelationsEditor.test.tsx src/modules/event-people/components/PersonPairFilters.test.tsx
```

## Milestone E - Sync AWS curado

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PersonAwsSyncTest.php tests/Unit/EventPeople
```

Backend extra:

```bash
cd apps/api
php artisan test tests/Unit/EventPeople/EventPeopleRepresentativeSelectorTest.php tests/Unit/EventPeople/EventPeopleAwsGovernanceTest.php
```

## Milestone F - Grupos e coverage intelligence

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PersonGroupsTest.php tests/Feature/EventPeople/CoverageIntelligenceTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/CoveragePanel.test.tsx src/modules/event-people/components/PersonGroupsPanel.test.tsx
```

## Milestone G - Momentos e entregas

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/RelationalCollectionsTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/CerimonialistaFlow.test.tsx src/modules/event-people/components/RelationalDeliveries.test.tsx
```

---

## Checklist tecnico objetivo

- [x] criar o modulo `EventPeople`
- [x] criar tabelas transacionais do dominio
- [x] criar tabelas projetadas da V1
- [ ] criar CRUD minimo de pessoa
- [x] criar atribuicao `rosto -> pessoa`
- [x] criar inbox de revisao
- [x] criar overlay de rostos na foto
- [ ] criar pagina de pessoas
- [x] criar detalhe da pessoa
- [x] criar filtro local por pessoa
- [ ] criar relacoes manuais
- [ ] criar presets por tipo de evento
- [ ] criar grupos sociais do evento
- [ ] criar coverage targets e alerts
- [x] criar coocorrencia por evento
- [x] criar pair scores locais
- [ ] criar colecoes e momentos por relacao
- [ ] criar representatives curados para AWS
- [ ] criar sync AWS assíncrono por job
- [x] criar observabilidade minima
- [x] configurar tags Horizon e thresholds das filas `event-people-*`
- [x] definir partial indexes e unique partial indexes da V1
- [ ] explicitar governanca de retention e limpeza AWS
- [x] garantir `afterCommit`, jobs unicos e jobs criptografados onde couber
- [ ] explicitar regras de `useDeferredValue` e `useTransition` nas telas quentes
- [ ] documentar runbook operacional

---

## Status de execucao

### 2026-04-10 - Fase 0 parcial concluida

Concluido:

- modulo backend `EventPeople` criado e registrado;
- provider e rotas base registrados;
- migrations iniciais criadas para tabelas transacionais e read models projetados;
- enums, models, factories e policy base criados;
- contratos de leitura criados para lista de pessoas, detalhe, review queue e overlay de rostos por midia;
- partial unique index para impedir mais de um `confirmed` por rosto detectado;
- chave normalizada `person_pair_key` para relacoes, pair scores e coocorrencias;
- jobs base criados com `redis`, `afterCommit`, `ShouldBeUnique`, `ShouldBeEncrypted`, `WithoutOverlapping`, `RateLimited` e tags Horizon;
- filas `event-people-high`, `event-people-medium` e `event-people-low` registradas no Horizon com thresholds de espera;
- rate limiter `event-people-aws-sync` registrado no provider do modulo;
- servico `EventPeopleOperationalMetricsService` criado para snapshot minimo de operacao por evento;
- README do modulo e mapa de modulos atualizados.

Bateria executada:

- `php artisan test tests/Feature/EventPeople tests/Unit/EventPeople` -> `13 passed`, `90 assertions`
- `php artisan test tests/Unit/EventPeople/EventPeopleQueueContractTest.php tests/Unit/EventPeople/EventPeopleObservabilityContractTest.php` -> `5 passed`, `47 assertions`
- `php artisan test tests/Feature/FaceSearch/FaceSearchSettingsTest.php tests/Feature/FaceSearch/FaceSearchSelfieEndpointsTest.php` -> `21 passed`, `201 assertions`
- `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch` -> `156 passed`, `7 skipped`, `1141 assertions`

Ainda pendente na Fase 0:

- governanca AWS/retention em runbook operacional;
- mapeamento operacional final de CloudWatch/alarmes fora do codigo;
- implementacao real das projecoes dos jobs, que pertence a Fase 1 e fases seguintes.

---

## Riscos reais e mitigacoes

### R1. Misturar `FaceSearch` e `EventPeople`

Mitigacao:

- manter fronteira clara;
- `FaceSearch` tecnico, `EventPeople` dominio.

### R2. AWS entrar no hot path

Mitigacao:

- nenhum filtro por pessoa chama AWS;
- sync sempre em fila.

### R3. Refresh global demais

Mitigacao:

- usar projeção incremental por delta;
- adiar materialized views para quando a medicao pedir.

### R4. UX burocratica

Mitigacao:

- revisao guiada primeiro;
- cadastro manual como excecao.

### R5. Representantes ruins na AWS

Mitigacao:

- curadoria de representatives;
- quality gate forte;
- politica de diversidade e deduplicacao.

### R6. Observabilidade entrar tarde demais

Mitigacao:

- tags e thresholds desde a fase 0;
- alarmes de backlog e throttling antes da homolog;
- metricas de UX acompanhadas junto com a entrega.

### R7. Partial index nao ser usado como esperado

Mitigacao:

- escrever queries com `WHERE` disciplinado;
- validar plano com EXPLAIN nas consultas quentes;
- evitar predicados genericos e indexes parciais demais.

### R8. Privacidade e retention ficarem implicitas

Mitigacao:

- runbook de limpeza obrigatoria;
- politica de regiao e retention fechada antes da homolog;
- consentimento e opt-out tratados como dependencia real da feature.

### R9. Ficar forte so como backoffice e fraco como produto percebido

Mitigacao:

- tratar grupos, coverage intelligence e momentos como fases reais do roadmap;
- nao deixar o plano parar em organizacao interna;
- puxar entrega emocional antes do grafo premium.

---

## Definicao de pronto da V1

Podemos chamar a V1 de pronta quando:

1. o operador consegue sair de uma foto com rostos detectados para pessoas confirmadas sem depender da AWS em tempo real;
2. a lista de pessoas do evento e navegavel e filtravel localmente;
3. o detalhe da pessoa mostra fotos, pares e relacoes basicas;
4. a inbox de revisao resolve a maior parte do fluxo sem formulario vazio;
5. relacoes manuais ja funcionam com presets simples de evento;
6. read models locais mantem a UX fluida;
7. representatives sao sincronizados com a AWS apenas em job;
8. logs e contadores minimos deixam falhas rastreaveis;
9. tags Horizon, thresholds e backlog das filas dedicadas estao ativos;
10. a bateria backend/frontend da milestone vigente esta verde;
11. o produto responde "quem e esta pessoa?" e "quais fotos tem essa pessoa?" de forma confiavel e operacional;
12. a governanca minima de retention e limpeza AWS esta documentada.

---

## Proximo passo depois deste plano

Ordem recomendada de execucao:

1. `Fase 0 - Fundacao e contratos`
2. `Fase 1 - Escrita local e confirmacao guiada`
3. `Fase 2 - Lista de pessoas, detalhe e filtros`
4. `Fase 3 - Relacoes manuais e presets`
5. `Fase 3.5 - Grupos e coverage intelligence`
6. `Fase 4 - Coocorrencia e sugestoes locais`
7. `Fase 4.5 - Momentos e entregas emocionais`
8. `Fase 5 - Sync AWS curado`

Motivo da ordem:

- primeiro vem valor operacional;
- depois navegacao;
- depois estrutura social;
- depois cobertura importante;
- depois inteligencia local;
- depois momentos e entregas;
- e so entao endurecimento completo de sync e camada premium.
