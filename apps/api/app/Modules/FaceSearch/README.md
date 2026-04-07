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

Comando local de benchmark:

- `php artisan face-search:benchmark --smoke-report=<caminho-do-relatorio-real>`

O benchmark usa um smoke report real como fonte de embeddings e salva relatorios em `storage/app/face-search-benchmark/`.
