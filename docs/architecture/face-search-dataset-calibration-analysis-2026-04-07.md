# FaceSearch Dataset Calibration Analysis

## Escopo

Este documento foca apenas em dataset e calibracao do `FaceSearch`.

Nao cobre `ContentModeration`, `MediaIntelligence` nem outras frentes da fase.

Leitura complementar importante:

- para a avaliacao consolidada da stack, da validacao em galeria real e da estrategia de provider pago + fallback, ver:
  - `docs/architecture/face-search-stack-assessment-and-provider-strategy-2026-04-08.md`
- para o plano detalhado de integracao `AWS Rekognition` como opcao inicial, ver:
  - `docs/architecture/face-search-aws-rekognition-integration-plan-2026-04-08.md`
- para o backlog executavel da trilha AWS com fases e bateria de testes, ver:
  - `docs/execution-plans/face-search-aws-rekognition-execution-plan-2026-04-08.md`

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

### 4. RMFD / RMFVD local

Arquivo novo validado em `C:\Users\Usuario\Desktop\model`:

- `Real-World-Masked-Face-Dataset-master.zip`

Inspecao rapida do zip:

- `README.md`
- `RWMFD_part_1/` com imagens `.jpg`
- `RMFD_part_2.part1.rar` ate `part4.rar`
- `RMFD_part_3.part1.rar` ate `part3.rar`

Leitura:

- a lane de mascara realmente ja tem artefatos locais;
- o pacote veio parcialmente em pasta de imagens e parcialmente em volumes `.rar`;
- a integracao dessa lane continua viavel, mas vai exigir etapa de consolidacao dos artefatos.

## Automacao Implementada

Foi adicionado ao modulo `FaceSearch` o comando:

- `php artisan face-search:sweep-min-face-size`

Foi adicionado tambem o comando:

- `php artisan face-search:load-cofw-local`
- `php artisan face-search:probe-detection-dataset`
- `php artisan face-search:sweep-search-threshold`
- `php artisan face-search:load-wider-face`

Objetivo:

- rodar um sweep real de `min_face_size_px` em dataset local de faces pequenas
- usar o provider facial do app, nao um calculo teorico separado
- gerar relatorio repetivel em `storage/app/face-search-min-face-size-sweep/`
- exportar o `COFW` local em imagens reais + manifesto reutilizavel para deteccao/oclusao
- evitar parse manual de `.mat` dentro do app, usando subprocesso Python dedicado
- rodar probe real de deteccao a partir de manifesto exportado, medindo `annotation_recall_estimated`, `detection_precision_estimated` e latencia
- permitir probes estratificados por bucket de oclusao, tamanho e densidade
- rodar sweep real de `search_threshold` sem confundir similaridade do provider com distancia do `pgvector`
- integrar `WIDER FACE` como lane oficial de stress para densidade alta e faces pequenas

Arquivos principais:

- `apps/api/app/Modules/FaceSearch/Console/RunFaceSizeThresholdSweepCommand.php`
- `apps/api/app/Modules/FaceSearch/Services/FaceSizeThresholdSweepService.php`
- `apps/api/tests/Feature/FaceSearch/RunFaceSizeThresholdSweepCommandTest.php`
- `apps/api/app/Modules/FaceSearch/Console/RunCofwLocalLoaderCommand.php`
- `apps/api/app/Modules/FaceSearch/Services/CofwLocalLoaderService.php`
- `apps/api/app/Modules/FaceSearch/Support/export_cofw.py`
- `apps/api/tests/Feature/FaceSearch/RunCofwLocalLoaderCommandTest.php`
- `apps/api/app/Modules/FaceSearch/Console/RunDetectionDatasetProbeCommand.php`
- `apps/api/app/Modules/FaceSearch/Services/DetectionDatasetProbeService.php`
- `apps/api/tests/Feature/FaceSearch/RunDetectionDatasetProbeCommandTest.php`
- `apps/api/app/Modules/FaceSearch/Console/RunSearchThresholdSweepCommand.php`
- `apps/api/app/Modules/FaceSearch/Services/FaceSearchThresholdSweepService.php`
- `apps/api/tests/Feature/FaceSearch/RunSearchThresholdSweepCommandTest.php`
- `apps/api/app/Modules/FaceSearch/Console/RunWiderFaceLocalLoaderCommand.php`
- `apps/api/app/Modules/FaceSearch/Services/WiderFaceLocalLoaderService.php`
- `apps/api/app/Modules/FaceSearch/Support/export_wider_face.py`
- `apps/api/tests/Feature/FaceSearch/RunWiderFaceLocalLoaderCommandTest.php`

## Loader Local Do `COFW`

Execucoes reais validadas:

- color completo:
  - `apps/api/storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/report.json`
  - `apps/api/storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json`
- gray probe:
  - `apps/api/storage/app/face-search-datasets/cofw/20260407-235038-cofw-gray/report.json`
  - `apps/api/storage/app/face-search-datasets/cofw/20260407-235038-cofw-gray/manifest.json`

Resultado do export `COFW_color` com `--splits=train,test` e `--include-lfpw=false`:

- `entries_exported=1007`
- `split_counts.train=500`
- `split_counts.test=507`
- `source_subset_counts.cofw_train=500`
- `source_subset_counts.cofw_test=507`
- `occluded_entries=908`
- `p50_occlusion_rate=0.2069`
- `p95_occlusion_rate=0.5517`
- `p50_face_span_min_px=114`
- `p95_face_span_min_px=131.7`

Resultado do probe `COFW_gray` com `--splits=test --limit=5`:

- `entries_exported=5`
- `occluded_entries=5`
- `p50_occlusion_rate=0.2759`
- `p95_occlusion_rate=0.5241`

Leitura:

- o loader local agora converte o `COFW` em imagens reais no disco + manifesto JSON reutilizavel;
- o default operacional correto para o lane de oclusao e `COFW_color`, porque aproxima melhor o dominio visual do app;
- o `gray` ficou validado tecnicamente e pode ser usado para probes rapidos ou comparacoes pontuais.

## Probe Real De Deteccao/Oclusao Com Manifesto Exportado Do `COFW`

Relatorio real gerado:

- `apps/api/storage/app/face-search-detection-probe/20260408-002335-face-search-detection-probe.json`

Execucao validada:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json`
- comando:
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json --splits=test --selection=highest_occlusion --limit=120 --iou-threshold=0.20`

Resumo:

- `images_sampled=120`
- `images_with_successful_detection=9`
- `images_failed_or_missing=111`
- `annotated_faces_total=120`
- `detected_faces_total=9`
- `matched_annotations_total=6`
- `annotation_recall_estimated=0.05`
- `detection_precision_estimated=0.6667`
- `p50_detect_latency_ms=597.99`
- `p95_detect_latency_ms=1020.26`

Breakdown relevante:

- `occlusion_breakdown.heavy`
  - `images=80`
  - `annotation_recall_estimated=0.05`
- `occlusion_breakdown.moderate`
  - `images=40`
  - `annotation_recall_estimated=0.05`
- `face_size_breakdown.xlarge_gte_96`
  - `images=119`
  - `annotation_recall_estimated=0.0504`

Leitura:

- o problema principal do `COFW` hard slice nao e `min_face_size_px`;
- mesmo com faces grandes, o provider real falha em massa quando a oclusao sobe;
- varios casos retornaram `400 No face is found in the given image`, o que aponta limite de deteccao do provider sob oclusao, nao apenas um gate do app.

## Probes Estratificados De `COFW` Por Bucket De Oclusao

Relatorios reais gerados:

- `apps/api/storage/app/face-search-detection-probe/20260408-004132-face-search-detection-probe.json`
- `apps/api/storage/app/face-search-detection-probe/20260408-004131-face-search-detection-probe.json`
- `apps/api/storage/app/face-search-detection-probe/20260408-004134-face-search-detection-probe.json`

Execucoes validadas:

- heavy:
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json --splits=test --selection=highest_occlusion --occlusion-buckets=heavy --limit=80`
- moderate:
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json --splits=test --selection=highest_occlusion --occlusion-buckets=moderate --limit=80`
- light:
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/cofw/20260407-234942-cofw-color/manifest.json --splits=test --selection=highest_occlusion --occlusion-buckets=light --limit=80`

Resumo por bucket:

- `heavy`
  - `images_sampled=80`
  - `images_with_successful_detection=6`
  - `annotation_recall_estimated=0.05`
  - `detection_precision_estimated=0.6667`
  - `p95_detect_latency_ms=1330.88`
- `moderate`
  - `images_sampled=80`
  - `images_with_successful_detection=7`
  - `annotation_recall_estimated=0.05`
  - `detection_precision_estimated=0.5714`
  - `p95_detect_latency_ms=1186.52`
- `light`
  - `images_sampled=80`
  - `images_with_successful_detection=16`
  - `annotation_recall_estimated=0.1625`
  - `detection_precision_estimated=0.8125`
  - `p95_detect_latency_ms=1027.03`

Leitura:

- a estratificacao confirma que a oclusao e o principal eixo de degradacao do provider no `COFW`;
- `heavy` e `moderate` ficaram praticamente no mesmo teto ruim de recall;
- o primeiro ganho material so aparece em `light`, e mesmo assim o recall continua baixo para um lane com faces grandes;
- isso reforca que `min_face_size_px` nao e o gargalo dominante nesse conjunto.

## Sweep Real De `min_face_size_px`

Relatorios reais gerados:

- `apps/api/storage/app/face-search-min-face-size-sweep/20260407-232513-face-search-min-face-size-sweep.json`
- `apps/api/storage/app/face-search-min-face-size-sweep/20260407-232615-face-search-min-face-size-sweep.json`
- `apps/api/storage/app/face-search-min-face-size-sweep/20260407-234352-face-search-min-face-size-sweep.json`

Dataset usado:

- `Caltech WebFaces`
- selecao:
  - `smallest_annotated_faces`
- amostra:
  - `20` imagens

### Resultado da passada `48,64,80,96`

Resumo:

- `images_sampled=20`
- `images_with_successful_detection=15`
- `images_failed_or_missing=5`
- `annotated_faces_total=115`
- `detected_faces_total=41`
- `p50_detect_latency_ms=505.2`
- `p95_detect_latency_ms=3829.76`

Threshold breakdown:

- `48`:
  - `detected_faces_gte_threshold=0`
- `64`:
  - `detected_faces_gte_threshold=0`
- `80`:
  - `detected_faces_gte_threshold=0`
- `96`:
  - `detected_faces_gte_threshold=0`

Leitura:

- no lane mais extremo de faces pequenas, `48+` ja zera retencao;
- isso nao quer dizer que o threshold de producao deve ser `16`, mas prova que `96` e inviavel para esse tipo de cena.

### Resultado da passada `16,24,32,40,48,64`

Resumo:

- `images_sampled=20`
- `images_with_successful_detection=15`
- `images_failed_or_missing=5`
- `annotated_faces_total=115`
- `detected_faces_total=41`
- `annotated_to_detected_face_ratio=0.3565`
- `p50_detect_latency_ms=524.71`
- `p95_detect_latency_ms=2823.88`

Threshold breakdown:

- `16`:
  - `detected_faces_gte_threshold=34`
  - `retained_detected_face_rate=0.8293`
  - `retained_detected_image_rate=0.9333`
- `24`:
  - `detected_faces_gte_threshold=4`
  - `retained_detected_face_rate=0.0976`
  - `retained_detected_image_rate=0.2`
- `32`:
  - `detected_faces_gte_threshold=2`
  - `retained_detected_face_rate=0.0488`
  - `retained_detected_image_rate=0.1333`
- `40`:
  - `detected_faces_gte_threshold=1`
  - `retained_detected_face_rate=0.0244`
  - `retained_detected_image_rate=0.0667`
- `48`:
  - `detected_faces_gte_threshold=0`
- `64`:
  - `detected_faces_gte_threshold=0`

Menores faces efetivamente detectadas pelo provider nessa amostra:

- faixa observada:
  - `13 px` ate `44 px`
- muitos casos ficaram entre:
  - `15 px` e `20 px`

Leitura:

- para o lane extremo de face pequena, a faixa operacional util do provider ficou muito abaixo de `48 px`;
- `64` e `96` ficam descartados para esse recorte;
- `48` tambem ficou inutil nesse slice;
- a decisao de producao ainda nao deve sair so desta amostra, porque o recorte foi intencionalmente pessimista.

Conclusao operacional desta rodada:

- precisamos de pelo menos dois sweeps complementares antes de fechar threshold global:
  - `smallest_annotated_faces`
  - `multi_face_density` ou outro recorte menos extremo
- o comando novo ja permite repetir isso sem trabalho manual.

### Resultado da passada `multi_face_density` em `16,24,32,40,48,64`

Resumo:

- `images_sampled=20`
- `images_with_successful_detection=20`
- `images_failed_or_missing=0`
- `annotated_faces_total=418`
- `detected_faces_total=369`
- `annotated_to_detected_face_ratio=0.8828`
- `p50_detect_latency_ms=4725.99`
- `p95_detect_latency_ms=6687`

Threshold breakdown:

- `16`:
  - `detected_faces_gte_threshold=368`
  - `retained_detected_face_rate=0.9973`
  - `retained_detected_image_rate=1.0`
- `24`:
  - `detected_faces_gte_threshold=313`
  - `retained_detected_face_rate=0.8482`
  - `retained_detected_image_rate=0.95`
- `32`:
  - `detected_faces_gte_threshold=198`
  - `retained_detected_face_rate=0.5366`
  - `retained_detected_image_rate=0.75`
- `40`:
  - `detected_faces_gte_threshold=74`
  - `retained_detected_face_rate=0.2005`
  - `retained_detected_image_rate=0.70`
- `48`:
  - `detected_faces_gte_threshold=44`
  - `retained_detected_face_rate=0.1192`
  - `retained_detected_image_rate=0.45`
- `64`:
  - `detected_faces_gte_threshold=21`
  - `retained_detected_face_rate=0.0569`
  - `retained_detected_image_rate=0.15`

Leitura:

- o recorte `multi_face_density` confirma que o slice extremo `smallest_annotated_faces` era pessimista de proposito;
- mesmo em cena densa, `64` continua agressivo demais;
- `48` nao zera a lane, mas ja derruba bastante retencao;
- a faixa que mais merece homologacao operacional agora fica entre `24` e `48`, nao entre `48` e `96`.

## Integracao Local Do `WIDER FACE`

Relatorio real gerado:

- `apps/api/storage/app/face-search-datasets/wider-face/20260408-002256-wider-face/report.json`

Execucao validada:

- comando:
  - `php artisan face-search:load-wider-face --cache-dir=%USERPROFILE%/Desktop/model/tfds-wider-face --splits=validation --selection=dense_annotations --limit=25`

Resumo:

- `entries_exported=25`
- `split_counts.validation=25`
- `valid_annotations_total=9299`
- `invalid_annotations_total=110`
- `p50_valid_annotations_per_image=311`
- `p95_valid_annotations_per_image=668.2`
- `p50_face_span_min_px=4`
- `p95_face_span_min_px=8`

Leitura:

- o `WIDER FACE` entrou como lane oficial de stress com manifestos reais reutilizaveis no app;
- o slice `dense_annotations` e muito mais duro que o acervo local atual;
- esse lane mede o teto operacional do provider para cena densa e rosto pequeno, nao matching de identidade.

Relatorios adicionais gerados:

- `apps/api/storage/app/face-search-datasets/wider-face/20260408-004146-wider-face/report.json`
- `apps/api/storage/app/face-search-datasets/wider-face/20260408-004157-wider-face/report.json`

Resumo dos novos slices:

- `smallest_face`
  - `entries_exported=25`
  - `valid_annotations_total=5171`
  - `p50_valid_annotations_per_image=122`
  - `p95_valid_annotations_per_image=597`
  - `p50_face_span_min_px=3`
  - `p95_face_span_min_px=3`
- `sequential`
  - `entries_exported=25`
  - `valid_annotations_total=838`
  - `p50_valid_annotations_per_image=16`
  - `p95_valid_annotations_per_image=109.2`
  - `p50_face_span_min_px=8`
  - `p95_face_span_min_px=90.4`

Leitura:

- `smallest_face` e o corte mais extremo para stress de escala pura;
- `sequential` aproxima melhor uma amostra menos enviesada da validacao e ja mistura crowd, grupos menores e algumas faces grandes;
- os dois cortes complementam o `dense_annotations` em vez de substitui-lo.

## Probe Real De Deteccao/Densidade Com `WIDER FACE`

Relatorio real gerado:

- `apps/api/storage/app/face-search-detection-probe/20260408-002600-face-search-detection-probe.json`

Execucao validada:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/wider-face/20260408-002256-wider-face/manifest.json`
- comando:
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/wider-face/20260408-002256-wider-face/manifest.json --splits=validation --selection=dense_annotations --limit=10 --iou-threshold=0.20`

Resumo:

- `images_sampled=10`
- `images_with_successful_detection=5`
- `images_failed_or_missing=5`
- `annotated_faces_total=5047`
- `detected_faces_total=97`
- `matched_annotations_total=97`
- `annotation_recall_estimated=0.0192`
- `detection_precision_estimated=1.0`
- `p50_detect_latency_ms=1970.38`
- `p95_detect_latency_ms=9433.51`

Breakdown relevante:

- `face_size_breakdown.small_lt_32`
  - `images=10`
  - `annotation_recall_estimated=0.0192`
- `density_breakdown.crowd_11_plus`
  - `images=10`
  - `annotation_recall_estimated=0.0192`

Leitura:

- o provider detecta poucas faces, mas quando detecta tende a acertar bem nesse slice extremo;
- a precisao estimada ficou alta porque os poucos retornos tiveram `IoU` util;
- o gargalo real nessa lane e recall em crowd denso com rostos de `4-8 px`, nao calibracao fina de threshold.

## Probes Reais Adicionais De `WIDER FACE`

Relatorios reais gerados:

- `apps/api/storage/app/face-search-detection-probe/20260408-004233-face-search-detection-probe.json`
- `apps/api/storage/app/face-search-detection-probe/20260408-004224-face-search-detection-probe.json`

Execucoes validadas:

- `smallest_face`
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/wider-face/20260408-004146-wider-face/manifest.json --splits=validation --selection=smallest_face --limit=10 --iou-threshold=0.20`
- `sequential`
  - `php artisan face-search:probe-detection-dataset --manifest=storage/app/face-search-datasets/wider-face/20260408-004157-wider-face/manifest.json --splits=validation --selection=sequential --limit=10 --iou-threshold=0.20`

Resumo:

- `smallest_face`
  - `images_sampled=10`
  - `images_with_successful_detection=6`
  - `annotated_faces_total=3140`
  - `annotation_recall_estimated=0.0283`
  - `detection_precision_estimated=1.0`
  - `p95_detect_latency_ms=8009.83`
- `dense_annotations`
  - `images_sampled=10`
  - `images_with_successful_detection=5`
  - `annotated_faces_total=5047`
  - `annotation_recall_estimated=0.0192`
  - `detection_precision_estimated=1.0`
  - `p95_detect_latency_ms=9433.51`
- `sequential`
  - `images_sampled=10`
  - `images_with_successful_detection=8`
  - `annotated_faces_total=262`
  - `annotation_recall_estimated=0.1374`
  - `detection_precision_estimated=0.973`
  - `p95_detect_latency_ms=2839.69`

Leitura:

- `smallest_face` continua duro, mas melhora um pouco sobre `dense_annotations` porque remove parte da carga de crowd extremo;
- `sequential` mostra um comportamento bem diferente e mais operacional:
  - ainda longe do ideal;
  - mas com recall muito acima dos cortes extremos;
  - e com latencia bem menor;
- isso confirma que o provider tem dois regimes distintos:
  - collapse em crowd extremo com rostos minimos;
  - desempenho intermediario em cenas mais mistas.

## Sweep Real De `search_threshold`

Relatorio real gerado:

- `apps/api/storage/app/face-search-threshold-sweep/20260408-004251-face-search-threshold-sweep.json`

Execucao validada:

- `php artisan face-search:sweep-search-threshold --smoke-report=storage/app/face-search-smoke/20260407-222703-compreface-real-run.json --thresholds=0.05,0.10,0.15,0.20,0.30,0.40,0.50,0.60 --strategies=exact,ann`

Semantica importante:

- no provider `CompreFace`, a documentacao oficial fala em `similarity threshold`;
- no app, o `search_threshold` atual filtra `pgvector cosine distance` com o operador `<=>`;
- portanto:
  - threshold menor no app = regra mais estrita;
  - `0.5` no app nao significa `0.5 similarity` do provider.

Resumo do sweep:

- `0.05`
  - `exact.top_1_hit_rate=0.0`
  - `ann.top_1_hit_rate=0.0`
- `0.10`
  - `exact.top_1_hit_rate=0.0`
  - `ann.top_1_hit_rate=0.0`
- `0.15`
  - `exact.top_1_hit_rate=0.0`
  - `ann.top_1_hit_rate=0.0`
- `0.20`
  - `exact.top_1_hit_rate=0.5714`
  - `ann.top_1_hit_rate=0.5714`
- `0.30`
  - `exact.top_1_hit_rate=0.7143`
  - `ann.top_1_hit_rate=0.7143`
- `0.40`
  - `exact.top_1_hit_rate=0.8571`
  - `ann.top_1_hit_rate=0.8571`
- `0.50`
  - `exact.top_1_hit_rate=1.0`
  - `ann.top_1_hit_rate=1.0`
  - `false_positive_top_1_rate=0.0`
- `0.60`
  - `exact.top_1_hit_rate=1.0`
  - `ann.top_1_hit_rate=1.0`
  - `false_positive_top_1_rate=0.0`

Recomendacao automatizada do sweep:

- `exact.recommended_threshold=0.5`
- `ann.recommended_threshold=0.5`

Leitura:

- no dataset consentido local atual, `0.5` continua sendo o menor threshold que fecha `top_1=1.0` sem falso positivo;
- `0.6` nao trouxe ganho adicional;
- thresholds abaixo de `0.5` ainda derrubam recall de forma material;
- portanto, o sweep inicial confirma o default atual, nao uma mudanca de config.

## Revalidacao Em Fontes Oficiais

As duvidas de aquisicao e escopo foram rechecadas nas fontes oficiais atuais:

- `COFW`
  - a pagina oficial do `CaltechDATA` continua listando `COFW.zip`, `COFW_color.zip` e `documentation.zip`
  - a descricao oficial continua apontando `1,007 faces`
- `Caltech 10k Web Faces`
  - a pagina oficial do `CaltechDATA` continua descrevendo `10,524 human faces`
- `LFW`
  - a documentacao oficial do `TensorFlow Datasets` continua listando `13,233` exemplos e `download size=172.20 MiB`
- `WIDER FACE`
  - a documentacao oficial do `TensorFlow Datasets` continua listando `32,203` imagens e `393,703` faces anotadas, incluindo campos como `blur`, `occlusion`, `pose`, `illumination` e `invalid`
  - o builder oficial mantido continua apontando para os arquivos oficiais no Google Drive
  - para a integracao local, foi necessario contornar confirmacao do Google Drive em uma parte do download
  - o exportador local passou a usar:
    - os IDs oficiais mantidos no builder
    - `gdown` para os arquivos de imagem
    - o zip oficial de anotacoes exposto pelo loader oficial mantido no `torchvision`
- `CompreFace`
  - a documentacao oficial continua tratando threshold do provider como `similarity threshold`
  - a referencia oficial segue apontando que valores acima de `0.5` sao recomendados para cenarios de maior seguranca
- `pgvector`
  - a documentacao oficial continua listando o operador `<=>` como `cosine distance`
- `CelebA`
  - a pagina oficial continua listando `202,599` imagens e `10,177` identidades
  - a ressalva de politica continua relevante porque a pagina oficial ainda fala em identities `upon request`

Leitura:

- a matriz de aquisicao continua valida;
- `WIDER FACE` deixou de ser apenas proximo passo e passou a estar integrado localmente;
- a calibracao de `search_threshold` precisa respeitar a diferenca entre similaridade do provider e distancia do vetor;
- a ressalva de conformidade do `CelebA` continua necessaria.

## Matriz De Aquisicao Validada Em 2026-04-07

Esta secao converte a analise tecnica em plano de aquisicao para o time.

CritÃ©rio usado:

- rota oficial com download direto;
- ou loader oficial mantido;
- ou acesso oficial `request-only` claramente documentado.

### Lane 1 - identidade e threshold

Prioridade:

- `LFW`
- `CelebA`

Uso:

- calibrar `search_threshold`
- medir `top-1` e `top-5`
- montar pares positivos e negativos mais realistas

Status de aquisicao:

- `LFW`
  - rota valida agora via `TensorFlow Datasets`
  - a documentacao oficial lista `13,233` exemplos de treino e `download size=172.20 MiB`
- `CelebA`
  - rota valida agora pela pagina oficial e pelo loader oficial do `torchvision`
  - a pagina oficial lista `10,177` identidades e `202,599` imagens
  - o loader oficial do `torchvision` continua expondo `identity_CelebA.txt` e demais metadados
  - ressalva importante:
    - a pagina oficial do `CelebA` ainda diz que as identidades sao `released upon request`
    - entao o time deve tratar `CelebA` como utilizavel, mas com observacao de licenca/politica

Leitura:

- `LFW` e o melhor caminho imediato para benchmark rapido de identidade;
- `CelebA` e o melhor caminho de escala para o lane de threshold, mas deve entrar com nota de conformidade.

### Lane 2 - deteccao, face pequena e oclusao

Prioridade:

- `COFW`
- `Caltech 10k Web Faces`
- `WIDER FACE`

Uso:

- calibrar `min_face_size_px`
- medir degradacao por oclusao e pose
- medir custo operacional de cenas densas

Status de aquisicao:

- `COFW`
  - download direto oficial ativo na pagina do `CaltechDATA`
- `Caltech 10k Web Faces`
  - download direto oficial ativo na pagina do `CaltechDATA`
- `WIDER FACE`
  - rota clara via loader oficial do `TensorFlow Datasets`
  - a documentacao oficial lista `32,203` imagens e `393,703` faces
  - o schema oficial exposto no `TFDS` inclui:
    - `bbox`
    - `blur`
    - `occlusion`
    - `pose`
    - `illumination`
    - `invalid`

Leitura:

- essa lane e a mais aderente ao problema real encontrado no app;
- `WIDER FACE` deve entrar porque o app hoje ainda nao foi estressado de verdade com densidade alta e faces pequenas;
- `COFW` continua sendo o melhor conjunto de oclusao com rota oficial limpa.

### Lane 3 - mascara e pares dificeis

Prioridade:

- `RMFD`
- `RMFVD`

Uso:

- testar mascara
- aumentar pares dificeis
- estressar falso positivo com face parcialmente encoberta

Status de aquisicao:

- o repositÃ³rio oficial no GitHub esta ativo
- o README atual continua listando:
  - pasta do Google Drive
  - arquivo real do dataset no Google Drive
  - parte do material no proprio GitHub
- ressalva:
  - a rota existe e e oficial
  - mas a disponibilidade operacional do Google Drive pode variar por sessao ou limite de trafego

Leitura:

- essa lane e boa para sprint operacional;
- nao deve bloquear o resto do plano se o Drive oscilar.

### Request-only oficial

Datasets validos, mas que nao devem bloquear sprint:

- `RFW`
  - a pagina oficial informa que e necessario enviar email institucional e receber senha
  - portanto entra como `request-only oficial`, nao como aquisicao imediata

### Fora do plano principal agora

Datasets que nao valem entrar como dependencia principal neste momento:

- `VGGFace2`
  - a pagina oficial informa que os links de download nao estao mais disponiveis
- `IJB-A`, `IJB-B`, `IJB-C`
  - a pagina da `NIST` informa que a distribuicao foi descontinuada em `March 14, 2023`

### Decisao Executiva Recomendada

Baixar ou integrar agora:

- `COFW`
- `Caltech 10k Web Faces`
- `LFW`
- `WIDER FACE`
- `CelebA` com ressalva de politica/licenca
- `RMFD/RMFVD` como lane paralela nao-bloqueante

Nao bloquear sprint esperando:

- `RFW`
- `VGGFace2`
- `IJB-C`

## O Que Falta Para O Match Ficar Realmente Bem Calibrado

### 1. Separar o problema em 3 lanes de dataset

#### Lane A - matching de identidade

Usar:

- dataset consentido local
- LFW
- CelebA com `identity labels`, com ressalva de politica/licenca da fonte oficial
- eventualmente RMFD/RMFVD para mascara

Objetivo:

- calibrar `search_threshold`
- medir `top-1/top-5`
- medir falso positivo entre pessoas parecidas

#### Lane B - deteccao e quality gate

Usar:

- COFW
- Caltech WebFaces
- WIDER FACE

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

Atualizacao apos sweep real em `smallest_annotated_faces` do `Caltech WebFaces`:

- `48/64/80/96` retiveram `0` faces detectadas na amostra extrema;
- `16` reteve `82.93%` das faces detectadas;
- `24` reteve `9.76%`;
- `32` reteve `4.88%`;
- `40` reteve `2.44%`.

Leitura:

- a hipotese original de testar `48/64/80/96` foi util para descartar extremos ruins, mas a faixa de homologacao agora precisa olhar `24/32/40/48`;
- o lane extremo de faces pequenas exige olhar tambem faixas abaixo de `48`;
- a decisao final de produto precisa combinar `Caltech WebFaces` com `WIDER FACE`, porque o segundo mostra o teto real do provider em crowd muito denso.

Atualizacao apos probes reais de `COFW` e `WIDER FACE`:

- `COFW` mostrou `annotation_recall_estimated=0.05` em slice de alta oclusao, mesmo com faces majoritariamente `>=96 px`;
- `WIDER FACE` mostrou `annotation_recall_estimated=0.0192` em slice de crowd denso com `p50_face_span_min_px=4`;
- isso prova que threshold sozinho nao resolve os casos mais duros;
- parte do gap atual e limite do provider de deteccao nas lanes extremas.

Atualizacao apos probes estratificados e sweep inicial de threshold:

- `COFW light` subiu para `annotation_recall_estimated=0.1625`, enquanto `heavy` e `moderate` ficaram em `0.05`;
- `WIDER FACE sequential` subiu para `annotation_recall_estimated=0.1374`, enquanto `dense_annotations` ficou em `0.0192`;
- o `search_threshold` local atual precisa de `0.5` para fechar `top_1=1.0` no dataset de identidade hoje disponivel;
- portanto:
  - o proximo ganho relevante nao esta em reduzir `search_threshold`;
  - esta em expandir o lane de identidade e seguir homologando o provider nos slices dificeis.

Atualizacao apos lane real de identidade com `XQLFW`:

- foi adicionado o comando:
  - `php artisan face-search:load-xqlfw-local`
- o export real gerou:
  - `32` entradas
  - `8` identidades
  - `4` imagens por identidade
- relatorios reais:
  - smoke dry-run:
    - `apps/api/storage/app/face-search-smoke/20260408-030803-compreface-dry-run.json`
  - smoke real:
    - `apps/api/storage/app/face-search-smoke/20260408-031022-compreface-real-run.json`
  - benchmark:
    - `apps/api/storage/app/face-search-benchmark/20260408-031044-face-search-benchmark.json`
- resultado no benchmark com `threshold=0.5`:
  - `exact.top_1_hit_rate=0.5938`
  - `exact.top_5_hit_rate=0.7813`
  - `exact.false_positive_top_1_rate=0.375`
  - `ann.top_1_hit_rate=0.5938`
  - `ann.top_5_hit_rate=0.7813`
  - `ann.false_positive_top_1_rate=0.375`
  - `exact.p95_search_ms=11.91`
  - `ann.p95_search_ms=158.86`
- leitura:
  - o lane maior derrubou a ilusao de que o baseline de matching ja estava resolvido;
  - o bucket `good` ficou forte:
    - `top_1=1.0`
    - `top_5=1.0`
    - `false_positive_top_1_rate=0.0`
  - os buckets `mixed` e `low_quality` concentraram o colapso:
    - `mixed.top_1=0.125`
    - `low_quality.top_1=0.25`
  - portanto:
    - o gargalo agora nao parece ser `ann` nem latencia de busca;
    - o gargalo esta na robustez do lane de identidade quando a qualidade cai;
    - mexer em `search_threshold` antes de ampliar e estratificar mais esse lane ainda seria prematuro.

Atualizacao apos segundo slice do `XQLFW` e holdouts de `CALFW` / `CFP-FP`:

- o smoke do modulo agora degrada sem abortar quando uma imagem falha na deteccao:
  - `request_outcome=degraded`
  - `verification_checks=[]`
  - a entrada falha fica rastreavel por `error_message`
- isso foi necessario porque lanes mais duras de qualidade e pose nao podem matar o lote inteiro no primeiro miss do provider.

### Lane adicional de identidade - `XQLFW` segundo slice

Export real:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/xqlfw/20260408-032451-xqlfw-original/manifest.json`

Smoke real:

- `apps/api/storage/app/face-search-smoke/xqlfw-second-slice/20260408-033107-411605-compreface-real-run.json`

Benchmark real:

- `apps/api/storage/app/face-search-benchmark/xqlfw-second-slice/20260408-033156-face-search-benchmark.json`

Resultado objetivo:

- `32` entradas no manifesto
- `31` deteccoes validas no smoke
- `1` falha de deteccao
- `exact.top_1_hit_rate=0.6774`
- `exact.top_5_hit_rate=0.7419`
- `exact.false_positive_top_1_rate=0.129`
- `ann.top_1_hit_rate=0.6774`
- `ann.top_5_hit_rate=0.7419`
- `ann.false_positive_top_1_rate=0.129`
- `exact.p95_search_ms=31.69`
- `ann.p95_search_ms=92.36`

Breakdown importante:

- `good.top_1=1.0`
- `good.top_5=1.0`
- `good.false_positive_top_1_rate=0.0`
- `low_quality.top_1=0.375`
- `mixed.top_1=0.2857`

Leitura:

- o segundo slice confirmou o mesmo padrao do primeiro:
  - o pipeline continua forte quando a qualidade esta boa;
  - o colapso real aparece em `mixed` e `low_quality`;
- portanto:
  - ainda nao ha justificativa tecnica para mexer em `search_threshold`;
  - o problema continua sendo robustez do lane de identidade, nao criterio de ranking.

### Holdout de idade - `CALFW`

Export real:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/calfw/20260408-032451-calfw/manifest.json`

Smoke real:

- `apps/api/storage/app/face-search-smoke/calfw-holdout/20260408-033107-504836-compreface-real-run.json`

Benchmark real:

- `apps/api/storage/app/face-search-benchmark/calfw-holdout/20260408-033156-face-search-benchmark.json`

Resultado objetivo:

- `32/32` deteccoes validas
- `exact.top_1_hit_rate=1.0`
- `exact.top_5_hit_rate=1.0`
- `exact.false_positive_top_1_rate=0.0`
- `ann.top_1_hit_rate=1.0`
- `ann.top_5_hit_rate=1.0`
- `ann.false_positive_top_1_rate=0.0`
- `exact.p95_search_ms=37.64`
- `ann.p95_search_ms=86.89`

Leitura:

- no slice local alinhado usado aqui, idade nao foi o principal gargalo;
- `CALFW` entrou como holdout util para evitar overfitting ao `XQLFW`, mas nao abriu um gap novo de matching;
- `exact` continua melhor default.

### Holdout de pose - `CFP-FP`

Export real:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/cfp-fp/20260408-032451-cfp-fp/manifest.json`

Smoke real:

- `apps/api/storage/app/face-search-smoke/cfp-fp-holdout/20260408-033110-030062-compreface-real-run.json`

Benchmark real:

- `apps/api/storage/app/face-search-benchmark/cfp-fp-holdout/20260408-033156-face-search-benchmark.json`

Resultado objetivo:

- `32` entradas no manifesto
- `31` deteccoes validas no smoke
- `1` falha de deteccao em `profile_extreme`
- `exact.top_1_hit_rate=0.9032`
- `exact.top_5_hit_rate=0.9032`
- `exact.false_positive_top_1_rate=0.0323`
- `ann.top_1_hit_rate=0.9032`
- `ann.top_5_hit_rate=0.9032`
- `ann.false_positive_top_1_rate=0.0323`
- `exact.p95_search_ms=49.97`
- `ann.p95_search_ms=98.89`

Breakdown importante:

- `single_prominent.top_1=1.0`
- `single_profile.top_1=0.8`
- `profile_extreme.top_1=0.8`
- `profile_extreme.false_positive_top_1_rate=0.0667`

Leitura:

- `CFP-FP` foi o primeiro holdout local que isolou bem o custo de pose;
- o pipeline continua forte em frontal, mas cai em perfil extremo;
- isso reforca a leitura ja vista no `COFW`: pose/oclusao ainda derrubam recall e matching antes de qualquer limite real de `pgvector`.

### Lane principal de identidade - `LFW`

Aquisicao validada pela fonte oficial do `TensorFlow Datasets` e pela implementacao oficial do builder:

- homepage oficial do dataset:
  - `http://vis-www.cs.umass.edu/lfw`
- o builder atual do `TFDS` usa o espelho:
  - `https://ndownloader.figshare.com/files/5976018`

Artefatos locais:

- `C:\Users\Usuario\Desktop\model\lfw.tgz`
- `C:\Users\Usuario\Desktop\model\extracted\lfw\lfw`

Comando adicionado ao modulo:

- `php artisan face-search:load-lfw-local`

Relatorios reais:

- manifesto:
  - `apps/api/storage/app/face-search-datasets/lfw/20260408-035458-lfw/manifest.json`
- smoke:
  - `apps/api/storage/app/face-search-smoke/lfw-identity-lane/20260408-035540-441914-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/lfw-identity-lane/20260408-035609-face-search-benchmark.json`

Resultado objetivo:

- `72` entradas exportadas
- `12` identidades
- `6` imagens por identidade
- `72/72` deteccoes validas no smoke
- `exact.top_1_hit_rate=1.0`
- `exact.top_5_hit_rate=1.0`
- `exact.false_positive_top_1_rate=0.0`
- `exact.p95_search_ms=43.71`
- `ann.top_1_hit_rate=1.0`
- `ann.top_5_hit_rate=1.0`
- `ann.false_positive_top_1_rate=0.0`
- `ann.p95_search_ms=34.38`

Leitura:

- o `LFW` virou o melhor lane principal de identidade hoje disponivel no ambiente local;
- ao contrario dos slices mais duros de `XQLFW`, aqui o pipeline fechou totalmente sem falso positivo;
- pela primeira vez apareceu um slice real em que `ann` ficou levemente melhor no `p95` do que `exact`, embora sem ganho de acuracia;
- isso ainda nao justifica mudar a estrategia default sozinho, mas ja marca o ponto em que vale reavaliar `exact` versus `ann` no proximo sweep de threshold e estrategia.

### Atualizacao apos sweep real de `search_threshold` com `LFW` como lane principal

Relatorios reais:

- `LFW` principal:
  - `apps/api/storage/app/face-search-threshold-sweep/lfw-identity-lane/20260408-041924-face-search-threshold-sweep.json`
- `XQLFW` regressao de qualidade:
  - `apps/api/storage/app/face-search-threshold-sweep/xqlfw-second-slice/20260408-041950-face-search-threshold-sweep.json`
- `CALFW` regressao de idade:
  - `apps/api/storage/app/face-search-threshold-sweep/calfw-holdout/20260408-042019-face-search-threshold-sweep.json`
- `CFP-FP` regressao de pose:
  - `apps/api/storage/app/face-search-threshold-sweep/cfp-fp-holdout/20260408-042046-face-search-threshold-sweep.json`

Correcao feita no modulo antes da rodada:

- a heuristica de recomendacao do sweep deixou de priorizar `min_false_positive` de forma cega;
- agora ela prioriza:
  - `top_1_hit_rate - false_positive_top_1_rate`
  - depois `false_positive_top_1_rate`
  - depois `top_1`
  - depois `top_5`
  - depois threshold menor
  - depois latencia menor
- isso evita recomendar thresholds inviaveis que apenas zeram os matches.

Recomendacao por dataset com a heuristica corrigida:

- `LFW`:
  - `exact=0.4`
  - `ann=0.4`
- `XQLFW`:
  - `exact=0.45`
  - `ann=0.45`
- `CALFW`:
  - `exact=0.5`
  - `ann=0.5`
- `CFP-FP`:
  - `exact=0.6`
  - `ann=0.6`

Leitura por thresholds mais relevantes para `exact`:

- `0.4`
  - `LFW`: `top_1=1.0`, `FP=0.0`
  - `XQLFW`: `top_1=0.6129`, `FP=0.129`
  - `CALFW`: `top_1=0.9375`, `FP=0.0`
  - `CFP-FP`: `top_1=0.7419`, `FP=0.0323`
- `0.45`
  - `LFW`: `top_1=1.0`, `FP=0.0`
  - `XQLFW`: `top_1=0.6774`, `FP=0.129`
  - `CALFW`: `top_1=0.9688`, `FP=0.0`
  - `CFP-FP`: `top_1=0.8065`, `FP=0.0323`
- `0.5`
  - `LFW`: `top_1=1.0`, `FP=0.0`
  - `XQLFW`: `top_1=0.6774`, `FP=0.129`
  - `CALFW`: `top_1=1.0`, `FP=0.0`
  - `CFP-FP`: `top_1=0.9032`, `FP=0.0323`

Conclusao operacional desta rodada:

- `0.4` fecha o lane principal, mas ainda corta demais `CALFW` e `CFP-FP`;
- `0.45` melhora `XQLFW` e `CFP-FP`, mas ainda nao fecha `CALFW`;
- `0.5` foi o melhor ponto global de compromisso:
  - preserva `LFW` em `1.0`
  - fecha `CALFW` em `1.0`
  - melhora bastante `CFP-FP`
  - nao piora `XQLFW` em relacao a `0.45`
- portanto:
  - `search_threshold=0.5` continua correto como default global do app;
  - `exact` continua a melhor estrategia default quando a decisao e feita pelo conjunto principal + regressao;
  - `ann` nao justificou troca de default no comparativo global, apesar de um ganho pontual no `LFW`.

## Recall De Deteccao E `min_face_size_px` Nas Lanes Duras

Automacao nova desta rodada:

- comando:
  - `php artisan face-search:sweep-manifest-min-face-size --manifest=<manifesto>`
- arquivos principais:
  - `apps/api/app/Modules/FaceSearch/Console/RunManifestFaceSizeThresholdSweepCommand.php`
  - `apps/api/app/Modules/FaceSearch/Services/ManifestFaceSizeThresholdSweepService.php`
  - `apps/api/tests/Feature/FaceSearch/RunManifestFaceSizeThresholdSweepCommandTest.php`

Objetivo:

- usar datasets por manifesto ja exportados no app;
- detectar apenas uma vez por imagem com o provider real;
- recalcular por threshold:
  - `annotation_recall_estimated`
  - `detection_precision_estimated`
  - `retained_detected_face_rate`
  - `retained_detected_image_rate`

Relatorios reais desta rodada:

- `COFW heavy occlusion`
  - `apps/api/storage/app/face-search-manifest-min-face-size-sweep/cofw-heavy/20260408-044516-306758-face-search-manifest-min-face-size-sweep.json`
- `WIDER FACE smallest_face`
  - `apps/api/storage/app/face-search-manifest-min-face-size-sweep/wider-smallest/20260408-044621-050977-face-search-manifest-min-face-size-sweep.json`
- `WIDER FACE dense_annotations`
  - `apps/api/storage/app/face-search-manifest-min-face-size-sweep/wider-dense/20260408-044824-158998-face-search-manifest-min-face-size-sweep.json`

### COFW heavy occlusion

Slice rodado:

- `dataset=cofw`
- `selection=highest_occlusion`
- `splits=test`
- `occlusion_buckets=heavy`
- `sample_size=80`
- thresholds:
  - `16,24,32,40,48,64,96`

Resultado baseline:

- `images_with_successful_detection=6/80`
- `annotation_recall_estimated=0.05`
- `detection_precision_estimated=0.6667`

Threshold breakdown:

- `16`:
  - `recall=0.05`
  - `precision=0.6667`
  - `retained_detected_face_rate=1.0`
- `24`:
  - igual a `16`
- `32`:
  - igual a `16`
- `40`:
  - igual a `16`
- `48`:
  - igual a `16`
- `64`:
  - `recall=0.05`
  - `precision=0.8`
  - `retained_detected_face_rate=0.8333`
- `96`:
  - `recall=0.0375`
  - `precision=0.75`
  - `retained_detected_face_rate=0.6667`

Leitura:

- aqui o problema principal nao e `min_face_size_px`;
- o detector ja falha antes do gate em `74/80` imagens pesadamente ocluidas;
- baixar threshold abaixo de `48` nao recupera recall;
- `64` so melhora precision porque remove um subconjunto pequeno de deteccoes ja raras;
- portanto:
  - `COFW heavy` nao deve ser usado para empurrar o threshold global para baixo;
  - ele prova um limite de recall estrutural do provider sob oclusao pesada.

### WIDER FACE `smallest_face`

Slice rodado:

- `dataset=wider_face`
- manifesto `selection=smallest_face`
- `splits=validation`
- `face_size_buckets=small_lt_32`
- `sample_size=25`
- thresholds:
  - `8,12,16,24,32,40,48`

Resultado baseline:

- `images_with_successful_detection=15/25`
- `annotation_recall_estimated=0.0412`
- `detection_precision_estimated=0.9953`

Threshold breakdown:

- `8`:
  - `recall=0.0412`
  - `precision=0.9953`
  - `retained_detected_face_rate=1.0`
- `12`:
  - igual a `8`
- `16`:
  - igual a `8`
- `24`:
  - `recall=0.0348`
  - `precision=0.9945`
  - `retained_detected_face_rate=0.8458`
- `32`:
  - `recall=0.0195`
  - `precision=1.0`
  - `retained_detected_face_rate=0.472`
- `40`:
  - `recall=0.0133`
  - `precision=1.0`
  - `retained_detected_face_rate=0.3224`
- `48`:
  - `recall=0.0101`
  - `precision=1.0`
  - `retained_detected_face_rate=0.243`

Leitura:

- no corte extremo de rosto muito pequeno, a faixa util ficou em `8-16`;
- `24` ja derruba recall de forma relevante;
- `32+` vira amputacao forte de cobertura.

### WIDER FACE `dense_annotations`

Slice rodado:

- `dataset=wider_face`
- manifesto `selection=dense_annotations`
- `splits=validation`
- `density_buckets=crowd_11_plus`
- `sample_size=25`
- thresholds:
  - `8,12,16,24,32,40,48`

Resultado baseline:

- `images_with_successful_detection=15/25`
- `annotation_recall_estimated=0.022`
- `detection_precision_estimated=0.9951`

Threshold breakdown:

- `8`:
  - `recall=0.022`
  - `precision=0.9951`
  - `retained_detected_face_rate=1.0`
- `12`:
  - igual a `8`
- `16`:
  - igual a `8`
- `24`:
  - `recall=0.0176`
  - `precision=0.9939`
  - `retained_detected_face_rate=0.801`
- `32`:
  - `recall=0.0055`
  - `precision=1.0`
  - `retained_detected_face_rate=0.2476`
- `40`:
  - `recall=0.004`
  - `precision=1.0`
  - `retained_detected_face_rate=0.1796`
- `48`:
  - `recall=0.0024`
  - `precision=1.0`
  - `retained_detected_face_rate=0.1068`

Leitura:

- crowd extremo confirmou o mesmo comportamento do slice de `smallest_face`;
- `24` ja corta cerca de `20%` das deteccoes retidas;
- `32+` colapsa recall.

### WIDER FACE `sequential`

Slice rodado:

- `dataset=wider_face`
- manifesto `selection=sequential`
- `splits=validation`
- `sample_size=25`
- thresholds:
  - `8,12,16,24,32,40,48`

Relatorio real:

- `apps/api/storage/app/face-search-manifest-min-face-size-sweep/wider-sequential/20260408-045653-963066-face-search-manifest-min-face-size-sweep.json`

Resultado baseline:

- `images_with_successful_detection=18/25`
- `annotation_recall_estimated=0.1241`
- `detection_precision_estimated=0.972`

Threshold breakdown:

- `8`:
  - `recall=0.1241`
  - `precision=0.972`
  - `retained_detected_face_rate=1.0`
- `12`:
  - igual a `8`
- `16`:
  - igual a `8`
- `24`:
  - comecou a cortar deteccoes em cenas moderadas
  - em imagens com min-side detectado entre `20-27 px`, a retencao cai localmente
- `32+`:
  - continua agressivo demais nos casos menores

Leitura:

- esse slice e mais proximo de um regime operacional misto que `smallest_face` e `dense_annotations`;
- mesmo nele, `16` continua neutro;
- `24` ja nao e totalmente neutro;
- isso reforca `24` como compromisso e `16` como faixa de preservacao.

### Lane real do produto via smoke `vipsocial`

Automacao nova desta rodada:

- comando:
  - `php artisan face-search:analyze-smoke-min-face-size --smoke-report=<relatorio>`
- arquivos principais:
  - `apps/api/app/Modules/FaceSearch/Console/RunSmokeMinFaceSizeAnalysisCommand.php`
  - `apps/api/app/Modules/FaceSearch/Services/SmokeMinFaceSizeAnalysisService.php`
  - `apps/api/tests/Feature/FaceSearch/RunSmokeMinFaceSizeAnalysisCommandTest.php`

Relatorio real:

- `apps/api/storage/app/face-search-smoke-min-face-size-analysis/vipsocial/20260408-045947-772514-face-search-smoke-min-face-size-analysis.json`

Smoke usado:

- `apps/api/storage/app/face-search-smoke/20260407-222703-compreface-real-run.json`

Resultado baseline:

- `entries_successful=7/7`
- `selected_face_min_side_px_min=217`
- `selected_face_min_side_px_p50=337`
- `selected_face_min_side_px_max=603`

Threshold breakdown relevante:

- `16` ate `192`:
  - `retained_entry_rate=1.0`
  - `retained_person_rate=1.0`
- `224`:
  - `retained_entry_rate=0.8571`
  - o primeiro corte aparece em:
    - `scene_type=conversation_group`
    - `quality_label=profile_extreme`
- `256`:
  - mesmo comportamento de `224` nesse smoke

Leitura:

- o lane consentido real do produto nao pressiona `16` versus `24`;
- nesse acervo atual, ambos sao completamente neutros;
- o smoke local inclusive suportou `192` sem perder nenhuma entrada;
- isso confirma que a decisao global do threshold deve ser puxada por `WIDER FACE` / `Caltech WebFaces` e por futuros lanes reais com rosto menor, nao pelo `vipsocial` atual.

Conclusao operacional desta rodada:

- existe assimetria clara entre as lanes duras:
  - `COFW heavy` sofre por limite do detector, nao do gate de tamanho;
  - `WIDER FACE` sofre diretamente com threshold agressivo de `min_face_size_px`
- o lane real consentido atual do produto nao limita a decisao:
  - ele continua confortavel muito acima de `24`
- leitura consolidada:
  - se o objetivo for preservar recall nas lanes mais duras de rosto pequeno, o threshold operacional precisa ficar em `8-16`;
  - `24` ja e um compromisso mais conservador que custa recall real;
  - `32+` nao e aceitavel para stress extremo de crowd/tiny faces;
  - `COFW heavy` nao justifica descer threshold, porque o gargalo ali nao responde a isso.

Recomendacao profissional neste ponto:

- nao usar um unico slice para decidir o threshold global;
- manter a homologacao global baseada em leitura conjunta de:
  - `Caltech WebFaces`
  - `WIDER FACE`
  - lanes reais do produto
- tratar o resultado desta rodada assim:
  - `8-16` = faixa de preservacao de recall extremo
  - `24` = ponto de compromisso mais defensavel se o time quiser conter ruÃ­do sem matar tanto recall
  - `32+` = alto risco de cortar deteccao demais em evento com grupo/crowd

### Homologacao final do default `min_face_size_px=24`

Depois da calibracao em `Caltech WebFaces`, `WIDER FACE`, `COFW` e no lane real `vipsocial`, o modulo foi alinhado para:

- `min_face_size_px=24`
- `search_threshold=0.5`
- `search_strategy=exact`

Arquivos ajustados:

- `apps/api/config/face_search.php`
- `apps/api/app/Modules/FaceSearch/Models/EventFaceSearchSetting.php`
- `apps/api/app/Modules/FaceSearch/Actions/UpsertEventFaceSearchSettingsAction.php`
- `apps/api/app/Modules/FaceSearch/Http/Requests/UpsertEventFaceSearchSettingsRequest.php`
- `apps/api/.env.example`
- `apps/web/src/modules/events/components/face-search/EventFaceSearchSettingsForm.tsx`

Validacao real final:

- smoke:
  - `apps/api/storage/app/face-search-smoke/20260408-050904-755889-compreface-real-run.json`
- benchmark:
  - `apps/api/storage/app/face-search-benchmark/20260408-050914-face-search-benchmark.json`
- lane throughput:
  - `apps/api/storage/app/face-search-lane-throughput/20260408-050948-face-index-lane-throughput.json`

Resultado consolidado:

- smoke:
  - `request_outcome=success`
  - `verification_checks` positivos e negativos coerentes
- benchmark:
  - `exact.top_1_hit_rate=1.0`
  - `exact.top_5_hit_rate=1.0`
  - `exact.false_positive_top_1_rate=0.0`
  - `exact.p95_search_ms=26.93`
  - `ann.top_1_hit_rate=1.0`
  - `ann.top_5_hit_rate=1.0`
  - `ann.false_positive_top_1_rate=0.0`
  - `ann.p95_search_ms=17.92`
  - `operational_summary.p95_detect_ms=281`
  - `operational_summary.p95_embed_ms=221`
  - `operational_summary.throughput_face_index_per_minute=29.65`
- lane throughput:
  - `jobs_completed=7`
  - `jobs_failed=0`
  - `jobs_skipped_or_missing=0`
  - `throughput_face_index_per_minute=16.08`
  - `p50_run_duration_ms=2000`
  - `p95_run_duration_ms=6000`

Leitura:

- o default `24` ficou homologado sem regressao no lane real consentido atual;
- a calibracao dura continua dizendo que `16` preserva mais recall extremo;
- mesmo assim, `24` permaneceu o melhor compromisso profissional entre recall e ruido para o default global atual;
- `search_threshold` nao precisou mudar e `exact` continua o default correto no modulo.

### Expansao dos lanes reais com rosto pequeno

Nesta rodada, o app passou a integrar `Caltech WebFaces` no mesmo pipeline de `manifest + probe` ja usado por `COFW` e `WIDER FACE`.

Automacao nova:

- comando:
  - `php artisan face-search:load-caltech-webfaces-local`
- arquivos principais:
  - `apps/api/app/Modules/FaceSearch/Console/RunCaltechWebFacesLocalLoaderCommand.php`
  - `apps/api/app/Modules/FaceSearch/Services/CaltechWebFacesLocalLoaderService.php`
  - `apps/api/app/Modules/FaceSearch/Support/export_caltech_webfaces.py`
  - `apps/api/tests/Feature/FaceSearch/RunCaltechWebFacesLocalLoaderCommandTest.php`

Observacao importante:

- `Caltech WebFaces` traz landmarks e nao bbox completas;
- por isso o exporter estima bbox via `landmark_envelope_v1`;
- isso e adequado para `probe` de deteccao e calibracao de lanes pequenos, mas deve ser tratado como aproximacao de anotacao.

Manifestos reais novos:

- `Caltech sequential`:
  - `apps/api/storage/app/face-search-datasets/caltech-webfaces/20260408-060048-caltech-webfaces/manifest.json`
- `Caltech multi_face_density`:
  - `apps/api/storage/app/face-search-datasets/caltech-webfaces/20260408-060106-caltech-webfaces/manifest.json`
- `WIDER sequential expandido`:
  - `apps/api/storage/app/face-search-datasets/wider-face/20260408-060105-wider-face/manifest.json`

Probes reais novos:

- `Caltech sequential product`:
  - `apps/api/storage/app/face-search-detection-probe/caltech-sequential-product/20260408-060252-face-search-detection-probe.json`
- `Caltech dense small`:
  - `apps/api/storage/app/face-search-detection-probe/caltech-dense-small/20260408-060837-face-search-detection-probe.json`
- `WIDER sequential product`:
  - `apps/api/storage/app/face-search-detection-probe/wider-sequential-product/20260408-060952-face-search-detection-probe.json`
- `WIDER sequential crowd small`:
  - `apps/api/storage/app/face-search-detection-probe/wider-sequential-crowd-small/20260408-061155-face-search-detection-probe.json`

Resultado consolidado:

- `Caltech sequential product`:
  - `images_sampled=80`
  - `annotation_recall_estimated=0.9688`
  - `detection_precision_estimated=0.8087`
  - `p95_detect_latency_ms=2049.4`
- `Caltech dense small`:
  - `images_sampled=60`
  - `annotation_recall_estimated=0.8407`
  - `detection_precision_estimated=0.881`
  - `p95_detect_latency_ms=9247.89`
- `WIDER sequential product`:
  - `images_sampled=19`
  - `annotation_recall_estimated=0.5833`
  - `detection_precision_estimated=0.9859`
  - `p95_detect_latency_ms=3632.33`
- `WIDER sequential crowd small`:
  - `images_sampled=30`
  - `annotation_recall_estimated=0.1217`
  - `detection_precision_estimated=0.9864`
  - `p95_detect_latency_ms=9637.3`

Leitura:

- `Caltech sequential` virou um lane pequeno mais aderente ao produto do que `WIDER smallest_face`;
- nesse lane, o detector esta forte em recall, mas ainda gera ruido visivel em grupos pequenos;
- `Caltech dense small` mostra que, quando a densidade sobe, o recall continua razoavel, mas a latencia cresce muito;
- `WIDER sequential product` confirma que o detector ainda preserva precision alta, mas perde recall material em grupos pequenos/densos com face pequena;
- `WIDER sequential crowd small` continua sendo o hard ceiling atual do provider: precision alta, recall muito baixo e varias falhas de `no face found` ou timeout.

### Avaliacao da estrutura atual do reconhecimento facial

Estado atual da estrutura:

- o modulo esta tecnicamente bem organizado:
  - provider facial desacoplado por interface
  - embedding desacoplado por interface
  - vector store desacoplado por interface
  - indexacao assincrona por job e fila dedicada
  - busca limitada por `event_id`
  - suite de smoke, benchmark, throughput, sweep e probes reais
- o stack de observabilidade melhorou bastante:
  - manifestos locais versionados
  - relatorios reais por slice
  - breakdowns por `scene_type`, `quality_label`, densidade e tamanho facial
- a maior fragilidade estrutural ainda nao esta na busca vetorial:
  - esta na deteccao primaria
  - e na semantica fraca do `qualityScore`

Pontos fortes:

- separacao limpa entre `detect`, `embed`, `quality gate` e `search`
- `pgvector` bem encapsulado e com estrategia `exact|ann` controlada
- toolchain de calibracao local hoje e madura o bastante para regressao repetivel
- lanes de identidade e holdouts principais ja estao montados

Gargalos reais:

- `CompreFaceDetectionProvider` ainda usa `qualityScore = probability`, o que continua insuficiente para quality gate serio
- o modulo ainda depende de um unico detector real; nao existe fallback operacional para crowd/tiny faces
- em lanes densos pequenos, o provider mistura dois sintomas:
  - `400 No face is found`
  - `cURL error 28` por timeout
- `Caltech WebFaces` agora entrou no pipeline de probe, mas sua bbox continua inferida e nao anotada nativamente
- o lane real consentido do produto ainda e pequeno e enviesado para rosto grande

Conclusao estrutural:

- a arquitetura do modulo esta boa e profissional para MVP e para calibracao controlada;
- a parte menos madura hoje nao e o desenho do codigo, e sim a robustez do detector escolhido para os regimes de crowd/tiny faces;
- a proxima evolucao estrutural correta nao e mexer em `search_threshold`;
- e fortalecer o bloco de deteccao/quality antes de qualquer nova reabertura de threshold global.

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

## Validacao Pratica Em Galeria Real

Para sair do regime de dataset curado e medir comportamento em evento real, foi executado o organizador local de galeria por embedding em:

- origem:
  - `C:\Users\Usuario\Desktop\ddddd\FINAL`
- saida:
  - `C:\Users\Usuario\Desktop\ddddd\AGRUPADO_POR_PESSOA\20260408-full-002`

Comando usado:

- `php artisan face-search:organize-local-gallery --input-dir="C:\Users\Usuario\Desktop\ddddd\FINAL" --output-dir="C:\Users\Usuario\Desktop\ddddd\AGRUPADO_POR_PESSOA\20260408-full-002" --cluster-threshold=0.35 --min-face-size=24 --min-quality-score=0.6`

Leitura do run real:

- `images_total=963`
- `images_clustered=852`
- `images_without_faces=111`
- `images_failed_or_invalid=0`
- `faces_accepted_total=2402`
- `clusters_total=161`
- `multi_person_images=638`
- `max_person_folders_per_image=16`

Leitura:

- o pipeline aguentou um evento real grande sem falha operacional;
- o ajuste para tratar `No face is found` como `no_face` estava correto;
- a estrategia de materializacao por `hard link` no mesmo volume foi necessaria para nao explodir uso de disco;
- o provider esta funcional para organizar material real por pessoa, inclusive em fotos de grupo.

Sinais positivos:

- `0` falhas tecnicas na rodada completa;
- cobertura alta de fotos com pessoa (`852/963`);
- fotos de grupo realmente entram em mais de uma pasta por pessoa;
- o app consegue produzir uma estrutura validavel manualmente sem mexer em `search_threshold`.

Sinais de atencao:

- `161` clusters para `2402` faces aceitas ainda indicam fragmentacao relevante nas caudas;
- `67` clusters ficaram com apenas `1` imagem;
- os maiores clusters ficaram muito grandes (`571`, `218`, `165`, `147` imagens) e exigem revisao visual para confirmar se houve fusao de pessoas parecidas;
- isso reforca que o proximo gargalo pratico e robustez de deteccao/embedding em qualidade variavel, nao ajuste do motor vetorial.

## Proxima Sequencia Recomendada

1. manter `LFW` como lane principal fixo para regressao de identidade e futuros sweeps
2. manter `XQLFW`, `CALFW` e `CFP-FP` como gate de regressao de qualidade, idade e pose
3. manter `search_threshold=0.5` como default global ate aparecer evidencia melhor com lane ainda maior
4. manter `exact` como estrategia default global; `ann` so deve voltar a pauta se ganhar no conjunto principal + regressao, nao apenas em um slice isolado
5. manter `Caltech WebFaces` e `WIDER sequential` no pipeline de probes reais e ampliar apenas se entrar lane consentido novo de rosto pequeno
6. manter `min_face_size_px=24` como default e usar leitura em tres bandas quando entrar lane novo:
   - `8/16` para preservacao de recall extremo
   - `24` como compromisso homologado atual
   - evitar `32+` como default enquanto crowd/tiny faces continuarem alvo relevante
7. usar `COFW heavy` para medir limite do detector e nao como argumento isolado para baixar threshold
8. nao recalibrar `min_quality_score` como se ele fosse quality real antes de corrigir o proxy

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
- limite de recall do provider em oclusao pesada e crowd com rosto muito pequeno
- ausencia de um threshold unico que resolva ao mesmo tempo oclusao pesada e tiny faces sem tradeoff

Mesmo assim, existe uma decisao operacional fechada nesta fase:

- `min_face_size_px=24` ficou homologado como default global atual do modulo;
- `search_threshold=0.5` continua correto;
- `exact` continua a estrategia default.

Se a proxima iteracao fizer apenas uma coisa, a melhor aposta e:

- fortalecer o bloco de deteccao e quality para crowd/tiny faces, mantendo a regressao fixa em `LFW`, `XQLFW`, `CALFW` e `CFP-FP`, sem reabrir `search_threshold` nem `min_face_size_px` sem evidencia nova.
