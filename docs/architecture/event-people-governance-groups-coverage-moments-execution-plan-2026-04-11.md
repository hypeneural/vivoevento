# EventPeople governance, groups, coverage and moments execution plan - 2026-04-11

## Objetivo

Detalhar as proximas frentes do `EventPeople` que continuam pendentes depois da base V1:

1. retention e limpeza AWS;
2. runbook operacional do backend;
3. grupos sociais do evento;
4. coverage intelligence;
5. momentos e entregas por relacao.

Este documento complementa, e nao substitui:

- `docs/architecture/event-people-identity-relations-aws-strategy-2026-04-10.md`
- `docs/architecture/event-people-identity-relations-execution-plan-2026-04-10.md`

---

## Leitura executiva

O modulo `EventPeople` ja tem:

- pessoa canonica;
- relacoes manuais;
- pair scores locais;
- representatives curados;
- sync AWS assincrono por job;
- pagina dedicada e fluxo guiado no frontend.

O que ainda falta nao e "mais da mesma coisa".

Faltam quatro blocos diferentes:

### 1. Governanca operacional da AWS

Hoje ja existe sync de representatives e exclusao de collection no `FaceSearch`, mas ainda nao existe:

- politica clara de retention por evento;
- limpeza automatica de user vectors orfaos;
- auditoria periodica da collection;
- runbook de reprocessamento, rollback e cleanup.

### 2. Estrutura social do evento

Pares resolvem so parte da navegacao.

Faltam grupos como:

- familia da noiva;
- padrinhos;
- mesa VIP;
- equipe do buffet;
- amigos da faculdade.

### 3. Cobertura importante

O produto ainda encontra pessoas, mas nao mede bem:

- quem esta sem boa cobertura;
- quais pares obrigatorios ainda faltam;
- quais grupos ainda nao renderam album forte.

### 4. Momentos e entregas por relacao

O produto ainda nao entrega:

- colecoes por vinculo;
- melhores fotos com uma pessoa;
- melhores fotos com um grupo;
- entregas prontas para operador, cerimonialista ou convidado.

---

## O que a documentacao oficial da AWS valida para esta fase

As referencias abaixo foram revisadas na documentacao oficial da AWS em `2026-04-11`.

### 1. Collection e user vector precisam de governanca explicita

`DeleteCollection` remove todas as faces da collection.

Fonte oficial:

- `DeleteCollection`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DeleteCollection.html

Leitura pratica:

- para limpeza total do evento, o caminho mais seguro continua sendo derrubar a collection inteira;
- isso combina com o que ja existe em `AwsRekognitionFaceSearchBackend::deleteEventBackend`.

### 2. User vector pode ser removido de forma limpa

`DeleteUser` apaga o `UserId` e desassocia antes as faces ligadas a ele.

Fonte oficial:

- `DeleteUser`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DeleteUser.html

Leitura pratica:

- para merge, delete ou arquivamento de pessoa, precisamos tratar limpeza de `UserId`;
- hoje o job de representatives marca `projected_empty`, mas nao apaga o user remoto;
- isso e um gap real de retention e consistencia.

### 3. Disassociation e delecao de faces sao operacoes diferentes

`DisassociateFaces` remove o vinculo entre `FaceIds` e `UserId`, enquanto `DeleteFaces` remove os vetores de face da collection.

Fontes oficiais:

- `DisassociateFaces`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DisassociateFaces.html
- `DeleteFaces`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DeleteFaces.html

Leitura pratica:

- reatribuicao ou merge parcial pede `DisassociateFaces` ou `DeleteUser`, nao `DeleteCollection`;
- limpeza de vetores inuteis pede `DeleteFaces`;
- a ordem de seguranca para pessoa individual e:
  - deletar ou desassociar o user;
  - depois remover faces remotas que nao devem continuar no indice;
  - ou simplesmente derrubar a collection inteira quando a limpeza for por evento encerrado.

### 4. Auditoria nao pode depender so do banco local

`DescribeCollection`, `ListCollections` e `ListUsers` existem exatamente para inspecionar o estado remoto.

Fontes oficiais:

- `DescribeCollection`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DescribeCollection.html
- `ListCollections`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_ListCollections.html
- `ListUsers`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_ListUsers.html

Leitura pratica:

- o runbook precisa ter modo `audit`;
- o backend precisa de um job de reconciliacao/auditoria que compare:
  - collection esperada no evento;
  - `FaceCount` e `UserCount`;
  - `UserId` deterministico esperado por pessoa;
  - estado local de provider records e representatives.

### 5. Custos continuam vindo de analise e storage

Amazon Rekognition cobra por analise de imagem e tambem por armazenamento de face metadata.

Fonte oficial:

- pricing: https://aws.amazon.com/rekognition/pricing/

Leitura pratica:

- retention/limpeza nao e detalhe operacional;
- collection esquecida e user vector orfao viram custo recorrente;
- grupos, coverage e momentos devem consumir so dados locais.

### 6. Regiao e limites ainda influenciam o desenho

A documentacao oficial confirma:

- o S3 usado na imagem deve estar na mesma regiao da operacao Rekognition;
- ha limites para face vectors, user vectors, payload e TPS;
- a AWS recomenda suavizar trafego, retries, backoff e jitter.

Fontes oficiais:

- `Image`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_Image.html
- quotas: https://docs.aws.amazon.com/rekognition/latest/dg/limits.html

Leitura pratica:

- a governanca precisa fixar regiao por evento ou tenant;
- o cleanup precisa continuar em fila;
- qualquer sweep massivo de retention precisa respeitar rate limit.

### 7. Monitoramento e auditoria de API ja existem na AWS

CloudWatch expoe metricas como `ResponseTime`, `SuccessfulRequestCount`, `ThrottledCount` e `UserErrorCount`.

CloudTrail registra chamadas de API do Rekognition.

Fontes oficiais:

- `CloudWatch`: https://docs.aws.amazon.com/rekognition/latest/dg/rekognition-monitoring.html
- `CloudTrail`: https://docs.aws.amazon.com/rekognition/latest/dg/logging-using-cloudtrail.html

Leitura pratica:

- o runbook precisa incluir paineis e alarmes;
- cleanup e sync precisam deixar trilha audivel;
- incidente de throttling ou cleanup parcial nao pode ser resolvido so "olhando log local".

---

## Estado local atual e gaps objetivos

## O que ja existe

### Backend

- `SyncEventPersonRepresentativeFacesJob` sincroniza representatives curados;
- `AwsRekognitionFaceSearchBackend::deleteEventBackend()` ja deleta a collection do evento;
- o user remoto hoje e deterministico no formato `evt:{event}:person:{person}`;
- o job de sync ja usa `RateLimited`, `WithoutOverlapping`, `ShouldBeUnique` e `ShouldBeEncrypted`.

### Frontend

- pagina dedicada de pessoas;
- relacoes manuais;
- copy em portugues;
- leitura local sem AWS no hot path.

## O gap real

### AWS governance

- nao existe job de auditoria remota;
- nao existe job de limpeza de `UserId` por pessoa;
- nao existe retention policy fechada para collection de evento encerrado;
- nao existe runbook backend formalizado.

### Grupos

- nao existem `event_person_groups` nem memberships;
- nao ha filtros por nucleo;
- nao ha preset de grupos por tipo de evento.

### Coverage

- nao existem `coverage_targets`, `must_have_pairs` e `coverage_alerts`;
- nao ha score claro de cobertura por pessoa, par ou grupo.

### Momentos e entregas

- nao existem colecoes derivadas por relacao;
- nao existe pipeline de entrega por vinculo;
- nao ha trilha guest-facing derivada das relacoes locais.

---

## Decisoes de arquitetura para a proxima fase

### 1. Collection continua sendo responsabilidade do `FaceSearch`

Motivo:

- collection e provider record continuam tecnicos;
- `EventPeople` nao deve virar backend provider-aware inteiro.

Implicacao:

- cleanup total por evento usa a trilha de `FaceSearch`;
- cleanup por pessoa e disparado por `EventPeople`, mas executado via backend AWS ja existente.

### 2. User cleanup precisa nascer no `EventPeople`

Motivo:

- quem sabe que uma pessoa foi mesclada, arquivada ou esvaziada e o dominio `EventPeople`;
- o user id remoto ja e deterministico por pessoa.

Implicacao:

- precisamos de action/job para `DeleteUser`;
- quando uma pessoa perder todas as faces representativas, o job nao pode so retornar `projected_empty`.

### 3. Grupos, coverage e momentos continuam 100% locais

Motivo:

- nao agregam valor chamar AWS para isso;
- o dominio ja tem dados suficientes depois da confirmacao humana.

Implicacao:

- novas leituras e calculos continuam em PostgreSQL + jobs;
- a AWS so participa quando houver impacto no reconhecimento.

### 4. Coverage e momentos nascem como read models projetados

Motivo:

- queremos consultas rapidas;
- queremos delta por evento, pessoa, grupo e par;
- nao queremos recalculo global a cada clique.

---

## Frente 1 - Retention, limpeza AWS e runbook operacional

## Meta

Fechar a governanca remota sem contaminar o hot path do operador.

## Tarefas

### GP1-T1 - fechar a politica de retention por evento

Definir, no minimo:

- quando uma collection continua viva depois do encerramento do evento;
- janela de seguranca antes da limpeza definitiva;
- quais estados impedem cleanup automatico:
  - evento ainda ativo;
  - reprocessamento em andamento;
  - incidente aberto;
  - aprovacao manual pendente.

Implementacao sugerida:

- adicionar campos em `EventFaceSearchSetting` ou criar config dedicada:
  - `aws_auto_cleanup_enabled`
  - `aws_cleanup_grace_days`
  - `aws_last_audit_at`
  - `aws_last_cleanup_at`
  - `aws_cleanup_status`
- registrar decisao de retention por evento, nao so global.

### GP1-T2 - criar auditoria remota de collection

Criar action/job dedicado, por exemplo:

- `AuditEventFaceSearchAwsCollectionAction`
- `AuditEventFaceSearchAwsCollectionJob`

Esse fluxo deve:

- confirmar se a collection esperada existe;
- ler `FaceCount`, `UserCount` e `FaceModelVersion` com `DescribeCollection`;
- listar usuarios com `ListUsers`, paginando;
- comparar `UserId` remotos com o formato deterministico local `evt:{event}:person:{person}`;
- registrar divergencias:
  - collection ausente;
  - users orfaos;
  - pessoa local sem user esperado;
  - contagens remotas fora da faixa esperada;
  - records locais apontando para face remota inexistente.

Saida recomendada:

- tabela ou log estruturado de auditoria;
- resumo operacional no detalhe do evento.

### GP1-T3 - criar cleanup de `UserId` por pessoa

Criar action/job, por exemplo:

- `DeleteEventPersonAwsUserAction`
- `DeleteEventPersonAwsUserJob`

Quando disparar:

- merge de pessoas, para limpar o user da pessoa fonte;
- delete/arquivamento permanente de pessoa;
- pessoa sem representatives remotos restantes;
- rollback de sync que precise recriar identidade limpa.

Regra:

- usar `DeleteUser` primeiro;
- tratar `ResourceNotFoundException` como cleanup idempotente;
- registrar resultado local com timestamp e payload.

### GP1-T4 - criar cleanup de faces remotas inuteis

Criar action/job, por exemplo:

- `DeleteEventPersonProviderFacesAction`
- `DeleteEventPersonProviderFacesJob`

Usar quando:

- houver provider face ids locais sem vinculo util;
- houver representatives substituidos por novas faces melhores;
- a pessoa foi desassociada e nao queremos manter faces antigas na collection.

Regra:

- preferir remover `UserId` primeiro quando a limpeza for por identidade;
- usar `DeleteFaces` para limpeza residual e por lote;
- registrar `DeletedFaces` e `UnsuccessfulFaceDeletions`.

### GP1-T5 - criar sweep de retention por evento encerrado

Criar job agendado, por exemplo:

- `RunEventFaceSearchRetentionSweepJob`

Fluxo:

1. selecionar eventos elegiveis pela janela de retention;
2. executar `audit` em modo dry-run;
3. bloquear limpeza se houver incidentes ou reprocessamento;
4. executar `deleteEventBackend()` para limpeza total;
5. limpar flags locais, provider records e status operacionais;
6. registrar execucao e resultado.

### GP1-T6 - criar runbook operacional do backend

Documentar:

- como auditar um evento;
- como forcar ressync de uma pessoa;
- como apagar `UserId` remoto de uma pessoa;
- como apagar a collection do evento;
- como operar em modo degradado local-only;
- como agir diante de throttling;
- como validar se a limpeza terminou.

O runbook precisa citar:

- logs locais;
- Horizon;
- CloudWatch;
- CloudTrail.

## Testes obrigatorios da frente 1

### Backend

- teste unitario do auditor de collection;
- teste unitario do cleanup idempotente com `ResourceNotFoundException`;
- teste unitario da ordem `DeleteUser` -> `DeleteFaces` quando aplicavel;
- teste de feature do endpoint/command administrativo de audit;
- teste de feature do fluxo de cleanup de evento encerrado;
- teste de throttling/retry nos jobs AWS;
- teste de `ShouldBeUnique` no sweep de retention.

### Contratos

- teste da serializacao dos logs estruturados;
- teste de tags Horizon por evento;
- teste de persistencia do resultado de auditoria e cleanup.

## Criterios de aceite da frente 1

- evento encerrado consegue limpar a collection sem intervencao manual comum;
- merge/delete de pessoa consegue limpar o `UserId` remoto;
- auditoria detecta user orfao e collection divergente;
- runbook fica suficiente para operacao sem leitura de codigo;
- cleanup continua fora do hot path.

---

## Frente 2 - Grupos sociais do evento

## Meta

Subir o produto de pessoa individual para nucleos sociais reais do evento.

## Tarefas

### GP2-T1 - criar modelo de grupos

Criar tabelas:

- `event_person_groups`
- `event_person_group_memberships`

Campos recomendados em `event_person_groups`:

- `id`
- `event_id`
- `display_name`
- `slug`
- `group_type`
- `side`
- `notes`
- `importance_rank`
- `status`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

Campos recomendados em `event_person_group_memberships`:

- `id`
- `event_id`
- `event_person_group_id`
- `event_person_id`
- `role_label`
- `source`
- `confidence`
- `status`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

### GP2-T2 - criar presets de grupos por tipo de evento

Casamento:

- familia da noiva;
- familia do noivo;
- padrinhos;
- madrinhas;
- cerimonial;
- fornecedores principais.

Corporativo:

- diretoria;
- speakers;
- equipe;
- patrocinadores;
- imprensa.

Show:

- artista;
- banda;
- producao;
- backstage;
- patrocinadores.

### GP2-T3 - CRUD e filtros de grupo

Endpoints sugeridos:

- `GET /api/v1/events/{event}/people/groups`
- `POST /api/v1/events/{event}/people/groups`
- `PATCH /api/v1/events/{event}/people/groups/{group}`
- `DELETE /api/v1/events/{event}/people/groups/{group}`
- `POST /api/v1/events/{event}/people/groups/{group}/members`
- `DELETE /api/v1/events/{event}/people/groups/{group}/members/{membership}`

Leituras locais:

- pessoas do grupo;
- fotos do grupo;
- pares fortes dentro do grupo;
- cobertura do grupo.

### GP2-T4 - read models locais de grupo

Criar pelo menos:

- `event_person_group_stats`
- `event_person_group_media_stats`

Uso:

- filtros rapidos;
- cards de grupo;
- ranking de grupos mais cobertos;
- gap de cobertura por nucleo.

## Testes obrigatorios da frente 2

### Backend

- `PersonGroupsTest`
- teste unitario de membership unique por pessoa e grupo;
- teste de filtro por grupo no catalogo de fotos;
- teste de preset de grupos por tipo de evento.

### Frontend

- `PersonGroupsPanel.test.tsx`
- teste de CRUD basico de grupo;
- teste de filtro por grupo e detalhe do grupo.

## Criterios de aceite da frente 2

- operador consegue criar e editar grupos sem AWS;
- pessoa pode pertencer a varios grupos do mesmo evento;
- fotos do grupo respondem de leitura local;
- grupos entram no fluxo do modo cerimonialista.

---

## Frente 3 - Coverage intelligence

## Meta

Transformar o produto de organizador em assistente de cobertura.

## Tarefas

### GP3-T1 - criar o dominio de coverage

Criar tabelas:

- `event_coverage_targets`
- `event_must_have_pairs`
- `event_coverage_alerts`

Opcionalmente:

- `event_coverage_target_stats`

Tipos de target:

- `person`
- `pair`
- `group`

Campos recomendados:

- `target_type`
- `person_a_id`
- `person_b_id`
- `event_person_group_id`
- `required_media_count`
- `required_published_media_count`
- `importance_rank`
- `status`
- `last_evaluated_at`

### GP3-T2 - definir score de cobertura

Primeira versao deve ser simples, defensavel e explicita.

Sinais recomendados:

- quantidade de fotos confirmadas;
- quantidade de fotos publicadas;
- quantidade de fotos com as duas pessoas juntas;
- existencia de foto solo forte;
- peso do grupo ou par dentro do preset do evento;
- status da moderacao/publicacao.

Estados sugeridos:

- `missing`
- `weak`
- `ok`
- `strong`

Importante:

- cobertura e local;
- cobertura nao chama AWS;
- cobertura nao inventa relacao, ela avalia alvo declarado.

### GP3-T3 - projetar alertas incrementais

Criar jobs:

- `ProjectEventCoverageTargetStatsJob`
- `ProjectEventCoverageAlertsJob`

Recalcular por delta quando:

- pessoa ganha ou perde assignment confirmado;
- relacao muda;
- membership de grupo muda;
- media muda de status de publicacao.

### GP3-T4 - criar painel `Cobertura importante`

Entradas:

- pessoas sem boa cobertura;
- pares obrigatorios incompletos;
- grupos importantes fracos;
- ranking de pendencias.

Saidas:

- alertas claros;
- filtros para abrir fotos faltantes;
- prioridade para fotografo, operador e cerimonialista.

## Testes obrigatorios da frente 3

### Backend

- `CoverageIntelligenceTest`
- teste unitario do score por pessoa;
- teste unitario do score por par;
- teste unitario do score por grupo;
- teste de job incremental por delta.

### Frontend

- `CoveragePanel.test.tsx`
- teste de filtros de alvo;
- teste de estados `missing`, `weak`, `ok`, `strong`.

## Criterios de aceite da frente 3

- o painel responde so com dados locais;
- targets prioritarios aparecem primeiro;
- operador entende o motivo do alerta;
- cobertura vira parte da rotina, nao um relatorio escondido.

---

## Frente 4 - Momentos e entregas por relacao

## Meta

Entregar colecoes com significado, nao so listas de fotos.

## Tarefas

### GP4-T1 - criar modelo de colecoes relacionais

Criar tabelas:

- `event_relational_collections`
- `event_relational_collection_items`

Campos recomendados:

- `event_id`
- `collection_type`
- `source_type`
- `person_a_id`
- `person_b_id`
- `event_person_group_id`
- `display_name`
- `status`
- `visibility`
- `generated_at`
- `published_at`

Tipos iniciais:

- `person_best_of`
- `pair_best_of`
- `group_best_of`
- `family_moment`
- `must_have_delivery`

### GP4-T2 - criar recipe engine local

Criar service/job, por exemplo:

- `BuildEventRelationalCollectionsAction`
- `ProjectEventRelationalCollectionsJob`

Entrada:

- relacoes declaradas;
- grupos;
- targets de coverage;
- stats de pessoa, par e grupo.

Regra:

- para guest-facing, usar so midia publicada;
- para curadoria interna, permitir leitura de pendentes se o operador tiver permissao;
- a AWS nao participa dessa geracao.

### GP4-T3 - criar entregas prontas por vinculo

Casos iniciais:

- melhores fotos da noiva com a mae;
- melhores fotos do casal com padrinhos;
- melhores fotos do aniversariante com avos;
- melhores fotos do convidado com o casal;
- melhores fotos do grupo da mesa ou da familia.

### GP4-T4 - preparar trilha guest-facing futura

Nao precisa abrir publico no primeiro corte, mas o backend deve nascer preparado para:

- link seguro por colecao;
- notificacao futura;
- visibilidade por evento;
- delivery por pessoa, grupo ou relacao.

## Testes obrigatorios da frente 4

### Backend

- `RelationalCollectionsTest`
- teste do recipe builder por pessoa;
- teste do recipe builder por par;
- teste do recipe builder por grupo;
- teste de visibilidade interna x publica.

### Frontend

- `RelationalDeliveries.test.tsx`
- teste de cards de momentos;
- teste de lista de entregas prontas;
- teste do fluxo futuro do modo cerimonialista.

## Criterios de aceite da frente 4

- operador consegue abrir colecoes prontas por vinculo;
- guest-facing futuro pode ser ligado sem redesign estrutural;
- o diferencial comercial fica evidente sem depender de grafo premium.

---

## Ordem recomendada de execucao

### Passo 1 - AWS governance e runbook

Motivo:

- hoje existe custo e risco operacional aberto;
- existe risco real de user remoto orfao;
- essa frente endurece o que ja esta em producao.

### Passo 2 - Grupos

Motivo:

- grupos desbloqueiam cobertura real;
- sem grupos, coverage fica pobre e centrado demais em pares.

### Passo 3 - Coverage intelligence

Motivo:

- coverage depende de pessoa, relacao e grupo;
- coverage e o que transforma o produto em assistente de curadoria.

### Passo 4 - Momentos e entregas

Motivo:

- essa camada depende de coverage e grupos para ficar forte;
- e a entrega comercial mais valiosa depois da organizacao.

---

## Bateria de testes recomendada por milestone

## Milestone H - AWS governance e runbook

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PersonAwsCleanupTest.php tests/Unit/EventPeople/EventPeopleAwsGovernanceTest.php tests/Feature/FaceSearch/DeleteEventFaceSearchCollectionTest.php
```

## Milestone I - Grupos

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/PersonGroupsTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/PersonGroupsPanel.test.tsx
```

## Milestone J - Coverage intelligence

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/CoverageIntelligenceTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/CoveragePanel.test.tsx
```

## Milestone K - Momentos e entregas por relacao

Backend:

```bash
cd apps/api
php artisan test tests/Feature/EventPeople/RelationalCollectionsTest.php tests/Unit/EventPeople
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/RelationalDeliveries.test.tsx src/modules/event-people/components/CerimonialistaFlow.test.tsx
```

---

## Checklist final desta doc

- [ ] fechar politica de retention AWS por evento
- [ ] criar auditoria remota de collection e users
- [ ] criar cleanup de `UserId` por pessoa
- [ ] criar cleanup de faces remotas inuteis
- [ ] criar sweep de cleanup por evento encerrado
- [ ] documentar runbook operacional de audit, ressync e cleanup
- [ ] criar `event_person_groups`
- [ ] criar `event_person_group_memberships`
- [ ] criar presets e CRUD de grupos
- [ ] criar stats e filtros por grupo
- [ ] criar `event_coverage_targets`
- [ ] criar `event_must_have_pairs`
- [ ] criar `event_coverage_alerts`
- [ ] criar score de cobertura por pessoa, par e grupo
- [ ] criar painel `Cobertura importante`
- [ ] criar `event_relational_collections`
- [ ] criar `event_relational_collection_items`
- [ ] criar recipe engine de colecoes relacionais
- [ ] criar entregas prontas por vinculo
- [ ] preparar trilha guest-facing derivada

---

## Conclusao

O backlog pendente do `EventPeople` agora se divide em dois tipos de trabalho:

1. endurecimento operacional da AWS e do backend;
2. ampliacao do produto para grupo, cobertura, momentos e entrega.

Os dois importam, mas a ordem correta e:

- primeiro limpar governanca e retention;
- depois subir grupos;
- depois medir cobertura;
- depois transformar isso em momentos e entregas.

Em uma frase:

**a base de pessoas ja existe; agora o produto precisa ficar seguro para operar e forte para entregar memoria por vinculo.**
