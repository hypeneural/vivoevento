# FaceSearch Stack Assessment And Provider Strategy

## Escopo

Este documento consolida, em `2026-04-08`, a avaliacao detalhada do modulo `FaceSearch` depois de:

- calibracao por dataset curado;
- probes reais em `COFW`, `Caltech WebFaces` e `WIDER FACE`;
- homologacao de `min_face_size_px=24` e `search_threshold=0.5`;
- validacao pratica em galeria real de evento;
- teste de organizacao automatica da galeria por pessoa.

O objetivo aqui nao e repetir todo o historico de experimentos.

O objetivo e responder quatro perguntas de negocio e arquitetura:

1. o que ja construimos de verdade;
2. como a stack esta hoje;
3. por que a organizacao automatica da galeria ficou ruim;
4. qual o caminho profissional para melhorar muito, incluindo rota com servico pago de bom custo-beneficio e fallback.

Leitura complementar importante:

- para o desenho detalhado da integracao `AWS Rekognition` como opcao inicial, ver:
  - `docs/architecture/face-search-aws-rekognition-integration-plan-2026-04-08.md`
- para o backlog executavel dessa trilha, com fases, tarefas e bateria de testes, ver:
  - `docs/architecture/face-search-aws-rekognition-execution-plan-2026-04-08.md`

---

## Resumo Executivo

Leitura direta:

- o stack atual esta bom para um MVP de `search by selfie` controlado por evento;
- o stack atual nao esta bom para `clusterizacao automatica de galeria inteira por pessoa` sem revisao humana;
- o principal gargalo hoje nao e `pgvector`, nem `search_threshold`;
- o principal gargalo e a combinacao de:
  - detector fraco em crowd, tiny faces e oclusao;
  - `qualityScore` sem semantica forte;
  - crop sem alinhamento facial;
  - clustering global simples demais para um problema aberto e ruidoso.

Conclusao pratica:

- para busca por selfie dentro de um evento, a base esta no caminho certo;
- para organizar centenas de fotos automaticamente por pessoa, a base atual ainda nao e profissional;
- para subir muito o nivel de qualidade, o caminho mais racional e:
  - endurecer o bloco de deteccao e quality;
  - separar claramente `search by selfie` de `auto-clustering de galeria`;
  - adicionar capacidade de `routing` entre providers;
  - adotar `AWS Rekognition` como opcao inicial de backend gerenciado;
  - tratar `Luxand Cloud` apenas como trilha secundaria de piloto barato e comparativo;
  - manter `CompreFace` como fallback, shadow lane e ambiente de calibracao continua.

---

## O Que Ja Temos Hoje

### Arquitetura funcional ja entregue

O modulo ja tem uma base real, nao apenas prototipo:

- provider facial desacoplado;
- embedding provider desacoplado;
- vector store desacoplado;
- indexacao assincorna por job;
- isolamento por `event_id`;
- quality gate configuravel por evento;
- smoke real do provider;
- benchmark reprodutivel;
- throughput do lane;
- probes de deteccao por manifesto;
- loaders locais para datasets relevantes;
- sweep real de `min_face_size_px`;
- sweep real de `search_threshold`;
- organizador local de galeria por clustering de embeddings para validacao manual.

### Decisoes homologadas ate aqui

Defaults homologados do modulo:

- `min_face_size_px=24`
- `search_threshold=0.5`
- `search_strategy=exact`

Leitura:

- isso foi suficiente para o lane principal de identidade local;
- isso nao resolveu crowd/tiny faces nem clustering aberto de galeria.

---

## Stack Atual Relevante

### Pipeline principal

Hoje o desenho funcional e:

1. `CompreFace` detecta faces e gera embedding;
2. o app aplica quality gate;
3. o app salva crop, bbox, scores e embedding;
4. o ranking final continua no app via `PostgreSQL + pgvector`;
5. a busca por selfie consulta apenas o acervo do evento.

### O que esta tecnicamente bom

- o provider esta encapsulado;
- o store vetorial esta encapsulado;
- a indexacao nao bloqueia o processamento principal;
- o modulo ja tem observabilidade suficiente para calibracao;
- a busca esta isolada por evento;
- a estrategia `exact` esta correta no volume atual;
- o modulo esta pronto para crescer em numero de providers.

### O que esta tecnicamente fraco

- `qualityScore` ainda deriva basicamente da `probability` do detector;
- o crop para embedding ainda e um crop bruto da bbox, sem alinhamento facial;
- nao existe score composto de qualidade;
- nao existe pipeline forte de reconciliacao de clusters;
- nao existe backend de busca gerenciado alem de `pgvector`;
- `provider_key` hoje resolve bem `detect/embed`, mas o fluxo de busca ainda assume `embedding + pgvector`;
- isso limita integracao limpa com providers gerenciados que fazem `identify/search in collection` dentro do proprio servico.

---

## O Que Ja Validamos

### 1. Lane de identidade

Nos slices internos e holdouts controlados, a busca por identidade ficou forte:

- `LFW` ficou muito bem como lane principal;
- `CALFW` segurou bem idade;
- `CFP-FP` mostrou queda em pose;
- `XQLFW` mostrou queda em qualidade.

Leitura:

- a busca controlada por selfie em lane limpo esta perto do esperado;
- a robustez cai quando entra pose, qualidade e imagem ruim;
- isso ja indicava que clustering aberto em galeria real seria bem mais dificil.

### 2. Lane de deteccao dura

Os probes mostraram um retrato consistente:

- `COFW heavy` ficou muito ruim em recall sob oclusao pesada;
- `WIDER FACE` ficou ruim em crowd e tiny faces;
- `Caltech WebFaces` ficou melhor e mais proximo do produto, mas ainda com ruido em grupo.

Leitura:

- o problema real nao esta na busca vetorial;
- o problema real aparece antes, na deteccao e na qualidade do face crop.

### 3. Galeria real do evento

Rodamos organizacao automatica em:

- origem:
  - `C:\Users\Usuario\Desktop\ddddd\FINAL`
- saida:
  - `C:\Users\Usuario\Desktop\ddddd\AGRUPADO_POR_PESSOA\20260408-full-002`

Resultado principal:

- `images_total=963`
- `images_clustered=852`
- `images_without_faces=111`
- `images_failed_or_invalid=0`
- `faces_accepted_total=2402`
- `clusters_total=161`
- `multi_person_images=638`
- `max_person_folders_per_image=16`
- `67` clusters com apenas `1` imagem
- maior cluster com `571` imagens

Leitura:

- cobertura operacional ficou boa;
- falha tecnica ficou zerada;
- mas a identidade ficou ruim para agrupamento automatico:
  - houve fragmentacao demais;
  - houve sinais fortes de sobre-fusao nos maiores clusters.

Conclusao:

- o stack atual consegue localizar muitas faces;
- o stack atual ainda nao consegue agrupar pessoas de forma confiavel em galeria aberta e heterogenea.

---

## Diagnostico Honesto: O Que Esta Bom E O Que Esta Ruim

## O que esta bom

- a arquitetura do modulo esta limpa e profissional para evolucao;
- a parte de benchmark e calibracao esta acima da media para um MVP;
- o isolamento por evento esta correto;
- o modulo ja permite validacao real, e nao so opiniao;
- a base atual serve bem como harness para comparar providers.

## O que esta ruim

- `CompreFace` esta abaixo do nivel desejado para crowd e tiny faces;
- o `quality gate` nao mede qualidade facial real;
- o pipeline de embedding ainda nao alinha a face;
- o clustering local usa uma heuristica simples demais para esse problema;
- a validacao interna ate aqui estava muito mais forte para `selfie search` do que para `full-gallery clustering`.

## O que esta enganando a leitura

Os resultados de `selfie search` e os resultados de `organizar galeria inteira automaticamente` nao medem a mesma coisa.

`Search by selfie`:

- problema fechado;
- uma pessoa de consulta;
- isolamento por evento;
- top-k com threshold;
- uso humano para decidir resultado final.

`Auto-clustering de galeria`:

- problema aberto;
- muitas pessoas desconhecidas;
- fotos de grupo;
- pose variavel;
- oclusao;
- rosto pequeno;
- sem identidade previa forte;
- muito mais sensivel a fragmentacao e sobre-fusao.

Conclusao:

- o stack atual pode estar aceitavel para `selfie search`;
- e ainda assim estar ruim para `organizar galeria inteira`.

Foi exatamente isso que aconteceu.

---

## Principais Causas Da Fragmentacao E Dos Erros

### 1. O detector ainda falha no regime que mais importa para galeria real

Os probes reais ja mostraram isso:

- `COFW heavy`: recall muito baixo em oclusao;
- `WIDER sequential crowd small`: recall muito baixo em crowd pequeno;
- `WIDER sequential product`: melhora fora do pior slice, mas ainda longe do ideal.

Na pratica:

- quando a deteccao inicial erra bbox, pose ou face pequena;
- o embedding ja nasce degradado;
- e o clustering vira ruido.

### 2. O score de qualidade atual e fraco

Hoje o gate ainda depende demais de:

- `min_face_size_px`
- `detection probability`

Isso e insuficiente para separar:

- blur
- pose extrema
- oclusao
- iluminacao ruim
- crop pouco alinhado

Resultado:

- varias faces “aceitas” ainda sao embeddings ruins para identidade;
- varias faces entram no clustering com qualidade semantica baixa.

### 3. O crop para embedding ainda e simples demais

O pipeline atual faz crop pela bbox.

Falta:

- alinhamento por landmarks;
- margem controlada;
- normalizacao mais consistente do rosto antes do embedding.

Para busca controlada isso ainda passa.
Para clustering aberto, isso pesa muito.

### 4. O clustering local foi util como teste, mas nao e algoritmo final de produto

O organizador local usa uma abordagem pragmatica:

- aceita faces pelo gate;
- calcula embeddings;
- anexa face ao cluster mais proximo abaixo de um threshold;
- materializa em pastas `pessoa-###`.

Isso e bom para validacao manual.
Nao e suficiente como motor final de agrupamento profissional.

Problemas desse tipo de clustering:

- efeito de encadeamento transitorio;
- sobre-fusao em clusters grandes;
- fragmentacao em qualidade baixa;
- falta de reconciliacao posterior;
- ausencia de regra forte para split/merge com evidencia acumulada.

### 5. Nossos datasets foram bons para calibrar o stack, mas ainda nao fecham o caso de galeria aberta

O que esta forte:

- identidade controlada;
- pose e qualidade em holdouts;
- crowd/tiny faces em probes.

O que ainda falta:

- dataset supervisionado do proprio produto para `same-person across event gallery`;
- labels reais de cluster no estilo “quem e quem” em 1 ou 2 eventos completos;
- benchmark proprio de fragmentacao e sobre-fusao.

Sem isso, o sistema melhora, mas continua cego no problema final.

---

## Avaliacao Da Maturidade Atual

### Para busca por selfie no evento

Status:

- `quase pronto para operacao controlada`, com ressalvas.

Condicoes:

- evento com acervo razoavelmente bom;
- fotos sem crowd extremo como principal caso;
- UX que aceite resultado top-k com validacao humana;
- operacao consciente de que tiny faces e oclusao pesada continuam limitando recall.

### Para organizacao automatica de galeria por pessoa

Status:

- `nao pronto para uso profissional sem revisao humana`.

Motivo:

- a rodada real mostrou fragmentacao e sobre-fusao acima do aceitavel;
- falta pipeline especifico para clustering profissional;
- falta score confiavel de qualidade e consistencia do rosto.

### Para produto premium profissional

Status:

- `a arquitetura permite chegar la`;
- `o provider atual sozinho nao entrega esse nivel`.

---

## O Que Precisamos Fazer Para Melhorar Muito

## Bloco A - Melhorar muito sem trocar de provider primeiro

Essas mudancas tem alto valor e baixo arrependimento.

### 1. Criar um `face quality score` de verdade

Precisa combinar sinais como:

- `face_area_ratio`
- largura/altura minima da bbox
- `sharpness`
- `brightness`
- `yaw/pitch/roll`
- oclusao
- simetria/alinhamento por landmarks
- confianca do detector

Meta:

- deixar de tratar `probability` como se fosse quality facial.

### 2. Alinhar a face antes do embedding

Necessario:

- usar landmarks para alinhar olhos/nariz;
- padronizar crop para embedding;
- testar margem de crop;
- reprocessar alguns lanes com essa mudanca.

Essa e provavelmente a melhoria tecnica mais barata com maior retorno dentro da stack atual.

### 3. Separar claramente `detect gate` de `search gate`

Hoje muito do comportamento operacional cai em um gate unico.

O correto e ter pelo menos:

- gate de aceitacao para indexacao;
- gate mais estrito para `search_priority`;
- gate proprio para clustering automatico.

Clustering automatico deve usar um gate mais conservador que indexacao.

### 4. Criar um pipeline proprio de clustering profissional

Minimo necessario:

- clustering em duas fases;
- restricao simetrica de merge;
- clusters com multiplos exemplares fortes;
- split automatico quando o cluster fica heterogeneo;
- scores de confianca por cluster;
- fila de revisao manual para clusters ambigos.

Em termos práticos:

- o comando local atual serve para experimento;
- nao deve ser promovido como motor final de agrupamento automatico sem essa evolucao.

### 5. Construir benchmark supervisionado de cluster com evento real

Precisamos de 1 ou 2 eventos com:

- labels humanas de pessoas repetidas;
- pares positivos/negativos;
- grupos;
- close-ups;
- pista/palco/decoracao.

Metricas necessarias:

- cluster purity
- fragmentation rate
- over-merge rate
- no-face false positive rate
- per-person recall

Sem isso, qualquer melhoria fica subjetiva.

---

## Bloco B - Melhorar a arquitetura para rotear entre servicos

Hoje a arquitetura ja ajuda, mas nao esta completa para provider gerenciado.

### Limite atual da arquitetura

Hoje o modulo assume majoritariamente:

- `detect`
- `embed`
- `store in pgvector`
- `search by embedding`

Isso funciona bem para `CompreFace`.

Mas varios providers pagos fortes funcionam diferente:

- eles fazem indexacao e busca dentro do proprio servico;
- nao necessariamente entregam embedding reutilizavel;
- trabalham com `collection/person list/search by image`.

### O que precisa mudar

Para rotear entre local e provider gerenciado, o desenho ideal e introduzir um backend de busca mais alto nivel, por exemplo:

- `FaceSearchBackendInterface`

Implementacoes:

- `local_pgvector`
- `aws_rekognition_collections`
- `luxand_managed`

Operacoes desse backend:

- `indexFace`
- `deleteFace`
- `searchByFaceImage`
- `searchByCrop`
- `healthCheck`

### Configuracao por evento recomendada

Hoje existe `provider_key`, `vector_store_key`, `search_strategy`.

Para um produto mais profissional, o ideal e separar:

- `detection_provider_key`
- `embedding_provider_key`
- `search_backend_key`
- `fallback_provider_key`
- `routing_policy`
- `shadow_mode_percentage`

Exemplos de politica:

- `local_only`
- `paid_only`
- `paid_primary_local_shadow`
- `local_primary_paid_on_no_face`
- `local_primary_paid_on_low_quality`

### Estrategia recomendada de rollout

Nao trocar tudo de uma vez.

Fazer em tres modos:

1. `shadow`
   - provider pago processa em paralelo
   - sem afetar usuario
   - compara recall e divergencia
2. `fallback`
   - local tenta primeiro
   - pago entra so em `no_face`, crowd ou baixa qualidade
3. `primary`
   - pago assume lane de producao
   - local fica como fallback e harness de melhoria continua

---

## Caminho Pago Com Bom Custo-Beneficio

## Opcao Inicial - AWS Rekognition

### Porque faz sentido

Documentacao oficial validada em `2026-04-08`:

- `IndexFaces` detecta faces, extrai feature vectors e armazena no backend da AWS; nao salva as faces em si, e sim o vetor de features;
- `SearchFacesByImage` faz busca em collection e suporta `FaceMatchThreshold`, `MaxFaces` e `QualityFilter`;
- a pagina oficial de preco mostra modelo pay-as-you-go sem minimo contratual, com `Group 1` cobrando `IndexFaces`, `CompareFaces`, `SearchFacesByImage` e afins;
- a FAQ oficial informa cobranca de armazenamento de vetores faciais.

Leitura:

- e a opcao mais pragmatica para um piloto serio com baixo investimento inicial;
- reduz operacao de infraestrutura;
- entrega um caminho gerenciado de `index + search`;
- tem filtros de qualidade nativos que ja ajudam no problema atual.

### Custo aproximado

Inferencia a partir da pagina oficial de pricing da AWS:

- `Group 1`: `US$ 0.001` por imagem na primeira faixa mensal;
- storage de vetores faciais: `US$ 0.01` por `1,000` vetores por mes.

Exemplo inferido para o evento real testado:

- `963` imagens indexadas -> cerca de `US$ 0.963`
- `100` buscas por selfie -> cerca de `US$ 0.10`
- `2402` faces armazenadas -> cerca de `US$ 0.024/mes`

Leitura:

- para piloto, o custo e baixo;
- o risco maior nao e preco, e sim o trabalho de integracao arquitetural.

### Ponto de atencao

Para integrar direito, precisamos aceitar que no modo AWS a busca nao ficaria mais centrada em `pgvector`.

Ou seja:

- `AWS` nao e apenas “mais um embedder”;
- ele e um `managed face search backend`.

### Minha leitura

Entre as opcoes pagas validadas oficialmente, esta e a melhor aposta inicial de custo-beneficio tecnico para piloto profissional.

## Opcao Secundaria - Luxand Cloud

### Porque faz sentido

Documentacao e pricing oficiais validados em `2026-04-08`:

- o servico oferece `Face recognition API`, `Face verification API`, `Face similarity API`, landmarks, crop, age/gender e liveness;
- a pagina oficial fala em identificacao de pessoas previamente etiquetadas;
- a pagina oficial de pricing tem plano previsivel e baixo custo de entrada.

Faixas oficiais de entrada encontradas:

- `US$ 9/mes` -> `1,500` requests e `500` faces
- `US$ 19/mes` -> `5,000` requests e `1,500` faces
- `US$ 39/mes` -> `10,000` requests e `2,500` faces
- `US$ 99/mes` -> `200,000` requests e `6,000` faces

Leitura:

- para piloto barato e rapido, e muito atraente;
- custo e previsivel;
- nao exige operacao propria de GPU;
- parece mais facil para provar valor rapido do que AWS.

### Ponto de atencao

- nos nao benchmarkamos Luxand no nosso dataset ainda;
- a documentacao valida a oferta e o preco, mas nao substitui teste comparativo real;
- storage por plano pode apertar rapido se o acervo por evento crescer e nao houver limpeza.

### Minha leitura

Se o objetivo for `piloto barato, rapido e com minimo overhead`, Luxand merece um teste curto.

Se o objetivo for `solidez de nuvem, ecossistema, governanca e escala`, AWS parece mais robusto.

## Candidato 3 - Azure Face

### Porque hoje eu nao recomendaria como primeira opcao

A documentacao oficial atual da Microsoft indica:

- identificacao e verificacao sao `Limited Access`;
- e preciso registro;
- o acesso depende de aprovacao da Microsoft;
- o acesso e restrito a clientes gerenciados pela Microsoft;
- a pagina oficial de pricing aponta para calculadora/quote, nao para um modelo simples de entrada.

Leitura:

- tecnicamente pode servir;
- operacionalmente nao e uma boa aposta para um time que quer se mover rapido sem processo comercial.

## Nao candidato para esse caso - Google Cloud Vision

A documentacao oficial do Google Cloud Vision diz explicitamente:

- ele faz `face detection`;
- `specific individual facial recognition is not supported`.

Leitura:

- serve para deteccao;
- nao serve como backend principal de reconhecimento por identidade.

---

## Minha Recomendacao Objetiva De Produto

### Se a prioridade for subir muito a qualidade com baixo investimento

Eu seguiria assim:

1. manter `CompreFace + pgvector` como stack local de desenvolvimento, benchmark e fallback;
2. criar arquitetura de `search backend` para aceitar provider gerenciado;
3. pilotar `AWS Rekognition` como backend gerenciado inicial;
4. em paralelo, se quiser comparativo barato e rapido, testar `Luxand` em 1 evento pequeno;
5. comparar tudo com o mesmo evento rotulado e com os mesmos indicadores de fragmentacao e sobre-fusao.

### Se a prioridade for gastar o minimo absoluto agora

Fazer primeiro:

- alinhamento facial;
- quality score composto;
- clustering em duas fases;
- benchmark supervisionado com evento real.

Mas leitura honesta:

- isso melhora bastante;
- porem dificilmente vai transformar o stack atual sozinho em um agrupador “profissional” de galeria inteira no curto prazo.

### Se a prioridade for confianca de producao rapidamente

Minha recomendacao e:

- `AWS Rekognition` como `primary managed backend`
- `CompreFace` como `shadow + fallback`

Porque:

- custo de piloto e baixo;
- qualidade e filtros nativos tendem a ajudar mais que continuar tunando threshold local;
- a arquitetura atual do modulo ja esta perto do ponto de suportar esse roteamento.

---

## Roadmap Recomendado Em Fases

## Fase 1 - 1 a 2 semanas

- criar `FaceSearchBackendInterface`
- manter backend local atual como `local_pgvector`
- adicionar score composto de qualidade
- adicionar alinhamento facial antes do embedding
- separar gate de `indexacao` e gate de `clustering`

Saida esperada:

- melhora visivel mesmo sem trocar provider.

## Fase 2 - 1 a 2 semanas

- implementar `aws_rekognition_collections` como backend inicial
- suportar `shadow mode`
- logar divergencia entre `local` e `aws`
- rerodar no mesmo evento real

Saida esperada:

- comparacao objetiva entre local e pago.

## Fase 3 - 1 semana

- opcionalmente implementar `luxand_managed`
- rodar benchmark curto em 1 evento
- comparar custo, latencia, recall e fragmentacao

Saida esperada:

- decisao de custo-beneficio real, nao opinativa.

## Fase 4 - produto

- UI de revisao de clusters
- merge/split manual
- limpezas e reindexacao controlada
- politica por evento para provider primario e fallback

Saida esperada:

- servico profissional, operavel e auditavel.

---

## Decisao Recomendada

Se eu tivesse que decidir hoje, com base no que foi validado:

- nao reabriria `search_threshold`;
- nao reabriria `min_face_size_px`;
- nao venderia a organizacao automatica de galeria atual como pronta;
- priorizaria arquitetura de backend roteavel;
- colocaria `AWS Rekognition` como opcao inicial paga;
- manteria `CompreFace` como fallback e harness de melhoria;
- trataria `Luxand` como opcao secundaria de piloto barato e rapido, mas ainda dependente de benchmark interno;
- deixaria `Azure Face` fora da trilha inicial por friccao de acesso;
- descartaria `Google Cloud Vision` como backend de reconhecimento por identidade.

---

## Fontes Oficiais Validadas Em 2026-04-08

### Stack atual / open source

- CompreFace repo:
  - https://github.com/exadel-inc/CompreFace
- CompreFace architecture and scalability:
  - https://raw.githubusercontent.com/exadel-inc/CompreFace/master/docs/Architecture-and-scalability.md

### AWS

- Amazon Rekognition pricing:
  - https://aws.amazon.com/rekognition/pricing/
- Amazon Rekognition `SearchFacesByImage`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_SearchFacesByImage.html
- Amazon Rekognition `IndexFaces`:
  - https://docs.aws.amazon.com/rekognition/latest/APIReference/API_IndexFaces.html
- Amazon Rekognition FAQ:
  - https://aws.amazon.com/rekognition/faqs/

### Azure

- Azure Face pricing:
  - https://azure.microsoft.com/en-us/pricing/details/cognitive-services/face-api/
- Azure Face limited access:
  - https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/computer-vision/limited-access-identity

### Google

- Google Cloud Vision face detection:
  - https://cloud.google.com/vision/docs/detecting-faces

### Luxand

- Luxand Face API pricing:
  - https://luxand.cloud/pricing
- Luxand Face recognition API:
  - https://luxand.cloud/face-recognition-api
- Luxand help / docs entry:
  - https://luxand.cloud/help
