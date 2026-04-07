# FaceSearch Dataset Calibration Analysis

## Escopo

Este documento foca apenas em dataset e calibracao do `FaceSearch`.

Nao cobre `ContentModeration`, `MediaIntelligence` nem outras frentes da fase.

## Stack Atual Relevante

- `CompreFace` faz `detection` e fornece embedding via `calculator`.
- o ranking final continua no app via `PostgreSQL + pgvector`.
- o smoke local usa manifesto consentido em `tests/Fixtures/AI/local/vipsocial.manifest.json`.
- o benchmark semeia embeddings do smoke em dataset temporario e mede `exact|ann`.
- o benchmark agora tambem expone:
  - composicao do dataset
  - breakdown por `scene_type`
  - breakdown por `quality_label`
  - breakdown por `detected_faces_count`

## O Que Foi Validado Localmente

### Relatorios reais mais recentes

- smoke:
  - `apps/api/storage/app/face-search-smoke/20260407-222703-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/20260407-222718-face-search-benchmark.json`

### Resultado atual no dataset consentido local

- `exact.top_1_hit_rate=1.0`
- `exact.top_5_hit_rate=1.0`
- `exact.false_positive_top_1_rate=0.0`
- `exact.p95_search_ms=28.71`
- `ann.top_1_hit_rate=1.0`
- `ann.top_5_hit_rate=1.0`
- `ann.false_positive_top_1_rate=0.0`
- `ann.p95_search_ms=104.97`
- `operational_summary.p95_detect_ms=601`
- `operational_summary.p95_embed_ms=420`

Leitura:

- o baseline local continua bom para o acervo consentido atual;
- `exact` segue melhor default que `ann` no volume atual;
- os resultados ainda nao provam robustez para faces pequenas, ocluidas ou densidade alta de pessoas.

## Achados Estruturais

### 1. O dataset local atual esta enviesado para faces grandes

No smoke real atual, o menor lado do bbox selecionado nas 7 imagens ficou entre `217 px` e `603 px`.

Isso significa que:

- `min_face_size_px=96` ainda nao foi estressado de verdade;
- o dataset atual e bom para validar matching basico;
- o dataset atual e fraco para calibrar cenas de grupo, palco, pista e fotos com rosto pequeno.

### 2. O quality gate atual nao mede qualidade facial de verdade

Hoje o `CompreFaceDetectionProvider` faz:

- `qualityScore = probability` do detector.

No smoke real mais recente, a probabilidade ficou entre:

- `0.999702513217926`
- `0.9999932050704956`

Media:

- `0.99991`

Leitura:

- esse score quase nao separa `good`, `mixed` e `profile_extreme`;
- na pratica, `min_quality_score=0.60` nao esta calibrando blur, pose, oclusao ou iluminacao;
- hoje o gate efetivo depende muito mais de `min_face_size_px` do que de `min_quality_score`.

### 3. O benchmark interno atual mede matching, nao cobertura de deteccao

Isso esta correto para o objetivo do produto.

Mas existe uma limitacao importante:

- o benchmark atual precisa de `person_id` repetido para medir `top-1/top-5`.

Logo:

- o dataset consentido local serve para matching;
- `COFW` e `Caltech WebFaces` nao entram diretamente nesse benchmark como estao;
- esses conjuntos servem melhor para deteccao, oclusao, face-size e quality gate.

## Datasets Ja Baixados E Extraidos

Diretorio local usado:

- `C:\Users\Usuario\Desktop\model`
- extraidos em:
  - `C:\Users\Usuario\Desktop\model\extracted`

### 1. VIPSocial local consentido

Uso correto:

- smoke real
- benchmark de matching
- regressao de threshold no pipeline atual

Limitacao:

- apenas `2` identidades
- apenas `7` entradas
- bias para rostos grandes

### 2. Caltech 10k Web Faces

Arquivos locais:

- `Caltech_WebFaces.tar`
- `WebFaces_GroundThruth.txt`

Inspecao local:

- `7092` imagens extraidas
- `10524` anotacoes de faces
- `1339` imagens com multiplas faces
- maximo observado:
  - `38` faces anotadas em uma unica imagem

Estatistica aproximada de escala facial a partir do ground truth:

- `interocular p50 ~= 21.37 px`
- `interocular p95 ~= 68.14 px`
- `span_min_side p50 ~= 21.01 px`
- `span_min_side p95 ~= 66.88 px`

Leitura:

- excelente para stress de deteccao e cenas com rostos pequenos;
- excelente para calibrar `min_face_size_px`;
- bom para negativos e multi-face;
- ruim para benchmark principal de matching porque nao traz identidade reutilizavel no formato exigido pelo app.

Probe real local no `CompreFace` em amostra pequena:

- `pic00011.jpg`:
  - `detected_faces_count=1`
  - `min_face_side_px=60`
- `pic00511.jpg`:
  - `detected_faces_count=1`
  - `min_face_side_px=48`
- `pic00811.jpg`:
  - `detected_faces_count=5`
  - `min_face_side_px_min=31`
  - `min_face_side_px_p50=37`
  - `min_face_side_px_max=46`

Leitura:

- se o produto quiser encontrar pessoas em fotos mais abertas, `min_face_size_px=96` esta conservador demais;
- o valor atual protege precision, mas tende a derrubar recall em cenas reais de evento com grupo.

### 3. COFW

Arquivos locais:

- `COFW.zip`
- `COFW_color.zip`
- `documentation.zip`

Inspecao local:

- o dataset vem em `.mat`, nao em pasta simples de `.jpg`
- o helper `loadCOFW.m` confirma:
  - `IsTr = cell(1345,1)`
  - `IsT = cell(507,1)`
  - `29 landmarks`
  - `occlusion bit` por landmark
  - primeiros `845` do treino sao `LFPW`
  - `500` imagens COFW no treino
  - `507` imagens COFW no teste

Da documentacao oficial local:

- `1,007 faces`
- `average occlusion of over 23%`

Leitura:

- excelente para calibrar robustez a oclusao e perfil;
- excelente para testar gate e deteccao com landmark/occlusion metadata;
- nao e bom benchmark principal de identidade porque o foco dele nao e pairing por pessoa;
- precisa de loader dedicado para converter `.mat` em imagens/manifesto utilizavel pelo app.

## O Que Falta Para O Match Ficar Realmente Bem Calibrado

### 1. Separar o problema em 3 lanes de dataset

#### Lane A - matching de identidade

Usar:

- dataset consentido local
- LFW
- CelebA com identity labels
- eventualmente RMFD/RMFVD para mascara

Objetivo:

- calibrar `search_threshold`
- medir `top-1/top-5`
- medir falso positivo entre pessoas parecidas

#### Lane B - deteccao e quality gate

Usar:

- COFW
- Caltech WebFaces
- WIDER FACE se entrar depois

Objetivo:

- calibrar `min_face_size_px`
- medir falha por oclusao
- medir falha por face pequena
- medir custo de cena multi-face

#### Lane C - negativos operacionais

Usar:

- Caltech WebFaces
- fotos de evento com muitas pessoas diferentes
- cross-event isolation

Objetivo:

- reduzir falso positivo
- validar isolamento por `event_id`
- validar que pessoas parecidas nao vazam match acima do threshold

### 2. Ampliar o dataset de identidade

Meta recomendada minima para o lane principal:

- `8-12` identidades
- `6-10` fotos por identidade
- distribuicao obrigatoria por identidade:
  - `2` retratos bons
  - `1` grupo pequeno
  - `1` grupo maior
  - `1` perfil ou semi-perfil
  - `1` ocluida parcial

Sem isso:

- `top_1_hit_rate=1.0` com `2` pessoas continua pouco informativo.

### 3. Recalibrar `min_face_size_px`

Recomendacao pratica:

- testar faixas `48`, `64`, `80`, `96`

Hipotese atual:

- `96` favorece precision e tende a cortar recall em fotos mais abertas;
- `64` provavelmente e o melhor primeiro candidato para homologacao;
- `48` pode entrar apenas se falso positivo nao subir demais e se o crop continuar util.

### 4. Nao confiar em `min_quality_score` enquanto o score for so `detection probability`

Antes de recalibrar esse campo de verdade, o app precisa derivar score util a partir de sinais como:

- sharpness do crop
- area relativa da face
- simetria/alinhamento via landmarks
- pose estimada

Enquanto isso nao existir:

- o threshold de qualidade nao deve ser tratado como gate forte de negocio;
- a principal calibracao operacional continua sendo `min_face_size_px + search_threshold`.

### 5. Manter `exact` como default

Com o volume atual e os relatorios reais atuais:

- `exact` esta mais rapido que `ann`
- `ann` ainda nao entrega ganho operacional

So faz sentido reavaliar `ann` quando:

- o volume de embeddings por evento crescer materialmente;
- ou o `p95` do `exact` sair da meta.

## Proxima Sequencia Recomendada

1. ampliar o lane de identidade com mais pessoas e mais variacao real do mesmo evento
2. adicionar loader local para COFW e usar esse conjunto so para deteccao/oclusao
3. usar Caltech WebFaces como stress de face pequena e multi-face
4. rodar sweep de `min_face_size_px` em `48/64/80/96`
5. so depois mexer em `search_threshold`
6. nao recalibrar `min_quality_score` como se ele fosse quality real antes de corrigir o proxy

## Conclusao

O stack atual esta no caminho certo para o MVP:

- provider real homologado
- benchmark reproduzivel
- manifestos rastreaveis
- relatorio real por `scene_type`

O principal gargalo agora nao e engine de busca.

O principal gargalo e dataset:

- pouco volume de identidade
- pouco rosto pequeno
- pouco caso duro de oclusao calibrado no app
- `qualityScore` ainda sem semantica forte para matching

Se a proxima iteracao fizer apenas uma coisa, a melhor aposta e:

- ampliar o dataset de identidade e recalibrar `min_face_size_px` com apoio de `Caltech WebFaces` e `COFW`.
