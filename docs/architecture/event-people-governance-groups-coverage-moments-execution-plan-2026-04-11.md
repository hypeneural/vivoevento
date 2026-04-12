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
- `docs/architecture/event-people-ux-graph-reference-photos-presets-analysis-2026-04-11.md`

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

## Validacao de partida

## Veredito

Sim, ja da para iniciar.

Nao falta base tecnica para comecar.

O que falta agora nao e infraestrutura minima; sao algumas decisoes de partida para evitar retrabalho logo na primeira sprint.

## O que ja esta pronto para iniciar

- modulo `EventPeople` ja existe e esta integrado;
- filas dedicadas `event-people-high`, `event-people-medium` e `event-people-low` ja existem;
- rate limit de sync AWS ja existe;
- Horizon ja monitora as filas do modulo;
- sync assincrono de representatives ja existe;
- pagina dedicada, fluxo guiado e leitura local ja existem;
- a bateria atual do modulo esta verde em backend e frontend.

## O que precisa ser decidido antes do primeiro commit da proxima fase

### 1. Sequencia exata da primeira sprint

Decisao recomendada:

- Sprint 1 = `Frente 0` inteira ou pelo menos `GP0-T1` ate `GP0-T5`;
- nao abrir grupos, coverage ou momentos antes disso.

### 2. Onde a auditoria e retention vao persistir estado

Precisamos fechar se vamos:

- estender `EventFaceSearchSetting`; ou
- criar tabela propria de auditoria/cleanup.

Decisao recomendada:

- status atual curto em `EventFaceSearchSetting`;
- historico detalhado em tabela propria.

### 3. Como vamos testar `EXPLAIN`

Os testes de plano so fazem sentido em PostgreSQL.

Precisamos decidir:

- se eles vao rodar apenas em ambiente PostgreSQL;
- ou se vao ser caracterizacoes opt-in fora da suite comum.

Decisao recomendada:

- manter esses testes como suite PostgreSQL dedicada, nao como unit test generico.

### 4. Qual sera a primeira superficie do cockpit

Precisamos fechar se o primeiro overview vai nascer em:

- `EventDetailPage`; ou
- `EventPeoplePage`; ou
- uma pagina nova de overview operacional.

Decisao recomendada:

- primeiro overview enxuto dentro da `EventPeoplePage`;
- sem abrir terceira superficie agora.

### 5. Quem pode disparar cleanup e audit

Precisamos explicitar:

- permissao administrativa;
- se audit e cleanup entram em rota HTTP, command, job agendado ou todos;
- como bloquear cleanup em incidente.

Decisao recomendada:

- command + job agendado para operacao normal;
- rota/admin action so para suporte e super-admin.

## O que NAO bloqueia o inicio

- CloudWatch dashboard final;
- guest-facing por relacao;
- modelagem completa de grupos sociais;
- recipe engine final de momentos;
- grafo premium;
- runbook finalizado em detalhe total.

Esses itens importam, mas nao travam a primeira sprint util.

## Go / no-go

### Go agora

Se a equipe aceitar estas quatro premissas:

1. `Frente 0` vem antes de grupos e coverage;
2. cleanup total por evento continua no `FaceSearch`;
3. cleanup por pessoa nasce em `EventPeople`;
4. testes de plano SQL vao rodar em PostgreSQL dedicado.

### No-go

Se a equipe quiser:

- comecar por grupos ou coverage sem congelar contrato operacional;
- tratar retention AWS como detalhe para depois;
- deixar query quente sem `EXPLAIN` e sem query object;
- crescer o frontend sem protocolo de cache otimista e status visivel.

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

## O que a documentacao oficial do resto da stack valida para esta fase

As referencias abaixo tambem foram revisadas nas docs oficiais em `2026-04-11`.

### 1. Laravel confirma que o contrato assincrono precisa ser mais explicito

O Laravel documenta:

- `afterCommit()` para impedir job antes do commit;
- `ShouldBeUnique` para evitar duplicidade de dispatch;
- `WithoutOverlapping` para bloquear disputa de processamento;
- `RateLimited` para segurar burst e reprocessamento concorrente.

Fonte oficial:

- `Laravel Queues`: https://laravel.com/docs/12.x/queues

Leitura pratica:

- a doc atual acerta ao usar esses recursos, mas ainda falta formalizar o contrato de estados e transicoes do dominio;
- sem isso, action, fila, read model e sync remoto continuam inferindo estado em paralelo.

### 2. PostgreSQL valida partial indexes, mas exige EXPLAIN e predicado congelado

O PostgreSQL documenta:

- o `WHERE` da query precisa implicar de forma reconhecivel o predicado do indice parcial;
- expressoes equivalentes escritas de outro jeito podem nao usar o indice;
- clausulas parametrizadas podem impedir uso do indice parcial;
- `EXPLAIN ANALYZE` executa a query e mostra contagem e tempo reais.

Fontes oficiais:

- `Partial Indexes`: https://www.postgresql.org/docs/current/indexes-partial.html
- `Using EXPLAIN`: https://www.postgresql.org/docs/current/using-explain.html

Leitura pratica:

- review queue, listagem de pessoas, pair filters e autocomplete precisam de SQL congelado;
- nao basta "ter indice", precisa provar plano nas leituras quentes.

### 3. React e TanStack Query validam um protocolo mais rigido de cache e transicao

O React documenta:

- `useTransition` vale quando temos acesso ao `setState`;
- updates depois de `await` precisam ser marcados novamente com `startTransition`;
- `useDeferredValue` nao cria render deferido se o update ja estiver dentro de uma transition;
- `useDeferredValue` nao evita requests extras por si so.

O TanStack Query documenta:

- `onMutate` serve para snapshot e update otimista;
- `onError` deve fazer rollback do snapshot;
- `invalidateQueries` e a invalidacao correta depois da mutacao.

Fontes oficiais:

- `useTransition`: https://react.dev/reference/react/useTransition
- `useDeferredValue`: https://react.dev/reference/react/useDeferredValue
- `Optimistic Updates`: https://tanstack.com/query/latest/docs/framework/react/guides/optimistic-updates
- `Invalidations from Mutations`: https://tanstack.com/query/latest/docs/framework/react/guides/invalidations-from-mutations

Leitura pratica:

- o frontend ja usa parte desse protocolo no `MediaPage`, mas isso ainda nao esta virando regra de engenharia do modulo;
- coverage, grupos e momentos vao herdar esse risco se a doc nao congelar o protocolo agora.

### 4. W3C/WCAG valida o cockpit operacional e a acessibilidade do backoffice

O W3C documenta:

- mensagens de status devem informar sucesso, espera, progresso ou erro sem mudar contexto;
- `role=status` e live regions sao o caminho correto para feedback nao intrusivo;
- foco visivel com tamanho e contraste suficientes continua sendo requisito relevante em WCAG 2.2.

Fontes oficiais:

- `Status Messages`: https://www.w3.org/WAI/WCAG21/Understanding/status-messages.html
- `What’s New in WCAG 2.2`: https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/

Leitura pratica:

- a proxima iteracao nao deve ser so "mais telas";
- deve virar cockpit operacional com estados visiveis, foco claro e feedback sem roubar contexto.

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

### Validacao local com testes atuais

Bateria rodada nesta revisao:

- `cd apps/api && php artisan test tests/Feature/EventPeople tests/Unit/EventPeople` -> `32 passed`, `262 assertions`
- `cd apps/web && npx.cmd vitest run src/modules/event-people/EventPeoplePage.test.tsx src/modules/event-people/components/EventPeopleIdentitySheet.test.tsx src/modules/event-people/components/EventPeopleFaceOverlay.test.tsx src/modules/media/MediaPage.test.tsx` -> `4 files passed`, `8 tests passed`

O que essa bateria confirma:

- a base atual continua verde para CRUD, review queue, merge/split, representatives e sync;
- o frontend guiado continua funcional.

O que ela ainda nao trava:

- transicoes permitidas de estado ponta a ponta;
- contrato de projecao e replay por `event_id`;
- plano real das queries quentes com `EXPLAIN`;
- rollback do cache otimista em todos os casos de erro;
- acessibilidade de status messages e foco;
- fluxos de audit e cleanup AWS.

## O gap real

### AWS governance

- nao existe job de auditoria remota;
- nao existe job de limpeza de `UserId` por pessoa;
- nao existe retention policy fechada para collection de evento encerrado;
- nao existe runbook backend formalizado.

### Grupos

- `event_person_groups` e `event_person_group_memberships` ja existem no modulo;
- painel de grupos e seed do `Modelo do evento` ja funcionam localmente;
- ainda faltam read models dedicados de stats e filtro de catalogo por nucleo.

### Coverage

- nao existem `coverage_targets`, `must_have_pairs` e `coverage_alerts`;
- nao ha score claro de cobertura por pessoa, par ou grupo.

### Momentos e entregas

- nao existem colecoes derivadas por relacao;
- nao existe pipeline de entrega por vinculo;
- nao ha trilha guest-facing derivada das relacoes locais.

### Consistencia operacional

O maior risco atual nao parece ser arquitetura errada.

O maior risco parece ser espalhamento de verdade entre:

- banco transacional;
- read models;
- cache otimista do frontend;
- fila;
- estado remoto na AWS.

Validacao local do codigo:

- a pasta `Queries` do modulo existe, mas esta vazia;
- nao ha trilha clara de eventos de dominio replayaveis dentro de `EventPeople`;
- a coordenacao atual esta concentrada em `Actions` + `Jobs` + logs;
- o frontend ja usa `onMutate`, `setQueryData` e `invalidateQueries` em `MediaPage`, mas isso ainda nao esta congelado como contrato do modulo.

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

### 5. Antes de crescer produto, precisamos congelar o contrato operacional

Isto e uma inferencia direta da combinacao entre:

- comportamento atual do codigo;
- cobertura existente;
- docs oficiais de Laravel, PostgreSQL, React e TanStack Query.

Implica:

- explicitar maquina de estados e transicoes permitidas;
- tratar projecao como contrato;
- tratar query quente como contrato com plano;
- tratar cache otimista como protocolo, nao implementacao ad-hoc.

---

## Frente 0 - Contrato operacional, performance real e cockpit do gestor

## Meta

Reduzir o risco de verdade espalhada antes de ampliar grupos, coverage e momentos.

## Tarefas

### GP0-T1 - explicitar maquina de estados do dominio

Documentar e depois codificar transicoes permitidas entre:

- `face`
- `suggestion`
- `review_item`
- `assignment`
- `person`
- `remote_sync_status`

Cada transicao deve ter:

- estado de origem;
- estado de destino;
- actor da mudanca;
- action responsavel;
- chave de idempotencia;
- motivo do estado;
- efeito colateral permitido;
- projecoes que precisam ser atualizadas.

Exemplos minimos:

- `pending -> confirmed`
- `pending -> ignored`
- `confirmed -> pending` no split
- `conflict -> resolved`
- `representative sync_status: pending -> synced|failed|skipped`

### GP0-T2 - tratar cada projecao como contrato

Para cada read model, documentar:

- produtor;
- consumidor;
- chave de idempotencia;
- atraso maximo aceitavel;
- comando de replay por `event_id`;
- politica de recomputacao parcial;
- fallback se a projecao estiver atrasada.

Alvo minimo:

- `event_person_review_queue`
- `event_person_media_stats`
- `event_person_pair_scores`
- `event_person_representative_faces`
- futuros `group_stats`, `coverage_alerts` e `relational_collections`

### GP0-T3 - mover leituras operacionais para query objects estaveis

O modulo ja tem pasta `Queries`, mas ela ainda nao e usada.

Criar pelo menos:

- `ListEventPeopleQuery`
- `ListEventPeopleReviewQueueQuery`
- `ListEventPersonPairScoresQuery`
- `SearchEventPeopleByNameQuery`

Regra:

- shape estavel de resposta;
- SQL centralizado;
- sem vazar join e regra de negocio para controller ou React.

### GP0-T4 - blindar queries quentes com `EXPLAIN`

Criar caracterizacao de plano para:

- review queue;
- listagem de pessoas;
- autocomplete;
- pair filters.

Regra pragmatica:

- nao testar custo numerico exato, porque isso e fragil;
- testar predicado congelado, shape do SQL e uso esperado do indice;
- rodar `EXPLAIN` ou `EXPLAIN ANALYZE` em ambiente de teste controlado quando fizer sentido.

### GP0-T5 - congelar protocolo de cache otimista do frontend

Definir regra de engenharia:

- input sempre urgente e isolado;
- lista derivada usa `useDeferredValue`;
- troca de aba, drawer, filtros grandes e troca de pessoa usam `useTransition`;
- updates depois de `await` reaplicam `startTransition`;
- mutacoes humanas usam `onMutate` com snapshot;
- falha usa rollback;
- sucesso usa invalidacao inteligente;
- ack local nunca desaparece so porque a projecao atrasou.

### GP0-T6 - transformar o fluxo em cockpit operacional

Organizar o backoffice para responder visualmente:

- o que esta resolvido;
- o que esta pendente;
- o que esta em risco;
- o que exige acao humana agora;
- o que o sistema esta processando sozinho.

Aplicar isso em:

- overview do evento;
- review queue;
- pagina dedicada de pessoas;
- futuros paineis de coverage e entregas.

### GP0-T7 - subir acessibilidade operacional minima

Padrao minimo:

- status messages com semantica correta;
- foco visivel em cards, tabs, drawers e acoes rapidas;
- feedback de erro e sucesso sem roubar foco;
- acoes destrutivas com recuperacao clara.

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

### Passo 0 - Contrato operacional, performance e cockpit

Motivo:

- hoje o maior risco e estado espalhado, nao falta de ideia;
- grupos, coverage e momentos vao herdar esse risco se nascerem antes;
- essa frente reduz custo de manutencao, replay e depuracao.

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

## Milestone G0 - Contrato operacional, performance e cockpit

Backend:

```bash
cd apps/api
php artisan test tests/Unit/EventPeople/EventPeopleStateMachineTest.php tests/Unit/EventPeople/EventPeopleProjectionContractTest.php tests/Unit/EventPeople/EventPeopleHotQueriesExplainTest.php
```

Frontend:

```bash
cd apps/web
npx.cmd vitest run src/modules/event-people/components/EventPeopleOptimisticFlow.test.tsx src/modules/event-people/components/EventPeopleStatusA11y.test.tsx
```

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

- [x] explicitar maquina de estados do dominio
- [x] explicitar contrato das projecoes e replay por `event_id`
- [x] mover leituras operacionais para query objects estaveis
- [x] blindar queries quentes com `EXPLAIN`
- [x] congelar protocolo de cache otimista e transicoes do frontend
- [x] transformar overview, fila e pagina de pessoas em cockpit operacional
- [x] subir acessibilidade minima de status e foco
- [ ] fechar politica de retention AWS por evento
- [ ] criar auditoria remota de collection e users
- [ ] criar cleanup de `UserId` por pessoa
- [ ] criar cleanup de faces remotas inuteis
- [ ] criar sweep de cleanup por evento encerrado
- [ ] documentar runbook operacional de audit, ressync e cleanup
- [x] criar `event_person_groups`
- [x] criar `event_person_group_memberships`
- [x] criar presets e CRUD de grupos
- [x] criar stats e filtros por grupo
- [x] criar `event_coverage_targets`
- [x] criar `event_must_have_pairs`
- [x] criar `event_coverage_alerts`
- [x] criar score de cobertura por pessoa, par e grupo
- [x] criar painel `Cobertura importante`
- [ ] criar `event_relational_collections`
- [ ] criar `event_relational_collection_items`
- [ ] criar recipe engine de colecoes relacionais
- [ ] criar entregas prontas por vinculo
- [ ] preparar trilha guest-facing derivada

Status em `2026-04-12`:

- `Mapa de relacoes` complementar em `React Flow` ja foi entregue em `EventPeoplePage`, com endpoint proprio `GET /people/graph`;
- `groups` e `coverage_targets` ja nascem como seeds do `Modelo do evento`;
- grupos reais agora existem com:
  - `GET/POST/PATCH/DELETE /people/groups`
  - memberships por grupo
  - aplicacao de seeds do modelo por evento
  - painel local em `EventPeoplePage`
- read models dedicados de grupo foram adicionados (`event_person_group_stats`, `event_person_group_media_stats`);
- dominio de coverage foi entregue com targets, alerts e painel local;
- momentos e colecoes relacionais continuam pendentes.

---

## Conclusao

O backlog pendente do `EventPeople` agora se divide em dois tipos de trabalho:

1. endurecimento operacional da AWS e do backend;
2. ampliacao do produto para grupo, cobertura, momentos e entrega.

Os dois importam, mas a ordem correta agora fica:

- primeiro congelar contrato operacional, performance e cockpit;
- depois limpar governanca e retention;
- depois subir grupos;
- depois medir cobertura;
- depois transformar isso em momentos e entregas.

Em uma frase:

**a base de pessoas ja existe; agora o produto precisa ficar seguro para operar e forte para entregar memoria por vinculo.**
