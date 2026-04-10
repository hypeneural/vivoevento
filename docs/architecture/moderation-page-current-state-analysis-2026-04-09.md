# /moderation current state analysis - 2026-04-09

## Objetivo

Este documento consolida o estado real da rota `/moderation` no monorepo `eventovivo`, com foco em:

- como a pagina funciona hoje;
- como a lista e carregada;
- como as imagens sao resolvidas;
- como a paginacao e a ordenacao funcionam;
- como os filtros e as acoes de moderacao funcionam;
- por que a UX atual passa sensacao de "pisca", "recarrega" ou fica carregando;
- o que precisa melhorar para performance, estabilidade visual e produtividade operacional.

Este material foi feito por leitura de codigo. Ele nao substitui profiling de runtime com DevTools, SQL EXPLAIN ANALYZE e medicao real de rede.

Plano derivado desta analise:

- `docs/architecture/moderation-page-execution-plan-2026-04-09.md`

## Stack relevante

### Frontend

Stack base do painel:

- React 18
- TypeScript
- Vite 5
- TailwindCSS 3
- shadcn/ui + Radix UI

Stack efetivamente usada pela rota `/moderation` e pelos fluxos correlatos:

- TanStack Query para feed cursorizado, detalhe e mutations;
- React Router para a rota e filtros por query string;
- data router com `createBrowserRouter`, `RouterProvider` e `ScrollRestoration` configurado no shell;
- Framer Motion para entradas/transicoes do painel;
- lucide-react para iconografia operacional;
- Pusher JS + Laravel Reverb para realtime;
- grade virtualizada custom em `ModerationVirtualGrid`;
- componentes compartilhados de badges, stats e permissao do painel.

### Backend

Stack base da API:

- Laravel 12
- PHP 8.3
- PostgreSQL
- Redis para filas, cache e broadcasting quando habilitado

Stack efetivamente usada pelos fluxos de moderacao, galeria e telao:

- Eloquent + API Resources para montagem do feed e detalhe;
- Form Requests para filtros e acoes;
- Actions para approve/reject/favorite/pin/publish/hide;
- Queries cursorizadas para o feed de moderacao;
- modulos `MediaProcessing`, `Gallery`, `Wall`, `InboundMedia`, `ContentModeration` e `MediaIntelligence`;
- broadcasting privado em `private-organization.{organizationId}.moderation`.

## Arquivos analisados

### Frontend

- `apps/web/src/modules/moderation/ModerationPage.tsx`
- `apps/web/src/modules/moderation/components/ModerationMediaCard.tsx`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.tsx`
- `apps/web/src/modules/moderation/components/ModerationVirtualGrid.tsx`
- `apps/web/src/modules/moderation/components/ModerationBulkActionBar.tsx`
- `apps/web/src/modules/moderation/components/ModerationPagination.tsx`
- `apps/web/src/modules/moderation/feed-utils.ts`
- `apps/web/src/modules/moderation/types.ts`
- `apps/web/src/modules/moderation/utils.ts`
- `apps/web/src/modules/moderation/services/moderation.service.ts`
- `apps/web/src/modules/moderation/hooks/useModerationRealtime.ts`
- `apps/web/src/modules/moderation/realtime/pusher.ts`
- `apps/web/src/lib/api.ts`
- `apps/web/src/lib/query-client.ts`
- `apps/web/src/lib/sender-filters.ts`
- `apps/web/src/lib/sender-blocking.ts`

### Backend

- `apps/api/app/Modules/MediaProcessing/routes/api.php`
- `apps/api/app/Modules/MediaProcessing/Http/Controllers/EventMediaController.php`
- `apps/api/app/Modules/MediaProcessing/Http/Requests/ListModerationMediaRequest.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaResource.php`
- `apps/api/app/Modules/MediaProcessing/Http/Resources/EventMediaDetailResource.php`
- `apps/api/app/Modules/MediaProcessing/Queries/ListModerationMediaQuery.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaAssetUrlService.php`
- `apps/api/app/Shared/Support/AssetUrlService.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`
- `apps/api/app/Modules/MediaProcessing/Services/ModerationBroadcasterService.php`
- `apps/api/app/Modules/MediaProcessing/Services/EventMediaSenderContextService.php`
- `apps/api/app/Modules/MediaProcessing/Actions/ApproveEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/RejectEventMediaAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/UpdateEventMediaFeaturedAction.php`
- `apps/api/app/Modules/MediaProcessing/Actions/UpdateEventMediaPinnedAction.php`
- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`

### Fluxos correlatos validados nesta rodada

- `apps/web/src/modules/gallery/GalleryPage.tsx`
- `apps/web/src/modules/gallery/PublicGalleryPage.tsx`
- `apps/web/src/modules/wall/player/engine/selectors.ts`
- `apps/web/src/modules/wall/player/engine/layoutStrategy.ts`
- `apps/api/app/Modules/Gallery/Http/Controllers/PublicGalleryController.php`
- `apps/api/app/Modules/MediaProcessing/Services/MediaEffectiveStateResolver.php`
- `apps/api/app/Modules/MediaProcessing/Actions/FinalizeMediaDecisionAction.php`
- `apps/api/app/Modules/Wall/Services/WallRuntimeMediaService.php`
- `apps/api/app/Modules/Wall/Services/WallPayloadFactory.php`
- `apps/api/tests/Feature/MediaProcessing/EventMediaListTest.php`
- `apps/api/tests/Feature/Gallery/PublicGalleryAvailabilityTest.php`
- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
- `apps/web/src/modules/wall/player/engine/selectors.test.ts`

## Veredito executivo

A pagina `/moderation` ja tem uma base funcional boa:

- feed cursorizado;
- infinite scroll;
- grade virtualizada;
- painel lateral de revisao;
- moderacao em item unico e em lote;
- atalhos de teclado;
- fila de itens novos via realtime;
- bloqueio rapido de remetente;
- sincronizacao por websocket.

Mas a implementacao atual ainda mistura tres responsabilidades pesadas no mesmo fluxo:

1. feed operacional;
2. renderizacao de midia;
3. reordenacao/reconciliacao em tempo real.

Isso gera uma UX visualmente instavel quando a fila muda, quando a imagem ainda nao tem variante pronta, ou quando a grade precisa desmontar e montar cards de novo.

O "pisca" e a sensacao de "fica carregando" nao parecem vir de um unico bug. O comportamento atual e mais coerente com a soma destes fatores:

- a grade usa virtualizacao custom e desmonta/monta cards;
- a API pode cair no arquivo original quando nao existe thumb/preview otimizado;
- a pagina nao tem estado visual proprio para loading/error de imagem;
- updates otimistas e eventos realtime refazem a lista carregada localmente;
- backend e frontend nao usam exatamente a mesma semantica de "pending" para ordenar e filtrar;
- os cards e o painel lateral trocam de estado com frequencia, mas sem mecanismos de continuidade visual.

## Mapa da arquitetura atual

### Entrada frontend

A rota `/moderation` aponta para `ModerationPage.tsx`.

A pagina:

- valida permissao `media.view` ou `media.moderate`;
- le `event_id` e `search` da query string para prefill;
- monta filtros locais;
- busca:
  - lista de eventos para o filtro;
  - feed cursorizado de moderacao;
  - detalhe da midia atualmente focada;
- abre canal realtime privado por organizacao;
- aplica acoes de moderacao e atualiza cache localmente.

### Entrada backend

O feed vem de `GET /api/v1/media/feed`.

Fluxo backend:

1. `ListModerationMediaRequest` valida os filtros.
2. `EventMediaController::moderationFeed()` cria `ListModerationMediaQuery`.
3. `ListModerationMediaQuery::fetchCursorPage()` devolve pagina cursorizada.
4. `EventMediaSenderContextService` injeta contexto do remetente em cada item.
5. `EventMediaResource` transforma cada midia em payload de feed.
6. A primeira pagina inclui `meta.stats`; paginas seguintes devolvem `stats = null`.

## Como a pagina funciona hoje

### 1. Boot e estado local

Estados principais no frontend:

- `perPage`
- `search`
- `eventFilter`
- `statusFilter`
- `mediaTypeFilter`
- `orientationFilter`
- `featuredOnly`
- `pinnedOnly`
- `blockedSenderOnly`
- `duplicatesOnly`
- `aiReviewOnly`
- `focusedMediaId`
- `selectedIds`
- `incomingItems`
- `mobileReviewOpen`
- `mobileFiltersOpen`
- `filtersPanelOpen`
- `previewOpen`
- `isNearTop`
- `senderBlockDuration`

Pontos importantes:

- a busca textual usa `useDeferredValue(search)`;
- a pagina zera selecao quando os filtros mudam;
- se a midia focada sair do feed, o foco vai para o primeiro item carregado;
- a pagina decide se novas midias entram direto no topo ou ficam em espera com base em `isNearTop` e selecao atual.

### 2. Carregamento do feed

O feed usa `useInfiniteQuery()` com chave `queryKeys.media.feed(feedFilters)`.

Parametros atuais enviados ao backend:

- `per_page`
- `cursor`
- `event_id`
- `search`
- `status`
- `media_type`
- `featured`
- `pinned`
- `sender_blocked`
- `orientation`
- `duplicates`
- `ai_review`

Comportamento:

- primeira carga: busca o primeiro bloco;
- paginas seguintes: `cursor` aponta para o ultimo item carregado;
- `staleTime` local da query: `15s`;
- `refetchOnWindowFocus`: globalmente desativado;
- `refetchOnReconnect`: globalmente ativado.
- o `queryFn` agora consome o `signal` do TanStack Query no feed, nas `stats` e no detalhe focado.
- ao focar uma midia, a pagina agora tambem faz `prefetch` do proximo detalhe para reduzir waterfall no painel lateral.

O infinite scroll e disparado por `IntersectionObserver` com `rootMargin: '1200px 0px'`.

Isso significa:

- a pagina tenta buscar antes do usuario chegar no final visivel;
- quando o feed ainda esta curto, ele pode encadear fetches rapidamente ate preencher melhor a area;
- isso ajuda continuidade, mas aumenta churn de render quando a tela ainda esta montando.

### 3. Paginacao atual

Hoje a paginacao real da pagina e:

- cursorizada;
- incremental;
- orientada a infinite scroll.

Nao existe paginacao numerica ativa no fluxo.

O componente legado `ModerationPagination.tsx` foi removido nesta entrega para travar o modulo no fluxo real de cursor + infinite scroll e reduzir ruido de manutencao.

### 4. Ordenacao atual

#### Ordenacao no backend

`ListModerationMediaQuery` ordena assim:

1. `sort_order desc`
2. prioridade para `moderation_status = pending`
3. `created_at desc`
4. `id desc`

Na pratica:

- itens fixados aparecem primeiro;
- dentro do restante, midias pendentes sobem;
- depois entra criterio cronologico decrescente.

#### Ordenacao no frontend

Quando o frontend faz merge local de itens com:

- realtime;
- optimistic update;
- prepend de novas midias;
- remoção local;

ele usa `compareModerationMedia()`:

1. `sort_order desc`
2. `status === 'pending_moderation'`
3. `created_at desc`
4. `id desc`

#### Gap importante

Backend e frontend nao estao usando exatamente a mesma definicao de "pending".

No backend, a prioridade usa `moderation_status = pending`.

No frontend, a prioridade usa `status === 'pending_moderation'`, que vem de `effective_media_state`.

Como `effective_media_state` inclui logica extra de IA, safety e VLM, uma midia pode:

- aparecer como `pending_moderation` no frontend;
- mas nao entrar na prioridade SQL do backend.

Impacto:

- a lista pode mudar de ordem depois de merge local;
- o card pode "pular";
- a fila pode parecer inconsistente em eventos com moderacao AI/gate;
- filtros e ordenacao podem divergir do que o operador esta vendo no badge.

Esse e um problema real de consistencia funcional, nao apenas estetico.

### 5. Filtros atuais

Filtros expostos na UI:

- busca textual;
- evento;
- status;
- tipo de midia;
- orientacao;
- IA em review;
- duplicatas;
- favoritas;
- fixadas;
- remetentes bloqueados;
- quantidade por bloco.

Quick filters:

- tudo;
- nao moderadas;
- aprovadas;
- reprovadas;
- com erro;
- imagens;
- videos;
- IA em review;
- duplicatas;
- favoritas;
- fixadas;
- remetente bloqueado.

Busca textual atual no backend:

- `caption`
- `title`
- `source_label`
- `original_filename`
- titulo do evento
- `sender_name`
- `sender_phone`
- `sender_lid`
- `sender_external_id`

#### Gap funcional nos filtros

O filtro `status` no backend usa campos de workflow bruto:

- `publication_status`
- `moderation_status`
- `processing_status`

Mas o card mostra `status` vindo de `effective_media_state`.

Impacto:

- o filtro visualmente parece operar sobre o badge do card;
- na pratica, ele opera sobre outro criterio;
- isso pode produzir recortes que nao batem com a percepcao do moderador.

Em eventos com IA isso fica mais sensivel.

### 5.1 O que aparece no card e no painel hoje

No card da fila de moderacao, o operador ve:

- titulo do evento;
- `sender_name`;
- badge de canal;
- badge de status efetivo;
- orientacao;
- hora curta de recebimento;
- legenda;
- badges de remetente bloqueado e possivel duplicata;
- icones de favorita e fixada;
- acoes de aprovar, reprovar, favoritar e fixar.

No painel lateral, entram mais campos:

- avatar e identidade do remetente;
- data de recebimento e de publicacao;
- nome do arquivo e mime type;
- estado na galeria (`Fixada no topo`, `Favorita` ou `Fluxo normal`);
- dados de deduplicacao;
- bloco completo de IA;
- bloqueio rapido de remetente.

O painel nao mostra quem foi o operador que aprovou ou reprovou a midia, embora o backend ja exponha `decision_override.overridden_by` quando o detalhe e carregado.

### 5.2 Como itens nao aprovados por IA aparecem hoje

A UI nao trabalha diretamente com `moderation_status`. Ela usa `effective_media_state`, calculado por `MediaEffectiveStateResolver`.

Na pratica:

- `safety_status = block` em modo bloqueante aparece como `rejected`;
- `safety_status = review`, `queued` ou `failed` aparece como `pending_moderation`;
- em VLM `gate`, `vlm_status = review` ou `failed` aparece como `pending_moderation`;
- em VLM `gate`, `vlm_status = rejected` aparece visualmente como `rejected`, mesmo quando o `moderation_status` bruto ainda ficou `pending`.

Isso gera duas leituras diferentes ao mesmo tempo:

- o badge do card mostra a decisao efetiva da esteira;
- o filtro/ordenacao SQL ainda trabalham mais perto do estado bruto.

Justificativa de IA hoje:

- `latest_safety_evaluation.reason_codes` e `category_scores` chegam no detalhe;
- `latest_vlm_evaluation.reason`, `reason_code`, `short_caption` e `tags` chegam no detalhe;
- o painel lateral renderiza essa justificativa;
- o card do feed nao mostra um resumo textual do motivo.

Conclusao pratica:

- sim, um item "nao aprovado pela IA" aparece na fila;
- sim, existe justificativa tecnica para consulta;
- mas essa justificativa fica concentrada no painel lateral, nao no card.

### 5.3 Nome do remetente, canal e autoria da decisao

Nome do remetente:

- vem de `inboundMessage.sender_name` quando existe;
- cai para `source_label` como fallback;
- ultimo fallback visual e `Convidado`.

Canal exibido:

- `public_upload` -> `upload`
- `whatsapp`, `whatsapp_group`, `whatsapp_direct` -> `whatsapp`
- `telegram` -> `telegram`
- `public_link` -> `link`
- fallback -> `upload`

Isso significa que a pagina mostra o nome de quem enviou e o canal normalizado do envio.

O que ela nao mostra hoje e a autoria da decisao manual:

- o backend sabe `decision_overridden_by_user_id`;
- o detalhe consegue expor `decision_override.overridden_by`;
- a tela de moderacao ainda nao usa esse dado.

### 5.4 Logica de favoritar e fixar em moderacao, galeria e telao

#### Favoritar

Favoritar hoje significa marcar `is_featured = true`.

Efeitos confirmados:

- moderacao: badge, filtro e acao manual;
- galeria admin: badge, filtro e acao manual;
- galeria publica: o item continua saindo na API, mas `is_featured` nao e criterio primario de ordenacao;
- telao: `is_featured` entra no payload, ajuda a escolher o proximo item e tambem muda o layout automatico.

Resumo:

- favorita e um destaque editorial;
- ela tem efeito real no telao;
- ela tem efeito mais fraco na galeria publica atual.

#### Fixar

Fixar hoje significa colocar `sort_order > 0`.

Efeitos confirmados:

- moderacao: itens fixados sobem para o topo do feed;
- galeria admin: itens fixados podem ser filtrados e ordenados primeiro;
- galeria publica: ordenacao principal e `sort_order desc`, depois `published_at desc`;
- telao: `sort_order` nao entra no payload nem na selecao do runtime.

Resumo:

- fixar e uma regra de ordenacao de galeria;
- serve para "topo da galeria";
- nao serve como prioridade do telao hoje.

### 5.5 Como videos aparecem em moderacao, galeria e telao

#### Moderacao

Na grade:

- `ModerationMediaCard` usa `<video>` quando detecta video;
- a source e `preview_url || thumbnail_url`;
- `muted`, `playsInline` e `preload="metadata"`.

No painel lateral:

- `ModerationReviewPanel` tambem detecta video;
- hoje a source base e `thumbnail_url ?? preview_url`;
- se existir poster/thumb, o painel pode acabar montando um `<video>` apontando para uma imagem.

No dialog ampliado:

- o preview ampliado usa sempre `<img>`;
- video nao recebe renderer dedicado ali;
- este e um gap confirmado.

#### Galeria admin

- `GalleryPage` usa `<video>` quando `media_type === 'video'` e `preview_url` existe;
- quando nao ha preview, cai no comportamento padrao de imagem/fallback.

#### Galeria publica

- `PublicGalleryPage` sempre usa `<img src={thumbnail_url}>`;
- logo, video publicado aparece como poster estatico;
- a API entrega `preview_url` de video, mas a pagina publica nao usa esse campo.

#### Telao

- o payload do wall usa `url` para o asset real;
- em video, `preview_url` vai com o poster;
- o player do telao trabalha com playback de video de verdade.

### 6. Carregamento das imagens

#### Card da grade

`ModerationMediaCard` usa:

- imagem: `thumbnail_url`
- video: `preview_url || thumbnail_url`

Sem:

- skeleton proprio;
- placeholder blur;
- estado de erro;
- retry visual;
- controle de decode/render.

Se a imagem falha:

- o navegador mostra o comportamento padrao;
- isso tende a aparecer como bloco cinza, alt text e sensacao de UI quebrada.

#### Painel lateral

`ModerationReviewPanel` usa:

- `thumbnail_url ?? preview_url`

Tambem sem estado visual proprio de loading/error.

#### Dialog de preview

O dialog ampliado usa:

- `preview_url || thumbnail_url`

#### Como a API resolve os assets

`MediaAssetUrlService` escolhe:

Para imagem:

- thumb: `thumb -> gallery -> wall -> original`
- preview: `fast_preview -> gallery -> wall -> thumb -> original`

Para video:

- thumb: `wall_video_poster -> poster -> thumb -> original`
- preview: `wall_video_720p -> wall_video_1080p -> wall -> gallery -> original`

#### Diagnostico direto

Se a midia ainda nao tem variante pronta, o feed pode cair no original.

Isso e ruim para `/moderation` porque:

- o feed nao precisa do original;
- o original pode ser pesado;
- o original pode estar em disco/storage mais lento;
- o decode pode ser caro;
- a experiencia visual piora muito no scroll;
- cada remount de card reaproveita menos cache util do navegador.

Para o caso da screenshot, este ponto e especialmente relevante:

- a tela mostra superficies cinzas com alt text aparente;
- isso e compatível com midia lenta, quebrada ou sem tratamento de erro;
- a pagina hoje nao tem nenhuma camada de UX para amortecer esse estado.

### 7. Grade virtualizada

`ModerationVirtualGrid` usa virtualizacao custom baseada em:

- largura do container;
- `window.scrollY`;
- `window.innerHeight`;
- altura fixa de card;
- overscan de 3 linhas.

Isso reduz DOM total, mas tambem traz custo:

- cards fora da janela virtual saem do DOM;
- ao voltar, eles entram de novo;
- imagens e videos podem redecodificar;
- surfaces lentas ou sem thumb dedicada passam sensacao de flicker.

Outro ponto:

- os cards nao estao memoizados com `React.memo`;
- o grid recalcula viewport no scroll;
- a cada update visivel, os cards visiveis re-renderizam.

Re-render por si so nao e o maior problema.

O problema aparece quando ele se combina com:

- URL de midia pesada;
- lista sendo reordenada;
- item entrando e saindo do range virtual;
- ausencia de transicao/skeleton/controlador de erro.

### 8. Painel lateral de revisao

Ao focar uma midia:

- a pagina usa `focusedMediaId`;
- dispara `useQuery` para buscar `GET /media/{id}`;
- o painel mostra primeiro o item do feed, depois o detalhe completo quando chegar.

Isso e bom para nao travar o clique.

Mas tambem significa:

- existe fetch extra por navegacao no foco;
- o painel pode sofrer refresh visual a cada troca;
- dados de IA entram depois;
- a percepcao e de painel "vivo", mas nao de painel "estavel".

### 9. Moderacao manual

Acoes disponiveis:

- aprovar;
- reprovar;
- favoritar;
- fixar;
- bloquear remetente;
- desbloquear remetente.

Fluxo atual:

1. frontend dispara mutation;
2. cancela queries do feed;
3. aplica update otimista local;
4. backend persiste;
5. backend retorna item atualizado;
6. frontend mergeia resposta no cache;
7. backend tambem emite broadcast de update;
8. frontend recebe update realtime do mesmo item.

Isso e funcional, mas gera churn:

- mutation otimista mexe na lista;
- resposta do backend mexe na lista;
- realtime do mesmo item pode mexer de novo.

Sem deduplicacao de origem ou bloqueio temporario por item, a fila pode parecer mexer mais do que o necessario.

### 10. Aprovacao e reprovacao no backend

#### Aprovar

`ApproveEventMediaAction`:

- muda `moderation_status` para `approved`;
- marca `decision_source = user_override`;
- registra auditoria;
- habilita `searchable` em faces;
- dispara `PublishMediaJob::dispatchSync()`.

Efeito pratico:

- aprovar nao so libera;
- aprovar ja tenta publicar imediatamente.

#### Reprovar

`RejectEventMediaAction`:

- muda `moderation_status` para `rejected`;
- volta `publication_status` para `draft`;
- limpa `published_at`;
- registra auditoria;
- desabilita faces pesquisaveis;
- dispara evento `MediaRejected`.

#### Favoritar

`UpdateEventMediaFeaturedAction`:

- altera `is_featured`.

#### Fixar

`UpdateEventMediaPinnedAction`:

- se fixar, calcula `max(sort_order) + 1` no evento;
- se desafixar, zera `sort_order`.

Observacao:

- nao existe reorder explicito de fixados alem de incrementar `sort_order`;
- com o tempo os valores podem crescer continuamente;
- funcionalmente funciona, mas operacionalmente e simplista.

### 11. Selecionar em lote e atalhos

Ja existe boa base de produtividade:

- `Shift` para selecionar intervalo;
- `Ctrl/Cmd + A` para selecionar carregadas;
- `J/K` ou setas para navegar;
- `A` aprova;
- `Shift + A` aprova e avanca para a proxima pendente;
- `R` reprova;
- `F` favorita;
- `P` fixa;
- `X` marca;
- `Enter` amplia;
- `Esc` fecha ou limpa selecao.

Essa entrega tambem adicionou:

- toggle de `auto-advance` no painel lateral;
- botao explicito `Aprovar e proxima`;
- dialog de reprovacao com motivos rapidos e motivo customizado para item unico ou selecao em lote.

Esse continua sendo um dos pontos mais fortes da pagina hoje.

### 12. Realtime

O canal usado e:

- `private-organization.{organizationId}.moderation`

Eventos:

- `moderation.media.created`
- `moderation.media.updated`
- `moderation.media.deleted`

Comportamento:

- se item ja existe no feed: faz upsert;
- se nao existe e o operador esta perto do topo e sem selecao: entra direto;
- senao: vai para `incomingItems`.

Isso e conceitualmente bom.

Mas o header da screenshot mostra:

- conexao `Reconectando`

Entao o ambiente atual ja sugere uma camada realtime instavel ou indisponivel. Isso nao explica sozinho o flicker dos cards, mas aumenta ruido visual e pode reforcar a sensacao de pagina instavel.

## Problemas concretos encontrados

### P1. Semantica de status inconsistente entre backend e frontend

Hoje:

- backend filtra e ordena por estado bruto;
- frontend exibe e reordena por estado efetivo.

Impacto:

- filtro pode nao bater com o badge;
- item pode "subir/descer" apos merge local;
- UX menos previsivel em moderacao AI.

### P2. Feed pode carregar original em vez de thumb otimizada

Hoje a API cai no original se a variante nao existir.

Impacto:

- mais bytes por card;
- maior custo de decode;
- scroll mais pesado;
- mais chance de boxes cinzas;
- pior comportamento sob virtualizacao.

### P3. Falta tratamento visual de loading/error nas surfaces

Hoje a pagina confia no comportamento nativo de `<img>` e `<video>`.

Impacto:

- alt text aparece;
- blocos cinza parecem bug;
- nao existe transicao suave entre placeholder e midia real;
- a UX parece inacabada.

### P4. Realtime + optimistic update + response merge refazem a lista varias vezes

Hoje o mesmo item pode sofrer:

- update otimista;
- update de sucesso;
- update realtime.

Impacto:

- card muda mais do que deveria;
- lista reordena mais do que deveria;
- isso amplifica a sensacao de flicker.

### P5. Grade virtualizada desmonta/monta cards e agrava recarregamento visual

Isso e aceitavel quando:

- a thumb e muito leve;
- o componente tem placeholder estavel;
- a lista quase nao muda de ordem.

Hoje nenhuma dessas tres condicoes esta forte o suficiente.

### P6. Stats do topo ficam stale

`meta.stats` so vem na primeira pagina.

Depois:

- optimistic update nao ajusta `stats`;
- realtime nao ajusta `stats`;
- remocao local nao ajusta `stats`.

Impacto:

- contadores podem nao bater com o feed visivel;
- depois de algum refetch ou troca de filtro, eles "saltam".
- arquiteturalmente, `stats` estao acopladas demais a uma `InfiniteQuery` mutavel e a pagina deveria tratá-las como leitura separada.

Isso tambem contribui para sensacao de tela instavel.

### P7. Resolvido nesta entrega: a rota agora tem cancelamento real end-to-end

Hoje:

- `api.ts` encaminha `AbortSignal`;
- `moderation.service.ts` propaga `signal` em `list`, `listStats` e `show`;
- `ModerationPage` consome o `signal` nas queries principais da rota.

Impacto:

- requests antigas de busca e filtro deixam de disputar o mesmo cache;
- a UX de filtro rapido fica mais previsivel;
- o feed perde uma fonte real de churn por respostas obsoletas.

### P8. Validado nesta entrega: a query quente do feed agora tem indices compostos dedicados

O backend agora ganhou uma migration operacional com:

- B-tree composta para `event_id + sort_order + moderation_status + created_at + id`;
- B-tree composta para `event_id + publication_status + processing_status + created_at + id`;
- predicado `WHERE deleted_at IS NULL` no caminho quente de `event_media`.

Impacto:

- o feed deixa de depender so dos indices genericos da tabela;
- o custo do caminho quente fica mais aderente ao contrato de ordenacao e filtros;
- a rerodagem com o comando dedicado `media:moderation-feed-explain` mostrou que o planner ainda prefere `Seq Scan` no dataset local atual, o que continua coerente com o volume ainda moderado:
  - `event_media = 562`
  - `inbound_messages = 43`
  - `organization 1 = 253` midias no recorte quente
- tempos medidos na ultima leitura local:
  - feed por organizacao: ~`4.6ms`
  - feed por evento quente (`event_id = 5`): ~`0.8ms`
  - feed com `status = pending_moderation`: ~`2.6ms`

Decisao:

- nao adicionar `partial index` extra agora;
- o predicado de `effective state` ainda depende de `CASE` com joins de configuracao e nao casa bem com `partial index` reconhecivel pelo planner.

### P9. Promovida nesta entrega: a busca textual ganhou `search document` dedicado

A busca do feed agora:

- usa `event_media.moderation_search_document` como documento denormalizado de busca operacional;
- deixou de fazer `left join` direto de `inbound_messages` para o hot path do `/media/feed`;
- remove o `orWhereHas('inboundMessage')` do trecho mais sensivel da query;
- ganhou indice GIN com `pg_trgm` em `event_media.moderation_search_document`;
- ganhou fast path para termo que bate exatamente com titulo de evento, aplicando `event_id` antes do documento textual amplo.

Impacto:

- a busca deixa de depender de subquery relacional no fluxo quente;
- o banco passa a ter uma base mais profissional para `LIKE`/`ILIKE` com wildcard em um unico campo;
- a sonda sintetica com `5.000` midias mostrou que a forma anterior estourava o budget de `500ms` em `search_sender_name_hot`;
- depois do `search document`, a mesma sonda com `--disable-jit` ficou dentro do budget:
  - feed por organizacao: ~`35ms`
  - feed por evento quente: ~`61ms`
  - feed com `status = pending_moderation`: ~`32ms`
  - busca por `event title`: ~`98ms`
  - busca por `sender name`: ~`56ms`
- a sonda ampliada com `20.000` midias reabriu a discussao porque busca pelo titulo completo do evento virou match de baixa seletividade;
- depois do fast path por titulo exato de evento, a sonda de `20.000` midias ficou dentro do budget:
  - feed por organizacao: ~`113ms`
  - feed por evento quente: ~`207ms`
  - feed com `status = pending_moderation`: ~`105ms`
  - busca por `event title`: ~`254ms`
  - busca por `sender name`: ~`30ms`
- a mesma sonda com JIT habilitado mostrou que o PostgreSQL pode gastar mais de `2s` apenas com compilacao JIT nessa query, entao homolog/producao precisam validar a politica de JIT para queries OLTP do painel;
- o modulo agora tem um comando dedicado para repetir essa medicao em PostgreSQL real sem depender de script ad hoc:
  - `php artisan media:moderation-feed-explain`
  - budgets formais desta rota: `feed = 700ms`, `search = 500ms`
  - com `--synthetic-media`, o comando cria volume transacional e faz rollback;
  - com `--disable-jit`, o comando mede a query sem custo de compilacao JIT;
  - com `--fail-on-budget`, o comando pode virar gate operacional de homolog.

Decisao:

- `search document` dedicado foi promovido nesta rodada;
- busca por titulo exato de evento nao deve mais passar pelo documento textual amplo; ela vira filtro por `event_id`;
- nao adicionar `partial index` extra agora;
- a validacao adicional com as chaves locais desta maquina confirmou que o ambiente efetivo ainda e `APP_ENV=local` com PostgreSQL em `127.0.0.1:5433`;
- repetir a medicao em homolog real quando houver credencial/configuracao disponivel.

### P10. Mitigado nesta entrega: o painel lateral ainda busca detalhe por foco, mas agora aquece o proximo item

Isso melhora riqueza do review, mas custa:

- request extra;
- refresh do painel;
- variacao de layout ao entrar IA e dados complementares.

Mitigacao aplicada:

- ao focar uma midia, a pagina faz `prefetch` do proximo detalhe carregado na fila;
- o painel deixa de depender exclusivamente de fetch frio em navegacao sequencial.

Em lote grande de revisao, isso reduz ruido, mas ainda nao elimina o request extra por foco.

### P11. Resolvido nesta entrega: o codigo morto de paginacao saiu do modulo

Estado atual:

- `ModerationPagination.tsx` foi removido do modulo;
- o teste de arquitetura trava a ausencia da paginacao numerica legada;
- o fluxo real da rota continua explicitamente cursorizado e orientado a infinite scroll.

Impacto:

- menos ruido cognitivo para o time;
- menos chance de reintroduzir uma estrategia antiga por acidente.

### P12. Justificativa de IA so aparece no painel lateral

Hoje a midia pode estar retida ou rejeitada por safety/VLM, mas o card do feed nao mostra o motivo textual.

Impacto:

- o operador precisa abrir o item para entender a causa;
- a triagem rapida de fila AI fica mais lenta;
- a tela passa menos confianca quando um badge muda sem contexto.

### P13. Preview ampliado da moderacao nao trata video corretamente

O dialog de preview ainda usa `<img>` para tudo.

Impacto:

- video nao tem ampliacao fiel;
- o operador revisa o poster, nao o ativo real;
- a UX da moderacao de video fica incompleta.

### P14. Resolvido nesta entrega: painel lateral e preview ampliado agora usam source consistente para video

Estado atual:

- `ModerationReviewPanel` passou a renderizar video com `preview_url` real;
- `thumbnail_url` fica restrita ao papel de `poster`;
- o dialog ampliado da moderacao reaproveita a mesma `ModerationMediaSurface`, entao video e fallback visual seguem o mesmo contrato do card e do painel.

Impacto:

- a revisao de video deixa de depender de URL de imagem montada como `<video>`;
- a superficie de preview fica tecnicamente fechada para a fase atual.

### P15. Resolvido nesta entrega: scroll restoration entrou no nivel do router

Estado atual:

- o app passou para `createBrowserRouter` + `RouterProvider`;
- o shell registra `ScrollRestoration`;
- a restauracao da fila de moderacao usa `getKey` estavel por pathname + filtros criticos conhecidos da rota.

Impacto:

- voltar para `/moderation` deixa de depender apenas do cache da query;
- a base oficial de scroll restoration ja esta pronta para os proximos refinamentos de UX.

Observacao remanescente:

- `preventScrollReset` continua desnecessario enquanto os filtros principais da pagina ainda viverem em estado local, nao em navegacao orientada por query string.

### P16. Resolvido nesta entrega: transporte realtime agora evita eco entre mutation e broadcast

Estado atual:

- o client HTTP injeta `X-Socket-ID` quando houver socket ativo;
- a trilha de broadcast da moderacao usa `broadcast(...)->toOthers()`.

Impacto:

- o mesmo item deixa de sofrer eco basico entre mutation local e broadcast;
- o frontend fica com menos churn estrutural para compensar.

### P17. Galeria publica ainda nao reproduz video

Mesmo com `preview_url` de video no payload, a `PublicGalleryPage` renderiza sempre `<img>`.

Impacto:

- video publicado aparece como foto;
- a experiencia publica nao reflete a capacidade real do backend;
- existe desalinhamento entre payload e renderer.

## Por que a pagina "fica carregando e piscando"

### Causa mais provavel

A causa mais provavel e combinada:

1. o card monta com `thumbnail_url`;
2. se nao existir variante boa, cai no original;
3. o grid virtualizado desmonta e monta cards no scroll;
4. o feed e reordenado localmente por merge otimista/realtime;
5. a pagina nao tem placeholder/controlador de erro/controlador de continuidade;
6. o navegador mostra a troca de asset de forma crua.

Isso nao vira um crash.

Vira uma UX com:

- blocos cinza;
- alt text aparente;
- cards parecendo "resetar";
- imagens entrando sem suavizacao;
- itens pulando na fila.

### Causa secundaria

O header em `Reconectando` indica que o realtime nao esta estavel no ambiente mostrado.

Quando a pagina ja esta visualmente fragil, isso piora a percepcao de confianca:

- status muda;
- itens podem entrar em burst;
- a tela parece "viva demais".

### Causa estrutural

A pagina ainda nao separa claramente:

- feed leve para operacao;
- preview leve para revisao;
- detalhe pesado sob demanda.

Hoje os tres fluxos se encostam demais.

## Validacoes rapidas no ambiente local

Antes de mexer na pagina, vale validar quatro pontos objetivos no ambiente da screenshot:

### 1. URLs de `thumbnail_url` e `preview_url`

Conferir no Network:

- se retornam `200`;
- se estao vindo do host esperado;
- se nao estao caindo em `404`, `403` ou redirect estranho.

Se a tela mostra alt text/bloco cinza, esta checagem e obrigatoria.

### 2. Existencia das variantes no storage

Confirmar para as midias da fila se existem arquivos de:

- `thumb`
- `fast_preview`
- `gallery`
- `wall`
- `wall_video_poster`

Se a variante nao existir, a API cai no original e a performance degrada muito.

### 3. `APP_URL` e resolucao de asset

`AssetUrlService` monta URL publica a partir de:

- `Storage::disk(...)->url(...)`
- ou `config('app.url')`

Entao vale conferir:

- `APP_URL`;
- `storage:link`;
- origem final do asset;
- se o Vite local esta apontando para um backend com URL publica coerente.

### 4. Realtime/Reverb

A screenshot mostra `Reconectando`.

Entao tambem vale validar:

- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`
- autenticacao em `/broadcasting/auth`

Mesmo que isso nao seja a causa principal do flicker, websocket instavel piora a sensacao de tela "vibrando".

## Testes adicionados nesta revisao

Para reduzir duvidas na documentacao, esta rodada adicionou cobertura especifica em:

- `apps/web/src/modules/moderation/components/ModerationReviewPanel.test.tsx`
  - valida nome do remetente, badge de canal, renderer de video, toggle de `auto-advance`, acao `Aprovar e proxima` e exposicao da posicao/restante da fila no painel de revisao;
- `apps/web/src/modules/moderation/components/ModerationMediaSurface.test.tsx`
  - valida `loading`, `error fallback` e escolha correta de `preview_url` + `thumbnail_url` para video;
- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
  - valida propagacao de `reason` nas acoes unitarias e em lote de moderacao;
- `apps/web/src/lib/api.realtime.test.ts`
  - valida que `api.ts` encaminha `AbortSignal` e injeta `X-Socket-ID` quando o socket estiver ativo;
- `apps/web/src/app/routing/router-architecture.test.ts`
  - valida que o shell agora usa data router com `RouterProvider` e `ScrollRestoration`;
- `apps/web/src/app/routing/scroll-restoration.test.ts`
  - valida a chave estavel de restauracao de scroll para `/moderation`;
- `apps/web/src/modules/moderation/moderation-architecture.test.ts`
  - valida `prefetch` do proximo detalhe, ausencia da paginacao legada, wiring de `approve-and-next` + motivos rapidos e exposicao do progresso da fila no `ModerationPage`;
- `apps/web/src/modules/moderation/feed-utils.test.ts`
  - valida filtros operacionais locais para `media_type`, `duplicates`, `ai_review` e calculo de posicao/restante da fila;
- `apps/web/src/modules/moderation/services/moderation.service.test.ts`
  - valida forwarding de `media_type`, `duplicates` e `ai_review` para feed e `stats`;
- `apps/web/src/modules/wall/player/engine/selectors.test.ts`
  - valida que `is_featured` influencia a escolha do proximo item do telao;
- `apps/api/tests/Feature/Gallery/PublicGalleryAvailabilityTest.php`
  - valida payload de video na galeria publica com poster em `thumbnail_url` e asset de parede em `preview_url`.
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationArchitectureCharacterizationTest.php`
  - valida o endpoint dedicado de `stats`, a supressao de eco da moderacao com `broadcast(...)->toOthers()`, a presenca da migration de indices do feed e o registro do comando de benchmark da moderacao;
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationFeedExplainAnalyzeServiceTest.php`
  - valida a leitura do plano JSON do PostgreSQL, a decisao de promover ou nao `search document` por budget e o estado de follow-up quando o documento ja existe mas algum cenario passa de `500ms`;
- `apps/api/tests/Unit/MediaProcessing/MediaEffectiveStateResolverTest.php`
  - valida a semantica de estado efetivo para cenarios AI bloqueantes e nao bloqueantes;
- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`
  - valida filtros operacionais do feed e das `stats` para `media_type`, `duplicates`, `ai_review`, `error` e totais pendentes usados pelo contador de fila;
- `apps/api/tests/Unit/Modules/MediaProcessing/ModerationSearchDocumentBuilderTest.php`
  - valida normalizacao do documento denormalizado de busca da moderacao;
- `apps/api/tests/Feature/MediaProcessing/RunModerationFeedExplainCommandTest.php`
  - valida que o comando falha de forma controlada fora de PostgreSQL real.

Continuam relevantes como base anterior:

- `apps/api/tests/Feature/MediaProcessing/ModerationMediaTest.php`
  - sender context, bloqueio, favorite/pin, ordenacao publica por fixacao, contrato do feed com `thumbnail_source`, `preview_source` e `updated_at`, alem da busca por `event title` e identidade do remetente;
- `apps/api/tests/Feature/MediaProcessing/ModerationFeedCharacterizationTest.php`
  - valida alinhamento de estado efetivo, surfaces de moderacao sem fallback para original e fast path de busca por titulo exato de evento;
- `apps/api/tests/Feature/MediaProcessing/EventMediaListTest.php`
  - detalhe de IA com `latest_safety_evaluation` e `latest_vlm_evaluation`, alem do contrato enriquecido de asset source.

## O que melhorar para performance

## Prioridade 0 - impacto alto e baixo risco

### 1. Nunca usar original no card do feed

Criar variante dedicada de moderacao, por exemplo:

- `moderation_thumb`
- `moderation_preview`

Regras:

- imagem do feed usa somente `moderation_thumb`;
- painel lateral usa `moderation_preview`;
- se a variante ainda nao existir, mostrar placeholder do produto, nao o original.

Resultado esperado:

- menos bytes;
- decode muito menor;
- menos flicker no scroll;
- mais consistencia visual.

### 2. Adicionar estado visual de loading/error para imagem e video

Cada surface precisa ter:

- skeleton;
- fade-in suave no `onLoad`;
- fallback visual limpo no `onError`;
- nunca deixar o browser mostrar alt text cru como UI final.

### 3. Unificar ordenacao e filtro pelo mesmo conceito de estado

Escolher uma das duas estrategias:

- filtrar/ordenar por estado bruto e exibir isso explicitamente;
- ou mover backend para filtrar/ordenar pelo mesmo `effective_media_state` que o frontend usa.

Hoje a pagina mistura os dois.

### 4. Atualizar stats localmente

Quando houver:

- approve;
- reject;
- favorite;
- pin;
- delete;
- create/update/delete realtime;

os contadores do topo devem ser atualizados junto com o feed, ou a primeira pagina deve ser revalidada de forma controlada.

### 5. Dedupe de updates no fluxo mutation + realtime

Sugestao:

- manter janela curta por `media.id` para ignorar broadcast do mesmo item logo apos mutation local;
- ou usar `updated_at/version` para aplicar somente payload mais novo;
- ou refetch targeted por item em vez de triplo merge.

## Prioridade 1 - performance de backend

### 6. Criar indice composto para o feed de moderacao

Recomendacao inicial em PostgreSQL, ajustando apos EXPLAIN:

- indice por `event_id, sort_order desc, moderation_status, created_at desc, id desc`
- indice por `event_id, moderation_status, created_at desc, id desc`
- avaliar indice com `publication_status` e `processing_status` conforme filtros reais

Se o feed por organizacao for muito quente, pode valer avaliar:

- denormalizar `organization_id` em `event_media`;
- ou criar uma view/materializacao operacional.

### 7. Revisar busca textual

Hoje o feed faz busca espalhada em varios campos com joins implicitos.

Melhorias possiveis:

- `pg_trgm` com GIN;
- coluna normalizada de busca;
- search document precomputado;
- busca assinc ou dedicada para campos menos quentes.

### 8. Evitar payload acima do necessario no feed

O feed deve devolver apenas o necessario para a grade.

Ideal:

- lista leve;
- detalhe rico so no painel.

Hoje isso ja esta parcialmente separado, mas ainda vale revisar o payload final de `EventMediaResource` para o caso operacional.

## O que melhorar para dar mais profissionalismo

### 1. Surface de midia mais robusta

Trocar a renderizacao crua por um componente unico de surface:

- skeleton padrao;
- blur placeholder;
- icone de erro;
- badge "preview indisponivel";
- transicao de entrada da imagem;
- suporte comum para imagem e video poster.

### 2. Fluxo visual mais estavel

A pagina precisa parecer uma ferramenta de operacao, nao um feed experimental.

Melhorias:

- evitar saltos bruscos na grade;
- auto-advance controlado;
- menos mutacao visual fora do foco principal;
- manter item atual estavel enquanto acao roda.

### 3. Contadores e estado de conexao mais confiaveis

O topo so deve mostrar numero que acompanha a realidade do feed.

O bloco de conexao deve distinguir:

- offline do websocket;
- feed ainda funcional;
- reconexao automatica;
- ultima sincronizacao bem sucedida.

### 4. Limpeza de modulo

Vale remover ou arquivar:

- componentes nao usados;
- caminhos antigos de paginacao;
- duplicacao de logica entre feed e painel.

Isso melhora manutencao e reduz ruido cognitivo para o time.

## O que melhorar para facilitar a moderacao manual

### 1. Entregue nesta entrega: auto-advance configuravel

Estado atual:

- toggle de `auto-advance` no painel lateral;
- o foco so avanca depois de mutation bem-sucedida;
- quando desligado, o operador continua no item atual.

### 2. Entregue nesta entrega: acao "aprovar e proxima"

Estado atual:

- botao `Aprovar e proxima` no painel lateral;
- atalho `Shift + A` para o mesmo fluxo no teclado.

### 3. Entregue nesta entrega: razoes rapidas de reprovacao

Estado atual:

- conteudo inadequado;
- baixa qualidade;
- duplicada;
- fora do contexto;
- spam.

O fluxo atual tambem permite:

- reprovar sem motivo;
- escrever motivo customizado com ate `500` caracteres;
- reaproveitar o mesmo dialog para selecao em lote.

### 4. Parcialmente entregue nesta entrega: modo "somente pendentes"

Estado atual:

- o topo do bloco de filtros mostra `Pendentes restantes` com base em `stats.pending` da query dedicada;
- quando a midia focada esta pendente, a UI mostra `Posicao atual: pendente X/Y`;
- quando a midia focada nao esta pendente, a UI mostra `Posicao atual: item X/Y`;
- o painel lateral repete a posicao e mostra quantas pendentes existem depois da midia atual;
- a posicao e calculada pela ordem carregada do feed, enquanto o total de pendentes vem de `stats.pending`.

Pendente:

- decidir se o quick filter `Nao moderadas` deve virar default do produto para todos os operadores ou ficar como preferencia configuravel.

### 5. Resolvido nesta entrega: revisao por cluster de duplicata

Estado atual:

- o painel lateral carrega `GET /media/{id}/duplicates` para a midia focada;
- a lista fica restrita ao mesmo `duplicate_group_key` dentro do mesmo evento;
- o operador consegue abrir outra captura do grupo sem sair do fluxo principal;
- o painel expoe a acao `Rejeitar demais como duplicada`, reaproveitando `bulkReject` com motivo rapido `Duplicada`.

Impacto:

- a triagem de duplicatas deixa de depender de memoria ou busca manual;
- a troca de enquadramento dentro do grupo fica imediata;
- o fluxo de "manter a melhor e reprovar o resto" fica viavel na propria rota de moderacao.

### 6. Prefetch do proximo item

Enquanto o operador revisa o item atual:

- prefetch do detalhe da proxima midia;
- preload da proxima `moderation_preview`.

Isso reduz o refresh visual do painel lateral.

### 7. Resolvido nesta entrega: undo curto apos acoes

Estado atual:

- acoes unitarias de `approve`, `reject`, `favorite` e `pin` agora abrem toast com `Desfazer`;
- `approve` e `reject` usam endpoint dedicado `POST /media/{id}/undo-decision`;
- o undo de decisao manual devolve a midia para `pending + draft` e limpa `decision_source`, `decision_overridden_*` e `decision_override_reason`;
- `favorite` e `pin` usam a trilha inversa ja existente para restaurar o estado anterior;
- ao desfazer, a pagina atualiza feed, detalhe, stats e foco sem recarregar a rota inteira.

Impacto:

- o operador consegue corrigir erro operacional rapido sem "caçar" o item de novo;
- o undo fica tecnicamente correto, sem transformar desfazer em uma nova rejeicao;
- a fila ganha seguranca operacional sem aumentar churn estrutural do feed.

### 8. Entregue nesta entrega: filtros operacionais mais fortes

Estado atual:

- a rota `/moderation` agora aceita `media_type=image|video`, `duplicates=1` e `ai_review=1` no mesmo contrato usado por feed e `stats`;
- a UI passou a expor quick filters para `Com erro`, `Imagens`, `Videos`, `IA em review` e `Duplicatas`;
- os filtros avancados agora combinam busca, evento, status, tipo de midia, orientacao, IA em review, duplicatas, favoritas, fixadas e remetente bloqueado;
- o matcher local de realtime e patches otimistas passou a respeitar o mesmo recorte operacional.

Semantica entregue:

- `Com erro` usa a semantica operacional do feed e agora considera `processing_status = failed` como `error` antes de cair em `pending_moderation`;
- `IA em review` cobre apenas pendencias bloqueantes vindas de safety/VLM (`queued`, `review` ou `failed`), sem misturar pendencia manual pura;
- `Duplicatas` usa `duplicate_group_key` / `is_duplicate_candidate`;
- `Imagens` e `Videos` filtram por `media_type`.

## Roadmap recomendado

### Fase 1 - estabilizar a experiencia visual

1. criar variant de moderacao leve;
2. parar de cair no original no feed;
3. criar componente unico de image/video surface com loading/error;
4. atualizar stats corretamente;
5. alinhar status/order/filter no mesmo conceito.

### Fase 2 - reduzir churn de render

1. dedupe mutation + realtime;
2. memoizar card;
3. revisar virtualizacao para manter mais estabilidade de DOM;
4. prefetch do detalhe da proxima midia.

### Fase 3 - melhorar produtividade operacional

Status atual:

- `1`, `2`, `3`, `4` e `5` concluidos nesta entrega.

1. auto-advance;
2. aprovar e proxima;
3. motivos rapidos de reprova;
4. cluster de duplicata;
5. undo curto.

### Fase 4 - escalar backend

1. indices compostos;
2. profiling com EXPLAIN ANALYZE;
3. estrategia de busca melhor que `LIKE` multiplo;
4. revisar payload do feed.

## Checklist tecnico objetivo

Status consolidado ate esta entrega:

- [x] alinhar backend/frontend para o mesmo conceito de status no feed operacional;
- [x] separar `stats` do feed e consumir em query dedicada;
- [x] propagar `AbortSignal` ponta a ponta nas queries principais da moderacao;
- [x] reduzir eco de mutation + broadcast com `X-Socket-ID` e `toOthers()`;
- [x] expor `thumbnail_source`, `preview_source` e `updated_at` no payload operacional;
- [x] adicionar surface compartilhada com `loading/error/fallback` para card e painel lateral;
- [x] deduplicar patch de feed/realtime por `updated_at`;
- [x] criar `moderation_thumb` e `moderation_preview`;
- [x] impedir fallback para original no feed da moderacao com fields dedicados;
- [x] tratar scroll restoration no router com data router + `ScrollRestoration`;
- [x] adicionar indice composto para ordenacao do feed;
- [x] revisar busca textual do feed;
- [x] prefetch do proximo detalhe;
- [x] validar os indices do feed com `EXPLAIN ANALYZE` em PostgreSQL real;
- [x] criar comando para repetir o benchmark do feed em PostgreSQL real com budget operacional formal;
- [x] repetir o benchmark com volume maior sintetico e rollback transacional;
- [x] promover `search document` dedicado apos `search_sender_name_hot` sair do budget em volume maior;
- [x] validar `search document` com `5.000` midias sinteticas e `--disable-jit` dentro do budget;
- [x] reabrir a sonda com `20.000` midias sinteticas e corrigir `search_event_title_hot` com fast path por `event_id`;
- [x] validar `20.000` midias sinteticas com `search document`, fast path de titulo exato e `--disable-jit` dentro do budget;
- [ ] publicar a release atual da moderacao em homolog e repetir o benchmark com o comando novo nesse ambiente;
- [x] adicionar auto-advance e razoes de reprova;
- [x] adicionar revisao por cluster de duplicata;
- [x] adicionar undo curto apos acoes unitarias;
- [x] adicionar filtros operacionais fortes para imagens, videos, IA em review, duplicatas e erro;
- [x] adicionar contador de fila restante e posicao do item atual;
- [x] remover codigo morto de paginacao nao usada.

## Conclusao

A rota `/moderation` ja tem estrutura suficiente para ser uma central de operacao forte.

O problema principal hoje nao e falta de funcionalidade. E falta de estabilizacao do fluxo.

O caminho correto nao e trocar a pagina inteira.

O caminho correto e:

1. separar feed leve de asset pesado;
2. alinhar semantica de status/order/filter;
3. reduzir churn de render provocado por realtime e optimistic updates;
4. profissionalizar a surface de midia;
5. acelerar o fluxo manual com auto-advance e motivos rapidos.

Com essas correcoes, a pagina tende a:

- carregar mais rapido;
- piscar menos;
- transmitir mais confianca;
- e reduzir o custo cognitivo da moderacao manual.
