# FaceSearch Module

## Responsabilidade

Este modulo concentra a configuracao, a indexacao facial por evento e a base vetorial inicial para busca por pessoa.

Na fase atual ele cobre:

- configuracao por evento para habilitar ou desligar `FaceSearch`;
- quality gate facial por tamanho e score;
- tiers operacionais de qualidade (`reject`, `index_only`, `search_priority`);
- indexacao non-blocking por `IndexMediaFacesJob`;
- persistencia de `event_media_faces` com bbox, crop privado, embedding e metadados de qualidade;
- porta vetorial inicial em `pgvector`, preservando o dominio desacoplado por `FaceVectorStoreInterface`;
- busca por selfie no backoffice por `/events/{event}/face-search/search`;
- bootstrap e busca publica por `/public/events/{slug}/face-search` e `/public/events/{slug}/face-search/search`;
- auditoria de requests em `event_face_search_requests`, com consentimento e retention por evento.

Rotas atuais:

- `GET /api/v1/events/{event}/face-search/settings`
- `PATCH /api/v1/events/{event}/face-search/settings`
- `POST /api/v1/events/{event}/face-search/search`
- `GET /api/v1/public/events/{slug}/face-search`
- `POST /api/v1/public/events/{slug}/face-search/search`

## Nao responsabilidade

Este modulo nao:

- aprova ou reprova nudez, violencia ou safety;
- decide caption ou regra semantica do VLM;
- publica midia.

## Contrato Atual

Configuracao por evento:

- `enabled`
- `provider_key`
- `embedding_model_key`
- `vector_store_key`
- `search_strategy`
- `min_face_size_px`
- `min_quality_score`
- `quality_tier`
- `search_threshold`
- `top_k`
- `allow_public_selfie_search`
- `selfie_retention_hours`

Default homologado atual do modulo:

- `min_face_size_px=24`
- `search_threshold=0.5`
- `search_strategy=exact`

## Integracao Atual

- `Events` usa `UpsertEventFaceSearchSettingsAction` para persistir configuracao no create/update e no endpoint dedicado `/events/{event}/face-search/settings`;
- `MediaProcessing` decide `face_index_status=queued/skipped` no pipeline base;
- `RunModerationJob` dispara `IndexMediaFacesJob` no heavy lane quando o evento tem `FaceSearch` habilitado;
- `ApproveEventMediaAction` e `RejectEventMediaAction` religam ou desligam `searchable` das faces indexadas;
- `EventMediaDetailResource` expoe `indexed_faces_count` para o backoffice;
- `SearchFacesBySelfieAction` valida a selfie, gera embedding da consulta e limita a busca ao `event_id`;
- `CollapseFaceSearchMatchesQuery` colapsa resultados por `event_media_id`;
- `EventFaceSearchRequestResource` e `FaceSearchMatchResource` padronizam o retorno para UI interna e publica.

## Jobs

| Job | Fila | Responsabilidade |
|-----|------|------------------|
| `IndexMediaFacesJob` | `face-index` | Detectar faces, aplicar quality gate, salvar crops privados e persistir embeddings por face |

## Dependencias

- `Events`
- `MediaProcessing`

## CompreFace Operacional

Para o provider `compreface`, o app aceita:

- `FACE_SEARCH_COMPRE_FACE_BASE_URL`
- `FACE_SEARCH_COMPRE_FACE_DETECTION_API_KEY`
- `FACE_SEARCH_COMPRE_FACE_VERIFICATION_API_KEY`
- `FACE_SEARCH_COMPRE_FACE_API_KEY` como fallback generico quando as chaves dedicadas ainda nao foram separadas

Regras praticas desta fase:

- `FACE_SEARCH_COMPRE_FACE_BASE_URL` deve apontar para a instancia real do `CompreFace`, nao para o servidor web do Evento Vivo;
- deteccao/indexacao usam `POST /api/v1/detection/detect`;
- smoke e QA podem usar `POST /api/v1/verification/embeddings/verify`;
- o payload principal deve preferir base64;
- arquivos acima de 5 MB precisam de derivado menor para smoke no CompreFace.

Comando local de smoke:

- `php artisan face-search:smoke-compreface --dry-run`
- `php artisan face-search:smoke-compreface`

O comando usa `tests/Fixtures/AI/local/vipsocial.manifest.json` por padrao e salva relatorios em `storage/app/face-search-smoke/`.

Quando uma ou mais imagens do manifesto falham na deteccao, o comando nao aborta mais o lote inteiro:

- salva o relatorio como `request_outcome=degraded`
- marca a entrada falha com `request_outcome=failed` + `error_message`
- pula `verification_checks` nessa rodada para nao misturar leitura de matching com lane degradado

Comando local de benchmark:

- `php artisan face-search:benchmark --smoke-report=<caminho-do-relatorio-real>`

O benchmark usa um smoke report real como fonte de embeddings e salva relatorios em `storage/app/face-search-benchmark/`.

Comando local de throughput real do lane de indexacao:

- `php artisan face-search:lane-throughput`

O comando executa o lane real com worker subprocessado, fila isolada e salva relatorios em `storage/app/face-search-lane-throughput/`.

Homologacao real mais recente do default atual:

- smoke:
  - `storage/app/face-search-smoke/20260408-050904-755889-compreface-real-run.json`
- benchmark:
  - `storage/app/face-search-benchmark/20260408-050914-face-search-benchmark.json`
- lane throughput:
  - `storage/app/face-search-lane-throughput/20260408-050948-face-index-lane-throughput.json`

Leitura:

- `min_face_size_px=24` nao gerou regressao no smoke local consentido;
- `search_threshold=0.5` segue com `top_1=1.0`, `top_5=1.0` e `false_positive_top_1_rate=0` no benchmark real;
- o lane real concluiu `7/7` jobs com `throughput_face_index_per_minute=16.08`.

Comando local de sweep para `min_face_size_px`:

- `php artisan face-search:sweep-min-face-size`

O comando usa por padrao o `Caltech WebFaces` extraido em `%USERPROFILE%/Desktop/model/extracted/caltech_webfaces` e salva relatorios em `storage/app/face-search-min-face-size-sweep/`.

Comando local para exportar o `Caltech WebFaces` em imagens + manifesto:

- `php artisan face-search:load-caltech-webfaces-local`

Por padrao ele usa o `Caltech WebFaces` extraido em `%USERPROFILE%/Desktop/model/extracted/caltech_webfaces`, le `WebFaces_GroundThruth.txt` em `%USERPROFILE%/Desktop/model/`, exporta um slice reutilizavel para `storage/app/face-search-datasets/caltech-webfaces/` e gera `manifest.json` + `report.json`.

Importante:

- o dataset original nao traz bbox prontas;
- o exporter estima `bbox` a partir dos landmarks de olhos, nariz e boca usando `landmark_envelope_v1`;
- esse manifesto e adequado para probe de deteccao, face pequena e densidade, nao para benchmark principal de identidade.

Comando local para exportar o `COFW` em imagens + manifesto:

- `php artisan face-search:load-cofw-local`

Por padrao ele usa o `COFW_color` extraido em `%USERPROFILE%/Desktop/model/extracted/cofw_color`, exporta `500` imagens de treino COFW mais `507` imagens de teste para `storage/app/face-search-datasets/cofw/` e gera `manifest.json` + `report.json`.

Para o pacote em tons de cinza, use `--variant=gray`, que aponta para `%USERPROFILE%/Desktop/model/extracted/cofw_gray/common/xpburgos/behavior/code/pose`.

Comando local para probe real de deteccao por manifesto:

- `php artisan face-search:probe-detection-dataset --manifest=<caminho-do-manifesto>`

O comando usa o provider facial do app, mede `annotation_recall_estimated`, `detection_precision_estimated`, latencia e breakdowns por `split`, oclusao, tamanho e densidade.

Para probes estratificados, o comando aceita filtros por bucket:

- `--occlusion-buckets=light,moderate,heavy`
- `--face-size-buckets=small_lt_32,medium_32_63,large_64_95,xlarge_gte_96`
- `--density-buckets=single,group_2_5,dense_6_10,crowd_11_plus`

Comando local para sweep real de `min_face_size_px` em datasets por manifesto:

- `php artisan face-search:sweep-manifest-min-face-size --manifest=<caminho-do-manifesto>`

Esse comando usa o mesmo manifesto do probe, detecta uma vez por imagem com o provider real do app e recalcula por threshold:

- `annotation_recall_estimated`
- `detection_precision_estimated`
- `retained_detected_face_rate`
- `retained_detected_image_rate`

Ele tambem aceita os mesmos filtros estratificados:

- `--occlusion-buckets=light,moderate,heavy`
- `--face-size-buckets=small_lt_32,medium_32_63,large_64_95,xlarge_gte_96`
- `--density-buckets=single,group_2_5,dense_6_10,crowd_11_plus`

E devolve uma recomendacao por regra operacional:

- maximiza a media harmonica entre `annotation_recall_estimated` e `detection_precision_estimated`
- depois maximiza recall
- depois precision
- depois retencao por imagem
- so entao prefere threshold menor

Comando local para exportar um slice oficial do `WIDER FACE`:

- `php artisan face-search:load-wider-face`

Por padrao ele exporta um slice de `validation` com foco em `dense_annotations` para `storage/app/face-search-datasets/wider-face/`.

Comando local para exportar um lane de identidade a partir do `XQLFW`:

- `php artisan face-search:load-xqlfw-local`

Por padrao ele usa o `XQLFW` original extraido em `%USERPROFILE%/Desktop/model/extracted/xqlfw/lfw_original_imgs_min_qual0.85variant11`, cruza `xqlfw_scores.txt` e `xqlfw_pairs.txt`, copia um slice para `storage/app/face-search-datasets/xqlfw/` e gera um manifesto reutilizavel por `smoke` e `benchmark`.

Opcoes principais:

- `--variant=original|aligned_112`
- `--selection=official_pairs|highest_quality|sequential`
- `--image-selection=score_spread|top_score|sequential`
- `--offset=0`
- `--people=12`
- `--images-per-person=4`

Comando local para exportar um lane maior de identidade a partir do `LFW`:

- `php artisan face-search:load-lfw-local`

Por padrao ele usa `%USERPROFILE%/Desktop/model/extracted/lfw/lfw`, exporta um slice por identidade para `storage/app/face-search-datasets/lfw/` e gera manifesto reutilizavel por `smoke` e `benchmark`.

Opcoes principais:

- `--selection=largest_identities|sequential`
- `--image-selection=spread|sequential`
- `--offset=0`
- `--people=12`
- `--images-per-person=4`
- `--min-images-per-person=4`

Comando local para exportar um holdout de idade a partir do `CALFW`:

- `php artisan face-search:load-calfw-local`

Por padrao ele usa `%USERPROFILE%/Desktop/model/extracted/calfw/calfw/aligned images`, exporta um slice de identidades para `storage/app/face-search-datasets/calfw/` e gera manifesto reutilizavel por `smoke` e `benchmark`.

Opcoes principais:

- `--selection=largest_identities|sequential`
- `--image-selection=spread|sequential`
- `--offset=0`
- `--people=12`
- `--images-per-person=4`

Comando local para exportar um holdout de pose a partir do `CFP-FP`:

- `php artisan face-search:load-cfp-fp-local`

Por padrao ele usa `%USERPROFILE%/Desktop/model/extracted/cfp_fp/cfp-dataset/Data/Images`, exporta imagens frontais e de perfil para `storage/app/face-search-datasets/cfp-fp/` e gera manifesto reutilizavel por `smoke` e `benchmark`.

Opcoes principais:

- `--image-selection=spread|sequential`
- `--offset=0`
- `--people=12`
- `--frontal-per-person=2`
- `--profile-per-person=2`

Comando local para sweep real de `search_threshold`:

- `php artisan face-search:sweep-search-threshold --smoke-report=<caminho-do-relatorio-real>`

Esse comando testa multiplos thresholds de distancia no `pgvector`, reutiliza o benchmark real e devolve uma recomendacao por estrategia.

Regra atual da recomendacao:

- prioriza `top_1_hit_rate - false_positive_top_1_rate`
- depois minimiza `false_positive_top_1_rate`
- depois maximiza `top_1` e `top_5`
- so depois usa threshold menor e latencia menor como desempate

Isso evita recomendar thresholds triviais que zeram falso positivo apenas porque tambem zeram todos os matches.

Comando local para analisar `min_face_size_px` sobre um smoke report real:

- `php artisan face-search:analyze-smoke-min-face-size --smoke-report=<caminho-do-relatorio-real>`

Esse comando nao usa anotacao externa. Ele lê o `selected_face_bbox` do smoke report e mede por threshold:

- `retained_entry_rate`
- `retained_person_rate`
- breakdown por `scene_type`
- breakdown por `quality_label`

Uso pratico:

- medir se o lane consentido/local do produto realmente pressiona `min_face_size_px`
- confirmar se thresholds como `16` e `24` sao neutros ou nao para o smoke real

Importante:

- a doc oficial do `CompreFace` fala em `similarity threshold`;
- no app, o `search_threshold` atual filtra `cosine distance` no `pgvector`;
- portanto `0.5` no provider e `0.5` no app nao tem a mesma semantica.
