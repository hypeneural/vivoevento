# Event people identity and relations strategy - 2026-04-10

## Objetivo

Este documento consolida a melhor abordagem para evoluir o `FaceSearch` atual para uma feature mais rica de:

- pessoas do evento;
- relacao pessoa <-> foto;
- relacoes entre pessoas;
- busca por nome e contexto social;
- UX guiada para operador nao tecnico;
- uso de AWS Rekognition sem custo escondido desnecessario.

O foco aqui nao e "arvore genealogica" como modelagem principal.

O foco correto e uma camada de **identidade e relacoes do evento** em cima da trilha tecnica de `FaceSearch`.

---

## Veredito executivo

### Decisao principal

O proximo passo do produto nao deve ser construir uma "arvore genealogica de fotos".

O proximo passo deve ser criar uma camada canonica de:

1. `Pessoa do evento`
2. `vinculo entre rosto detectado e pessoa`
3. `relacao declarada entre pessoas`
4. `coocorrencia inferida entre pessoas`

A arvore familiar vira apenas uma visualizacao especializada dentro disso.

### Motivo

Casamento, corporativo, show e social nao sao naturalmente uma arvore.

Eles sao um **grafo**:

- noiva <-> noivo
- mae da noiva -> noiva
- fotografo -> evento
- cerimonialista -> evento
- amigo do noivo <-> noivo
- padrinho <-> casal
- palestrante <-> patrocinador
- artista <-> backstage

Se a modelagem nascer como grafo, a experiencia familiar cabe dentro dela.

Se a modelagem nascer como arvore, o resto do produto fica torto.

---

## O que a documentacao oficial da AWS valida

As referencias abaixo foram revisadas na documentacao oficial da AWS em `2026-04-10`.

### 1. Busca por imagem nao resolve foto de grupo inteira sozinha

`SearchFacesByImage` e `SearchUsersByImage` trabalham a partir da **maior face detectada** na imagem.

Implicacao pratica:

- sao muito boas para selfie e busca pontual;
- nao sao estrutura principal para entender uma foto com 4 pessoas;
- nao substituem uma camada local de pessoas, rostos e relacoes.

Fontes oficiais:

- `SearchFacesByImage`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchFacesByImage.html
- `SearchUsersByImage`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchUsersByImage.html

Pontos relevantes da AWS:

- a busca usa a maior face da imagem;
- outras faces podem voltar em `UnsearchedFaces`;
- para pesquisar todas as faces da imagem, a propria AWS recomenda detectar/cropar antes.

### 2. A AWS suporta fotos com varias pessoas no nivel tecnico

`DetectFaces` detecta as `100` maiores faces da imagem.

`IndexFaces` tambem pode indexar ate as `100` maiores faces, com `MaxFaces` e `QualityFilter`.

Implicacao pratica:

- foto de casamento com varias pessoas e suportada tecnicamente;
- o que falta nao e capacidade da AWS;
- o que falta e a camada de produto que organiza quem e quem dentro da foto.

Fontes oficiais:

- `DetectFaces`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_DetectFaces.html
- `IndexFaces`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_IndexFaces.html

Pontos relevantes da AWS:

- `DetectFaces` retorna bounding box, confidence, pose, quality e landmarks;
- `IndexFaces` retorna `UnindexedFaces` com motivos como face pequena, blur, imagem escura, pose extrema e pouco detalhe;
- `IndexFaces` ordena faces por tamanho do bounding box, da maior para a menor.

### 3. `Users` ajuda muito para identidade, mas nao deve ser a verdade do produto

A AWS oferece `CreateUser`, `AssociateFaces` e `SearchUsersByImage`.

Isso e excelente para consolidar varias faces da mesma pessoa.

Mas existe um limite importante:

- cada `UserID` aceita no maximo `100` faces associadas.

Implicacao pratica:

- `AWS User` deve ser acelerador de matching;
- `EventPerson` no nosso banco deve ser a identidade canonica;
- nao devemos depender da AWS como sistema de dominio.

Fontes oficiais:

- `CreateUser`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_CreateUser.html
- `AssociateFaces`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_AssociateFaces.html
- `SearchUsersByImage`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchUsersByImage.html

Pontos relevantes da AWS:

- `CreateUser` usa token idempotente;
- `AssociateFaces` aceita ate `100` `FaceIds` por request;
- o total de faces associadas a um `UserID` tambem e limitado a `100`;
- `UserStatus` pode ficar em `CREATED`, `CREATING`, `UPDATING` e `ACTIVE`;
- associacoes malsucedidas retornam razoes especificas;
- `UserMatchThreshold` default de associacao e `75`;
- `SearchUsersByImage` usa `UserMatchThreshold` default `80`.

### 4. A propria AWS recomenda review humano e varias imagens por pessoa

Na documentacao da AWS:

- para cenarios de photo sharing/social media, a orientacao e indexar fotos com varias pessoas e identificar cada pessoa para agrupar as fotos por presenca;
- em uso geral, a recomendacao e indexar varias imagens da mesma pessoa e associar essas faces a um user;
- a AWS recomenda processo de review para corrigir falhas de match e melhorar buscas futuras.

Fonte oficial:

- `Guidance for indexing faces in common scenarios`: https://docs.aws.amazon.com/rekognition/latest/dg/guidance-index-faces.html

Isso conversa diretamente com o nosso produto:

- IA sozinha nao fecha o problema;
- o produto precisa de confirmacao guiada;
- review manual nao e "fallback ruim", e parte oficial do desenho correto.

### 5. A qualidade da imagem impacta diretamente custo util e recall

A AWS recomenda:

- imagens nitidas e claras;
- face ocupando proporcao grande da imagem;
- yaw abaixo de `45` graus;
- pitch abaixo de `30` para baixo e `45` para cima;
- rosto sem obstrucao;
- evitar crop apertado demais no rosto para imagem de referencia.

Fonte oficial:

- `Recommendations for facial comparison input images`: https://docs.aws.amazon.com/rekognition/latest/dg/recommendations-facial-input-images.html

Implicacao pratica:

- precisamos continuar usando gate de qualidade e crop-first na indexacao tecnica;
- mas para cadastro de pessoas importantes do evento vale guardar tambem uma foto ancora boa, nao apenas qualquer frame detectado.

### 6. Custos escondidos existem e sao principalmente de volume, repeticao e armazenamento

A pagina oficial de pricing confirma:

- Group 1: `AssociateFaces`, `CompareFaces`, `DisassociateFaces`, `IndexFaces`, `SearchFacesByImage`, `SearchFaces`, `SearchUsersByImage`, `SearchUsers`;
- Group 2: `DetectFaces`, `DetectModerationLabels`, `DetectLabels`, `DetectText`, etc;
- executar varias APIs na mesma imagem conta como varias imagens processadas;
- ha cobranca mensal de armazenamento de `face vectors` e `user vectors`, pro-rata;
- o custo nao esta so no request de busca; ele cresce com `IndexFaces + AssociateFaces + SearchUsersByImage + storage`.

Fonte oficial:

- pricing: https://aws.amazon.com/rekognition/pricing/

Implicacao pratica:

- filtro por nome de pessoa nao pode chamar AWS;
- navegacao do catalogo nao pode depender de AWS;
- coocorrencia e relacoes precisam ser locais;
- a AWS deve ser usada para ingestao, bootstrap e selfie search, nao para cada interacao do backoffice.

### 7. Existem limites e quotas que afetam desenho de produto

A documentacao oficial de quotas confirma:

- bytes em request: max `5 MB`;
- imagem via S3: max `15 MB`;
- formatos: `PNG` e `JPEG`;
- face precisa ser pelo menos `40x40` em imagem `1920x1080`, proporcionalmente maior em resolucao maior;
- colecao suporta ate `20M` face vectors;
- default de `10M` user vectors por collection;
- search retorna ate `4096` matches;
- stored video suporta ate `10 GB`, ate `6` horas e `20` jobs concorrentes por conta;
- AWS recomenda suavizar trafego, retries, exponential backoff e jitter.

Fonte oficial:

- quotas: https://docs.aws.amazon.com/rekognition/latest/dg/limits.html

Implicacao pratica:

- reindex/backfill em burst precisa continuar em fila;
- traffic shaping nao e opcional;
- qualquer feature interativa nova deve consumir read models locais, nao fazer fan-out de chamadas AWS.

### 8. Video e outra trilha, nao deve contaminar o MVP de pessoas

Para video armazenado em S3, a AWS oferece `StartFaceSearch` assincrono, com `SNS` para completion e `GetFaceSearch` para leitura.

Fonte oficial:

- `StartFaceSearch`: https://docs.aws.amazon.com/rekognition/latest/APIReference/API_StartFaceSearch.html

Implicacao pratica:

- MVP de pessoas/rede deve nascer em imagem;
- video pode entrar depois como enriquecimento assincrono;
- misturar video no primeiro corte aumenta custo, complexidade operacional e latencia de reconciliacao.

### 9. Monitoramento nativo da AWS precisa entrar no desenho operacional

O Amazon Rekognition publica metricas no CloudWatch por operacao.

Fonte oficial:

- `Monitoring Rekognition with Amazon CloudWatch`: https://docs.aws.amazon.com/rekognition/latest/dg/rekognition-monitoring.html

Pontos relevantes:

- `ResponseTime` permite acompanhar latencia por operacao;
- `ThrottledCount` mostra pressao de quota e burst ruim;
- `UserErrorCount` ajuda a separar problema funcional de problema transitivo;
- `SuccessfulRequestCount` por operacao ajuda a medir volume real de `IndexFaces`, `SearchUsersByImage` e afins.

Implicacao pratica:

- o plano precisa prever alarmes e paineis desde cedo;
- custo e throughput nao podem ser medidos so pelo nosso log local;
- a trilha `EventPeople` precisa nascer com telemetria cruzada entre app, Horizon e CloudWatch.

---

## O que a documentacao oficial do resto da stack valida

As referencias abaixo tambem foram revisadas na documentacao oficial em `2026-04-10`.

### 1. Laravel queues exigem contrato assincrono explicito logo no inicio

O Laravel documenta:

- `after_commit` e `afterCommit()` para evitar job correndo antes do commit;
- `WithoutOverlapping` para proteger recalculos concorrentes;
- `RateLimited` para segurar burst;
- `ShouldBeUnique` para rebuilds e backfills amplos;
- `ShouldBeEncrypted` para jobs com payload sensivel.

Fonte oficial:

- `Laravel Queues`: https://laravel.com/docs/12.x/queues

Implicacao pratica:

- confirmacao humana deve gravar local primeiro, projetar depois e sincronizar AWS por fora;
- job de pessoa/revisao nao pode sair de transacao incompleta;
- jobs com dados de rosto/pessoa devem ser criptografados;
- rebuilds amplos e sincronizacoes repetitivas devem ser unicos e rate-limited.

### 2. Horizon ja oferece as alavancas que a V1 precisa

O Horizon documenta:

- estrategias de balancing por supervisor;
- `tags` para rastrear grupos de jobs;
- `wait time thresholds`;
- `horizon:snapshot` para metricas;
- supervisors separados por fila.

Fonte oficial:

- `Laravel Horizon`: https://laravel.com/docs/12.x/horizon

Implicacao pratica:

- observabilidade nao deve ficar para uma fase tardia;
- `EventPeople` deve nascer com filas dedicadas, tags por `event_id` e thresholds de espera;
- jobs barulhentos ou de backfill podem ser silenciados no painel sem esconder a trilha critica.

### 3. React pede regras explicitas de responsividade percebida

O React documenta:

- `useDeferredValue` para manter partes nao criticas da UI atrasadas sem travar o input;
- `useTransition` para updates nao urgentes, como trocar aba, filtro ou painel;
- `Transition` nao pode controlar input de texto;
- o trabalho apos `await` precisa ser remarcado com `startTransition`;
- `Suspense` para data fetching ainda depende de fonte de dados realmente compativel.

Fontes oficiais:

- `useDeferredValue`: https://react.dev/reference/react/useDeferredValue
- `useTransition`: https://react.dev/reference/react/useTransition
- `Suspense`: https://react.dev/reference/react/Suspense

Implicacao pratica:

- o input de busca/autocomplete deve ficar isolado em subcomponentes;
- review queue, filtro `X + Y` e detalhe da pessoa devem usar transicoes nao bloqueantes;
- a V1 nao deve nascer `Suspense-first` para fetching administrativo comum.

### 4. PostgreSQL valida a decisao de usar tabelas projetadas quentes e indices parciais disciplinados

O PostgreSQL documenta:

- materialized views persistem resultado em formato de tabela;
- partial indexes reduzem custo de update quando focam no subconjunto quente;
- o planner so usa bem partial index quando o predicado casa claramente com o `WHERE`;
- partial unique indexes permitem unicidade apenas sobre o subconjunto relevante.

Fontes oficiais:

- `Materialized Views`: https://www.postgresql.org/docs/current/rules-materializedviews.html
- `Partial Indexes`: https://www.postgresql.org/docs/current/indexes-partial.html

Implicacao pratica:

- a V1 quente deve usar tabelas projetadas incrementais;
- materialized views ficam reservadas para agregados mais pesados;
- indices parciais precisam ser escritos junto com queries previsiveis, nao de forma generica;
- unicidade parcial e a forma certa de garantir um unico `confirmed` por rosto sem travar historico.

## Validacao da nossa stack atual

## O que ja temos pronto

### Backend

Base tecnica relevante ja existente:

- `EventMediaFace` guarda cada rosto detectado por midia, com `bbox`, qualidade e flag `searchable`.
- `FaceSearchProviderRecord` guarda o espelho tecnico do provider, inclusive AWS.
- `FaceSearchRouter` ja faz roteamento entre backends.
- `AwsRekognitionFaceSearchBackend` ja suporta `faces`, `users`, fallback e shadow.
- `SelfiePreflightService` ja garante selfie com uma pessoa dominante e rejeita foto de grupo no fluxo atual.
- `EventFaceSearchOperationalSummaryService` ja resume o estado operacional do evento.

Arquivos chave:

- `apps/api/app/Modules/FaceSearch/Models/EventMediaFace.php`
- `apps/api/app/Modules/FaceSearch/Models/FaceSearchProviderRecord.php`
- `apps/api/app/Modules/FaceSearch/Actions/SearchFacesBySelfieAction.php`
- `apps/api/app/Modules/FaceSearch/Services/SelfiePreflightService.php`
- `apps/api/app/Modules/FaceSearch/Services/AwsRekognitionFaceSearchBackend.php`
- `apps/api/app/Modules/FaceSearch/Services/AwsUserVectorReadinessService.php`

### Frontend

Superficies ja existentes:

- toggle simples no CRUD do evento para ativar reconhecimento facial;
- card operacional detalhado no detalhe do evento;
- busca interna por selfie no detalhe do evento;
- busca publica por selfie;
- CTA publico em hub e galeria;
- busca por pessoa por foto na pagina `/media`.

Arquivos chave:

- `apps/web/src/modules/events/components/EventEditorPage.tsx`
- `apps/web/src/modules/events/EventDetailPage.tsx`
- `apps/web/src/modules/events/components/face-search/EventFaceSearchSettingsCard.tsx`
- `apps/web/src/modules/face-search/components/FaceSearchSearchPanel.tsx`
- `apps/web/src/modules/face-search/PublicFaceSearchPage.tsx`
- `apps/web/src/modules/gallery/PublicGalleryPage.tsx`
- `apps/web/src/modules/hub/PublicHubPage.tsx`
- `apps/web/src/modules/media/MediaPage.tsx`

### Leitura funcional da stack atual

Hoje o produto resolve muito bem:

- preparar acervo para reconhecimento facial;
- buscar fotos a partir de selfie;
- operar fallback/shadow entre AWS e local;
- distinguir uso interno vs publico;
- manter observabilidade basica e resumo operacional.

Isso significa que a camada tecnica de reconhecimento esta madura o suficiente para receber uma camada de dominio acima dela.

## O que ainda nao existe

Ainda nao existe na stack:

- entidade canonica de `Pessoa do evento`;
- relacao explicita `rosto -> pessoa`;
- relacao `pessoa -> pessoa`;
- inbox de sugestoes para merge/split/confirm;
- overlay operacional para nomear varios rostos dentro da mesma foto;
- filtro estruturado por pessoa cadastrada no catalogo;
- modelo de coocorrencia entre pessoas;
- visualizacao de rede social/familiar do evento.

Conclusao pratica:

- o gargalo agora nao e deteccao;
- o gargalo e modelagem de dominio e UX operacional.

---

## Validacao de mercado e posicionamento

As paginas oficiais de players do mercado confirmam que varias capacidades ja viraram expectativa basica:

- `GUESTPIX` enfatiza QR privado, galeria privada, upload sem app/registro e slideshow ao vivo;
- `Kululu` enfatiza QR/link, album digital e photo wall ao vivo;
- `Waldo` enfatiza entrega com IA e face-tagging;
- `Memzo` enfatiza selfie search para o convidado encontrar as proprias fotos rapidamente.

Fontes oficiais:

- `GUESTPIX`: https://guestpix.com/engagements-how-it-works/
- `Kululu`: https://www.kululu.com/wedding-photo-sharing-app
- `Waldo`: https://waldophotos.com/memorial/
- `Memzo`: https://memzo.ai/web/faq.php

Leitura de produto:

- QR sem app;
- galeria privada;
- slideshow ao vivo;
- upload simples;
- selfie search;
- IA para encontrar fotos

ja nao bastam, sozinhos, para diferenciar forte o produto em eventos sociais.

### Implicacao pratica para o nosso norte

`EventPeople` nao deve ser vendido apenas como "organizar pessoas" ou "temos face search tambem".

O norte que realmente faz sentido colocar na doc e no plano e:

- **orquestrar memorias por relacao**;
- **medir cobertura das pessoas e momentos que realmente importam**;
- **entregar galerias e colecoes por vinculo emocional, nao so por match facial**.

Em outras palavras:

- pessoa e a base;
- relacao e o contexto;
- cobertura e o valor operacional;
- momentos e entregas sao o diferencial comercial.

---

## Onde a logica ainda pode melhorar

## 1. Separar completamente fluxo de escrita e fluxo de leitura

Essa e a melhoria estrutural mais importante.

Regra recomendada:

### AWS so entra para

- deteccao e indexacao inicial;
- selfie search;
- sugestao de identidade;
- sync assincrono de faces representativas;
- video search futuro, se entrar depois.

### Banco local entra para

- filtro por nome;
- abrir pessoa;
- fotos com `X + Y`;
- relacoes;
- coocorrencia;
- dashboard operacional;
- autocomplete;
- contadores e badges;
- inbox de revisao.

Motivo:

- reduz latencia externa nas telas;
- elimina custo oculto de chamadas repetidas;
- preserva UX dinamica;
- encaixa no limite da AWS, que nao resolve foto de grupo inteira via `SearchFacesByImage` ou `SearchUsersByImage`.

### Decisao de arquitetura

`FaceSearch` vira pipeline de escrita e matching.

`EventPeople` vira camada de leitura e dominio.

## 2. Trocar "cadastro manual primeiro" por "confirmacao guiada primeiro"

A friccao de "cadastrar pessoas" antes de o usuario enxergar valor e alta demais.

O fluxo melhor e:

1. o sistema detecta faces;
2. o sistema sugere agrupamentos;
3. a interface abre uma caixa de revisao;
4. o operador so confirma, corrige, mescla, separa ou nomeia.

Ou seja:

- `EventPerson` nao deve nascer principalmente de um formulario vazio;
- `EventPerson` deve nascer, preferencialmente, na primeira confirmacao util.

### Acao principal recomendada

Em vez de:

- `Cadastrar pessoa`

usar:

- `Quem e esta pessoa?`

Com opcoes:

- escolher pessoa existente;
- criar nova pessoa com um clique;
- marcar como irrelevante;
- ignorar por enquanto.

Essa mudanca reduz muito a sensacao de trabalho administrativo.

## 3. Criar read models locais desde o inicio

As tabelas transacionais de dominio sao necessarias, mas nao suficientes para a UX.

Além de:

- `event_people`
- `event_person_face_assignments`
- `event_person_relations`
- `event_person_cooccurrences`

vale criar desde o inicio:

- `event_person_media_stats`
- `event_person_pair_scores`
- `event_person_review_queue`
- `event_person_representative_faces`
- `event_person_name_search`

### Por que isso faz sentido no nosso stack

O PostgreSQL documenta materialized views como resultados persistidos em formato de tabela, mas tambem deixa claro que partial indexes so ajudam quando o predicado do indice combina de forma clara com o `WHERE` real da query.

Fontes oficiais:

- PostgreSQL materialized views: https://www.postgresql.org/docs/current/rules-materializedviews.html
- PostgreSQL partial indexes: https://www.postgresql.org/docs/current/indexes-partial.html

Implicacao pratica:

- a fila quente da V1 nao deve depender de `REFRESH MATERIALIZED VIEW` como mecanismo principal;
- `event_person_review_queue`, `event_person_media_stats` e `event_person_name_search` devem nascer como tabelas projetadas incrementais;
- materialized views ficam para agregados tardios e mais pesados;
- os partial indexes da V1 precisam casar com `WHERE status = 'pending'`, `WHERE status = 'confirmed'` e afins, sem predicates vagos.

## 4. Manter dois conjuntos de faces por pessoa

Essa e uma decisao de custo-beneficio muito forte.

### Conjunto A - local completo

Contem:

- todas as faces confirmadas da pessoa no banco local.

Uso:

- historico;
- analytics;
- coocorrencia;
- filtros;
- revisao;
- cobertura editorial.

### Conjunto B - representativo para AWS

Contem:

- apenas as melhores faces para matching.

Uso:

- `AssociateFaces`;
- `SearchUsersByImage`;
- consolidacao de identidade remota.

### Motivo

A AWS recomenda varias imagens da mesma pessoa para melhorar busca em collection e sugere pelo menos cinco imagens com diversidade de pose.

Fonte oficial:

- recommendations for searching faces in a collection: https://docs.aws.amazon.com/rekognition/latest/dg/recommendations-facial-input-images-search.html

Como existe limite de `100` faces por `UserID`, nao vale mandar tudo.

Faixa recomendada para o produto:

- `5` a `15` faces representativas por pessoa no primeiro corte;
- diversidade de pose, iluminacao e distancia;
- descartar duplicatas muito parecidas;
- priorizar qualidade, nitidez e frontalidade.

## 5. Nao usar `AssociateFaces` nem `SearchUsersByImage` no microfluxo

O microfluxo humano nao deve chamar AWS em tempo real para cada interacao.

### Nao fazer

- abriu uma foto -> chama AWS;
- corrigiu um rosto -> chama AWS;
- abriu pessoa -> chama AWS;
- digitou nome -> chama AWS.

### Fazer

- salva local imediatamente;
- UI atualiza na hora;
- sync com AWS vai para fila;
- refresh de read model vai para fila;
- erro de sync nao bloqueia o operador.

Isso e coerente com o pricing da AWS:

- varias APIs na mesma imagem contam como varias imagens processadas;
- Group 1 e storage crescem rapido se o produto tratar AWS como backend de navegacao.

## 6. Aplicar gate mais forte antes de indexar

A documentacao oficial da AWS deixa claro que qualidade da face impacta a utilidade da indexacao e da busca.

Isso significa endurecer a ingestao para o conjunto representativo:

- nao indexar face minuscula;
- nao indexar blur alto;
- nao indexar face com pose extrema para representatives;
- nao indexar duplicatas quase identicas da mesma sequencia;
- nao indexar tudo que passou so porque "deu para detectar".

Essa melhoria reduz:

- custo de `IndexFaces`;
- custo de storage;
- ruido nas sugestoes;
- necessidade de correcao manual.

## 7. Processar tudo que e pesado em fila, com prioridade

O Laravel documenta explicitamente:

- despacho em filas especificas com `onQueue`;
- workers processando filas especificas;
- prioridades de fila;
- jobs apos commit (`after_commit` / `afterCommit`);
- middleware para `RateLimited` e `WithoutOverlapping`.

Fonte oficial:

- Laravel queues: https://laravel.com/docs/12.x/queues

### Leitura da nossa stack atual

O repositorio ja tem base boa para isso:

- `apps/api/config/queue.php` ja usa `after_commit=true` na conexao `redis`;
- `BackfillEventFaceSearchGalleryJob` ja entra em `face-index`;
- `apps/api/config/horizon.php` ja monitora `redis:face-index` e outras filas quentes.

### Filas recomendadas para EventPeople

Fila alta:

- salvar confirmacao manual;
- atualizar review queue minima;
- atualizar contadores minimos da pessoa.

Fila media:

- gerar sugestoes de agrupamento;
- recalcular pares da pessoa afetada;
- recalcular coocorrencia local dos deltas impactados.

Fila baixa:

- sync com AWS;
- refresh de materialized views pesadas;
- limpeza e retencao;
- backfill incremental.

### Middleware recomendados

- `afterCommit` para jobs disparados por confirmacao manual;
- `WithoutOverlapping` por `event_id` ou `event_person_id` em recalculos sensiveis;
- `RateLimited` para sync AWS quando houver burst.
- `ShouldBeUnique` para rebuilds, replays e backfills amplos;
- `ShouldBeEncrypted` para jobs com payload sensivel de pessoa/rosto.

### Leitura objetiva da configuracao atual

Validacao local do repositorio:

- `apps/api/config/queue.php` ja deixa `after_commit=true` na conexao `redis`;
- outras conexoes continuam com `after_commit=false`;
- `apps/api/routes/console.php` ja agenda `horizon:snapshot`;
- `apps/api/config/horizon.php` ja tem `waits`, `silenced_tags` e supervisors dedicados, inclusive para `face-index`.

Implicacao pratica:

- `EventPeople` deve usar `redis` como trilha principal de fila;
- se algum job precisar de outra conexao, deve chamar `afterCommit()` explicitamente;
- tags por `event_id` e `event_person_id` precisam nascer junto com os jobs, nao depois.

## 8. Observabilidade precisa entrar na fase 0 e na fase 1

Nao faz sentido deixar observabilidade para o fim.

Horizon, CloudWatch e os logs da aplicacao precisam nascer juntos com a feature.

Camadas minimas:

- tags de fila por `event_id`, `event_person_id` e `job_kind`;
- thresholds de espera por fila quente;
- metricas AWS por operacao;
- contadores de review queue;
- logs estruturados de confirmacao, merge, split, sync e falha.

Sem isso, o time vai descobrir gargalo depois da UX pronta, quando o custo de ajuste ja e maior.

## 9. Mudar a unidade de trabalho da tela

A interface nao deve trabalhar com entidades tecnicas.

Ela deve trabalhar com tarefas humanas.

Entradas principais recomendadas:

- `Organizar pessoas`
- `Confirmar quem aparece`
- `Relacionar familiares`
- `Ver fotos dessa pessoa`
- `Quem aparece junto com quem`

Em vez de:

- criar person;
- associar face;
- criar relation record.

## 10. Criar uma caixa de revisao priorizada

Nao mostrar tudo ao mesmo tempo.

Mostrar primeiro o que gera mais valor:

1. pessoas mais frequentes ainda sem nome;
2. rostos com alta confianca de agrupamento;
3. possiveis pessoas importantes do preset do evento;
4. conflitos de identidade;
5. relacoes sugeridas por coocorrencia.

Para casamento, a ordem recomendada e:

- noiva;
- noivo;
- pais;
- padrinhos;
- fotografo e cerimonial;
- convidados recorrentes.

## 11. Usar presets prontos por tipo de evento

Esse e um ganho de UX quase sem custo.

Quando o evento nasce, o sistema ja pode sugerir papeis provaveis:

- casamento: noiva, noivo, pais, padrinhos, fotografo, cerimonial;
- corporativo: host, speaker, executivo, equipe, patrocinador, imprensa;
- show: artista, banda, producao, equipe tecnica, patrocinador.

O usuario nao modela o mundo do zero.

Ele so adapta o que o produto ja trouxe pronto.

## 12. Relacao declarada e coocorrencia inferida nao podem se misturar

Na interface, isso deve ficar separado visualmente.

### Relacoes confirmadas

Exemplos:

- `mae de`
- `noivo de`
- `fotografo do evento`

### Conexoes sugeridas

Exemplos:

- aparece muito com;
- possivel grupo recorrente;
- possivel nucleo familiar;
- possivel equipe/producao.

Se misturar esses dois niveis, o usuario perde confianca.

## 13. Atualizacao incremental, nunca recalculo global por clique

Quando uma confirmacao acontece:

- atualiza so a pessoa impactada;
- atualiza so os pares impactados;
- atualiza so os contadores necessarios;
- adia recalculos amplos.

Nada de recalcular coocorrencia do evento inteiro a cada clique.

Estrategia recomendada:

- deltas por `event_media_id`;
- deltas por `event_person_id`;
- jobs idempotentes;
- refresh pesado por lote, nao por clique.

## 14. Coocorrencia sempre por evento

Toda relacao inferida deve nascer em `event_id`.

Isso evita contaminar:

- casamento A;
- aniversario B;
- corporativo C;

com historico cruzado.

Pessoa, rede, relevancia e cobertura devem ser do evento.

## 15. Responsividade percebida do front precisa virar criterio de aceite

O frontend dessa feature nao pode se limitar a "carregou ou nao carregou".

Regras explicitas:

- input controlado de busca/autocomplete fica isolado em subcomponente;
- `useDeferredValue` entra quando a lista ou o autocomplete dependem do texto digitado;
- `useTransition` entra em troca de aba, filtro composto, ordenacao e abertura de painel;
- nao usar `useTransition` para controlar o input em si;
- nao desenhar a V1 em cima de Suspense para data fetching administrativo comum.

Resultado esperado:

- digitar nome de pessoa continua fluido mesmo com lista grande;
- review queue nao trava ao confirmar e avancar;
- filtros `X + Y` parecem instantaneos porque consomem read model local.

## 16. Indices parciais e normalizacao de pares precisam ser tratados como parte do dominio

Essa melhoria nao e detalhe de banco. Ela impacta consistencia.

Regras recomendadas:

- partial unique index para permitir no maximo um `confirmed` por `event_media_face_id`;
- indice parcial da review queue so para itens ativos e pendentes;
- chave de par normalizada para evitar duplicidade logica `A-B` vs `B-A`;
- indice proprio para nome normalizado e alias, em vez de depender so de `ILIKE` na tabela principal.

Sem isso:

- a fila fica cara;
- o planner pode ignorar o indice esperado;
- a mesma conexao aparece duplicada em scores e coocorrencia.

## 17. Governanca de regiao, retention e privacidade precisa estar explicita

Como quotas, throughput e metricas Rekognition sao region-scoped, a operacao precisa assumir uma estrategia clara de regiao por evento ou tenant.

Tambem e necessario explicitar:

- politica de retention para collections, face vectors e user vectors;
- limpeza de eventos encerrados;
- revisao do opt-out organizacional aplicavel aos servicos de IA usados;
- consentimento claro para busca facial no onboarding do evento e nas superficies publicas.

Isso nao e detalhe juridico separado da engenharia.

Sem essa governanca:

- custo acumulado vira surpresa;
- colecoes antigas ficam esquecidas;
- o produto cresce sem trilha clara de privacidade e retencao.

---

## Arquitetura recomendada

## Regra de ouro

`Foto` nao e `Pessoa`.

`Rosto detectado` nao e `Pessoa`.

`Match AWS` nao e `Pessoa`.

`Pessoa do evento` deve ser uma entidade propria do dominio.

## Camada 1 - tecnica

Mantem o que ja existe:

- `EventMedia`
- `EventMediaFace`
- `FaceSearchProviderRecord`
- `FaceSearchQuery`
- `EventFaceSearchRequest`

Essa camada continua tecnica e provider-aware.

## Camada 2 - pessoa canonica

Criar `EventPeople` como modulo novo.

Tabela sugerida: `event_people`

Campos iniciais:

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

Valores sugeridos para `type`:

- `bride`
- `groom`
- `mother`
- `father`
- `sibling`
- `guest`
- `friend`
- `groomsman`
- `bridesmaid`
- `vendor`
- `staff`
- `speaker`
- `artist`
- `executive`

Valores sugeridos para `side`:

- `bride_side`
- `groom_side`
- `host_side`
- `company_side`
- `neutral`

## Camada 3 - atribuicao rosto <-> pessoa

Tabela sugerida: `event_person_face_assignments`

Campos:

- `id`
- `event_person_id`
- `event_media_face_id`
- `source`
- `confidence`
- `status`
- `reviewed_by`
- `reviewed_at`
- `created_at`
- `updated_at`

Valores sugeridos para `source`:

- `aws_match`
- `cluster_suggestion`
- `manual_confirmed`
- `manual_corrected`
- `imported`

Valores sugeridos para `status`:

- `suggested`
- `confirmed`
- `rejected`

Essa tabela separa claramente:

- o que a IA sugeriu;
- o que o operador confirmou;
- o que foi corrigido manualmente.

## Camada 4 - relacoes declaradas

Tabela sugerida: `event_person_relations`

Campos:

- `id`
- `event_id`
- `person_a_id`
- `person_b_id`
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

Valores sugeridos para `relation_type` em casamento:

- `spouse_of`
- `mother_of`
- `father_of`
- `sibling_of`
- `child_of`
- `godparent_of`
- `friend_of`
- `vendor_of_event`
- `ceremonialist_of_event`
- `photographer_of_event`

## Camada 5 - coocorrencia inferida

Tabela sugerida: `event_person_cooccurrences`

Campos:

- `id`
- `event_id`
- `person_a_id`
- `person_b_id`
- `co_photo_count`
- `solo_photo_count_a`
- `solo_photo_count_b`
- `average_face_distance`
- `weighted_score`
- `last_seen_together_at`
- `created_at`
- `updated_at`

Essa tabela nao representa parentesco.

Ela representa proximidade visual/social inferida.

Esse desacoplamento e obrigatorio para o produto nao inventar familia onde so existe proximidade de pista, palco ou mesa.

## Camada 6 - grupos sociais do evento

Pares sao importantes, mas evento social real tambem gira em torno de nucleos.

Tabelas futuras recomendadas:

- `event_person_groups`
- `event_person_group_memberships`

Exemplos de grupos:

- familia da noiva;
- familia do noivo;
- padrinhos;
- madrinhas;
- amigos da faculdade;
- equipe do buffet;
- banda;
- mesa VIP.

Valor desbloqueado:

- filtros por nucleo;
- albuns por grupo;
- cobertura por tribo social;
- curadoria de slideshow por grupo;
- entregas por grupo, e nao so por individuo.

## Camada 7 - coverage intelligence

Esse e o ponto de maior valor para social e casamento.

Tabelas futuras recomendadas:

- `event_coverage_targets`
- `event_coverage_alerts`
- `event_must_have_pairs`

Exemplos:

- noiva + pai;
- noiva + mae;
- casal + padrinhos;
- aniversariante + avos;
- debutante + amigas.

Essa camada transforma o produto de organizador em assistente de cobertura:

- "faltam fotos fortes de X + Y";
- "ha poucas fotos boas da mae da noiva";
- "o casal com padrinhos ja tem cobertura suficiente".

## Camada 8 - momentos e entregas por relacao

Depois de pessoa, relacao, grupo e cobertura, faz sentido criar uma camada de entrega emocional.

Exemplos:

- melhores fotos da noiva com cada nucleo da familia;
- fotos do aniversariante com cada grupo importante;
- fotos do convidado com o casal;
- entregas automaticas por grupo ou relacao confirmada.

Essa camada e o que mais diferencia o produto comercialmente.

---

## UX recomendada

## 1. Pessoas do evento

Tela principal de pessoas:

- cards ou lista de pessoas principais;
- foto ancora;
- quantidade de fotos vinculadas;
- nivel de confirmacao;
- tipo e lado;
- status.

Entradas principais:

- `Organizar pessoas`
- `Confirmar sugestoes`
- `Ver fotos`
- `Relacionar`
- `Criar pessoa` so como acao secundaria.

## 2. Caixa de entrada de revisao

Tela operacional de sugestoes:

- "essas faces parecem ser a mesma pessoa";
- "essas faces parecem estar erradas";
- "essa pessoa pode ser a mae da noiva";
- "essas 14 fotos parecem do mesmo padrinho".

Acoes:

- `Confirmar`
- `Mesclar`
- `Separar`
- `Ignorar`
- `Criar nova pessoa`
- `Mover para pessoa existente`

Essa tela e a parte mais importante do MVP.

## 3. Tela de foto com overlay de rostos

Ao abrir uma foto com varias pessoas:

- cada bounding box vira um chip/overlay;
- o operador pode clicar no rosto;
- o sistema mostra sugestoes de pessoa;
- o operador pode confirmar ou corrigir.

Acao ideal:

- `Quem e esta pessoa?`

Opcoes:

- selecionar pessoa existente;
- criar pessoa nova;
- marcar como irrelevante;
- rejeitar sugestao.

Essa tela deve ser a superficie principal do fluxo de escrita humano.

## 4. Detalhe da pessoa

Cada pessoa precisa ter:

- galeria dela;
- fotos solo;
- fotos com outras pessoas;
- relacoes declaradas;
- pessoas que mais aparecem juntas;
- timeline opcional.

Filtros fortes:

- `fotos desta pessoa`
- `fotos desta pessoa com X`
- `fotos desta pessoa com grupo Y`
- `melhores fotos`
- `solo`
- `com familiares`
- `com fornecedores`

## 5. Rede do evento

A visualizacao de rede entra depois.

Ela deve consumir:

- `event_person_relations`
- `event_person_cooccurrences`

e nao chamar AWS.

Views sugeridas:

- `Mapa de conexoes`
- `Familia`
- `Equipe e fornecedores`
- `Quem mais aparece com quem`

## 6. Cobertura importante

Essa view nao e so operacional. Ela e comercial.

Entradas:

- `Cobertura importante`
- `Pessoas sem boa cobertura`
- `Pares obrigatorios ainda incompletos`
- `Grupos importantes sem album forte`

Saidas:

- alertas por par ou grupo;
- ranking de cobertura insuficiente;
- sugestoes de priorizacao para fotografo, cerimonial ou operador.

## 7. Modo cerimonialista

Essa feature precisa de uma entrada guiada para usuario nao tecnico.

Fluxo recomendado:

1. confirmar pessoas principais;
2. relacionar familiares e papeis importantes;
3. revisar sugestoes prioritarias;
4. acompanhar cobertura importante;
5. aprovar momentos e entregas.

CTAs mais humanos:

- `Organizar pessoas`
- `Cobertura importante`
- `Grupos do evento`
- `Momentos do evento`
- `Entregas prontas`

## 8. Guest-facing relacional

Depois da camada local estabilizada, faz sentido puxar uma trilha guest-facing mais forte.

Exemplos:

- `Veja suas fotos com o casal`
- `Veja fotos com sua familia`
- `Veja fotos do seu grupo`
- `Receba quando aparecer uma nova foto sua`

Essa trilha deve nascer em cima do dominio local e dos filtros prontos, nao como dependencia de AWS a cada clique.

---

## Como deixar interativo sem explodir custo AWS

## Principio

AWS deve entrar em:

- deteccao/indexacao inicial;
- busca por selfie;
- consolidacao de user vectors quando fizer sentido;
- enriquecimento assincrono.

AWS nao deve entrar em:

- filtro por nome;
- abrir detalhe de pessoa;
- navegar no catalogo;
- montar rede social;
- contar coocorrencia;
- revisar sugestao;
- combinar pessoa A + pessoa B.

## Estrategia de custo correta

### 1. Verdade do dominio no banco local

`EventPerson` e tabelas derivadas ficam no PostgreSQL.

Isso reduz:

- custo por interacao;
- dependencia de latencia externa;
- fragilidade operacional.

### 2. AWS como motor de matching, nao como banco do produto

`AWS User` ajuda a responder:

- "essa selfie parece com quem?"

Mas nao deve ser a resposta para:

- "quais sao as relacoes dessa pessoa?"
- "quais fotos tem noiva + mae?"
- "quem aparece mais com o noivo?"

### 3. Read models locais

Criar agregados/materializacoes:

- `event_person_media_stats`
- `event_person_pair_scores`
- `event_person_group_memberships`
- `event_person_missing_coverage`

Isso deixa o sistema:

- dinamico no front;
- barato no back;
- previsivel sob carga.

Quando fizer sentido, essas leituras podem ser servidas por materialized views ou tabelas projetadas mantidas por job.

### 4. Evitar fan-out de API calls AWS

Nao chamar:

- `SearchUsersByImage` para cada foto aberta;
- `SearchFacesByImage` para montar grupos;
- `AssociateFaces` em cada microcorrecao.

Em vez disso:

- processar sugestoes em lote;
- usar filas para sync;
- consolidar representantes por pessoa.

### 5. Respeitar o limite de 100 faces por `UserID`

Como a AWS limita `100` faces por user:

- nosso `EventPerson` pode ter muito mais aparicoes locais;
- a sync com `UserID` deve usar as melhores faces, nao todas;
- vale manter um conjunto curado de rostos representativos por pessoa.

Estrutura sugerida:

- `representative faces` para AWS;
- `all assignments` no banco local.

### 6. Reduzir armazenamento AWS inutil

Custos escondidos tipicos:

- indexar rosto ruim que nunca vai servir;
- associar faces demais ao mesmo user;
- manter collections antigas sem limpeza;
- reindexar acervo inteiro sem necessidade;
- usar Group 1 mais vezes que o necessario.

Mitigacoes:

- manter gate de qualidade;
- continuar com crop-first onde fizer sentido;
- usar `MaxFaces` e `QualityFilter` alinhados ao caso de uso;
- limpar acervos encerrados conforme politica de retention;
- reindex incremental por delta, nao full scan recorrente;
- usar `ExternalImageId` e indices locais para evitar buscas redundantes.

### 7. Processamento assincrono e traffic shaping

Como a propria AWS recomenda suavizar trafego:

- ingestao e backfill continuam em fila;
- merge/split/sync de pessoas deve ser assicrono quando tocar AWS;
- retries com backoff e jitter continuam obrigatorios;
- qualquer feature nova de people graph deve usar jobs dedicados.

Somado a isso:

- fila de sync AWS com rate limit;
- recalculo incremental local por prioridade;
- refresh pesado fora do request-response.

---

## Observabilidade minima recomendada

Para a feature nao virar uma caixa-preta, vale separar telemetria em quatro camadas:

### 1. Pipeline tecnico

- `faces_detected`
- `faces_indexed`
- `faces_skipped`
- `faces_rejected_by_quality`
- `aws_sync_pending`
- `aws_sync_failed`

### 2. Inbox operacional

- `suggestions_pending`
- `suggestions_confirmed`
- `suggestions_rejected`
- `merge_actions`
- `split_actions`

### 3. Dominio

- `people_named`
- `people_without_name`
- `photos_with_identified_people`
- `important_people_without_good_photos`

### 4. UX

- tempo para primeira confirmacao;
- pessoas confirmadas por sessao;
- taxa de abandono da revisao;
- consultas por nome local;
- percentual de busca que dependeu de selfie.

### 5. Infra operacional

- tempo medio de espera por fila `event-people-high`;
- backlog por fila dedicada;
- falhas por middleware de overlap ou rate limit;
- quantidade de retries AWS por operacao;
- diferenca entre erro funcional e erro transitivo.

Na AWS, continua valendo integrar o necessario para:

- erros de throttling;
- falhas transitorias;
- operacoes de collection;
- latencia por operacao critica.

No backend Laravel, vale expor:

- tags Horizon por evento;
- tempo de fila acima do threshold;
- jobs lentos e jobs silenciados por categoria de baixa relevancia.

---

## Melhor fluxo de produto para usuario leigo

## No cadastro do evento

Continuar simples:

- `Ativar reconhecimento facial`
- `Liberar para convidados`

Nao expor linguagem de collection, user vector ou backend.

## Depois do evento ligado

Mostrar um bloco simples:

- `Reconhecimento facial ligado`
- `Fotos sendo preparadas`
- `Pessoas ja reconhecidas`
- `Sugestoes para revisar`

CTA principal:

- `Organizar pessoas do evento`

## Dentro da feature de pessoas

Entradas mais humanas:

- `Quem e esta pessoa?`
- `Organizar pessoas`
- `Confirmar quem aparece`
- `Adicionar familiar`
- `Adicionar fornecedor`
- `Confirmar quem aparece nas fotos`

Nao usar texto tecnico como:

- `cluster`
- `provider`
- `user vector`
- `associate faces`

Isso fica so em telemetria e superficie avancada.

---

## Presets por tipo de evento

Vale criar templates de tipos e relacoes.

## Casamento

Pessoas:

- noiva
- noivo
- pais
- irmaos
- padrinhos
- madrinhas
- amigos
- cerimonial
- fotografo
- banda
- convidados

Relacoes:

- `spouse_of`
- `mother_of`
- `father_of`
- `sibling_of`
- `friend_of`
- `vendor_of_event`

## Corporativo

Pessoas:

- host
- speaker
- executive
- team_member
- sponsor_rep
- press
- vendor
- attendee

Relacoes:

- `manager_of`
- `teammate_of`
- `works_with`
- `speaker_with`
- `sponsor_of`

## Show / festival

Pessoas:

- artista
- banda
- producao
- equipe tecnica
- influenciador
- patrocinador
- convidado VIP

Relacoes:

- `artist_of_event`
- `works_with`
- `backstage_with`
- `sponsor_of`

---

## Roadmap recomendado

## Fase 1 - Pessoa do evento

Entregar:

- modulo `EventPeople`
- `event_people`
- `event_person_face_assignments`
- `event_person_review_queue`
- `event_person_representative_faces`
- lista de pessoas
- detalhe da pessoa
- filtro por pessoa
- overlay de rostos na foto
- confirmacao/correcao manual
- criacao de pessoa a partir da confirmacao
- observabilidade minima da review queue e dos jobs
- sync AWS somente por job

Essa fase entrega valor comercial real imediatamente.

## Fase 2 - Relacoes

Entregar:

- `event_person_relations`
- `event_person_media_stats`
- `event_person_name_search`
- editor simples de relacoes
- filtros `X + Y`
- presets por tipo de evento

Aqui ja nasce a parte "familia" sem limitar o produto a arvore.

## Fase 3 - Coocorrencia e sugestoes

Entregar:

- `event_person_cooccurrences`
- `event_person_pair_scores`
- score de proximidade
- sugestoes de pares e grupos
- curadoria de cobertura
- refresh incremental dos read models

Aqui entra a parte "rede viva" do evento.

## Fase 3.5 - Grupos e coverage intelligence

Entregar:

- `event_person_groups`
- `event_person_group_memberships`
- `event_coverage_targets`
- `event_coverage_alerts`
- `event_must_have_pairs`
- painel `Cobertura importante`
- filtros por grupo
- alertas de cobertura insuficiente

Aqui o produto deixa de ser apenas organizador e vira assistente de cobertura.

## Fase 4 - Momentos e entregas emocionais

Entregar:

- colecoes por relacao;
- colecoes por grupo;
- "melhores fotos com X";
- entregas prontas por vinculo;
- base para notificacoes futuras.

Aqui nasce o diferencial comercial mais forte da trilha.

## Fase 5 - Sync AWS curado e endurecimento completo

Entregar:

- politica final de representatives;
- governanca de retention;
- runbook de limpeza;
- alarmes e thresholds endurecidos;
- observabilidade cruzada app + Horizon + CloudWatch.

Esse endurecimento continua importante, mas nao e o unico diferencial de produto.

## Fase 6 - Visualizacao premium

Entregar:

- mapa interativo
- visualizacao familiar
- timeline de conexoes
- insights sociais do evento

Essa fase e wow factor, nao base operacional.

### Nota de ordem operacional

O valor de `review_queue`, `people`, `media_stats`, `pair_scores` e filtros `X + Y` nasce inteiro do banco local.

Isso significa:

- o sync AWS precisa existir cedo apenas como trilha assincrona minima para nao degradar selfie search;
- o endurecimento completo de sync, retention e governanca pode vir depois;
- a UX nao deve esperar a AWS "ficar perfeita" para a camada local entregar valor.

---

## O que eu nao recomendaria agora

- construir grafo bonito antes de ter backoffice forte;
- depender de AWS para filtro por nome;
- usar Neo4j/Neptune no primeiro corte;
- colocar video no MVP de pessoas;
- inferir parentesco automaticamente;
- tratar `confidence` como verdade final;
- obrigar o operador a entender termos tecnicos da AWS.
- usar `AssociateFaces` ou `SearchUsersByImage` no microfluxo de navegacao;
- recalcular o evento inteiro a cada confirmacao;
- criar pessoa manual como fluxo principal antes da primeira confirmacao guiada.

---

## Bateria de validacao executada para esta analise

Para sustentar a leitura da stack atual, foi executada uma rodada objetiva do modulo `FaceSearch`.

### Backend

Comando:

- `php artisan test tests/Feature/FaceSearch tests/Unit/FaceSearch`

Resultado:

- `156 passed`
- `7 skipped`
- `1141 assertions`

Leitura:

- a trilha tecnica de indexacao, selfie search, fallback, shadow, `users`, crop-first, readiness e reconciliacao continua verde;
- a base atual ja e forte o suficiente para receber a camada de `EventPeople`;
- os `7 skipped` continuam sendo contratos opt-in da trilha AWS/TDD.

### Frontend

Comando:

- `npx.cmd vitest run src/modules/face-search/components/FaceSearchSearchPanel.test.tsx src/modules/face-search/components/EventFaceSearchSearchCard.test.tsx src/modules/face-search/PublicFaceSearchPage.test.tsx src/modules/media/MediaPage.test.tsx`

Resultado:

- `4 files passed`
- `6 tests passed`

Leitura:

- a UX atual de selfie search interno/publico continua estavel;
- a entrada simples "Buscar pessoa por foto" no catalogo continua verde;
- a analise de produto parte de superficies reais ja existentes no sistema.

### Frontend extra - superfícies do evento

Comando:

- `npx.cmd vitest run src/modules/events/face-search-status.test.ts src/modules/events/components/face-search/EventFaceSearchSettingsForm.test.tsx src/modules/events/components/face-search/EventFaceSearchSettingsCard.test.tsx src/modules/events/EventDetailPage.test.tsx`

Resultado:

- `4 files passed`
- `14 tests passed`

Leitura:

- o CRUD simples do evento continua consistente com a trilha atual de reconhecimento facial;
- o card avancado de operacao AWS continua coberto;
- o detalhe do evento continua resolvendo corretamente o status operacional do `FaceSearch`;
- a estrategia proposta encaixa sobre as superfícies reais que o produto ja possui hoje.

### Validacao repetida apos a revisao do plano

Backend:

- `156 passed`
- `7 skipped`
- `1141 assertions`

Frontend:

- `8 files passed`
- `20 tests passed`

Leitura:

- a base que sustenta a estrategia continua verde;
- a revisao endurece a execucao, mas nao exige reabrir a trilha atual de `FaceSearch`;
- o proximo risco real agora e de implementacao operacional, nao de conceito.

---

## Conclusao

O caminho mais forte para o produto e:

1. manter AWS como motor de matching e indexacao;
2. criar `EventPerson` como verdade do dominio;
3. separar relacao declarada de coocorrencia inferida;
4. deixar a navegacao e os filtros 100% locais;
5. usar review humano guiado como parte oficial do fluxo;
6. puxar observabilidade, contrato assincrono e governanca AWS para o comeco da execucao;
7. adicionar grupos, cobertura importante e momentos por relacao como camadas derivadas de maior valor comercial;
8. tratar "arvore genealogica" como uma view derivada, nao como a arquitetura.

Em uma frase:

**o produto nao deve virar uma arvore de fotos; ele deve virar uma camada de identidade, relacao, cobertura e contexto social do evento, com UX local fluida e a AWS invisivel para o operador.**
